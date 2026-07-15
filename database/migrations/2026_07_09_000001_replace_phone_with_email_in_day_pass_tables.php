<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_lists.day_pass_visitors', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->string('email')->nullable()->after('last_name');
        });

        Schema::table('guest_lists.visitor_incidents', function (Blueprint $table) {
            $table->dropColumn('visitor_phone');
            $table->string('visitor_email')->nullable()->after('visitor_last_name');
        });
    }

    public function down(): void
    {
        Schema::table('guest_lists.day_pass_visitors', function (Blueprint $table) {
            $table->dropColumn('email');
            $table->string('phone')->after('last_name');
        });

        Schema::table('guest_lists.visitor_incidents', function (Blueprint $table) {
            $table->dropColumn('visitor_email');
            $table->string('visitor_phone')->after('visitor_last_name');
        });
    }
};
