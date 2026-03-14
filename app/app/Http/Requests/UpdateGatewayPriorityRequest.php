<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGatewayPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'priority' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'priority.required' => 'A prioridade é obrigatória.',
            'priority.integer' => 'A prioridade deve ser um número inteiro.',
            'priority.min' => 'A prioridade deve ser pelo menos 1.',
        ];
    }
}
