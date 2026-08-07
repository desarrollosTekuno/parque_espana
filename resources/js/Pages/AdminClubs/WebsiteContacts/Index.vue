<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";

/* ====================== Props ====================== */
interface ContactMessage {
    id: number;
    name: string;
    subject: string;
    message: string;
    created_at: string;
}

interface PaginatedMessages {
    data: ContactMessage[];
    total: number;
    current_page: number;
    per_page: number;
}

const props = defineProps<{ messages: PaginatedMessages }>();

/* ====================== Variables ====================== */
const headers = [
    { title: "Nombre", key: "name", sortable: false },
    { title: "Asunto", key: "subject", sortable: false },
    { title: "Mensaje", key: "message", sortable: false },
    { title: "Fecha", key: "created_at", sortable: false },
];
const items = ref(props.messages.data);
const total = ref(props.messages.total);
const loading = ref(false);
const options = ref({
    page: props.messages.current_page,
    itemsPerPage: props.messages.per_page,
});

/* ====================== Funciones ====================== */
const fetchItems = () => {
    loading.value = true;

    router.get(
        route("website-contacts.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
        },
        {
            preserveState: true,
            replace: true,
            only: ["messages"],
            onFinish: () => loading.value = false,
        },
    );
};

const dateTime = (value: string) => {
    return new Date(value).toLocaleString("es-MX");
};

/* ====================== Watchers ====================== */
watch(
    () => props.messages,
    (messages) => {
        items.value = messages.data;
        total.value = messages.total;
        loading.value = false;
    },
);

watch(options, fetchItems, { deep: true });
</script>

<template>
    <Head title="Mensajes de contacto" />

    <AppLayout>
        <template #header>Mensajes de contacto</template>

        <v-card>
            <v-card-text>
                <v-data-table-server
                    v-model:page="options.page"
                    v-model:items-per-page="options.itemsPerPage"
                    :headers="headers"
                    :items="items"
                    :items-length="total"
                    :loading="loading"
                >
                    <template #item.message="{ item }">
                        <span class="message-cell">{{ item.message }}</span>
                    </template>

                    <template #item.created_at="{ item }">
                        {{ dateTime(item.created_at) }}
                    </template>
                </v-data-table-server>
            </v-card-text>
        </v-card>
    </AppLayout>
</template>

<style scoped>
.message-cell {
    white-space: pre-line;
}
</style>
