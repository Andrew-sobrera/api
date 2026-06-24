<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait BelongsToProducer
{
    protected static function bootBelongsToProducer(): void
    {
        static::creating(function ($model) {
            $user = Auth::user();

            if (! $user || $user->role !== 'producer' || blank($user->producer_id)) {
                return;
            }

            if (blank($model->producer_id)) {
                $model->producer_id = $user->producer_id;
            }
        });
    }
}
