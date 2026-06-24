<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ProducerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user || $user->role !== 'producer' || blank($user->producer_id)) {
            return;
        }

        $builder->where($model->getTable().'.producer_id', $user->producer_id);
    }
}
