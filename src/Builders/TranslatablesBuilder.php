<?php

namespace Organi\Translatables\Builders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class TranslatablesBuilder extends Builder
{
    public function delete()
    {
        $key = $this->model->getKeyName();

        // @phpstan-ignore-next-line
        DB::table($this->model->getTranslationsTable())
            ->whereIn($key, $this->query->pluck($key))
            ->delete();

        return parent::delete();
    }
}
