<?php

namespace Organi\Translatables\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Organi\Translatables\Builders\TranslatablesBuilder;
use Organi\Translatables\Models\Translation;

/**
 * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder whereTranslation(string $column, string $operator = null, $value = null, string $locale = null)
 * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder orderByTranslation(string $column, string $locale = null, string $direction = 'asc')
 */
trait HasTranslations
{
    /**
     * Contains the original translations.
     *
     * @var array
     */
    private $originalTranslations;

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
     * Locale that should be returned when casting to array
     * ex: This is useful when pushing a model to a search index.
     * If you want to have an index per locale.
     * You'll split out each model for every locale you have
     * and set this attribute on the model.
     */
    private ?string $outputLocale = null;

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

        // Delete the translations when deleting the model
        static::deleting(function ($model) {
            // Keep translations when soft-deleting an item
            if (! in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($model), true)) {
                $model->deleteTranslations();
            }
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
     * Set a single property for a single locale.
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

        // If the locale is not initialized yet, and the value is empty, we don't have to do anything.
        // Otherwise we'll just clutter the dirty array
        if (! Arr::has($this->translations, $locale) && ($value === '' || is_null($value))) {
            return;
        }

        // If the locale is not initialized yet -> do it now
        if (! Arr::has($this->translations, $locale)) {
            Arr::set($this->translations, $locale, $this->getEmptyTranslationsArray());
        }

        /*
         * We store null values on purpose as ''
         *
         * From High Performance MySQL, 3rd Edition
         *
         *  - Avoid NULL if possible.
         * A lot of tables include nullable columns even when the application
         * does not need to store NULL (the absence of a value),
         * merely because it’s the default.
         * It’s usually best to specify columns as NOT NULL unless you intend
         * to store NULL in them. It’s harder for MySQL to optimize queries
         * that refer to nullable columns, because they make indexes,
         * index statistics, and value comparisons more complicated.
         */
        if (is_null($value)) {
            $value = '';
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
     * Set a single property for all available locales.
     */
    public function setAllLocales(string $attribute, string $value): Translation
    {
        foreach ($this->locales() as $locale) {
            $this->setTranslation($locale, $attribute, $value);
        }

        return $this->getAttribute($attribute);
    }

    /**
     * Set translations (for easy replication).
     *
     * @return $this
     */
    public function setAllTranslations(array $translations)
    {
        $this->translations = $translations;
        $this->dirty = true;

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

        // Empty translations array
        $this->translations = [];

        // If the key is null, the item is new
        // There will be no translations in the database
        if ($this->getKey() === null) {
            return $this->translations;
        }

        $columnsInDatabase = $this->translatableColumnsInDatabase();

        $allowedColumns = array_intersect($columnsInDatabase, $this->localizable);


        // TODO: Now this an array. Should prolly return a custom translations object
        $translations = DB::table($this->getTranslationsTable())
            ->where($this->getKeyName(), $this->getKey())
            ->select(array_merge([$this->getLocaleColumn()], $allowedColumns))
            ->get();

        foreach ($translations as $translation) {
            // Get the locale from the object
            $locale = $translation->locale;

            // Remove the locale field from the data
            unset($translation->locale);

            $this->translations[$locale] = (array) $translation;
        }

        $this->originalTranslations = $this->translations;

        return $this->translations;
    }

    private function translatableColumnsInDatabase(): array
    {
        $key = $this->getTranslationsTable() . '_columns';

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $columns = Schema::getColumnListing($this->getTranslationsTable());

        Cache::put($key, $columns);

        return $columns;
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
                if (null === implode('', $translatable) || '' === implode('', $translatable)) {
                    // All translatable values are null or empty. Delete the record.
                    DB::table($this->getTranslationsTable())
                        ->where($this->getKeyName(), $this->getKey())
                        ->where($this->getLocaleColumn(), $locale)
                        ->delete();
                } else {
                    DB::table($this->getTranslationsTable())
                        ->updateOrInsert(
                            [
                                $this->getKeyName() => $this->getKey(), $this->getLocaleColumn() => $locale,
                            ],
                            $translatable
                        );
                }
            }
        });

