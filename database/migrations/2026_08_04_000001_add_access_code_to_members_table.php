<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Migration intentionally left empty — access_code moved to memberships.account_members
    // (same member can have different QR per club)
    public function up(): void {}
    public function down(): void {}
};
