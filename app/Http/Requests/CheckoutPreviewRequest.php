<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fromCart = $this->boolean('from_cart');

        $rules = [
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'installments' => ['sometimes', 'integer', 'min:1', 'max:12'],
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

            return $rules;
        }

        $rules['event_ticket_id'] = ['required', 'integer', 'exists:ticket_events,id'];
        $rules['quantity'] = ['required', 'integer', 'min:1'];
        $rules['batch_id'] = ['nullable', 'integer', 'exists:ticket_batches,id'];
        $rules['seat_id'] = ['nullable', 'integer', 'exists:seats,id'];

        return $rules;
    }
}
