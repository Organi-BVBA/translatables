<?php

namespace Organi\Translatables\Models;

use Illuminate\Support\Arr;

class Translation implements \JsonSerializable
{
    // Should contain an array with keys referring to a locale
    private array $translations;

    public function __construct(array $translations = [])
    {
        // TODO: parse input?
        $this->translations = $translations;
    }

    public function __toString(): string
    {
        return $this->get();
    }

    public static function make(array $translations = []): self
    {
        if (! is_array($translations)) {
            $translations = [\App::getLocale() => $translations];
        }

        return new self($translations);
    }

    /**
     * Represents the translations in the current or given locale.
     *
     * @param null|mixed $locale
     */
    public function get($locale = null): string
    {
        if (! $locale) {
            $locale = \App::getLocale();
        }

        // Get the translations in the current locale, or return an empty array if its not set yet
        return (string) Arr::get($this->translations, $locale, '');
    }

    public function translations(): array
    {
        return $this->translations;
    }

    // @return string|array
    public function jsonSerialize()
    {
        // Check if locale/language property is set on the request.
        // If so, translate the object instead returning all translations
        // This way this will work for translated properties and mutators
        // or accessors returning a Translatable object
        $locale = request()->query('locale', request()->query('language'));

        if ($locale) {
            return $this->get($locale);
        }

        return $this->translations;
    }
}
