<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_price' => ['required', 'numeric', 'min:0.01'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'payment_type' => ['required', 'string', Rule::in(['PIX', 'CREDIT_CARD', 'BOLETO', 'DEBIT_CARD'])],
            'installments' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'fee_mode' => ['sometimes', 'string', Rule::in(['CUSTOMER', 'PRODUCER'])],
        ];
    }
}
