<?php

namespace App\Models;

use App\Enums\AsaasAccountStatus;
use App\Enums\PaymentFeeMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producer extends Model
{
    protected $fillable = [
        'name',
        'fantasy_name',
        'cnpj',
        'phone',
        'email',
        'address',
        'asaas_account_id',
        'asaas_wallet_id',
        'asaas_status',
        'asaas_onboarding_completed',
        'asaas_created_at',
        'ticket_commission_percentage',
        'payment_fee_mode',
    ];

    protected function casts(): array
    {
        return [
            'asaas_status' => AsaasAccountStatus::class,
            'payment_fee_mode' => PaymentFeeMode::class,
            'asaas_onboarding_completed' => 'boolean',
            'asaas_created_at' => 'datetime',
            'ticket_commission_percentage' => 'decimal:2',
            'address' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(ProducerPaymentMethod::class);
    }

    public function isAsaasReady(): bool
    {
        return $this->asaas_account_id !== null
            && $this->asaas_status === AsaasAccountStatus::ACTIVE;
    }

    public function hasAsaasAccount(): bool
    {
        return $this->asaas_account_id !== null;
    }

    public function acceptsPaymentMethod(string $method): bool
    {
        return $this->paymentMethods()
            ->where('payment_method', $method)
            ->where('active', true)
            ->exists();
    }
}
