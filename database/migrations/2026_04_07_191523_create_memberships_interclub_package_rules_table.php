<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memberships.interclub_package_rules', function (Blueprint $table) {
            $table->id();

            // Club origen (ej: PE1)
            $table->foreignId('source_club_id')
                ->constrained('clubs.clubs')
                ->cascadeOnDelete();

            // Club destino (ej: PE2)
            $table->foreignId('target_club_id')
                ->constrained('clubs.clubs')
                ->cascadeOnDelete();

            // Tipo de membresía destino (ej: PE2_FAM_ASC)
            $table->foreignId('target_membership_type_id')
                ->constrained('memberships.types')
                ->cascadeOnDelete();

            // Tipo de membresía origen (opcional)
            $table->foreignId('source_membership_type_id')
                ->nullable()
                ->constrained('memberships.types')
                ->nullOnDelete();

            // Código del paquete
            $table->string('package_code', 100);

            // Antigüedad requerida (ej: 5 años)
            $table->unsignedSmallInteger('min_years_in_source_club')->nullable();

            // Debe estar activa la membresía origen
            $table->boolean('requires_active_source_membership')->default(true);

            // Costos
            $table->decimal('inscription_fee', 12, 2)->default(0);
            $table->decimal('monthly_fee', 12, 2);

            // Prioridad (menor = más importante)
            $table->unsignedInteger('priority')->default(10);

            // Vigencia opcional
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            $table->boolean('is_active')->default(true);

            $table->json('metadata')->nullable();

            $table->timestamps();

            // Índices
            $table->index('source_club_id');
            $table->index('target_club_id');
            $table->index('target_membership_type_id');
            $table->index('source_membership_type_id');
            $table->index('package_code');
            $table->index('priority');

            // Evitar duplicados lógicos
            $table->unique([
                'source_club_id',
                'target_club_id',
                'target_membership_type_id',
                'source_membership_type_id',
                'package_code'
            ], 'uq_interclub_rules');
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships.interclub_package_rules');
    }
};
