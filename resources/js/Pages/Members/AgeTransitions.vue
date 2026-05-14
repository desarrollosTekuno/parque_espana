<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal, customConfirmSwal } from "@/utils/swal";
import { Head, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";

interface AgeTransitionItem {
    id: number;
    member_id: number;
    member_name: string;
    membership_id: number;
    membership_account_id: number;
    club_code: string | null;
    from_membership_type: string | null;
    target_membership_type: string | null;
    target_membership_type_id: number;
    transition_type: "family_to_solidaria" | "solidaria_to_individual";
    monthly_fee: number;
    has_multiple_clubs: boolean;
    status: "pending" | "promoted" | "dismissed";
    identified_at: string | null;
    promoted_at: string | null;
    promoted_by_name: string | null;
    dismissed_at: string | null;
    dismissed_by_name: string | null;
    dismissal_reason: string | null;
}

interface Filters {
    search?: string;
    status?: string;
    transition_type?: string;
}

interface Props {
    transitions: {
        data: AgeTransitionItem[];
        total: number;
        per_page: number;
        current_page: number;
    };
    filters: Filters;
    messageError?: string;
}

const props = defineProps<Props>();

const prefix = "age_transitions";

const search = ref(props.filters.search ?? "");
const statusFilter = ref(props.filters.status ?? "pending");
const transitionTypeFilter = ref(props.filters.transition_type ?? "");

const items = ref<AgeTransitionItem[]>(props.transitions.data);
const totalItems = ref(props.transitions.total);
const loading = ref(false);
const promotingId = ref<number | null>(null);
const dismissingItem = ref<AgeTransitionItem | null>(null);
const dismissalReason = ref("");
const dismissDialog = ref(false);
const submittingDismiss = ref(false);

const options = ref({
    page: props.transitions.current_page ?? 1,
    itemsPerPage: props.transitions.per_page ?? 15,
    sortBy: [] as { key: string; order: "asc" | "desc" }[],
});

const headers = [
    { title: "Integrante", key: "member_name", sortable: false },
    { title: "Club", key: "club_code", sortable: false },
    { title: "Tipo actual", key: "from_membership_type", sortable: false },
    { title: "Tipo destino", key: "target_membership_type", sortable: false },
    { title: "Transición", key: "transition_type", sortable: false },
    { title: "Cuota mensual", key: "monthly_fee", sortable: false },
    { title: "Multiclub", key: "has_multiple_clubs", sortable: false },
    { title: "Estado", key: "status", sortable: false },
    { title: "Identificado", key: "identified_at", sortable: false },
    { title: "Acciones", key: "actions", sortable: false, align: "end" as const },
];

const statusOptions = [
    { title: "Pendientes", value: "pending" },
    { title: "Promovidos", value: "promoted" },
    { title: "Descartados", value: "dismissed" },
    { title: "Todos", value: "all" },
];

const transitionTypeOptions = [
    { title: "Todos", value: "" },
    { title: "Familiar → Solidaria", value: "family_to_solidaria" },
    { title: "Solidaria → Individual", value: "solidaria_to_individual" },
];

function transitionLabel(type: string): string {
    if (type === "family_to_solidaria") return "Familiar → Solidaria";
    if (type === "solidaria_to_individual") return "Solidaria → Individual";
    return type;
}

function statusLabel(status: string): string {
    if (status === "pending") return "Pendiente";
    if (status === "promoted") return "Promovido";
    if (status === "dismissed") return "Descartado";
    return status;
}

function statusColor(status: string): string {
    if (status === "pending") return "warning";
    if (status === "promoted") return "success";
    if (status === "dismissed") return "default";
    return "default";
}

function formatDate(isoString: string | null): string {
    if (!isoString) return "-";
    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    }).format(new Date(isoString));
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
        minimumFractionDigits: 2,
    }).format(amount);
}

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function fetchItems() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        loading.value = true;
        router.get(
            route("members.age-transitions.index"),
            {
                [`${prefix}_page`]: options.value.page,
                [`${prefix}_per_page`]: options.value.itemsPerPage,
                [`${prefix}_search`]: search.value || undefined,
                [`${prefix}_status`]: statusFilter.value || undefined,
                [`${prefix}_transition_type`]: transitionTypeFilter.value || undefined,
            },
            {
                preserveState: true,
                replace: true,
                onSuccess: (page) => {
                    const data = (page.props as any).transitions;
                    items.value = data.data;
                    totalItems.value = data.total;
                },
                onFinish: () => {
                    loading.value = false;
                },
            }
        );
    }, 400);
}

watch([options, search, statusFilter, transitionTypeFilter], fetchItems, { deep: true });

async function promoteTransition(item: AgeTransitionItem) {
    const confirmed = await customConfirmSwal({
        title: "¿Promover esta transición?",
        text: `Se creará una nueva membresía de tipo "${item.target_membership_type}" para ${item.member_name}. Esta acción no se puede deshacer.`,
        confirmButtonText: "Sí, promover",
    });

    if (!confirmed) return;

    promotingId.value = item.id;
    router.patch(
        route("members.age-transitions.promote", item.id),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({ title: "Transición promovida correctamente.", icon: "success" });
                fetchItems();
            },
            onError: (errors: Record<string, string>) => {
                const msg = errors.messageError ?? "Ocurrió un error al promover la transición.";
                customToastSwal({ title: msg, icon: "error" });
            },
            onFinish: () => {
                promotingId.value = null;
            },
        }
    );
}

function openDismissDialog(item: AgeTransitionItem) {
    dismissingItem.value = item;
    dismissalReason.value = "";
    dismissDialog.value = true;
}

