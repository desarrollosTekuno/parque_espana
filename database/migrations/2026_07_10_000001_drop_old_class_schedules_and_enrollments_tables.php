<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // Se elimina primero por su llave foránea hacia class_schedules.
        Schema::dropIfExists('classes.class_enrollments');
        Schema::dropIfExists('classes.class_schedules');
    }

    public function down(): void
    {
        // Migración de limpieza de un solo sentido: las migraciones que
        // recrean estas tablas con la nueva estructura son las que
        // restauran el esquema.
    }
};
