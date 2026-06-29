<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, router } from "@inertiajs/vue3";
import axios from "axios";
import { ref, watch } from "vue";

interface CancellationItem {
    id: number;
    membership_id: number | null;
    membership_number: string | null;
    holder_name: string;
    email: string | null;
    membership_type_name: string | null;
    cancellation_type: "voluntary" | "sanction" | null;
    cancelled_at: string | null;
    cancelled_by_name: string | null;
    cancelled_by_id: number | null;
    has_letter: boolean;
}

interface Processor {
    id: number;
    name: string;
}

interface Filters {
    cancellations_search?: string;
    cancellations_type?: string;
    cancellations_from?: string;
    cancellations_to?: string;
    cancellations_processed_by?: string;
}

interface Props {
    cancellations: {
        data: CancellationItem[];
        total: number;
        per_page: number;
        current_page: number;
    };
    processors: Processor[];
    filters: Filters;
}

const props = defineProps<Props>();

const prefix = "cancellations";

const search = ref(props.filters.cancellations_search ?? "");
const typeFilter = ref(props.filters.cancellations_type ?? "");
const fromDate = ref(props.filters.cancellations_from ?? "");
const toDate = ref(props.filters.cancellations_to ?? "");
const processedByFilter = ref(
    props.filters.cancellations_processed_by
        ? Number(props.filters.cancellations_processed_by)
        : null
);

const items = ref<CancellationItem[]>(props.cancellations.data);
const totalItems = ref(props.cancellations.total);
const loading = ref(false);
const downloadingId = ref<number | null>(null);

const options = ref({
    page: props.cancellations.current_page ?? 1,
    itemsPerPage: props.cancellations.per_page ?? 15,
    sortBy: [{ key: "cancelled_at", order: "desc" as const }],
});

const headers = [
    { title: "No. Usuario", key: "membership_number", sortable: true },
    { title: "Titular", key: "holder_name", sortable: false },
    { title: "Tipo de membresía", key: "membership_type_name", sortable: false },
    { title: "Tipo de baja", key: "cancellation_type", sortable: false },
    { title: "Fecha de baja", key: "cancelled_at", sortable: true },
    { title: "Procesada por", key: "cancelled_by_name", sortable: false },
    { title: "Acciones", key: "actions", sortable: false, align: "end" as const },
];

const cancellationTypeOptions = [
    { title: "Todos", value: "" },
    { title: "Voluntaria", value: "voluntary" },
    { title: "Sanción", value: "sanction" },
];

function typeLabel(type: string | null): string {
    if (type === "voluntary") return "Voluntaria";
    if (type === "sanction") return "Sanción";
    return "-";
}

function typeColor(type: string | null): string {
    if (type === "voluntary") return "primary";
    if (type === "sanction") return "error";
    return "default";
}

function motivoLabel(type: string | null): string {
    if (type === "voluntary") return "Solicitud voluntaria del titular";
    if (type === "sanction") return "Baja por sanción disciplinaria";
    return "-";
}

