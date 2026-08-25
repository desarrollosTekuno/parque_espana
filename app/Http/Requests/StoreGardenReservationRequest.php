<?php

namespace App\Http\Requests;

use App\Rules\ExistsInSchema;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreGardenReservationRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'club_id'             => ['required', new ExistsInSchema('clubs', 'clubs', 'id')],
            'member_id'           => ['nullable', new ExistsInSchema('members', 'members', 'id')],
            'garden_resource_id'  => ['required_without:grill_resource_id', 'nullable', new ExistsInSchema('amenities', 'resources', 'id')],
            'grill_resource_id'   => ['required_without:garden_resource_id', 'nullable', new ExistsInSchema('amenities', 'resources', 'id')],
            'date'                => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'requires_tent'       => ['nullable', 'boolean'],
            'tables_count'        => ['required', 'integer', 'min:0'],
            'chairs_count'        => ['required', 'integer', 'min:0'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array {
        return [
            'club_id.required'                 => 'Debes indicar el club.',
            'garden_resource_id.required_without' => 'Debes seleccionar un jardín o un asador.',
            'grill_resource_id.required_without'  => 'Debes seleccionar un jardín o un asador.',
            'date.required'                    => 'Debes seleccionar el día de uso.',
            'date.after_or_equal'              => 'La fecha de uso no puede ser anterior a hoy.',
            'tables_count.required'            => 'Debes indicar el número de tablones.',
            'chairs_count.required'            => 'Debes indicar el número de sillas.',
        ];
    }

    protected function failedValidation(Validator $validator): void {
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Errores de validacion',
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }
}
