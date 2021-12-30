<?php

namespace Organi\Translatables\Tests;

use Illuminate\Database\Eloquent\Model;
use Organi\Translatables\Traits\HasTranslations;

class Product extends Model
{
    use HasTranslations;

    /**
     * The attributes that should be translatable.
     */
    protected array $localizable = [
        'title', 'description',
    ];
}
