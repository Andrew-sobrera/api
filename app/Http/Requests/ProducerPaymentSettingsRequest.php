<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProducerPaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->producer !== null;
    }

    public function rules(): array
    {
        return [
            'payment_fee_mode' => ['sometimes', 'string', Rule::in(['CUSTOMER', 'PRODUCER'])],
            'ticket_commission_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'payment_methods' => ['sometimes', 'array', 'min:1'],
            'payment_methods.*.payment_method' => ['required', 'string', Rule::in(['PIX', 'CREDIT_CARD', 'BOLETO', 'DEBIT_CARD'])],
            'payment_methods.*.max_installments' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'payment_methods.*.active' => ['sometimes', 'boolean'],
        ];
    }
}
