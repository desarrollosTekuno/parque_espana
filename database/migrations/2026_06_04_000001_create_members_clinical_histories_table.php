<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('members.clinical_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained('members.members')
                ->cascadeOnDelete();

            // Tipo de sangre
            $table->string('blood_type', 5)->nullable();   // A, B, AB, O
            $table->string('blood_rh', 10)->nullable();    // positive, negative

            // Padecimientos
            $table->boolean('has_diabetes')->default(false);
            $table->string('diabetes_type', 5)->nullable(); // I, II
            $table->boolean('has_heart_condition')->default(false);
            $table->boolean('has_epilepsy')->default(false);
            $table->boolean('has_asthma')->default(false);
            $table->boolean('has_allergy')->default(false);

            // Medicamentos
            $table->boolean('takes_medication')->default(false);
            $table->text('medication_details')->nullable();

            // Alérgenos
            $table->boolean('has_allergens')->default(false);
            $table->text('allergen_details')->nullable();

            // Presión arterial
            $table->boolean('normal_blood_pressure')->nullable();
            $table->boolean('has_hypertension')->default(false);

            // Condiciones especiales
            $table->text('special_conditions')->nullable();

            // Contacto de emergencia
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('emergency_contact_mobile', 50)->nullable();
            $table->string('emergency_notify_name')->nullable();

            // Médico tratante
            $table->string('treating_physician')->nullable();
            $table->string('treating_physician_phone', 50)->nullable();

            // Seguridad social y seguro médico
            $table->string('social_security_number', 100)->nullable();
            $table->string('medical_insurance')->nullable();
            $table->string('insurance_company')->nullable();
            $table->string('insurance_policy_number', 100)->nullable();
            $table->string('insurance_mobile', 50)->nullable();

            $table->timestamps();

            $table->unique('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members.clinical_histories');
    }
};
