<?php

namespace Organi\Translatables\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @template TModelClass
 * @extends Builder<TModelClass>
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder whereTranslation(string $column, string $operator = null, $value = null, string $locale = null)
 * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder orderByTranslation(string $column, string $locale = null, string $direction = 'asc')
 */
class TranslatablesBuilder extends Builder
{
    public function delete()
    {
        $key = $this->model->getKeyName();

        // @phpstan-ignore-next-line
        DB::table($this->model->getTranslationsTable())
            ->whereIn($key, $this->toBase()->pluck($key))
            ->delete();

        return parent::delete();
    }
}
