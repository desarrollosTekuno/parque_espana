<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una cuenta sin account_group_id (frecuente en datos migrados —
     * account_group_id solo lo asigna la app al crear cuentas nuevas, ver
     * MemberController::createMembershipAccount) hasta ahora no podía
     * registrar ningún permiso por ausencia: MemberController::
     * storeAbsencePermit lo exigía como requisito. Ahora, si no hay grupo,
     * el permiso se liga directo a membership_account_id (ya era nullable)
     * y aplica solo a esa cuenta — ver
     * MembershipChargeService::resolveApplicableAbsencePermit.
     */
    public function up(): void
    {
        Schema::table('memberships.absence_permits', function (Blueprint $table) {
            $table->foreignId('account_group_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('memberships.absence_permits', function (Blueprint $table) {
            $table->foreignId('account_group_id')->nullable(false)->change();
        });
    }
};
