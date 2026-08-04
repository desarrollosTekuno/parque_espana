<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import TicketPreview from "@/Components/TicketPreview.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { getTicketData, printTicket, type TicketData } from "@/utils/ticket";
import { customToastSwal } from "@/utils/swal";
import { Head, router, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";

interface TicketListItem {
    id: number;
    folio: string | null;
    fecha: string | null;
    estatus: string | null;
    cuenta_numero: string | null;
    cuenta_interna: string | null;
    titular: string | null;
    monto: number;
    forma_pago: string | null;
    cajero: string | null;
    cajero_codigo: string | null;
}

interface PaginatedTickets {
    data: TicketListItem[];
    total: number;
    current_page: number;
    per_page: number;
}

/* ====================== Props ====================== */
const props = defineProps<{
    tickets: PaginatedTickets;
    filters: { search?: string };
}>();

/* ====================== Variables ====================== */
const page = usePage();
const headers = [
    { title: "Folio / pago", key: "folio", sortable: false },
    { title: "Fecha", key: "fecha", sortable: false },
    { title: "Cuenta", key: "cuenta", sortable: false },
    { title: "Titular", key: "titular", sortable: false },
    { title: "Monto", key: "monto", sortable: false },
    { title: "Forma de pago", key: "forma_pago", sortable: false },
    { title: "Cajero", key: "cajero", sortable: false },
    { title: "Estado", key: "estatus", sortable: false },
    { title: "Acciones", key: "actions", sortable: false, align: "center" as const },
];
const items = ref<TicketListItem[]>(props.tickets.data);
const total = ref(props.tickets.total);
const loading = ref(false);
const search = ref(props.filters.search ?? "");
const options = ref({
    page: props.tickets.current_page,
    itemsPerPage: props.tickets.per_page,
});
const showPreview = ref(false);
const previewLoading = ref(false);
const selectedTicket = ref<TicketData | null>(null);

/* ====================== Funciones ====================== */
const fetchItems = () => {
    loading.value = true;
    router.get(
        route("tickets.index"),
        {
            tickets_page: options.value.page,
            tickets_per_page: options.value.itemsPerPage,
            tickets_search: search.value.trim() || null,
        },
        {
            preserveState: true,
            replace: true,
            only: ["tickets", "filters"],
            onFinish: () => loading.value = false,
        }
    );
};

const openPreview = async (item: TicketListItem) => {
    showPreview.value = true;
    previewLoading.value = true;
    selectedTicket.value = null;

    try {
        selectedTicket.value = await getTicketData(item.id);
    } catch (error) {
        showPreview.value = false;
        customToastSwal({ title: "No fue posible cargar el ticket.", icon: "error" });
    } finally {
        previewLoading.value = false;
    }
};

const sendToPrint = async (item: TicketListItem | null = null) => {
    const paymentId = item?.id ?? selectedTicket.value?.payment_id;

    if (!paymentId) {
        return;
    }

    try {
        await printTicket(paymentId);
    } catch (error) {
        const message = error instanceof Error ? error.message : "No fue posible imprimir el ticket.";
        customToastSwal({ title: message, icon: "error" });
    }
};

const money = (value: number) => {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(value);
};

const dateTime = (value: string | null) => {
    return value ? new Date(value).toLocaleString("es-MX") : "-";
};

const statusColor = (status: string | null) => {
    if (status === "completed") return "success";
    if (status === "cancelled") return "error";
    return "grey";
};

const statusLabel = (status: string | null) => {
    if (status === "completed") return "Completado";
    if (status === "cancelled") return "Cancelado";
    return status || "Sin estado";
};

/* ====================== Watchers ====================== */
watch(
    () => props.tickets,
    (tickets) => {
        items.value = tickets.data;
        total.value = tickets.total;
        loading.value = false;
    },
    { immediate: true }
);

watch(search, debounce(() => {
    options.value.page = 1;
    fetchItems();
}, 400));

watch(options, debounce(fetchItems, 400), { deep: true });

watch(() => page.props.auth.currentClub, () => {
    options.value.page = 1;
    fetchItems();
});
</script>

<template>
    <Head title="Tickets de pago" />

    <AppLayout>
        <template #header>Tickets de pago</template>

        <v-card>
            <v-card-text>
                <v-alert type="info" variant="tonal" class="mb-4">
                    Módulo de prueba. Los pagos sin folio se imprimen identificados por su número de pago.
                </v-alert>

                <v-text-field
                    v-model="search"
                    label="Buscar por folio, referencia, cuenta o titular"
                    prepend-inner-icon="mdi-magnify"
                    clearable
                    hide-details
                    class="mb-4"
                />

                <v-data-table-server
                    v-model:page="options.page"
                    v-model:items-per-page="options.itemsPerPage"
                    :headers="headers"
                    :items="items"
                    :items-length="total"
                    :loading="loading"
                >
                    <template #item.folio="{ item }">
                        <span v-if="item.folio">{{ item.folio }}</span>
                        <v-chip v-else size="small" color="warning" variant="tonal">Pago #{{ item.id }}</v-chip>
                    </template>

                    <template #item.fecha="{ item }">{{ dateTime(item.fecha) }}</template>
                    <template #item.cuenta="{ item }">{{ item.cuenta_numero || item.cuenta_interna || "-" }}</template>
                    <template #item.monto="{ item }">{{ money(item.monto) }}</template>
                    <template #item.cajero="{ item }">
                        {{ item.cajero || "-" }}<span v-if="item.cajero_codigo"> ({{ item.cajero_codigo }})</span>
                    </template>
                    <template #item.estatus="{ item }">
                        <v-chip :color="statusColor(item.estatus)" size="small" variant="tonal">
                            {{ statusLabel(item.estatus) }}
                        </v-chip>
                    </template>
                    <template #item.actions="{ item }">
                        <BaseButton action="view" tooltip="Vista previa" @click="openPreview(item)" />
                        <BaseButton icon="mdi-printer-outline" color="primary" tooltip="Imprimir o reimprimir" @click="sendToPrint(item)" />
                    </template>
                </v-data-table-server>
            </v-card-text>
        </v-card>

        <v-dialog v-model="showPreview" max-width="520">
            <v-card>
                <v-card-title>Vista previa del ticket</v-card-title>
                <v-card-text>
                    <div v-if="previewLoading" class="text-center pa-8">
                        <v-progress-circular indeterminate color="primary" />
                    </div>
                    <TicketPreview v-else-if="selectedTicket" :ticket="selectedTicket" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <BaseButton action="close" :icon-only="false" text="Cerrar" @click="showPreview = false" />
                    <BaseButton
                        icon="mdi-printer-outline"
                        text="Imprimir"
                        tooltip="Imprimir"
                        :icon-only="false"
                        :disabled="!selectedTicket"
                        @click="sendToPrint()"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