function formatDate(isoString: string | null): string {
    if (!isoString) return "-";
    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(isoString));
}

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function fetchItems() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        loading.value = true;
        const sort = options.value.sortBy?.[0];
        router.get(
            route("members.cancellations.index"),
            {
                [`${prefix}_page`]: options.value.page,
                [`${prefix}_per_page`]: options.value.itemsPerPage,
                [`${prefix}_search`]: search.value || undefined,
                [`${prefix}_type`]: typeFilter.value || undefined,
                [`${prefix}_from`]: fromDate.value || undefined,
                [`${prefix}_to`]: toDate.value || undefined,
                [`${prefix}_processed_by`]: processedByFilter.value ?? undefined,
                [`${prefix}_sort`]: sort?.key ?? "cancelled_at",
                [`${prefix}_order`]: sort?.order ?? "desc",
            },
            {
                preserveState: true,
                replace: true,
                onSuccess: (page) => {
                    const data = (page.props as any).cancellations;
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

watch(
    [options, search, typeFilter, fromDate, toDate, processedByFilter],
    fetchItems,
    { deep: true }
);

async function downloadLetter(item: CancellationItem) {
    if (!item.has_letter) return;
    downloadingId.value = item.id;
    try {
        const response = await axios.get(
            route("members.cancellations.letter-url", item.id)
        );
        window.open(response.data.url, "_blank");
    } catch {
        customToastSwal({
            title: "No se pudo obtener el enlace de descarga.",
            icon: "error",
        });
    } finally {
        downloadingId.value = null;
    }
}

function viewProfile(item: CancellationItem) {
    if (!item.membership_id) return;
    router.visit(route("members.manage.show", item.membership_id));
}

function exportToExcel() {
    const sort = options.value.sortBy?.[0];
    const params = new URLSearchParams();
    if (search.value) params.set(`${prefix}_search`, search.value);
    if (typeFilter.value) params.set(`${prefix}_type`, typeFilter.value);
    if (fromDate.value) params.set(`${prefix}_from`, fromDate.value);
    if (toDate.value) params.set(`${prefix}_to`, toDate.value);
    if (processedByFilter.value)
        params.set(`${prefix}_processed_by`, String(processedByFilter.value));
    if (sort?.key) params.set(`${prefix}_sort`, sort.key);
    if (sort?.order) params.set(`${prefix}_order`, sort.order);
    window.location.href = `${route("members.cancellations.export")}?${params.toString()}`;
}

function clearFilters() {
    search.value = "";
    typeFilter.value = "";
    fromDate.value = "";
    toDate.value = "";
    processedByFilter.value = null;
}
</script>

<template>
    <Head title="Historial de Bajas" />

    <AppLayout>
        <template #header>Historial de Bajas</template>
        <template #options>
            <BaseButton
                :icon-only="false"
                action="download"
                text="Exportar a Excel"
                @click="exportToExcel"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-container class="py-4">

                <!-- Filtros -->
                <v-card variant="outlined" class="pa-4 mb-4">
                    <div class="text-subtitle-2 font-weight-bold mb-3">Filtros</div>
                    <v-row dense>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="search"
                                label="Buscar (No. usuario, nombre, correo)"
                                prepend-inner-icon="mdi-magnify"
                                variant="outlined"
                                density="compact"
                                clearable
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-select
                                v-model="typeFilter"
                                :items="cancellationTypeOptions"
                                item-title="title"
                                item-value="value"
                                label="Tipo de baja"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-text-field
                                v-model="fromDate"
                                type="date"
                                label="Fecha desde"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-text-field
                                v-model="toDate"
                                type="date"
                                label="Fecha hasta"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-select
                                v-model="processedByFilter"
                                :items="[{ id: null, name: 'Todos' }, ...props.processors]"
                                item-title="name"
                                item-value="id"
                                label="Procesada por"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                    </v-row>

                    <div class="d-flex justify-end mt-2">
                        <v-btn
                            variant="text"
                            size="small"
                            prepend-icon="mdi-filter-off"
                            @click="clearFilters"
                        >
                            Limpiar filtros
                        </v-btn>
                    </div>
                </v-card>

                <!-- Tabla -->
                <v-data-table-server
                    v-model:options="options"
                    :headers="headers"
                    :items="items"
                    :items-length="totalItems"
                    :loading="loading"
                    :items-per-page-options="[10, 15, 25, 50]"
                    no-data-text="No se encontraron registros de bajas."
                    loading-text="Cargando..."
                >
                    <!-- No. Usuario -->
                    <template #item.membership_number="{ item }">
                        <span class="font-weight-medium">
                            {{ item.membership_number ?? "-" }}
                        </span>
                    </template>

                    <!-- Titular + email -->
                    <template #item.holder_name="{ item }">
                        <div>{{ item.holder_name }}</div>
                        <div v-if="item.email" class="text-caption text-medium-emphasis">
                            {{ item.email }}
                        </div>
                    </template>

                    <!-- Tipo de membresía -->
                    <template #item.membership_type_name="{ item }">
                        {{ item.membership_type_name ?? "-" }}
                    </template>

                    <!-- Tipo de baja -->
                    <template #item.cancellation_type="{ item }">
                        <div>
                            <v-chip
                                :color="typeColor(item.cancellation_type)"
                                size="small"
                                variant="tonal"
                            >
                                {{ typeLabel(item.cancellation_type) }}
                            </v-chip>
                        </div>
                        <div class="text-caption text-medium-emphasis mt-1">
                            {{ motivoLabel(item.cancellation_type) }}
                        </div>
                    </template>

                    <!-- Fecha de baja -->
                    <template #item.cancelled_at="{ item }">
                        {{ formatDate(item.cancelled_at) }}
                    </template>

                    <!-- Procesada por -->
                    <template #item.cancelled_by_name="{ item }">
                        {{ item.cancelled_by_name ?? "-" }}
                    </template>

                    <!-- Acciones -->
                    <template #item.actions="{ item }">
                        <div class="d-flex align-center justify-end ga-1">
                            <v-tooltip text="Ver perfil" location="top">
                                <template #activator="{ props: tooltipProps }">
                                    <v-btn
                                        v-bind="tooltipProps"
                                        icon="mdi-account-eye"
                                        size="small"
                                        variant="text"
                                        color="primary"
                                        :disabled="!item.membership_id"
                                        @click="viewProfile(item)"
                                    />
                                </template>
                            </v-tooltip>

                            <v-tooltip
                                :text="item.has_letter ? 'Descargar carta de baja' : 'Sin carta de baja'"
                                location="top"
                            >
                                <template #activator="{ props: tooltipProps }">
                                    <v-btn
                                        v-bind="tooltipProps"
                                        icon="mdi-file-download"
                                        size="small"
                                        variant="text"
                                        :color="item.has_letter ? 'success' : undefined"
                                        :disabled="!item.has_letter"
                                        :loading="downloadingId === item.id"
                                        @click="downloadLetter(item)"
                                    />
                                </template>
                            </v-tooltip>
                        </div>
                    </template>
                </v-data-table-server>

            </v-container>
        </div>
    </AppLayout>
</template>
