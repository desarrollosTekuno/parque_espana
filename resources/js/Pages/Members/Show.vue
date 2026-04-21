<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";

interface SourceMembership {
    id: number;
    membership_number: string | null;
    holder_name: string;
    membership_type_name: string | null;
    membership_type_code: string | null;
    club_name: string | null;
    club_code: string | null;
    status: string;
    start_date: string | null;
}

interface ActiveMembershipItem {
    id: number;
    membership_type_name: string | null;
    membership_type_code: string | null;
    club_name: string | null;
    club_code: string | null;
    monthly_fee: number;
    monthly_fee_total: number;
    monthly_fee_share: number;
    billing_split_mode: string | null;
    is_billable: boolean;
    status: string;
    start_date: string | null;
    end_date: string | null;
}

interface AbsencePermitItem {
    id: number;
    start_date: string;
    end_date: string;
    charge_percentage: number;
    status: string;
    blocks_facility_access: boolean;
    blocks_reservations: boolean;
    notes: string | null;
    approved_at: string | null;
}

interface MemberAddress {
    street: string | null;
    neighborhood: string | null;
    postal_code: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    years_in_city: number | null;
}

interface MemberEmployment {
    company_name: string | null;
    company_address: string | null;
    company_phone: string | null;
}

interface AccountMemberItem {
    member_id: number;
    full_name: string;
    relationship_id: number | null;
    relationship_name: string | null;
    email: string | null;
    phone: string | null;
    is_primary_holder: boolean;
    birthdate: string | null;
    age: number | null;
    birth_place: string | null;
    city: string | null;
    state: string | null;
    nationality: string | null;
    marital_status: string | null;
    occupation: string | null;
    school_name: string | null;
    address: MemberAddress;
    employment: MemberEmployment;
}

interface MembershipAccount {
    id: number;
    membership_number: string | null;
    account_club_name: string | null;
    account_club_code: string | null;
    account_type: string | null;
    status: string | null;
    current_monthly_fee: number;
    absence_permit_preview_fee: number | null;
    current_absence_permit: AbsencePermitItem | null;
    absence_permits: AbsencePermitItem[];
    primary_holder: AccountMemberItem | null;
    members: AccountMemberItem[];
    active_memberships: ActiveMembershipItem[];
}

interface Props {
    membership: SourceMembership;
    account: MembershipAccount;
    canAddFamilyMembers?: boolean;
    canChangePrimaryHolder?: boolean;
    canSeparateMembers?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canAddFamilyMembers: false,
    canChangePrimaryHolder: false,
    canSeparateMembers: false,
});

const showAbsencePermitDialog = ref(false);
const absencePermitForm = useForm({
    start_date: "",
    end_date: "",
    charge_percentage: 25,
    notes: "",
});

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const accountTypeLabel = computed(() =>
    props.account.account_type === "family" ? "Familiar" : "Individual",
);

const statusLabel = (status: string | null) => {
    const map: Record<string, string> = {
        active: "Activa",
        approved: "Programado",
        finished: "Finalizado",
        pending: "Pendiente",
        suspended: "Suspendida",
        cancelled: "Cancelada",
    };

    return status ? map[status] ?? status : "-";
};

const statusColor = (status: string | null) => {
    const map: Record<string, string> = {
        active: "success",
        approved: "info",
        finished: "default",
        pending: "warning",
        suspended: "orange",
        cancelled: "error",
    };

    return status ? map[status] ?? "default" : "default";
};

const formatDate = (value: string | null) => {
    if (!value) return "-";

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    }).format(date);
};

const addressSummary = (member: AccountMemberItem) => {
    const address = member.address;

    return [
        address.street,
        address.neighborhood,
        address.city,
        address.state,
        address.country,
    ]
        .filter(Boolean)
        .join(", ");
};

const openAbsencePermitDialog = () => {
    absencePermitForm.reset();
    absencePermitForm.clearErrors();
    absencePermitForm.charge_percentage = 25;
    showAbsencePermitDialog.value = true;
};

