<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members.members', function (Blueprint $table) {
            // Colegio
            $table->string('school_name')->nullable()->after('occupation');

            // (Opcionales recomendados según tu formato)
            $table->string('birth_place')->nullable()->after('birthdate');
            $table->string('state')->nullable()->after('birth_place');
            $table->string('city')->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('members.members', function (Blueprint $table) {
            $table->dropColumn([
                'school_name',
                'birth_place',
                'state',
                'city',
            ]);
        });
    }
};