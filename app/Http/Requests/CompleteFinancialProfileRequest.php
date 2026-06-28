<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteFinancialProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->producer !== null;
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', 'min:11', 'max:18'],
            'fantasy_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:20'],
            'email' => ['required', 'email'],
            'address' => ['required', 'array'],
            'address.street' => ['required', 'string', 'max:255'],
            'address.number' => ['required', 'string', 'max:20'],
            'address.district' => ['required', 'string', 'max:100'],
            'address.city' => ['required', 'string', 'max:100'],
            'address.state' => ['required', 'string', 'size:2'],
            'address.postal_code' => ['required', 'string', 'min:8', 'max:10'],
            'address.complement' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'CPF ou CNPJ é obrigatório.',
            'phone.required' => 'Telefone é obrigatório.',
            'address.required' => 'Endereço é obrigatório.',
            'address.postal_code.required' => 'CEP é obrigatório.',
        ];
    }
}
