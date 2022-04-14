<?php

namespace Organi\Translatables\Rules;

use Illuminate\Contracts\Validation\Rule;

class Translatable implements Rule
{
    // When soft mode is false the passed translation object should
    // contain all accepted locales
    protected bool $soft;

    /**
     * Create a new rule instance.
     */
    public function __construct(bool $soft = true)
    {
        $this->soft = $soft;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $locales = config('translatables.accepted_locales');
        $usedLocales = array_keys($value);

        return is_array($value)
            && (count($value) === count($locales) || $this->soft)
            && 0 === count(array_diff($usedLocales, $locales));
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Invalid translatable object';
    }
}
