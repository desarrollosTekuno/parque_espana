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
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->integer('total_billable_guests')->nullable();
            $table->decimal('non_billable_subtotal', 12, 2)->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->foreign('member_id')->references('id')->on('members.members')->onDelete('cascade');
        });

        Schema::table('guest_lists.guest_list_items', function (Blueprint $table){
            $table->boolean('is_billable_to_member')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('is_comped')->default(false);
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
            $table->dropColumn('date');
            $table->dropColumn('time');
            $table->dropColumn('description');
            $table->dropColumn('total_billable_guests');
            $table->dropColumn('non_billable_subtotal');
            $table->dropForeign(['member_id']);
            $table->dropColumn('member_id');
        });

        Schema::table('guest_lists.guest_list_items', function (Blueprint $table) {
            $table->dropColumn('is_billable_to_member');
            $table->dropColumn('is_paid');
            $table->dropColumn('price');
            $table->dropColumn('is_comped');
        });
    }
};
