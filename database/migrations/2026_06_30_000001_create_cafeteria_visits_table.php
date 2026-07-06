<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guest_lists.cafeteria_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->string('visitor_name', 200);
            $table->string('document_type', 50);   
            $table->string('document_number', 100);
            $table->timestamp('entered_at');
            $table->timestamp('expires_at');
            $table->decimal('min_consumption', 12, 2);
            $table->decimal('consumption_amount', 12, 2)->nullable();
            $table->boolean('access_fee_waived')->nullable();     
            $table->decimal('access_fee_charged', 12, 2)->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->boolean('document_returned')->default(false);
            $table->timestamp('document_returned_at')->nullable();
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->unsignedBigInteger('checked_out_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_lists.cafeteria_visits');
    }
};