function confirmDismiss() {
    if (!dismissingItem.value) return;
    submittingDismiss.value = true;
    router.patch(
        route("members.age-transitions.dismiss", dismissingItem.value.id),
        { dismissal_reason: dismissalReason.value || null },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                dismissDialog.value = false;
                customToastSwal({ title: "Transición descartada.", icon: "success" });
                fetchItems();
            },
            onError: (errors: Record<string, string>) => {
                const msg = errors.messageError ?? "Ocurrió un error al descartar la transición.";
                customToastSwal({ title: msg, icon: "error" });
            },
            onFinish: () => {
                submittingDismiss.value = false;
            },
        }
    );
}
</script>

<template>
    <Head title="Transiciones por Edad" />

    <AppLayout>
        <template #header>Transiciones por Edad</template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-container class="py-4">

                <v-alert
                    v-if="props.messageError"
                    type="error"
                    class="mb-4"
                    closable
                >
                    {{ props.messageError }}
                </v-alert>

                <!-- Filtros -->
                <v-card variant="outlined" class="pa-4 mb-4">
                    <div class="text-subtitle-2 font-weight-bold mb-3">Filtros</div>
                    <v-row dense>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="search"
                                label="Buscar por nombre"
                                prepend-inner-icon="mdi-magnify"
                                variant="outlined"
                                density="compact"
                                clearable
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-select
                                v-model="statusFilter"
                                :items="statusOptions"
                                item-title="title"
                                item-value="value"
                                label="Estado"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-select
                                v-model="transitionTypeFilter"
                                :items="transitionTypeOptions"
                                item-title="title"
                                item-value="value"
                                label="Tipo de transición"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                    </v-row>
                </v-card>

                <!-- Tabla -->
                <v-data-table-server
                    v-model:options="options"
                    :headers="headers"
                    :items="items"
                    :items-length="totalItems"
                    :loading="loading"
                    item-value="id"
                    density="compact"
                >
                    <template #item.transition_type="{ item }">
                        {{ transitionLabel(item.transition_type) }}
                    </template>

                    <template #item.monthly_fee="{ item }">
                        {{ formatCurrency(item.monthly_fee) }}
                    </template>

                    <template #item.has_multiple_clubs="{ item }">
                        <v-icon v-if="item.has_multiple_clubs" color="primary" size="small">
                            mdi-check-circle-outline
                        </v-icon>
                        <span v-else class="text-medium-emphasis">—</span>
                    </template>

                    <template #item.status="{ item }">
                        <v-chip
                            :color="statusColor(item.status)"
                            size="small"
                            label
                        >
                            {{ statusLabel(item.status) }}
                        </v-chip>
                    </template>

                    <template #item.identified_at="{ item }">
                        {{ formatDate(item.identified_at) }}
                    </template>

                    <template #item.actions="{ item }">
                        <div class="d-flex justify-end gap-1">
                            <v-tooltip
                                v-if="item.status === 'pending'"
                                text="Promover"
                                location="top"
                            >
                                <template #activator="{ props: tooltipProps }">
                                    <v-btn
                                        v-bind="tooltipProps"
                                        icon="mdi-check-circle-outline"
                                        color="success"
                                        size="small"
                                        variant="text"
                                        :loading="promotingId === item.id"
                                        @click="promoteTransition(item)"
                                    />
                                </template>
                            </v-tooltip>

                            <v-tooltip
                                v-if="item.status === 'pending'"
                                text="Descartar"
                                location="top"
                            >
                                <template #activator="{ props: tooltipProps }">
                                    <v-btn
                                        v-bind="tooltipProps"
                                        icon="mdi-close-circle-outline"
                                        color="error"
                                        size="small"
                                        variant="text"
                                        @click="openDismissDialog(item)"
                                    />
                                </template>
                            </v-tooltip>

                            <v-tooltip
                                v-if="item.status === 'promoted'"
                                :text="`Promovido el ${formatDate(item.promoted_at)} por ${item.promoted_by_name ?? 'N/D'}`"
                                location="top"
                            >
                                <template #activator="{ props: tooltipProps }">
                                    <v-icon v-bind="tooltipProps" color="success" size="small">
                                        mdi-information-outline
                                    </v-icon>
                                </template>
                            </v-tooltip>

                            <v-tooltip
                                v-if="item.status === 'dismissed'"
                                :text="`Descartado el ${formatDate(item.dismissed_at)} por ${item.dismissed_by_name ?? 'N/D'}${item.dismissal_reason ? ': ' + item.dismissal_reason : ''}`"
                                location="top"
                            >
                                <template #activator="{ props: tooltipProps }">
                                    <v-icon v-bind="tooltipProps" color="error" size="small">
                                        mdi-information-outline
                                    </v-icon>
                                </template>
                            </v-tooltip>
                        </div>
                    </template>
                </v-data-table-server>
            </v-container>
        </div>

        <!-- Dialog: Descartar transición -->
        <v-dialog v-model="dismissDialog" max-width="480">
            <v-card>
                <v-card-title class="text-h6 pa-4">Descartar transición</v-card-title>
                <v-card-text class="pa-4">
                    <p class="mb-3 text-body-2">
                        ¿Confirmas que deseas descartar la transición para
                        <strong>{{ dismissingItem?.member_name }}</strong>?
                    </p>
                    <v-textarea
                        v-model="dismissalReason"
                        label="Motivo (opcional)"
                        variant="outlined"
                        density="compact"
                        rows="2"
                        auto-grow
                        hide-details
                    />
                </v-card-text>
                <v-card-actions class="pa-4 pt-0 justify-end">
                    <v-btn variant="text" @click="dismissDialog = false">Cancelar</v-btn>
                    <v-btn
                        color="error"
                        variant="flat"
                        :loading="submittingDismiss"
                        @click="confirmDismiss"
                    >
                        Descartar
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
