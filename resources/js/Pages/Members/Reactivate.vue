<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

const page = usePage<any>();

interface MemberItem {
    member_id: number;
    full_name: string;
    relationship_name: string | null;
    is_primary_holder: boolean;
    email: string | null;
    phone: string | null;
}

interface PreviousMembership {
    id: number;
    membership_type_name: string | null;
    membership_type_code: string | null;
    monthly_fee: number;
    start_date: string | null;
    end_date: string | null;
    status: string;
    is_primary: boolean;
}

interface ReactivationHistoryItem {
    reactivated_at: string;
    reactivated_by_name: string | null;
    requires_documentation: boolean;
    enrollment_fee_applied: boolean;
    enrollment_fee_amount: number | null;
    notes: string | null;
}

interface MembershipInfo {
    id: number;
    membership_number: string | null;
    holder_name: string;
    membership_type_name: string | null;
    club_name: string | null;
    status: string;
    cancelled_at: string | null;
    cancelled_by_name: string | null;
    cancellation_type: string | null;
    cancellation_reason: string | null;
}

interface Props {
    membership: MembershipInfo;
    members: MemberItem[];
    previous_memberships: PreviousMembership[];
    reactivation_history: ReactivationHistoryItem[];
}

const props = defineProps<Props>();

const confirmed = ref(false);
const deferEnrollmentFeeToMonths = ref(false);

const form = useForm({
    apply_enrollment_fee: false,
    enrollment_fee_amount: null as number | null,
    enrollment_fee_installment_months: null as number | null,
    notes: "",
    confirmed: false,
});

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const formatDate = (value: string | null) => {
    if (!value) return "-";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    }).format(date);
};

const formatDateTime = (value: string | null) => {
    if (!value) return "-";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(date);
};

const membershipStatusLabel = (status: string) => {
    const map: Record<string, string> = {
        active: "Activa",
        pending: "Pendiente",
        suspended: "Suspendida",
        cancelled: "Cancelada",
    };
    return map[status] ?? status;
};

const cancellationTypeLabel = (type: string | null) => {
    const map: Record<string, string> = {
        voluntary: "Voluntaria",
        sanction: "Sanción",
    };
    return type ? (map[type] ?? type) : "-";
};

const cancellationTypeColor = (type: string | null) => {
    const map: Record<string, string> = {
        voluntary: "warning",
        sanction: "error",
    };
    return type ? (map[type] ?? "default") : "default";
};

const membershipStatusColor = (status: string) => {
    const map: Record<string, string> = {
        active: "success",
        pending: "warning",
        suspended: "orange",
        cancelled: "error",
    };
    return map[status] ?? "default";
};

const isFormValid = computed(() => {
    if (!confirmed.value) return false;
    if (form.apply_enrollment_fee && (!form.enrollment_fee_amount || form.enrollment_fee_amount <= 0)) return false;
    if (deferEnrollmentFeeToMonths.value && (!form.enrollment_fee_installment_months || form.enrollment_fee_installment_months < 2)) return false;
    return true;
});

watch(() => form.apply_enrollment_fee, (applies) => {
    if (!applies) {
        deferEnrollmentFeeToMonths.value = false;
    }
});

watch(deferEnrollmentFeeToMonths, (defer) => {
    form.enrollment_fee_installment_months = defer ? 2 : null;
});

