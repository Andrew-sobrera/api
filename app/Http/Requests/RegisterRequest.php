<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('document')) {
            $document = $this->sanitizeDigits((string) $this->input('document'));
            $payload['document'] = $document !== '' ? $document : null;
        }

        if ($this->has('cnpj')) {
            $payload['cnpj'] = $this->sanitizeDigits((string) $this->input('cnpj'));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'role' => ['required', 'string', Rule::in(['user', 'producer'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ];

        if ($this->input('role') === 'producer') {
            return array_merge($rules, [
                'company_name' => ['required', 'string', 'max:255'],
                'cnpj' => ['required', 'string', 'size:14', 'unique:producers,cnpj'],
            ]);
        }

        return array_merge($rules, [
            'document' => ['nullable', 'string', 'size:11'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnpj.size' => 'Informe um CNPJ válido com 14 dígitos.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado.',
            'document.size' => 'Informe um CPF válido com 11 dígitos.',
            'email.unique' => 'Este e-mail já está cadastrado.',
        ];
    }

    private function sanitizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
