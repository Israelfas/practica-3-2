<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

/**
 * Contrato del mensaje. Se valida antes de filtrar y enrutar.
 * "tipo" acepta cualquier texto a propósito: decidir si es conocido es
 * trabajo del enrutador, que manda los desconocidos a no_enrutables.
 */
class EventoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]{1,60}$/', 'unique:eventos,message_id'],
            'tipo' => ['required', 'string', 'max:50'],
            'prioridad' => ['required', 'in:alta,media,baja'],
            'datos' => ['required', 'array'],
            'datos.product_id' => ['required', 'integer', 'exists:damaged_products,id'],
            'datos.canal' => ['sometimes', 'in:email,sms'],
            'datos.cantidad' => ['sometimes', 'integer', 'min:1'],
            'correlation_id' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9._-]{1,100}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Falta el id del mensaje.',
            'id.unique' => 'Ya se recibió un mensaje con ese id.',
            'tipo.required' => 'Falta el tipo del mensaje.',
            'prioridad.required' => 'Falta la prioridad.',
            'prioridad.in' => 'La prioridad debe ser alta, media o baja.',
            'datos.required' => 'Faltan los datos del mensaje.',
            'datos.product_id.required' => 'Falta datos.product_id.',
            'datos.product_id.exists' => 'No existe un producto con ese id.',
            'datos.canal.in' => 'El canal debe ser email o sms.',
            'datos.cantidad.min' => 'La cantidad debe ser al menos 1.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('[validacion] Mensaje rechazado por contrato', [
            'id' => $this->input('id'),
            'campos' => array_keys($validator->errors()->toArray()),
        ]);

        throw new HttpResponseException(response()->json([
            'aceptado' => false,
            'estado' => 'invalido',
            'message' => 'El mensaje no cumple el contrato.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
