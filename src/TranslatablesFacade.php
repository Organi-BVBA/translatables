<?php

namespace Organi\Translatables;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Organi\Translatables\Translatables
 */
class TranslatablesFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'translatables';
    }
}
