<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { debounce } from 'lodash';

const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;

interface Props {
    reservations?: any;
}

interface Reservation {
    id: number | null;
    start_time: string;
    end_time: string;
    status: string;
    cancelled_at: string | null;
    club_id: string,
    amenity_id: string,
    user_id: string
}

const props = withDefaults(defineProps<Props>(), {
    reservations: null,
});

// Forms
const form = useForm<Reservation>({
    id: null,
    start_time: "",
    end_time: "",
    status: "",
    cancelled_at: "",
    club_id: "",
    amenity_id: "",
    user_id: ""
});

const create = () => {

};

// Refs
const showModal = ref(false);


//* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "ID", key: "id" },
    { title: "Hora Inicio", key: "start_time" },
    { title: "Hora Fin", key: "end_time" },
    { title: "Estatus", key: "status" },
    { title: "Amenidad", key: "amenity.name" },
    { title: "Acciones", key: "actions", sortable: false },
];

// variables reactivas
const items = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref("");
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});
const prefix = "reservations";
// función para cargar datos desde Laravel
const fetchItems = async () => {
    loading.value = true;
    const params = {
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
    };

    router.get(route("reservations.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            const data = page.props[prefix]?.data ?? [];
            const totalCount = page.props[prefix]?.total ?? 0;

            items.value = data;
            total.value = totalCount;
            loading.value = false;
        },
    });
};

// 🔁 Observadores con debounce para evitar muchas peticiones
watch([options, search], debounce(fetchItems, 400), { deep: true });
/* FIN DATATABLE SERVER SIDE */

</script>

<template>
    <Head title="Dashboard"/>

    <AppLayout>
        <template #header> Reservaciones </template>
        <template #options>
            <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="create()"
                action="add"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
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
                        items-per-page-text=" Mostrar"
                        no-data-text="No hay registros para mostrar"
                    >
                        <template #top>
                            <v-text-field
                                v-model="search"
                                label="Buscar reservación"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.actions="{ item }">
                            <!-- <BaseButton
                                action="edit"
                                @click="edit(item)"
                                v-if="can.includes('clubs.update')"
                            />
                            <BaseButton
                                @click="destroy(item)"
                                action="delete"
                                v-if="can.includes('clubs.destroy')"
                            /> -->
                        </template>

                    </v-data-table-server>
                </v-col>
            </v-row>

            <pre>{{ reservations }}</pre>
        </div>

    </AppLayout>

</template>
