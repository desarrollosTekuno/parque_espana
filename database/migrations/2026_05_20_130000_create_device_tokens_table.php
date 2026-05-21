<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Token FCM del dispositivo
            $table->text('token');

            // "android" | "ios" | "web"
            $table->string('platform', 20);

            // Nombre descriptivo del dispositivo (opcional, útil para debug)
            $table->string('device_name')->nullable();

            // false cuando el usuario hace logout o FCM reporta el token inválido
            $table->boolean('is_active')->default(true);

            // Cada vez que la app abre y manda el token se actualiza
            // Sirve para detectar y limpiar tokens de apps desinstaladas (> 60 días sin actividad)
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            // Un token es único por sí mismo (FCM garantiza unicidad global)
            $table->unique('token');

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
