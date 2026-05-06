<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS guest_lists');
        } else {
            // sqlserver
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'guest_lists')
                BEGIN
                    EXEC('CREATE SCHEMA guest_lists');
                END
            ");
        }

        Schema::dropIfExists('reservations.guest_list_items');
        Schema::dropIfExists('reservations.guest_lists');

        Schema::create('guest_lists.guest_lists', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->integer('total_guests');
            $table->integer('total_adults');
            $table->integer('total_children');
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('discount', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->date('approved_at')->nullable();
            $table->text('comments')->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations.reservations');
            $table->foreignId('club_id')->constrained('clubs.clubs');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('guest_lists.guest_list_items', function (Blueprint $table){
            $table->id();
            $table->string('name');
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('phone', 15)->nullable();
            $table->integer('age');
            $table->foreignId('guest_list_id')->constrained('guest_lists.guest_lists');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_lists.guest_list_items');
        Schema::dropIfExists('guest_lists.guest_lists');

        Schema::create('reservations.guest_lists', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->integer('total_guests');
            $table->integer('total_adults');
            $table->integer('total_children');
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('discount', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->date('approved_at')->nullable();
            $table->text('comments')->nullable();
            $table->foreignId('reservation_id')->constrained('reservations.reservations');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reservations.guest_list_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('age');
            $table->foreignId('guest_list_id')->constrained('reservations.guest_lists');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
