<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { Head, router } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";

interface Props {
    tickets?: any;
    statuses?: any[];
    priorities?: any[];
    filters?: {
        search?: string;
        status_id?: number | null;
        priority_id?: number | null;
    };
}

const props = withDefaults(defineProps<Props>(), {
    tickets: null,
    statuses: () => [],
    priorities: () => [],
    filters: () => ({}),
});

const headers = [
    { title: "Fecha", key: "ticket_date" },
    { title: "Folio", key: "ticket_number" },
    { title: "Titulo", key: "title" },
    { title: "Categoria", key: "category.name" },
    { title: "Prioridad", key: "priority.name" },
    { title: "Estatus", key: "status.name" },
    { title: "Asignado a", key: "assigned_to.name" },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref(props.tickets?.data ?? []);
const total = ref(props.tickets?.total ?? 0);
const loading = ref(false);

const search = ref(props.filters?.search ?? "");
const statusFilter = ref(props.filters?.status_id ?? null);
const priorityFilter = ref(props.filters?.priority_id ?? null);

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});

const showDetailModal = ref(false);
const selectedTicket = ref<any | null>(null);

const statusChipColor = (status: any): string => {
    if (status?.color) return status.color;
    const code = String(status?.code ?? "").toUpperCase();
    if (code === "SUBMITTED") return "info";
    if (code === "IN_PROGRESS") return "warning";
    if (code === "RESOLVED") return "success";
    if (code === "CANCELLED") return "grey";
    if (code === "REJECTED") return "error";
    return "primary";
};

const fetchItems = async () => {
    loading.value = true;

    router.get(
        route("feedback-management.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value,
            status_id: statusFilter.value,
            priority_id: priorityFilter.value,
        },
        {
            preserveState: true,
            replace: true,
            onSuccess: (payload) => {
                items.value = payload.props.tickets?.data ?? [];
                total.value = payload.props.tickets?.total ?? 0;
                loading.value = false;
            },
            onError: () => {
                loading.value = false;
            },
        },
    );
};

const openDetail = (item: any) => {
    selectedTicket.value = item;
    showDetailModal.value = true;
};

const closeDetail = () => {
    showDetailModal.value = false;
    selectedTicket.value = null;
};

watch([options, search, statusFilter, priorityFilter], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Gestion de casos" />

    <AppLayout>
        <template #header> Gestion de casos de quejas y sugerencias </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-data-table-server
                        fixed-header
                        hover
                        height="550px"
                        :headers="headers"
                        :items="items"
                        :items-length="total"
                        :loading="loading"
                        v-model:options="options"
                        :items-per-page-options="[10, 25, 50, 100]"
                        items-per-page-text="Mostrar"
                        no-data-text="No hay registros para mostrar"
                    >
                        <template #top>
                            <v-row class="mx-2 mt-2">
                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="search"
                                        label="Buscar por folio, titulo o descripcion"
                                        clearable
                                    />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-select
                                        v-model="statusFilter"
                                        :items="props.statuses"
                                        item-title="name"
                                        item-value="id"
                                        label="Estatus"
                                        clearable
                                    />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-select
                                        v-model="priorityFilter"
                                        :items="props.priorities"
                                        item-title="name"
                                        item-value="id"
                                        label="Prioridad"
                                        clearable
                                    />
                                </v-col>
                            </v-row>
                        </template>

                        <template #item.status.name="{ item }">
                            <v-chip v-if="item.status" :color="statusChipColor(item.status)" size="small" variant="tonal">
                                {{ item.status.name }}
                            </v-chip>
                            <span v-else>-</span>
                        </template>

                        <template #item.assigned_to.name="{ item }">
                            {{ item.assigned_to?.name ?? "Sin asignar" }}
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                icon="mdi-eye"
                                color="primary"
                                @click="openDetail(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showDetailModal" max-width="1100">
            <v-card v-if="selectedTicket">
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>Detalle del caso {{ selectedTicket.ticket_number }}</span>
                    <v-btn icon="mdi-close" variant="text" @click="closeDetail" />
                </v-card-title>

                <v-divider />

                <v-card-text style="max-height: 75vh; overflow-y: auto;">
                    <pre>
                        {{ selectedTicket }}
                    </pre>
                    <v-row>
                        <v-col cols="12" md="8">
                            <v-alert type="info" variant="tonal">
                                <div><strong>Titulo:</strong> {{ selectedTicket.title }}</div>
                                <div class="mt-2"><strong>Descripcion:</strong> {{ selectedTicket.description }}</div>
                            </v-alert>
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-list density="compact">
                                <v-list-item title="Estatus" :subtitle="selectedTicket.status?.name ?? '-'" />
                                <v-list-item title="Prioridad" :subtitle="selectedTicket.priority?.name ?? '-'" />
                                <v-list-item title="Asignado a" :subtitle="selectedTicket.assigned_to?.name ?? 'Sin asignar'" />
                                <v-list-item title="Fecha" :subtitle="selectedTicket.ticket_date ?? '-'" />
                            </v-list>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-card variant="outlined">
                                <v-card-title>Evidencia adjunta</v-card-title>
                                <v-divider />
                                <v-list density="compact">
                                    <v-list-item
                                        v-for="file in selectedTicket.attachments ?? []"
                                        :key="file.id"
                                    >
                                        <template #title>
                                            <a
                                                :href="`/storage/${file.file_path}`"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                {{ file.file_name }}
                                            </a>
                                        </template>
                                        <template #subtitle>
                                            {{ file.file_type ?? 'archivo' }}
                                        </template>
                                    </v-list-item>
                                    <v-list-item v-if="!(selectedTicket.attachments ?? []).length" title="Sin adjuntos" />
                                </v-list>
                            </v-card>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-card variant="outlined">
                                <v-card-title>Historial de estatus</v-card-title>
                                <v-divider />
                                <v-list density="compact" lines="two">
                                    <v-list-item
                                        v-for="history in selectedTicket.status_history ?? []"
                                        :key="history.id"
                                    >
                                        <template #title>
                                            {{ history.old_status?.name ?? 'Sin estatus' }} -> {{ history.new_status?.name ?? 'Sin estatus' }}
                                        </template>
                                        <template #subtitle>
                                            {{ history.change_reason ?? 'Sin motivo' }}
                                        </template>
                                    </v-list-item>
                                    <v-list-item v-if="!(selectedTicket.status_history ?? []).length" title="Sin historial" />
                                </v-list>
                            </v-card>
                        </v-col>

                        <v-col cols="12">
                            <v-card variant="outlined">
                                <v-card-title>Seguimiento / comentarios</v-card-title>
                                <v-divider />
                                <v-list density="compact" lines="two">
                                    <v-list-item
                                        v-for="comment in selectedTicket.comments ?? []"
                                        :key="comment.id"
                                    >
                                        <template #title>
                                            {{ comment.user?.name ?? 'Sistema' }}
                                        </template>
                                        <template #subtitle>
                                            {{ comment.comment }}
                                        </template>
                                    </v-list-item>
                                    <v-list-item v-if="!(selectedTicket.comments ?? []).length" title="Sin comentarios" />
                                </v-list>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
