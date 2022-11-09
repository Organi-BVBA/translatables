<?php

namespace Organi\Translatables\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TranslationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        // Add a select on the main table
        $builder->getQuery()->addSelect($model->getTable() .  '.*');
        // Group by the main table id
        $builder->getQuery()->groupBy($model->getTable() .  '.id');

        foreach ($model->locales() as $locale) {
            // Generate an alias for the locale
            $as = 't' . $locale;

            // Join the translations table
            $builder->getQuery()->leftJoin(
                $model->getTranslationsTable() . ' as ' . $as,
                function ($join) use ($model, $as, $locale) {
                    $join->on(
                        $model->getQualifiedKeyName(),
                        '=',
                        $as . '.' . $model->getKeyName()
                    )
                     ->where($as . '.locale', '=', $locale);
                }
            );

            // Add a select for all attributes.
            // ex: select `tnl`.`name` as `nl.name`, `ten`.`name` as `en.name`...
            foreach ($model->getLocalizable() as $attribute) {
                $builder->getQuery()->addSelect(
                    $as . '.' . $attribute . ' as ' . $locale . '.' . $attribute,
                );
            }
        }
    }
}
