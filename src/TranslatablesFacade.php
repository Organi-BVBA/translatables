<?php

namespace RoobieBoobieee\Translatables;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RoobieBoobieee\Translatables\Translatables
 */
class TranslatablesFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'translatables';
    }
}