const submit = () => {
    form.confirmed = confirmed.value;
    form.post(route("members.reactivate.store", props.membership.id), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash?.success || "Cuenta reactivada correctamente.",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${form.errors.messageError || "No se pudo procesar la reactivación"}`,
                text: `${form.errors.exception || ""}`,
                icon: "error",
            });
        },
    });
};
</script>

<template>
    <Head title="Reactivar Cuenta" />

    <AppLayout>
        <template #header>Reactivar Cuenta</template>
        <template #options>
            <BaseButton
                :icon-only="false"
                action="cancel"
                text="Volver"
                @click="router.visit(route('members.index'))"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12" md="10" class="mx-auto">
                    <v-container class="py-6">

                        <!-- Información de la cuenta -->
                        <v-card class="pa-4 mb-4" variant="tonal" color="primary">
                            <div class="text-subtitle-1 font-weight-bold mb-3">
                                Información de la cuenta
                            </div>
                            <v-row dense>
                                <v-col cols="12" sm="6">
                                    <p><strong>No. cuenta:</strong> {{ props.membership.membership_number || "-" }}</p>
                                    <p><strong>Titular:</strong> {{ props.membership.holder_name }}</p>
                                    <p><strong>Membresía:</strong> {{ props.membership.membership_type_name || "-" }}</p>
                                    <p><strong>Club:</strong> {{ props.membership.club_name || "-" }}</p>
                                </v-col>
                                <v-col cols="12" sm="6">
                                    <p>
                                        <strong>Fecha de baja:</strong>
                                        {{ formatDateTime(props.membership.cancelled_at) }}
                                    </p>
                                    <p>
                                        <strong>Autorizada por:</strong>
                                        {{ props.membership.cancelled_by_name || "Sistema" }}
                                    </p>
                                    <p>
                                        <strong>Tipo de baja:</strong>
                                        <v-chip
                                            size="x-small"
                                            :color="cancellationTypeColor(props.membership.cancellation_type)"
                                            variant="tonal"
                                            class="ml-1"
                                        >
                                            {{ cancellationTypeLabel(props.membership.cancellation_type) }}
                                        </v-chip>
                                    </p>
                                    <p v-if="props.membership.cancellation_reason">
                                        <strong>Motivo:</strong>
                                        {{ props.membership.cancellation_reason }}
                                    </p>
                                </v-col>
                            </v-row>
                        </v-card>

                        <!-- Integrantes de la cuenta -->
                        <v-card class="pa-4 mb-4" variant="outlined">
                            <div class="text-subtitle-1 font-weight-bold mb-3">
                                Integrantes de la cuenta
                            </div>
                            <v-list density="compact">
                                <v-list-item
                                    v-for="member in props.members"
                                    :key="member.member_id"
                                    :title="member.full_name"
                                    :subtitle="member.is_primary_holder
                                        ? 'Titular'
                                        : (member.relationship_name || 'Integrante')"
                                >
                                    <template #append>
                                        <div class="d-flex flex-column align-end ga-1 text-caption text-medium-emphasis">
                                            <span>{{ member.email || "Sin correo" }}</span>
                                            <v-chip
                                                v-if="member.is_primary_holder"
                                                size="x-small"
                                                color="primary"
                                                variant="flat"
                                            >
                                                Titular
                                            </v-chip>
                                        </div>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-card>

                        <!-- Historial de membresías -->
                        <v-card class="pa-4 mb-4" variant="outlined">
                            <div class="text-subtitle-1 font-weight-bold mb-3">
                                Historial de membresías
                            </div>
                            <v-table density="compact">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Cuota mensual</th>
                                        <th>Inicio</th>
                                        <th>Fin</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="m in props.previous_memberships"
                                        :key="m.id"
                                    >
                                        <td>
                                            {{ m.membership_type_name || "-" }}
                                            <v-chip
                                                v-if="m.is_primary"
                                                size="x-small"
                                                color="primary"
                                                variant="tonal"
                                                class="ml-1"
                                            >
                                                Principal
                                            </v-chip>
                                        </td>
                                        <td>{{ currencyFormatter.format(m.monthly_fee) }}</td>
                                        <td>{{ formatDate(m.start_date) }}</td>
                                        <td>{{ formatDate(m.end_date) }}</td>
                                        <td>
                                            <v-chip
                                                size="x-small"
                                                :color="membershipStatusColor(m.status)"
                                                variant="tonal"
                                            >
                                                {{ membershipStatusLabel(m.status) }}
                                            </v-chip>
                                        </td>
                                    </tr>
                                    <tr v-if="props.previous_memberships.length === 0">
                                        <td colspan="5" class="text-center text-medium-emphasis py-3">
                                            Sin historial de membresías
                                        </td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card>

                        <!-- Historial de reactivaciones previas -->
                        <v-card
                            v-if="props.reactivation_history.length > 0"
                            class="pa-4 mb-4"
                            variant="outlined"
                        >
                            <div class="text-subtitle-1 font-weight-bold mb-3">
                                Reactivaciones anteriores
                            </div>
                            <v-list density="compact">
                                <v-list-item
                                    v-for="(r, idx) in props.reactivation_history"
                                    :key="idx"
                                    :title="formatDateTime(r.reactivated_at)"
                                    :subtitle="`Autorizada por: ${r.reactivated_by_name || 'Sistema'}`"
                                >
                                    <template #append>
                                        <div class="d-flex ga-1">
                                            <v-chip
                                                v-if="r.requires_documentation"
                                                size="x-small"
                                                color="warning"
                                                variant="tonal"
                                            >
                                                Doc. requerida
                                            </v-chip>
                                            <v-chip
                                                v-if="r.enrollment_fee_applied"
                                                size="x-small"
                                                color="info"
                                                variant="tonal"
                                            >
                                                Inscripción: {{ currencyFormatter.format(r.enrollment_fee_amount ?? 0) }}
                                            </v-chip>
                                        </div>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-card>

                        <!-- Formulario de reactivación -->
                        <v-alert type="info" variant="tonal" class="mb-4">
                            Al reactivar la cuenta se restaurarán todas las membresías canceladas.
                            El historial de pagos y registros previos se conserva.
                            Se generarán nuevas credenciales de acceso para todos los integrantes.
                        </v-alert>

                        <v-form @submit.prevent="submit">
                            <v-card class="pa-4">
                                <div class="text-subtitle-1 font-weight-bold mb-4">
                                    Opciones de reactivación
                                </div>

                                <!-- Cobro de reinscripción -->
                                <v-card variant="outlined" class="pa-3 mb-4">
                                    <v-checkbox
                                        v-model="form.apply_enrollment_fee"
                                        color="info"
                                        hide-details
                                        class="mb-2"
                                    >
                                        <template #label>
                                            <div>
                                                <div class="font-weight-medium">
                                                    Aplicar cobro de reinscripción
                                                </div>
                                                <div class="text-caption text-medium-emphasis">
                                                    Se generará un cargo de reinscripción pendiente de pago.
                                                </div>
                                            </div>
                                        </template>
                                    </v-checkbox>

                                    <div v-if="form.apply_enrollment_fee" class="mt-2">
                                        <v-text-field
                                            v-model.number="form.enrollment_fee_amount"
                                            label="Monto de reinscripción"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            prefix="$"
                                            :error-messages="form.errors.enrollment_fee_amount"
                                            density="compact"
                                            variant="outlined"
                                        />

                                        <v-checkbox
                                            v-model="deferEnrollmentFeeToMonths"
                                            color="info"
                                            hide-details
                                            density="compact"
                                            class="mb-2"
                                            label="Diferir a meses"
                                        />

                                        <v-text-field
                                            v-if="deferEnrollmentFeeToMonths"
                                            v-model.number="form.enrollment_fee_installment_months"
                                            label="Cantidad de meses"
                                            type="number"
                                            min="2"
                                            max="12"
                                            step="1"
                                            :error-messages="form.errors.enrollment_fee_installment_months"
                                            density="compact"
                                            variant="outlined"
                                        />
                                    </div>
                                </v-card>

                                <!-- Notas -->
                                <v-textarea
                                    v-model="form.notes"
                                    label="Notas (opcional)"
                                    placeholder="Observaciones sobre la reactivación..."
                                    rows="3"
                                    variant="outlined"
                                    class="mb-4"
                                    :error-messages="form.errors.notes"
                                />

                                <!-- Confirmación -->
                                <v-checkbox
                                    v-model="confirmed"
                                    color="success"
                                    class="mb-2"
                                    :error-messages="form.errors.confirmed"
                                >
                                    <template #label>
                                        <span class="text-body-2">
                                            Confirmo que la reactivación de esta cuenta ha sido autorizada
                                            y que el usuario cumple con los requisitos para su reingreso.
                                        </span>
                                    </template>
                                </v-checkbox>

                                <div class="d-flex justify-end ga-2 mt-2">
                                    <v-btn
                                        variant="text"
                                        @click="router.visit(route('members.index'))"
                                    >
                                        Cancelar
                                    </v-btn>
                                    <v-btn
                                        color="success"
                                        :disabled="!isFormValid"
                                        :loading="form.processing"
                                        @click="submit"
                                    >
                                        Reactivar cuenta
                                    </v-btn>
                                </div>
                            </v-card>
                        </v-form>

                    </v-container>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>
