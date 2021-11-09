<?php

namespace Organi\Translatables\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Organi\Translatables\Models\Translation;

trait HasTranslations
{
    /**
     * Contains the actual translations.
     *
     * @var array
     */
    private $translations;

    /**
     * Indicate the translations are changed.
     *
     * @var bool
     */
    private $dirty = false;

    /**
     * Magic method for retrieving a missing attribute.
     *
     * @param string $attribute
     *
     * @return mixed
     */
    public function getAttribute($attribute)
    {
        // Check if requested attribute is a translatable attribute
        if (! $this->isTranslatableAttribute($attribute)) {
            return parent::getAttribute($attribute);
        }

        return $this->getTranslatedLocales($attribute);
    }

    /**
     * Magic method for setting a missing attribute.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return mixed
     */
    public function setAttribute($attribute, $value)
    {
        // Check if requested attribute is a translatable attribute
        if (! $this->isTranslatableAttribute($attribute)) {
            return parent::setAttribute($attribute, $value);
        }

        if (is_array($value)) {
            $value = Translation::make($value);
        }

        if ($value instanceof Translation) {
            foreach ($value->translations() as $locale => $v) {
                $this->setTranslation($locale, $attribute, $v);
            }
        } else {
            $this->setTranslation(\App::getLocale(), $attribute, $value);
        }
    }

    public static function bootHasTranslations()
    {
        static::saved(function ($model) {
            $model->commitTranslations();
        });
    }

    public function setTranslations($locale, $attributes)
    {
        foreach ($attributes as $attribute => $value) {
            $this->setTranslation($locale, $attribute, $value);
        }
    }

    public function setTranslation($locale, $attribute, $value)
    {
        // Check if the given locale is allowed
        if (! in_array($locale, $this->locales())) {
            throw new \RuntimeException('Locale \'' . $locale . '\' is not allowed');
        }

        // Check if the given attribute is a localizable
        if (! in_array($attribute, $this->localizable)) {
            throw new \RuntimeException("Attribute `{$attribute}` is not in localizable array");
        }

        // If we haven't retrieved the translations yet, do that now. This wil set the translations propery
        if (! $this->translations) {
            $this->translations = $this->translatables();
        }

        // Indicate the translations have changed
        $this->dirty = true;

        // If the locale is not initialized yet -> do it now
        if (! Arr::has($this->translations, $locale)) {
            Arr::set($this->translations, $locale, $this->getEmptyTranslationsArray());
        }

        return Arr::set($this->translations, implode('.', [$locale, $attribute]), $value);
    }

    /**
     * Represents the translations in the current or given locale.
     *
     * @param null|mixed $locale
     */
    public function translatable($locale = null)
    {
        if (! $locale) {
            $locale = \App::getLocale();
        }

        // Get the translations in the current locale, or return an empty array if its not set yet
        return Arr::get($this->translatables(), $locale, $this->getEmptyTranslationsArray());
    }

    /**
     * Set translations (for easy replication).
     *
     * @param mixed $translatables
     * @param mixed $translations
     */
    public function setAllTranslations($translations)
    {
        $this->translations = $translations;
        $this->dirty        = true;

        return $this;
    }

    /**
     * Represents all translations.
     */
    public function translatables()
    {
        // If we already retrieved the translations -> use the one in memory
        if ($this->translations) {
            return $this->translations;
        }

        // TODO: Now this an array. Should prolly return a custom translations object
        $translations = DB::table($this->getTranslationsTable())
            ->where($this->getKeyName(), $this->getKey())
            ->select(array_merge(['locale'], $this->localizable))
            ->get();

        // Empty translations array
        $this->translations = [];

        foreach ($translations as $translation) {
            // Get the locale from the object
            $locale = $translation->locale;

            // Remove the locale field from the data
            unset($translation->locale);

            $this->translations[$locale] = (array) $translation;
        }

        return $this->translations;
    }

    /**
     * Save translations to database.
     */
    public function commitTranslations()
    {
        // If nothing changed -> dont do anything
        if (! $this->dirty || ! $this->translations) {
            return;
        }

        foreach ($this->translations as $locale => $translatable) {
            DB::table($this->getTranslationsTable())
                ->updateOrInsert(
                    [
                        $this->getKeyName() => $this->getKey(), 'locale' => $locale,
                    ],
                    $translatable
                );
        }
    }

    public function isTranslatableAttribute($attribute)
    {
        return in_array($attribute, $this->localizable);
    }

    public function getTranslatedLocales($attribute)
    {
        $value = array_reduce($this->locales(), function ($output, $locale) use ($attribute) {
            // Get the translated value
            $value = Arr::get($this->translatable($locale), $attribute, '');

            // Run in through transformModelValue, this way we're able to use
            // mutators with translatable attributes
            $value = $this->transformModelValue($attribute, $value);

            $output[$locale] = $value;

            return $output;
        });

        return new Translation($value);
    }

    public function getTranslationsTable()
    {
        return $this->getTable() . '_translations';
    }

    public function getLocalizable()
    {
        return $this->localizable;
    }

    public function attributesToArray($localizeOnly = false)
    {
        return $this->addLocalizableAttributesToArray(
            $localizeOnly ? [] : parent::attributesToArray()
        );
    }

    public function toTranslatedArray($locale, $localizeOnly = false)
    {
        return array_merge(
            $localizeOnly ? [] : parent::attributesToArray(),
            $this->translatable($locale)
        );
    }

    public function scopeWhereTranslation($query, $column, $value): Builder
    {
        // Get the table + field name for the where clause
        $column = $this->getTranslationsTable() . '.' . $column;

        // Check if table is already joined
        $joined = false;
        foreach ($query->getQuery()->joins ?: [] as $join) {
            $joined = $joined || $join->table === $this->getTranslationsTable();
        }

        if (! $joined) {
            // Get the table + field names for the join
            $t  = $this->getTable() . '.' . $this->getKeyName();
            $tt = $this->getTranslationsTable() . '.' . $this->getKeyName();

            // Join the translations table
            $query->join($this->getTranslationsTable(), $t, '=', $tt);
        }

        return $query->where($column, $value);
    }

    public function replicate(array $except = null)
    {
        $new = parent::replicate();

        $new->setAllTranslations($this->translatables());

        return $new;
    }

    public function delete()
    {
        $translations = DB::table($this->getTranslationsTable())
            ->where($this->getKeyName(), $this->getKey())
            ->delete();

        return parent::delete();
    }

    public static function translationsTable(): string
    {
        return (new static())->getTranslationsTable();
    }

    public static function translationsColumn(string $column): string
    {
        return (new static())->qualifyTranslationsColumn($column);
    }

    public function qualifyTranslationsColumn(string $column): string
    {
        return implode('.', [$this->getTranslationsTable(), $column]);
    }

    protected function addLocalizableAttributesToArray(array $attributes)
    {
        foreach ($this->localizable as $key) {
            if (in_array($key, $this->hidden)) {
                continue;
            }

            if (count($this->visible) && ! in_array($key, $this->visible)) {
                continue;
            }
            $attributes[$key] = $this->getTranslatedLocales($key);
        }

        return $attributes;
    }

    protected function locales()
    {
        return config('translatables.accepted_locales');
    }

    /**
     * Returns an array with all translatable attributes as empty string.
     */
    private function getEmptyTranslationsArray()
    {
        return array_fill_keys($this->localizable, '');
    }
}
