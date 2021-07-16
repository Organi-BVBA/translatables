<?php

namespace Roobieboobieee\Translatables;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Roobieboobieee\Translatables\Translatables
 */
class TranslatablesFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'translatables';
    }
}
