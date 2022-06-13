<?php

namespace Organi\Translatables\Builders;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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
