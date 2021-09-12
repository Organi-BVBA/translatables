<?php

namespace RoobieBoobieee\Translatables\Rules;

use Illuminate\Contracts\Validation\Rule;

class Translatable implements Rule
{
    /**
     * Create a new rule instance.
     */
    public function __construct()
    {
        //
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

        return is_array($value)
            && count($value) === count($locales)
            && count(array_diff($value, $locales)) === 0;
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
