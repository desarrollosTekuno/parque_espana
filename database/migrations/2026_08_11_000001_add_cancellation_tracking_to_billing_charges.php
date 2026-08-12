<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.charges', function (Blueprint $table) {
            // Para cuando un cargo pasa a status='cancelled' por condonación
            // al dar de baja una cuenta (ver AccountCancellationController)
            // — deja mapeado quién lo condonó, cuándo y por qué, en vez de
            // solo desaparecer de los pendientes sin dejar rastro.
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->after('cancelled_at');
            $table->string('cancellation_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('billing.charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
