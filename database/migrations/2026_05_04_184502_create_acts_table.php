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
        Schema::create('members.acts', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('member_id')->constrained('members.members')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('memberships.accounts')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();

            $table->string('violation_type');
            $table->text('description');

            $table->date('date');
            $table->time('time')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members.acts');
    }
};
