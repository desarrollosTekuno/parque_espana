<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmailConfigRequest extends FormRequest {
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'entity_id' => ['required', 'integer'],
            'profile_name' => ['required', 'string', 'max:120'],
            'template_name' => ['nullable', 'string', 'max:50'],
            'host' => ['required', 'string', 'max:150'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:150'],
            'password' => $isUpdate
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl'],
            'from_address' => ['required', 'email', 'max:150'],
            'from_name' => ['required', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
