<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NotificacionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:damaged_products,id'],
            'canal' => ['required', 'in:email,sms'],
            'correlation_id' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9._-]{1,100}$/'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Los datos de la notificación no son válidos.',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Falta el product_id.',
            'product_id.integer' => 'El product_id debe ser un número.',
            'product_id.exists' => 'No existe un producto con ese id.',
            'canal.required' => 'Falta el canal.',
            'canal.in' => 'El canal debe ser email o sms.',
            'correlation_id.regex' => 'El correlation_id solo admite letras, números, punto, guion y guion bajo.',
        ];
    }
}
