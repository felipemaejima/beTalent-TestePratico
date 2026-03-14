<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'card_number' => ['required', 'string', 'size:16'],
            'cvv' => ['required', 'string', 'min:3', 'max:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'O produto é obrigatório.',
            'product_id.exists' => 'Produto não encontrado.',
            'quantity.required' => 'A quantidade é obrigatória.',
            'quantity.min' => 'A quantidade deve ser pelo menos 1.',
            'customer_name.required' => 'O nome do cliente é obrigatório.',
            'customer_email.required' => 'O e-mail do cliente é obrigatório.',
            'customer_email.email' => 'Informe um e-mail válido.',
            'card_number.required' => 'O número do cartão é obrigatório.',
            'card_number.size' => 'O número do cartão deve ter exatamente 16 dígitos.',
            'cvv.required' => 'O CVV é obrigatório.',
            'cvv.min' => 'O CVV deve ter pelo menos 3 dígitos.',
            'cvv.max' => 'O CVV deve ter no máximo 4 dígitos.',
        ];
    }
}
