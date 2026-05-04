<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { required, maxLength } from "@/constants/validationRules";
import { Head, router } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";

interface Props {
    tickets?: any;
    categories?: any[];
    ticketTypes?: any[];
    statuses?: any[];
    priorities?: any[];
    messageError?: string;
}

const props = withDefaults(defineProps<Props>(), {
    tickets: null,
    categories: () => [],
    ticketTypes: () => [],
    statuses: () => [],
    priorities: () => [],
    messageError: "",
});

const headers = [
    { title: "Folio", key: "ticket_number" },
    { title: "Titulo", key: "title" },
    { title: "Tipo", key: "type.name" },
    { title: "Categoria", key: "category.name" },
    { title: "Prioridad", key: "priority.name" },
    { title: "Estatus", key: "status.name" },
    { title: "Fecha ticket", key: "ticket_date" },
    { title: "Vence", key: "due_at" },
    { title: "Rechazado", key: "rejected_at" },
];

const items = ref(props.tickets?.data ?? []);
const total = ref(props.tickets?.total ?? 0);
const loading = ref(false);
const search = ref("");

const options = ref({
    page: props.tickets?.current_page ?? 1,
    itemsPerPage: props.tickets?.per_page ?? 10,
    sortBy: [{ key: "id", order: "desc" }],
});

const filters = ref({
    ticket_type_id: null as number | null,
    category_id: null as number | null,
    status_id: null as number | null,
    priority_id: null as number | null,
    title_preview: "",
    rejection_reason_preview: "",
});

const fetchItems = async () => {
    loading.value = true;

    router.get(
        route("feedback-tickets.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value,
            ticket_type_id: filters.value.ticket_type_id,
            category_id: filters.value.category_id,
            status_id: filters.value.status_id,
            priority_id: filters.value.priority_id,
        },
        {
            preserveState: true,
            replace: true,
            onSuccess: (page) => {
                const tickets = page.props.tickets as any;
                items.value = tickets?.data ?? [];
                total.value = tickets?.total ?? 0;
                loading.value = false;
            },
            onError: () => {
                loading.value = false;
            },
        },
    );
};

watch([options, search, filters], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Tickets de feedback" />

    <AppLayout>
        <template #header> Tickets de feedback </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row class="px-4 pt-4">
                <v-col cols="12" md="3">
                    <v-select
                        v-model="filters.ticket_type_id"
                        :items="props.ticketTypes"
                        item-title="name"
                        item-value="id"
                        label="Tipo"
                        clearable
                    />
                </v-col>
                <v-col cols="12" md="3">
                    <v-select
                        v-model="filters.category_id"
                        :items="props.categories"
                        item-title="name"
                        item-value="id"
                        label="Categoria"
                        clearable
                    />
                </v-col>
                <v-col cols="12" md="3">
                    <v-select
                        v-model="filters.priority_id"
                        :items="props.priorities"
                        item-title="name"
                        item-value="id"
                        label="Prioridad"
                        clearable
                    />
                </v-col>
                <v-col cols="12" md="3">
                    <v-select
                        v-model="filters.status_id"
                        :items="props.statuses"
                        item-title="name"
                        item-value="id"
                        label="Estatus"
                        clearable
                    />
                </v-col>

                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="filters.title_preview"
                        label="Titulo (validacion)"
                        :rules="[required, maxLength(200)]"
                    />
                </v-col>
                <v-col cols="12" md="6">
                    <v-textarea
                        v-model="filters.rejection_reason_preview"
                        label="Razon rechazo (validacion)"
                        rows="2"
                        :rules="[maxLength(500)]"
                    />
                </v-col>
            </v-row>

            <v-row>
                <v-col cols="12">
                    <v-data-table-server
                        fixed-header
                        hover
                        height="500px"
                        :headers="headers"
                        :items="items"
                        :items-length="total"
                        :loading="loading"
                        v-model:options="options"
                        class="elevation-1"
                        :items-per-page-options="[10, 25, 50, 100]"
                        items-per-page-text="Mostrar"
                        no-data-text="No hay registros para mostrar"
                    >
                        <template #top>
                            <v-text-field
                                v-model="search"
                                label="Buscar tickets"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.status.name="{ item }">
                            <v-chip v-if="item.status" :color="item.status.color" size="small" variant="flat">
                                {{ item.status.name }}
                            </v-chip>
                            <span v-else>-</span>
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>
