<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_lists.visitor_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('day_pass_visitor_id')->nullable();
            $table->string('visitor_first_name');
            $table->string('visitor_last_name');
            $table->string('visitor_phone');
            $table->string('incident_type');
            $table->text('description');
            $table->decimal('charged_amount', 12, 2)->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_lists.visitor_incidents');
    }
};
