<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClinicalHistoryRequest extends FormRequest
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
    
    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_diabetes'        => $this->boolean('has_diabetes'),
            'has_heart_condition' => $this->boolean('has_heart_condition'),
            'has_epilepsy'        => $this->boolean('has_epilepsy'),
            'has_asthma'          => $this->boolean('has_asthma'),
            'has_allergy'         => $this->boolean('has_allergy'),
            'takes_medication'    => $this->boolean('takes_medication'),
            'has_allergens'       => $this->boolean('has_allergens'),
            'has_hypertension'    => $this->boolean('has_hypertension'),
        ]);
    }

    public function messages(): array
    {
        return [
            'emergency_contact_name.regex'   => 'El nombre solo puede contener letras y espacios.',
            'emergency_notify_name.regex'    => 'El nombre solo puede contener letras y espacios.',
            'treating_physician.regex'       => 'El nombre del médico solo puede contener letras y espacios.',
            'emergency_contact_phone.digits' => 'El teléfono debe tener exactamente 10 dígitos.',
            'emergency_contact_mobile.digits'=> 'El celular debe tener exactamente 10 dígitos.',
            'treating_physician_phone.digits'=> 'El teléfono debe tener exactamente 10 dígitos.',
            'social_security_number.digits'  => 'El NSS debe tener exactamente 11 dígitos.',
            'insurance_mobile.digits'        => 'El celular debe tener exactamente 10 dígitos.',
        ];
    }

    public function rules(): array
    {
        return [
                'blood_type'               => ['nullable', 'string', 'max:5'],
                'blood_rh'                 => ['nullable', 'in:positive,negative'],
                'has_diabetes'             => ['boolean'],
                'diabetes_type'            => ['nullable', 'in:I,II'],
                'has_heart_condition'      => ['boolean'],
                'has_epilepsy'             => ['boolean'],
                'has_asthma'               => ['boolean'],
                'has_allergy'              => ['boolean'],
                'takes_medication'         => ['boolean'],
                'medication_details'       => ['nullable', 'string', 'max:1000'],
                'has_allergens'            => ['boolean'],
                'allergen_details'         => ['nullable', 'string', 'max:1000'],
                'normal_blood_pressure'    => ['nullable', 'boolean'],
                'has_hypertension'         => ['boolean'],
                'special_conditions'       => ['nullable', 'string', 'max:2000'],
                'emergency_contact_name'   => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s.\'\-`]+$/u'],
                'emergency_contact_phone'  => ['nullable', 'digits:10'],
                'emergency_contact_mobile' => ['nullable', 'digits:10'],
                'emergency_notify_name'    => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s.\'\-`]+$/u'],
                'treating_physician'       => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s.\'\-`]+$/u'],
                'treating_physician_phone' => ['nullable', 'digits:10'],
                'social_security_number'   => ['nullable', 'digits:11'],
                'medical_insurance'        => ['nullable', 'string', 'max:255'],
                'insurance_company'        => ['nullable', 'string', 'max:255'],
                'insurance_policy_number'  => ['nullable', 'string', 'max:100'],
                'insurance_mobile'         => ['nullable', 'digits:10'],
        ];
    }
}
