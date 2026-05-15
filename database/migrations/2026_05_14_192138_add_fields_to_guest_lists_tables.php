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
        Schema::table('guest_lists.guest_lists', function (Blueprint $table) {
            $table->renameColumn('subtotal', 'billable_subtotal');
            $table->string('title', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('total_billable_guests')->nullable();
            $table->decimal('non_billable_subtotal', 12, 2)->nullable();
        });

        Schema::table('guest_lists.guest_list_items', function (Blueprint $table){
            $table->boolean('is_billable_to_member')->nullable();
            $table->boolean('is_paid')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guest_lists.guest_lists', function (Blueprint $table) {
            $table->renameColumn('billable_subtotal', 'subtotal');
            $table->dropColumn('title');
            $table->dropColumn('description');
            $table->dropColumn('total_billable_guests');
            $table->dropColumn('non_billable_subtotal');
        });

        Schema::table('guest_lists.guest_list_items', function (Blueprint $table) {
            $table->dropColumn('is_billable_to_member');
            $table->dropColumn('is_paid');
        });
    }
};