const submitAbsencePermit = () => {
    absencePermitForm.post(route("members.absence-permits.store", props.membership.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAbsencePermitDialog.value = false;
            absencePermitForm.reset();
            absencePermitForm.charge_percentage = 25;
        },
    });
};

const cancelAbsencePermit = (absencePermitId: number) => {
    router.patch(
        route("members.absence-permits.cancel", {
            membership: props.membership.id,
            absencePermit: absencePermitId,
        }),
        {},
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Gestionar Cuenta" />

    <AppLayout>
        <template #header>Gestionar Cuenta</template>
        <template #options>
            <div class="d-flex flex-wrap ga-2">
                <BaseButton
                    :icon-only="false"
                    action="cancel"
                    text="Volver"
                    @click="router.visit(route('members.index'))"
                />

                <BaseButton
                    v-if="props.canAddFamilyMembers"
                    :icon-only="false"
                    action="add"
                    text="Agregar familiar"
                    @click="
                        router.visit(
                            route('members.family-members.create', props.membership.id),
                        )
                    "
                />
            </div>
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12" md="11" class="mx-auto">
                    <v-container class="py-6">
                        <v-row>
                            <v-col cols="12" md="4">
                                <v-card class="pa-4 h-100" variant="tonal">
                                    <div class="text-caption text-medium-emphasis">
                                        Cuenta
                                    </div>
                                    <div class="text-h6 font-weight-bold">
                                        {{ props.account.membership_number || "-" }}
                                    </div>
                                    <div class="text-body-2 mt-2">
                                        {{ props.account.account_club_code || "-" }} ·
                                        {{ props.account.account_club_name || "Sin club" }}
                                    </div>
                                    <div class="text-body-2 mt-2">
                                        Cuenta {{ accountTypeLabel }}
                                    </div>
                                    <div class="text-body-2">
                                        Estatus {{ statusLabel(props.account.status) }}
                                    </div>
                                </v-card>
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-card class="pa-4 h-100" variant="tonal">
                                    <div class="text-caption text-medium-emphasis">
                                        Titular actual
                                    </div>
                                    <div class="text-h6 font-weight-bold">
                                        {{ props.account.primary_holder?.full_name || "-" }}
                                    </div>
                                    <div class="text-body-2 mt-2">
                                        {{ props.account.primary_holder?.email || "Sin correo" }}
                                    </div>
                                    <div class="text-body-2">
                                        {{ props.account.primary_holder?.phone || "Sin teléfono" }}
                                    </div>
                                </v-card>
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-card class="pa-4 h-100" variant="tonal">
                                    <div class="text-caption text-medium-emphasis">
                                        Cuota actual
                                    </div>
                                    <div class="text-h6 font-weight-bold">
                                        {{
                                            currencyFormatter.format(
                                                props.account.current_monthly_fee,
                                            )
                                        }}
                                    </div>
                                    <div class="text-body-2 mt-2">
                                        Total actual a cobrar
                                    </div>
                                </v-card>
                            </v-col>
                        </v-row>

                        <v-card class="pa-4 mt-4">
                            <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-4">
                                <div>
                                    <div class="text-subtitle-1 font-weight-bold">
                                        Acciones de la cuenta
                                    </div>
                                    <div class="text-body-2 text-medium-emphasis">
                                        Desde aquí puedes gestionar la membresía y sus integrantes.
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap ga-2">
                                    <v-btn
                                        v-if="props.canChangePrimaryHolder"
                                        color="primary"
                                        variant="tonal"
                                        @click="
                                            router.visit(
                                                route(
                                                    'members.change-holder.create',
                                                    props.membership.id,
                                                ),
                                            )
                                        "
                                    >
                                        Cambiar titular
                                    </v-btn>

                                    <v-btn
                                        v-if="props.canSeparateMembers"
                                        color="primary"
                                        variant="tonal"
                                        @click="
                                            router.visit(
                                                route(
                                                    'members.separation.create',
                                                    props.membership.id,
                                                ),
                                            )
                                        "
                                    >
                                        Separar integrante
                                    </v-btn>

                                    <v-btn
                                        v-if="props.canAddFamilyMembers"
                                        color="primary"
                                        @click="
                                            router.visit(
                                                route(
                                                    'members.family-members.create',
                                                    props.membership.id,
                                                ),
                                            )
                                        "
                                    >
                                        Agregar familiar
                                    </v-btn>
                                </div>
                            </div>
                        </v-card>

                        <v-card class="pa-4 mt-4">
                            <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-4">
                                <div>
                                    <div class="text-subtitle-1 font-weight-bold">
                                        Permiso por ausencia
                                    </div>
                                    <div class="text-body-2 text-medium-emphasis">
                                        Durante su vigencia se cobra el porcentaje configurado sobre las membresías cobrables y se bloquea el uso de instalaciones.
                                    </div>
                                </div>

                                <v-btn color="primary" variant="tonal" @click="openAbsencePermitDialog">
                                    Registrar permiso
                                </v-btn>
                            </div>

                            <v-row>
                                <v-col cols="12" md="4">
                                    <v-card variant="tonal" class="pa-4 h-100">
                                        <div class="text-caption text-medium-emphasis">
                                            Estado actual
                                        </div>
                                        <div class="text-h6 font-weight-bold">
                                            {{
                                                props.account.current_absence_permit
                                                    ? statusLabel(
                                                          props.account.current_absence_permit.status,
                                                      )
                                                    : "Sin permiso activo"
                                            }}
                                        </div>
                                        <div
                                            v-if="props.account.current_absence_permit"
                                            class="text-body-2 mt-2"
                                        >
                                            Vigencia:
                                            {{ formatDate(props.account.current_absence_permit.start_date) }}
                                            a
                                            {{ formatDate(props.account.current_absence_permit.end_date) }}
                                        </div>
                                    </v-card>
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-card variant="tonal" class="pa-4 h-100">
                                        <div class="text-caption text-medium-emphasis">
                                            Porcentaje durante permiso
                                        </div>
                                        <div class="text-h6 font-weight-bold">
                                            {{
                                                props.account.current_absence_permit
                                                    ? `${props.account.current_absence_permit.charge_percentage}%`
                                                    : "25%"
                                            }}
                                        </div>
                                        <div class="text-body-2 mt-2">
                                            Aplicado sobre membresías cobrables del titular.
                                        </div>
                                    </v-card>
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-card variant="tonal" class="pa-4 h-100">
                                        <div class="text-caption text-medium-emphasis">
                                            Cuota estimada con permiso
                                        </div>
                                        <div class="text-h6 font-weight-bold">
                                            {{
                                                props.account.absence_permit_preview_fee !== null
                                                    ? currencyFormatter.format(
                                                          props.account.absence_permit_preview_fee,
                                                      )
                                                    : "-"
                                            }}
                                        </div>
                                        <div class="text-body-2 mt-2">
                                            Estimado mensual mientras el permiso esté activo.
                                        </div>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <div class="mt-4">
                                <div class="text-subtitle-2 font-weight-bold mb-3">
                                    Historial
                                </div>

                                <div
                                    v-if="!props.account.absence_permits.length"
                                    class="text-body-2 text-medium-emphasis"
                                >
                                    No hay permisos por ausencia registrados.
                                </div>

                                <div v-else class="d-flex flex-column ga-3">
                                    <div
                                        v-for="absencePermit in props.account.absence_permits"
                                        :key="absencePermit.id"
                                        class="border rounded-lg px-4 py-3"
                                    >
                                        <div class="d-flex flex-wrap align-center justify-space-between ga-2">
                                            <div>
                                                <div class="font-weight-medium">
                                                    {{ formatDate(absencePermit.start_date) }}
                                                    a
                                                    {{ formatDate(absencePermit.end_date) }}
                                                </div>
                                                <div class="text-caption text-medium-emphasis">
                                                    {{ absencePermit.charge_percentage }}% sobre cuota cobrable
                                                </div>
                                            </div>

                                            <div class="d-flex flex-wrap ga-2">
                                                <v-chip
                                                    size="small"
                                                    :color="statusColor(absencePermit.status)"
                                                    variant="tonal"
                                                >
                                                    {{ statusLabel(absencePermit.status) }}
                                                </v-chip>

                                                <v-btn
                                                    v-if="
                                                        ['approved', 'active'].includes(
                                                            absencePermit.status,
                                                        )
                                                    "
                                                    color="error"
                                                    size="small"
                                                    variant="text"
                                                    @click="
                                                        cancelAbsencePermit(absencePermit.id)
                                                    "
                                                >
                                                    Cancelar
                                                </v-btn>
                                            </div>
                                        </div>

                                        <div class="text-body-2 mt-2">
                                            {{
                                                absencePermit.blocks_facility_access
                                                    ? "Bloquea instalaciones"
                                                    : "No bloquea instalaciones"
                                            }}
                                            ·
                                            {{
                                                absencePermit.blocks_reservations
                                                    ? "Bloquea reservaciones"
                                                    : "No bloquea reservaciones"
                                            }}
                                        </div>

                                        <div
                                            v-if="absencePermit.notes"
                                            class="text-body-2 text-medium-emphasis mt-2"
                                        >
                                            {{ absencePermit.notes }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </v-card>

                        <v-card class="pa-4 mt-4">
                            <div class="text-subtitle-1 font-weight-bold mb-4">
                                Membresías activas
                            </div>

                            <div class="d-flex flex-column ga-3">
                                <div
                                    v-for="activeMembership in props.account.active_memberships"
                                    :key="activeMembership.id"
                                    class="border rounded-lg px-4 py-3"
                                >
                                    <div class="d-flex flex-wrap align-center justify-space-between ga-2">
                                        <div>
                                            <div class="font-weight-medium">
                                                {{ activeMembership.membership_type_name }}
                                            </div>
                                            <div class="text-caption text-medium-emphasis">
                                                {{ activeMembership.club_code }} ·
                                                {{ activeMembership.club_name }}
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap ga-2">
                                            <v-chip
                                                size="small"
                                                :color="
                                                    activeMembership.is_billable
                                                        ? 'success'
                                                        : 'default'
                                                "
                                                :variant="
                                                    activeMembership.is_billable
                                                        ? 'flat'
                                                        : 'tonal'
                                                "
                                            >
                                                {{
                                                    activeMembership.is_billable
                                                        ? "Se cobra"
                                                        : "Incluida"
                                                }}
                                            </v-chip>
                                            <v-chip
                                                v-if="activeMembership.billing_split_mode === 'equal_split'"
                                                size="small"
                                                color="info"
                                                variant="tonal"
                                            >
                                                50/50
                                            </v-chip>

                                            <v-chip
                                                size="small"
                                                :color="statusColor(activeMembership.status)"
                                                variant="tonal"
                                            >
                                                {{ statusLabel(activeMembership.status) }}
                                            </v-chip>
                                        </div>
                                    </div>

                                    <div class="text-body-2 mt-2">
                                        {{
                                            currencyFormatter.format(
                                                activeMembership.monthly_fee_share,
                                            )
                                        }}
                                    </div>
                                    <div
                                        v-if="activeMembership.monthly_fee_total !== activeMembership.monthly_fee_share"
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Cuota total del esquema:
                                        {{
                                            currencyFormatter.format(
                                                activeMembership.monthly_fee_total,
                                            )
                                        }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis">
                                        Vigencia:
                                        {{ formatDate(activeMembership.start_date) }}
                                        a
                                        {{ formatDate(activeMembership.end_date) }}
                                    </div>
                                </div>
                            </div>
                        </v-card>

                        <v-card class="pa-4 mt-4">
                            <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-4">
                                <div>
                                    <div class="text-subtitle-1 font-weight-bold">
                                        Integrantes de la cuenta
                                    </div>
                                    <div class="text-body-2 text-medium-emphasis">
                                        Aquí puedes revisar la información general de cada integrante.
                                    </div>
                                </div>

                                <v-chip color="primary" variant="tonal">
                                    {{ props.account.members.length }} integrante(s)
                                </v-chip>
                            </div>

                            <v-row>
                                <v-col
                                    v-for="member in props.account.members"
                                    :key="member.member_id"
                                    cols="12"
                                    md="6"
                                >
                                    <v-card variant="outlined" class="pa-4 h-100">
                                        <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
                                            <div>
                                                <div class="font-weight-medium">
                                                    {{ member.full_name }}
                                                </div>
                                                <div class="text-caption text-medium-emphasis">
                                                    {{ member.relationship_name || "Sin parentesco" }}
                                                </div>
                                            </div>

                                            <v-chip
                                                v-if="member.is_primary_holder"
                                                color="primary"
                                                size="small"
                                                variant="flat"
                                            >
                                                Titular
                                            </v-chip>
                                        </div>

                                        <div class="text-body-2">
                                            <strong>Edad:</strong>
                                            {{ member.age ?? "-" }}
                                        </div>
                                        <div class="text-body-2">
                                            <strong>Nacimiento:</strong>
                                            {{ formatDate(member.birthdate) }}
                                        </div>
                                        <div class="text-body-2">
                                            <strong>Correo:</strong>
                                            {{ member.email || "-" }}
                                        </div>
                                        <div class="text-body-2">
                                            <strong>Teléfono:</strong>
                                            {{ member.phone || "-" }}
                                        </div>
                                        <div class="text-body-2">
                                            <strong>Nacionalidad:</strong>
                                            {{ member.nationality || "-" }}
                                        </div>
                                        <div class="text-body-2">
                                            <strong>Estado civil:</strong>
                                            {{ member.marital_status || "-" }}
                                        </div>
                                        <div class="text-body-2">
                                            <strong>Ocupación:</strong>
                                            {{ member.occupation || member.school_name || "-" }}
                                        </div>
                                        <div class="text-body-2">
                                            <strong>Domicilio:</strong>
                                            {{ addressSummary(member) || "-" }}
                                        </div>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </v-card>
                    </v-container>
                </v-col>
            </v-row>
        </div>
    </AppLayout>

    <v-dialog v-model="showAbsencePermitDialog" max-width="640">
        <v-card>
            <v-card-title class="text-h6">Registrar permiso por ausencia</v-card-title>
            <v-card-text>
                <v-row>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="absencePermitForm.start_date"
                            label="Fecha de inicio"
                            type="date"
                            :error-messages="absencePermitForm.errors.start_date"
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="absencePermitForm.end_date"
                            label="Fecha de fin"
                            type="date"
                            :error-messages="absencePermitForm.errors.end_date"
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="absencePermitForm.charge_percentage"
                            label="Porcentaje a cobrar"
                            type="number"
                            min="0.01"
                            max="100"
                            step="0.01"
                            :error-messages="
                                absencePermitForm.errors.charge_percentage
                            "
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-textarea
                            v-model="absencePermitForm.notes"
                            label="Notas"
                            rows="3"
                            auto-grow
                            :error-messages="absencePermitForm.errors.notes"
                        />
                    </v-col>
                </v-row>
            </v-card-text>
            <v-card-actions class="justify-end">
                <v-btn variant="text" @click="showAbsencePermitDialog = false">
                    Cerrar
                </v-btn>
                <v-btn
                    color="primary"
                    :loading="absencePermitForm.processing"
                    @click="submitAbsencePermit"
                >
                    Guardar
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
