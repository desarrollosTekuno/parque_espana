<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS guest_lists');
        } else {
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'guest_lists')
                BEGIN
                    EXEC('CREATE SCHEMA guest_lists');
                END
            ");
        }

        Schema::create('guest_lists.day_passes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('member_id');
            $table->date('date');
            $table->integer('total_visitors');
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('payment_method_id');
            $table->timestamp('paid_at');
            $table->string('reference')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('check_number')->nullable();
            $table->timestamp('ticket_sent_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_lists.day_passes');
    }
};
