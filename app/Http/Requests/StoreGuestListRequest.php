<?php

namespace App\Http\Requests;

use App\Rules\ExistsInSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class StoreGuestListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'club_id' => ['required', new ExistsInSchema('clubs', 'clubs', 'id')],
            'reservation_id' => ['nullable', new ExistsInSchema('reservations', 'reservations', 'id')],
            'guests' => 'required|array|min:1',
            'guests.*.name' => 'required|string|max:200',
            'guests.*.last_name' => 'required|string|max:200',
            'guests.*.email' => 'required|email',
            'guests.*.phone' => 'required|string|max:15|regex:/^\+?[0-9]{8,15}$/',
            'guests.*.age' => 'required|integer',
        ];
    }

    public function messages()
    {
        return [
            'club_id.required' => 'El club es obligatorio.',
            'club_id.exists_in_schema' => 'El club proporcionado es inválido.',
            'reservation_id.exists_in_schema' => 'La  reservación es inválida.',
            'guests.required' => 'Los invitados son obligatorios.',
            'guests.array' => 'Los invitados deben ser un arreglo.',
            'guests.min' => 'Debe proporcionar al menos un invitado.',
            'guests.*.name.required' => 'El nombre del invitado es obligatorio.',
            'guests.*.name.string' => 'El nombre del invitado debe ser una cadena de texto.',
            'guests.*.name.max' => 'El nombre del invitado no puede exceder los 200 caracteres.',
            'guests.*.last_name.required' => 'Los apellidos son obligatorios.',
            'guests.*.last_name.string' => 'Los apellidos deben ser una cadena de texto.',
            'guests.*.last_name.max' => 'Los apellidos no puede exceder los 200 caracteres.',
            'guests.*.email.required' => 'El correo electrónico es obligatorio.',
            'guests.*.email.email' => 'El correo electrónico debe ser una dirección de correo válida.',
            'guests.*.phone.required' => 'El teléfono es obligatorio.',
            'guests.*.phone.string' => 'El teléfono debe ser un numero valido.',
            'guests.*.phone.max' => 'El teléfono no puede exceder los 15 caracteres.',
            'guests.*.phone.regex' => 'El teléfono debe ser un número válido.',
            'guests.*.age.required' => 'La edad es obligatoria.',
            'guests.*.age.integer' => 'La edad debe ser un número entero.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => 'Error de validación',
            'error_details' => $validator->errors()
        ], 200));
    }
}
