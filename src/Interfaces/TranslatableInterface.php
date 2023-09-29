<?php

namespace Organi\Translatables\Interfaces;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Organi\Translatables\Builders\TranslatablesBuilder;
use Organi\Translatables\Models\Translation;

interface TranslatableInterface
{
    public function setTranslations(string $locale, array $attributes): void;

    public function setTranslation(string $locale, string $attribute, $value): void;

    public function translatable(?string $locale = null): array;

    public function setAllLocales(string $attribute, string $value): Translation;

    public function setAllTranslations(array $translations);

    public function translatables(): array;

    public function commitTranslations(): void;

    public function isTranslatableAttribute(string $attribute): bool;

    public function getTranslatedLocales(string $attribute): Translation;

    public function getTranslationsTable(): string;

    public function getLocalizable(): array;

    public function attributesToArray(bool $localizeOnly = false): array;

    public function toTranslatedArray(string $locale = null, bool $localizeOnly = false): array;

    public function scopeOrderByTranslation(
        Builder $query,
        string $column,
        string $locale = null,
        string $direction = 'asc'
    ): Builder | TranslatablesBuilder;

    public function scopeWhereTranslation(
        Builder $query,
        string $column,
        string $operator = null,
        $value = null,
        string $locale = null
    ): Builder | TranslatablesBuilder;

    public function joinTranslationsTable(QueryBuilder $query, string $locale = null): QueryBuilder;

    public function replicate(array $except = null);

    public function deleteTranslations();

    public static function translationsTable(): string;

    public static function translationsColumn(string $column): string;

    public function qualifyTranslationsColumn(string $column): string;

    public function getActiveLocales(): array;

    public function setOutputLocale(?string $locale): self;

    public function getOutputLocale(): ?string;

    public function getLocaleColumn(): ?string;

    public function locales(): array;

    public function getEmptyTranslation(): Translation;

    public function newEloquentBuilder(QueryBuilder $query);

    public function getDirtyTranslations(): array;

    public function getOriginaltranslation(string $locale, string $key, $default = null);
}
