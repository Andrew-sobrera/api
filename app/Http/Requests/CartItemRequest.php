<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return [
                'quantity' => ['required', 'integer', 'min:0'],
            ];
        }

        return [
            'event_ticket_id' => ['required', 'integer', 'exists:ticket_events,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'batch_id' => ['nullable', 'integer', 'exists:ticket_batches,id'],
            'seat_id' => ['nullable', 'integer', 'exists:seats,id'],
            'sector_id' => ['nullable', 'integer', 'exists:event_sectors,id'],
        ];
    }
}
