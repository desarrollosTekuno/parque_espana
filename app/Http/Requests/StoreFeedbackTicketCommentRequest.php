<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreFeedbackTicketCommentRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'comment' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array {
        return [
            'comment.required' => 'Debes ingresar un comentario.',
            'comment.max' => 'El comentario no puede exceder 500 caracteres.',
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
