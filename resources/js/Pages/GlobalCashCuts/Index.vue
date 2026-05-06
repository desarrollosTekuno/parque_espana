<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { customToastSwal } from "@/utils/swal";
import { nowAsLocalInput, utcToLocalDisplay } from "@/constants/formatDates";
import { DateTime } from "luxon";

interface GlobalCutItem {
    id: number;
    date: string;
    status: string;
    created_by_name: string | null;
    closed_at: string | null;
    individual_cuts_count: number;
}

interface PendingCut {
    id: number;
    date: string;
    cashier_name: string | null;
    cash_counted: number | null;
    cash_difference: number | null;
}

interface Props {
    cuts?: { data: GlobalCutItem[]; total: number };
    pendingCuts?: PendingCut[];
}

const props = withDefaults(defineProps<Props>(), {
    cuts: () => ({ data: [], total: 0 }),
    pendingCuts: () => [],
});

const page = usePage<any>();
const can = page.props.auth.permissions ?? [];

const showCreateModal = ref(false);
const selectedCutIds = ref<number[]>([]);
const selectedDate = ref(DateTime.now().setZone("America/Mexico_City").toFormat("yyyy-MM-dd"));
const createNotes = ref("");
const createForm = useForm({
    date: "",
    cash_cut_ids: [] as number[],
    notes: "",
});

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
});

const formatCurrency = (v: number | null) =>
    currencyFormatter.format(Number(v ?? 0));

const formatDate = (v: string) =>
    new Intl.DateTimeFormat("es-MX", { dateStyle: "medium" }).format(
        new Date(`${v}T12:00:00`),
    );

const differenceColor = (diff: number | null) => {
    if (diff === null) return "default";
    if (diff === 0) return "success";
    return diff > 0 ? "warning" : "error";
};

const statusLabel = (s: string) => (s === "open" ? "Abierto" : "Cerrado");
const statusColor = (s: string) => (s === "open" ? "warning" : "success");

const openCreateModal = () => {
    selectedCutIds.value = [];
    selectedDate.value = DateTime.now().setZone("America/Mexico_City").toFormat("yyyy-MM-dd");
    createNotes.value = "";
    showCreateModal.value = true;
};

const submitCreate = () => {
    if (!selectedCutIds.value.length) {
        customToastSwal({
            title: "Selecciona al menos un corte individual.",
            icon: "warning",
        });
        return;
    }

    createForm.date = selectedDate.value;
    createForm.cash_cut_ids = selectedCutIds.value;
    createForm.notes = createNotes.value;

    createForm.post(route("global-cash-cuts.store"), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            customToastSwal({ title: "Corte global creado.", icon: "success" });
        },
        onError: () => {
            customToastSwal({
                title: createForm.errors.messageError || "No se pudo crear el corte global.",
                icon: "error",
            });
        },
    });
};

const headers = [
    { title: "Fecha", key: "date", sortable: false },
    { title: "Creado por", key: "created_by_name", sortable: false },
    { title: "Cortes individuales", key: "individual_cuts_count", sortable: false },
    { title: "Estado", key: "status", sortable: false },
    { title: "Cierre", key: "closed_at", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
];
</script>

<template>
    <Head title="Cortes globales" />

    <AppLayout>
        <template #header>Cortes globales (Administración)</template>

        <div class="d-flex flex-column ga-4">
            <div class="d-flex justify-space-between align-center">
                <div>
                    <span class="text-body-2 text-medium-emphasis">
                        {{ pendingCuts.length }} corte(s) individual(es) pendiente(s) de consolidar
                    </span>
                </div>
                <BaseButton
                    v-if="can.includes('global-cash-cuts.store')"
                    :icon-only="false"
                    action="save"
                    text="Crear corte global"
                    :disabled="!pendingCuts.length"
                    @click="openCreateModal"
                />
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
                        <span class="font-weight-medium">{{ formatDate(item.date) }}</span>
                    </template>
                    <template #item.individual_cuts_count="{ item }">
                        {{ item.individual_cuts_count }} corte(s)
                    </template>
                    <template #item.status="{ item }">
                        <v-chip size="small" :color="statusColor(item.status)" variant="tonal">
                            {{ statusLabel(item.status) }}
                        </v-chip>
                    </template>
                    <template #item.closed_at="{ item }">
                        {{ utcToLocalDisplay(item.closed_at) }}
                    </template>
                    <template #item.actions="{ item }">
                        <BaseButton
                            v-if="can.includes('global-cash-cuts.show')"
                            :icon-only="false"
                            action="view"
                            text="Ver detalle"
                            @click="router.visit(route('global-cash-cuts.show', item.id))"
                        />
                    </template>
                    <template #no-data>
                        <div class="pa-6 text-center text-medium-emphasis">
                            No hay cortes globales registrados.
                        </div>
                    </template>
                </v-data-table>
            </v-card>
        </div>

        <!-- Modal crear corte global -->
        <v-dialog v-model="showCreateModal" max-width="640" persistent scrollable>
            <v-card>
                <v-card-title>Crear corte global</v-card-title>
                <v-card-subtitle>Selecciona los cortes individuales a consolidar.</v-card-subtitle>

                <v-card-text class="d-flex flex-column ga-4">
                    <v-text-field
                        v-model="selectedDate"
                        label="Fecha del corte global"
                        type="date"
                        hide-details="auto"
                    />

                    <div>
                        <div class="text-subtitle-2 mb-2">Cortes individuales disponibles</div>
                        <v-card
                            v-for="cut in pendingCuts"
                            :key="cut.id"
                            variant="outlined"
                            class="mb-2"
                        >
                            <v-card-text class="d-flex justify-space-between align-center py-2">
                                <div>
                                    <div class="font-weight-medium">{{ cut.cashier_name }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ formatDate(cut.date) }}</div>
                                    <div class="text-caption">
                                        Contado: {{ formatCurrency(cut.cash_counted) }}
                                        <v-chip
                                            size="x-small"
                                            :color="differenceColor(cut.cash_difference)"
                                            variant="tonal"
                                            class="ml-1"
                                        >
                                            {{
                                                cut.cash_difference === 0
                                                    ? "Cuadrado"
                                                    : cut.cash_difference! > 0
                                                      ? `+${formatCurrency(cut.cash_difference)}`
                                                      : formatCurrency(cut.cash_difference)
                                            }}
                                        </v-chip>
                                    </div>
                                </div>
                                <v-checkbox
                                    v-model="selectedCutIds"
                                    :value="cut.id"
                                    color="primary"
                                    hide-details
                                />
                            </v-card-text>
                        </v-card>
                    </div>

                    <v-textarea
                        v-model="createNotes"
                        label="Notas (opcional)"
                        rows="2"
                        auto-grow
                        hide-details="auto"
                    />
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        :icon-only="false"
                        action="cancel"
                        variant="tonal"
                        @click="showCreateModal = false"
                    />
                    <BaseButton
                        :icon-only="false"
                        action="save"
                        text="Crear corte global"
                        :loading="createForm.processing"
                        @click="submitCreate"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
