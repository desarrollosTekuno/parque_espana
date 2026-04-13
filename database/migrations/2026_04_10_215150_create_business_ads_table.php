<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('advertising.business_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')
                ->constrained('members.members')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->foreignId('status_id')
                ->default(1)
                ->constrained('advertising.business_ad_statuses');
            $table->timestamp('approved_at')
                ->nullable();
            $table->timestamp('paid_at')
                ->nullable();
            $table->timestamp('published_at')
                ->nullable();
            $table->timestamp('expires_at')
                ->nullable();
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertising.business_ads');
    }
};
