<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreFeedbackTicketRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'ticket_type_id' => ['required'],
            'category_id' => ['required'],
            'priority_id' => ['required'],
            'title' => ['required', 'string', 'max:85'],
            'description' => ['required', 'string'],
            'is_anonymous' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx', 'max:10240'],
        ];
    }

    public function messages(): array {
        return [
            'ticket_type_id.required' => 'Debes seleccionar un tipo.',
            'category_id.required' => 'Debes seleccionar una categoria.',
            'priority_id.required' => 'Debes seleccionar una prioridad.',
            'title.required' => 'Debes ingresar un titulo.',
            'title.max' => 'El titulo no puede exceder 85 caracteres.',
            'description.required' => 'Debes ingresar una descripcion.',
            'is_anonymous.boolean' => 'El campo de anonimato debe ser verdadero o falso.',
            'attachments.array' => 'Los adjuntos deben enviarse como una lista de archivos.',
            'attachments.max' => 'Solo puedes subir hasta 5 archivos por ticket.',
            'attachments.*.file' => 'Cada adjunto debe ser un archivo valido.',
            'attachments.*.mimes' => 'Formato no permitido. Usa: jpg, jpeg, png, pdf, doc, docx, xls o xlsx.',
            'attachments.*.max' => 'Cada archivo puede pesar maximo 10 MB.',
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
