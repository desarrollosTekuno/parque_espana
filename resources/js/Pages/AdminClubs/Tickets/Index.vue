<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import TicketPreview from "@/Components/TicketPreview.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { getTicketBundle, getTicketData, printTicket, type TicketData } from "@/utils/ticket";
import { customToastSwal } from "@/utils/swal";
import { Head, router, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";

interface TicketListItem {
    id: number;
    payment_group_id: string | null;
    folio: string | null;
    formas_de_pago_count: number;
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
const page = usePage<any>();
const can = page.props.auth.permissions;
const headers = [
    { title: "Folio / pago", key: "folio", sortable: false },
    { title: "Fecha", key: "fecha", sortable: false },
    { title: "Cuenta", key: "cuenta", sortable: false },
    { title: "Titular", key: "titular", sortable: false },
    { title: "Monto", key: "monto", sortable: false },
    { title: "Forma de pago", key: "forma_pago", sortable: false },
    { title: "Cajero", key: "cajero", sortable: false },
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
const selectedTickets = ref<TicketData[]>([]);

const showCancelDialog = ref(false);
const cancelDialogLoading = ref(false);
const cancelDialogItem = ref<TicketListItem | null>(null);
const cancelDialogTicket = ref<TicketData | null>(null);

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
    selectedTickets.value = [];

    try {
        selectedTickets.value = (await getTicketBundle(item.id)).tickets;
    } catch (error) {
        showPreview.value = false;
        customToastSwal({ title: "No fue posible cargar el ticket.", icon: "error" });
    } finally {
        previewLoading.value = false;
    }
};

const sendToPrint = async (item: TicketListItem | null = null, duplicate = true) => {
    const paymentId = item?.id ?? selectedTickets.value[0]?.payment_id;

    if (!paymentId) {
        return;
    }

    try {
        await printTicket(paymentId, duplicate);
    } catch (error) {
        const message = error instanceof Error ? error.message : "No fue posible imprimir el ticket.";
        customToastSwal({ title: message, icon: "error" });
    }
};

// El renglón de la tabla solo representa el cobro completo (el pago con
// menor id del grupo, ver TicketController::index). Si se pagó con una sola
// forma de pago no hay nada que elegir: se va directo a cancelar ese pago.
// Si hubo varias, se abre un diálogo para elegir entre cancelar el ticket
// completo (rollback de todas las formas de pago) o solo una en particular.
const goToCancelPayment = async (item: TicketListItem) => {
    if (item.formas_de_pago_count <= 1) {
        router.visit(route("payments.cancel.create", item.id));
        return;
    }

    cancelDialogItem.value = item;
    cancelDialogTicket.value = null;
    showCancelDialog.value = true;
    cancelDialogLoading.value = true;

    try {
        cancelDialogTicket.value = await getTicketData(item.id);
    } catch (error) {
        showCancelDialog.value = false;
        customToastSwal({ title: "No fue posible cargar las formas de pago de este cobro.", icon: "error" });
    } finally {
        cancelDialogLoading.value = false;
    }
};

const hasCancellablePayments = computed(() =>
    (cancelDialogTicket.value?.formas_de_pago ?? []).some((fp) => fp.status !== "cancelled")
);

const goToCancelWholeTicket = () => {
    if (!cancelDialogItem.value?.payment_group_id) return;
    router.visit(route("payments.cancel-group.create", cancelDialogItem.value.payment_group_id));
};

const goToCancelSinglePayment = (paymentId: number) => {
    router.visit(route("payments.cancel.create", paymentId));
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
                        <v-chip
                            v-if="item.formas_de_pago_count > 1"
                            size="small"
                            color="info"
                            variant="tonal"
                            class="ml-1"
                        >
                            +{{ item.formas_de_pago_count - 1 }}
                        </v-chip>
                    </template>

                    <template #item.fecha="{ item }">{{ dateTime(item.fecha) }}</template>
                    <template #item.cuenta="{ item }">{{ item.cuenta_numero || item.cuenta_interna || "-" }}</template>
                    <template #item.monto="{ item }">{{ money(item.monto) }}</template>
                    <template #item.cajero="{ item }">
                        {{ item.cajero || "-" }}<span v-if="item.cajero_codigo"> ({{ item.cajero_codigo }})</span>
                    </template>
                    <template #item.actions="{ item }">
                        <BaseButton action="view" tooltip="Vista previa" @click="openPreview(item)" />
                        <BaseButton icon="mdi-printer-outline" color="primary" tooltip="Reimprimir" @click="sendToPrint(item, true)" />
                        <BaseButton
                            v-if="can.includes('payments.cancel') && item.estatus !== 'cancelled'"
                            icon="mdi-cash-remove"
                            color="error"
                            tooltip="Cancelar pago"
                            @click="goToCancelPayment(item)"
                        />
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
                    <div v-else-if="selectedTickets.length">
                        <TicketPreview
                            v-for="ticket in selectedTickets"
                            :key="ticket.club_id"
                            :ticket="ticket"
                            class="mb-6"
                        />
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <BaseButton action="close" :icon-only="false" text="Cerrar" @click="showPreview = false" />
                    <BaseButton
                        icon="mdi-printer-outline"
                        text="Imprimir"
                        tooltip="Imprimir"
                        :icon-only="false"
                        :disabled="!selectedTickets.length"
                        @click="sendToPrint()"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showCancelDialog" max-width="560">
            <v-card>
                <v-card-title>Cancelar cobro</v-card-title>
                <v-card-text>
                    <div v-if="cancelDialogLoading" class="text-center pa-8">
                        <v-progress-circular indeterminate color="primary" />
                    </div>
                    <template v-else-if="cancelDialogTicket">
                        <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                            Este cobro se pagó con {{ cancelDialogItem?.formas_de_pago_count }} formas de pago.
                            Puedes cancelar el ticket completo o solo una forma de pago en particular.
                        </v-alert>

                        <v-btn
                            v-if="hasCancellablePayments"
                            color="error"
                            variant="flat"
                            block
                            class="mb-4"
                            prepend-icon="mdi-cash-remove"
                            @click="goToCancelWholeTicket"
                        >
                            Cancelar ticket completo ({{ money(cancelDialogTicket.total) }})
                        </v-btn>
                        <v-alert v-else type="warning" variant="tonal" density="compact" class="mb-4">
                            Todas las formas de pago de este ticket ya están canceladas.
                        </v-alert>

                        <div class="text-subtitle-2 font-weight-medium mb-2">
                            O cancelar solo una forma de pago:
                        </div>
                        <v-list density="compact" lines="two">
                            <v-list-item
                                v-for="fp in cancelDialogTicket.formas_de_pago"
                                :key="fp.payment_id"
                                :title="`${fp.codigo ?? '-'} · ${fp.nombre ?? '-'} — ${money(fp.monto)}`"
                            >
                                <template #subtitle>
                                    <span v-if="fp.status === 'cancelled'" class="text-error">Ya cancelado</span>
                                    <span v-else>{{ [fp.referencia, fp.banco, fp.numero_cheque].filter(Boolean).join(" · ") || undefined }}</span>
                                </template>
                                <template #append>
                                    <BaseButton
                                        v-if="fp.status !== 'cancelled'"
                                        icon="mdi-cash-remove"
                                        color="error"
                                        tooltip="Cancelar esta forma de pago"
                                        @click="goToCancelSinglePayment(fp.payment_id)"
                                    />
                                </template>
                            </v-list-item>
                        </v-list>
                    </template>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <BaseButton action="close" :icon-only="false" text="Cerrar" @click="showCancelDialog = false" />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
