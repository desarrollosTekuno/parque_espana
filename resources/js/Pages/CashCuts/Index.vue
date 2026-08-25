<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { customToastSwal } from "@/utils/swal";
import { DateTime } from "luxon";
import { formatDate, formatDateTime } from '../../constants/formatDates';
import { formatCurrency } from '../../constants/formatCurrency';

interface CashCutItem {
    id: number;
    date: string;
    status: string;
    opening_amount: number;
    cash_counted: number | null;
    cash_expected: number | null;
    cash_difference: number | null;
    opened_at: string | null;
    closed_at: string | null;
    cashier_name: string | null;
    global_cash_cut_id: number | null; 
}

interface PaginatedCuts {
    data: CashCutItem[];
    total: number;
}

interface Props {
    cuts?: PaginatedCuts;
    canViewAll?: boolean;
    messageError?: string;
}

const props = withDefaults(defineProps<Props>(), {
    cuts: () => ({ data: [], total: 0 }),
    canViewAll: false,
});

const page = usePage<any>();
const can = page.props.auth.permissions ?? [];

const showOpenModal = ref(false);
const openForm = useForm({ opening_amount: "0.00", notes: "" });
const formRef = ref();

const statusLabel = (status: string) =>
    status === "open" ? "Abierto" : "Cerrado";

const statusColor = (status: string) =>
    status === "open" ? "warning" : "success";

const differenceColor = (diff: number | null) => {
    if (diff === null) return "default";
    if (diff === 0) return "success";
    return diff > 0 ? "warning" : "error";
};

const differenceLabel = (diff: number | null) => {
    if (diff === null) return "—";
    if (diff === 0) return "Cuadrado";
    return diff > 0 ? `Sobrante ${formatCurrency(diff)}` : `Faltante ${formatCurrency(Math.abs(diff))}`;
};

const hasOpenCutToday = computed(() =>
    props.cuts.data.some(
        (c) =>
            c.status === "open" &&
            c.date === DateTime.now().setZone("America/Mexico_City").toFormat("yyyy-MM-dd"),
    ),
);

const openCutModal = () => {
    openForm.reset();
    showOpenModal.value = true;
};

const submitOpen = async () => {
    const result = await formRef.value?.validate();
    if (result && !result.valid) return;

    openForm.post(route("cash-cuts.store"), {
        preserveScroll: true,
        onSuccess: () => {
            showOpenModal.value = false;
            customToastSwal({ title: "Corte de caja abierto.", icon: "success" });
        },
        onError: () => {
            customToastSwal({
                title: openForm.errors.messageError || "No se pudo abrir el corte.",
                icon: "error",
            });
        },
    });
};

const headers = [
    { title: "Fecha", key: "date", sortable: false },
    { title: "Cajero/a", key: "cashier_name", sortable: false },
    { title: "Fondo inicial", key: "opening_amount", sortable: false },
    { title: "Efectivo esperado", key: "cash_expected", sortable: false },
    { title: "Efectivo contado", key: "cash_counted", sortable: false },
    { title: "Diferencia", key: "cash_difference", sortable: false },
    { title: "Estado", key: "status", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
];
</script>

<template>
    <Head title="Cortes de caja" />

    <AppLayout>
        <template #header>Cortes de caja</template>

        <div class="d-flex flex-column ga-4">
            <div class="d-flex justify-end">
                <BaseButton
                    v-if="can.includes('cash-cuts.store') && !hasOpenCutToday"
                    :icon-only="false"
                    action="save"
                    text="Abrir corte de caja"
                    @click="openCutModal"
                />
                <v-chip v-else-if="hasOpenCutToday" color="warning" variant="tonal">
                    Ya tienes un corte abierto hoy
                </v-chip>
            </div>

            <v-card class="overflow-hidden">
                <v-data-table
                    :headers="headers"
                    :items="cuts.data"
                    :items-per-page="-1"
                    hide-default-footer
                    class="elevation-0"
                >
                    <template #item.date="{ item }">
                        <div class="font-weight-medium">{{ formatDate(item.date) }}</div>
                        <div class="text-caption text-medium-emphasis">
                            Apertura: {{ formatDateTime(item.opened_at) }}
                        </div>
                    </template>

                    <template #item.cashier_name="{ item }">
                        {{ item.cashier_name || "—" }}
                    </template>

                    <template #item.opening_amount="{ item }">
                        {{ formatCurrency(item.opening_amount) }}
                    </template>

                    <template #item.cash_expected="{ item }">
                        {{ item.cash_expected !== null ? formatCurrency(item.cash_expected) : "—" }}
                    </template>

                    <template #item.cash_counted="{ item }">
                        {{ item.cash_counted !== null ? formatCurrency(item.cash_counted) : "—" }}
                    </template>

                    <template #item.cash_difference="{ item }">
                        <v-chip
                            v-if="item.status === 'closed'"
                            size="small"
                            :color="differenceColor(item.cash_difference)"
                            variant="tonal"
                        >
                            {{ differenceLabel(item.cash_difference) }}
                        </v-chip>
                        <span v-else class="text-medium-emphasis">—</span>
                    </template>

                    <template #item.status="{ item }">
                        <v-chip size="small" :color="statusColor(item.status)" variant="tonal">
                            {{ statusLabel(item.status) }}
                        </v-chip>
                    </template>

                    <template #item.actions="{ item }">
                        <BaseButton
                            v-if="can.includes('cash-cuts.show')"
                            :icon-only="false"
                            action="view"
                            :text="item.status === 'open' ? 'Ver / Cerrar' : 'Ver detalle'"
                            @click="router.visit(route('cash-cuts.show', item.id))"
                        />
                    </template>

                    <template #no-data>
                        <div class="pa-6 text-center text-medium-emphasis">
                            No hay cortes de caja registrados.
                        </div>
                    </template>
                </v-data-table>
            </v-card>
        </div>

        <!-- Modal abrir corte -->
        <v-dialog v-model="showOpenModal" max-width="480" persistent>
            <v-card>
                <v-card-title>Abrir corte de caja</v-card-title>
                <v-card-subtitle>Captura el fondo inicial en efectivo para comenzar el día.</v-card-subtitle>

                <v-card-text>
                    <v-form ref="formRef" validate-on="input" @submit.prevent="submitOpen">
                        <v-text-field
                            v-model="openForm.opening_amount"
                            label="Fondo inicial (efectivo)"
                            type="number"
                            min="0"
                            step="0.01"
                            prefix="$"
                            :rules="[(v) => Number(v) >= 0 || 'Debe ser mayor o igual a cero']"
                            hide-details="auto"
                        />
                        <v-textarea
                            v-model="openForm.notes"
                            label="Notas (opcional)"
                            rows="2"
                            auto-grow
                            class="mt-4"
                            hide-details="auto"
                        />
                    </v-form>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        :icon-only="false"
                        action="cancel"
                        variant="tonal"
                        @click="showOpenModal = false"
                    />
                    <BaseButton
                        :icon-only="false"
                        action="save"
                        text="Abrir corte"
                        :loading="openForm.processing"
                        @click="submitOpen"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
