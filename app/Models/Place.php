<?php

namespace App\Models;

use App\Models\Scopes\ProducerScope;
use App\Traits\BelongsToProducer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    use BelongsToProducer;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'producer_id',
        'name',
        'address',
        'address_normalized',
        'latitude',
        'longitude',
        'provider',
        'geocoding_status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ProducerScope());
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PlaceAudit::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
