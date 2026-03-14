<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do produto é obrigatório.',
            'amount.required' => 'O valor do produto é obrigatório.',
            'amount.integer' => 'O valor deve ser um número inteiro em centavos.',
            'amount.min' => 'O valor deve ser pelo menos 1 centavo.',
        ];
    }
}
