<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $fromCart = $this->boolean('from_cart');

        $rules = [
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'card_token' => [
                Rule::requiredIf(fn () => $this->input('payment_method') === PaymentMethod::CREDIT_CARD->value),
                'nullable',
                'string',
            ],
            'document' => [
                Rule::requiredIf(fn () => ! $user?->document),
                'nullable',
                'string',
                'regex:/^\d{11}$|^\d{14}$/',
            ],
            'from_cart' => ['sometimes', 'boolean'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ];

        if ($fromCart) {
            return $rules;
        }

        if ($this->has('items')) {
            $rules['items'] = ['required', 'array', 'min:1'];
            $rules['items.*.event_ticket_id'] = ['required', 'integer', 'exists:ticket_events,id'];
            $rules['items.*.quantity'] = ['required', 'integer', 'min:1'];
            $rules['items.*.batch_id'] = ['nullable', 'integer', 'exists:ticket_batches,id'];
            $rules['items.*.seat_id'] = ['nullable', 'integer', 'exists:seats,id'];
            $rules['items.*.sector_id'] = ['nullable', 'integer', 'exists:event_sectors,id'];

            return $rules;
        }

        $rules['event_ticket_id'] = ['required', 'integer', 'exists:ticket_events,id'];
        $rules['quantity'] = ['required', 'integer', 'min:1'];
        $rules['batch_id'] = ['nullable', 'integer', 'exists:ticket_batches,id'];
        $rules['seat_id'] = ['nullable', 'integer', 'exists:seats,id'];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'card_token.required_if' => 'O token do cartão é obrigatório para pagamento com cartão de crédito.',
            'payment_method.required' => 'O método de pagamento é obrigatório.',
            'payment_method.enum' => 'O método de pagamento deve ser PIX ou CREDIT_CARD.',
        ];
    }
}