        // Touch the model without raising events
        // Otherwise we'll end up in an infinite loop
        static::withoutEvents(function () {
            return $this->touch();
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

    public function toTranslatedArray(string $locale = null, bool $localizeOnly = false): array
    {
        if (is_null($locale)) {
            $locale = App::getLocale();
        }

        return array_merge(
            $localizeOnly ? [] : parent::attributesToArray(),
            $this->translatable($locale)
        );
    }

    public function scopeOrderByTranslation(
        Builder $query,
        string $column,
        string $locale = null,
        string $direction = 'asc'
    ): Builder | TranslatablesBuilder {
        $this->joinTranslationsTable($query->getQuery());

        if (is_null($locale)) {
            $locale = App::getLocale();
        }

        $query->where($this->getLocaleColumn(), $locale);

        // Get the table + field name for the where clause
        $column = $this->getTranslationsTable() . '.' . $column;

        return $query->orderBy($column, $direction);
    }

    /**
     * Add scope.
     *
     * @param ?mixed $operator
     * @param ?mixed $value
     */
    public function scopeWhereTranslation(
        Builder $query,
        string $column,
        string $operator = null,
        $value = null,
        string $locale = null
    ): Builder | TranslatablesBuilder {
        [$value, $operator] = $query->getQuery()->prepareValueAndOperator(
            $value,
            $operator,
            2 === func_num_args()
        );

        $this->joinTranslationsTable($query->getQuery());

        if (! is_null($locale)) {
            $query->where($this->qualifyTranslationsColumn($this->getLocaleColumn()), $locale);
        }

        // Get the table + field name for the where clause
        $column = $this->qualifyTranslationsColumn($column);

        return $query->where($column, $operator, $value);
    }

    public function joinTranslationsTable(QueryBuilder $query, string $locale = null): QueryBuilder
    {
        // Check if table is already joined
        $joined = false;

        foreach ($query->joins ?: [] as $join) {
            $joined = $joined || $join->table === $this->getTranslationsTable();
        }

        if (! $joined) {
            // Get the table + field names for the join
            $t = $this->getTable() . '.' . $this->getKeyName();
            $tt = $this->getTranslationsTable() . '.' . $this->getKeyName();
            $localeColumn = $this->qualifyTranslationsColumn($this->getLocaleColumn());

            // Join the translations table
            $query->leftJoin($this->getTranslationsTable(), function ($join) use ($t, $tt, $localeColumn, $locale) {
                $join->on($t, '=', $tt);

                // Add option to filter on locale directly
                if (! is_null($locale)) {
                    $join->where($localeColumn, '=', $locale);
                };
            });

            // $query->select($this->getTable() . '.*');
        }

        return $query;
    }

    public function replicate(array $except = null)
    {
        $new = parent::replicate();

        $new->setAllTranslations($this->translatables());

        return $new;
    }

    /**
     * Delete translations for a specific model.
     */
    public function deleteTranslations(): void
    {
        DB::table($this->getTranslationsTable())
            ->where($this->getKeyName(), $this->getKey())
            ->delete();
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

    // Return an array of all set locales for this model
    public function getActiveLocales(): array
    {
        return array_keys($this->translatables());
    }

    public function setOutputLocale(?string $locale): self
    {
        if (! in_array($locale, $this->locales())) {
            throw new \RuntimeException('Locale \'' . $locale . '\' is not allowed');
        }

        $this->outputLocale = $locale;

        return $this;
    }

    public function getOutputLocale(): ?string
    {
        return $this->outputLocale;
    }

    public function getLocaleColumn(): ?string
    {
        return 'locale';
    }

    public function locales(): array
    {
        return config('translatables.accepted_locales');
    }

    /**
     * Returns an array with all translatable attributes as empty string.
     */
    public function getEmptyTranslation(): Translation
    {
        return Translation::make(array_fill_keys($this->locales(), ''));
    }

    /**
     * @param QueryBuilder  $query
     *
     * @return TranslatablesBuilder
     */
    public function newEloquentBuilder($query)
    {
        return new TranslatablesBuilder($query);
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

            $translation = $this->getTranslatedLocales($key);

            if (! is_null($this->getOutputLocale())) {
                $attributes[$key] = $translation->get($this->getOutputLocale());
            } else {
                $attributes[$key] = $translation;
            }
        }

        return $attributes;
    }

    /**
     * Returns an array with all translatable attributes as empty string.
     */
    private function getEmptyTranslationsArray(): array
    {
        return array_fill_keys($this->localizable, '');
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @return array
     */
    public function getDirtyTranslations(): array
    {
        $dirty = [];

        foreach ($this->getActiveLocales() as $locale) {
            $original = Arr::get($this->originalTranslations, $locale, []);
            $new = Arr::get($this->translations, $locale, []);
            $diff = array_diff($new, $original);

            if (empty($diff)) {
                continue;
            }

            foreach ($diff as $name => $value) {
                $dirty[$name][$locale] = $value;
            }
        }

        return $dirty;
    }

    public function getOriginaltranslation(string $locale, string $key, $default = null)
    {
        return Arr::get($this->originalTranslations, implode('.', [ $locale, $key ]), $default);
    }
    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function newCollection(array $models = [])
    {
        // If there's 0 or 1 model in this collection there's no
        // performance improvement if we retrieve the collections now
        if (count($models) <= 1) {
            return parent::newCollection($models);
        }

        // When you call the get method the builder executes the query and collects all
        // of the results using the getModels method. getModels is using Model::hydrate to
        // build the first collection ( using newCollection ) without eager loads but then
        // needs to unwrap it because getModels needs to return an array. After eager loading
        // is complete ( if necessary ) then newCollection is called again to return the
        // newly eager loaded dataset.
        // So make sure translations aren't already loaded
        $loaded = is_array($models[0]->translations);

        if ($loaded) {
            // If so, skip the bottom part
            return parent::newCollection($models);
        }

        // Get all ids
        $ids = array_map(function ($model) {
            return $model->id;
        }, $models);

        // Get all translations at once
        $ts = DB::table($this->getTranslationsTable())
                ->whereIn($this->getKeyName(), $ids)
                ->select(array_merge([$this->getKeyName(), $this->getLocaleColumn()], $this->localizable))
                ->get()
                ->groupBy($this->getKeyName());

        // Get the fields that are defined as translatables
        $localizable = $this->localizable;

        // Set the translations for all models
        foreach ($models as $model) {
            $translations = Arr::get($ts, $model->id);

            if (is_null($translations)) {
                // No translations for this model
                $model->setAllTranslations([]);

                continue;
            }


            $translations = $translations
                ->keyBy('locale')
                ->map(
                    function ($t) use ($localizable) {
                        // Only keep translations (remove id and locale values)
                        foreach ($t as $key => $value) {
                            if (! in_array($key, $localizable)) {
                                unset($t->{$key});
                            }
                        }

                        return (array) $t;
                    }
                );

            $model->setAllTranslations($translations->toArray());
        }

        return parent::newCollection($models);
    }
}
