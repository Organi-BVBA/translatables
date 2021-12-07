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
            // If not, execute default eloquent logic
            return parent::setAttribute($attribute, $value);
        }

        // If the value is an array, turn it into a translation object
        if (is_array($value)) {
            $value = Translation::make($value);
        }

        // If the value is a translation, set all locales from the translation
        if ($value instanceof Translation) {
            // Loop over all locales,
            // so if a locale is missing from the translations object we'll clear it
            foreach ($this->locales() as $locale) {
                // Get the value for this locale
                $v = Arr::get($value->translations(), $locale);
                // Set it on the model
                $this->setTranslation($locale, $attribute, $v);
            }
        } else {
            // Otherwise set the value for the current locale
            $this->setTranslation(\App::getLocale(), $attribute, $value);
        }
    }

    /**
     * Boot the trait. Listen to the saved event and save the translations
     * when it happens.
     */
    public static function bootHasTranslations()
    {
        static::saved(function ($model) {
            $model->commitTranslations();
        });
    }

    /**
     * Set multiple properties for a single locale.
     */
    public function setTranslations(string $locale, array $attributes): void
    {
        foreach ($attributes as $attribute => $value) {
            $this->setTranslation($locale, $attribute, $value);
        }
    }

    /**
     * Set a single propertie for a single locale.
     *
     * @param mixed $value
     */
    public function setTranslation(string $locale, string $attribute, $value): void
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

        Arr::set($this->translations, implode('.', [$locale, $attribute]), $value);
    }

    /**
     * Represents the translations in the current or given locale.
     */
    public function translatable(?string $locale = null): array
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
     * @return $this
     */
    public function setAllTranslations(array $translations)
    {
        $this->translations = $translations;
        $this->dirty        = true;

        return $this;
    }

    /**
     * Represents all translations.
     */
    public function translatables(): array
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
    public function commitTranslations(): void
    {
        // If nothing changed -> dont do anything
        if (! $this->dirty || ! $this->translations) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->translations as $locale => $translatable) {
                if (null === max($translatable)) {
                    // All translatable values are null. Delete the record.
                    DB::table($this->getTranslationsTable())
                        ->where($this->getKeyName(), $this->getKey())
                        ->where('locale', $locale)
                        ->delete();
                } else {
                    DB::table($this->getTranslationsTable())
                        ->updateOrInsert(
                            [
                                $this->getKeyName() => $this->getKey(), 'locale' => $locale,
                            ],
                            $translatable
                        );
                }
            }
        });
    }

    /**
     * Check if the given attribute is in the localizable array.
     *
     * @param mixed $attribute
     */
    public function isTranslatableAttribute(string $attribute): bool
    {
        return in_array($attribute, $this->localizable);
    }

    public function getTranslatedLocales(string $attribute): Translation
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

    public function getTranslationsTable(): string
    {
        return $this->getTable() . '_translations';
    }

    public function getLocalizable(): array
    {
        return $this->localizable;
    }

    public function attributesToArray(bool $localizeOnly = false): array
    {
        return $this->addLocalizableAttributesToArray(
            $localizeOnly ? [] : parent::attributesToArray()
        );
    }

    public function toTranslatedArray(string $locale, bool $localizeOnly = false): array
    {
        return array_merge(
            $localizeOnly ? [] : parent::attributesToArray(),
            $this->translatable($locale)
        );
    }

    /**
     * Add scope.
     *
     * @param mixed $value
     */
    public function scopeWhereTranslation(Builder $query, string $column, $value): Builder
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

    protected function addLocalizableAttributesToArray(array $attributes): array
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

    protected function locales(): array
    {
        return config('translatables.accepted_locales');
    }

    /**
     * Returns an array with all translatable attributes as empty string.
     */
    private function getEmptyTranslationsArray(): array
    {
        return array_fill_keys($this->localizable, '');
    }
}
