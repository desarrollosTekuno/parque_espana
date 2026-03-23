<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import { formatDateTimeNoTZ } from '@/constants/formatDates';
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";
import DatePicker from '@/Components/DatePicker.vue';

const page = usePage();
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;
const showModalCancel = ref(false);
const filterDate = ref("");
const filterStatus = ref(null);

interface Props {
    reservations?: any;
    activeStatus?: any;
    reservationStatus?: any;
}

interface Reservation {
    id: number | null;
    date: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
    cancelled_at: string | null;
    club_id: string,
    amenity_id: string,
    user_id: string
}

const props = withDefaults(defineProps<Props>(), {
    reservations: null,
    activeStatus: null,
    reservationStatus: null
});

// Forms
const form = useForm<Reservation>({
    id: null,
    date: "",
    start_datetime: "",
    end_datetime: "",
    status: "",
    cancelled_at: "",
    club_id: "",
    amenity_id: "",
    user_id: ""
});

const clearFilters = () => {

    if (filterDate.value !== "" || filterStatus.value !== null) {
        filterDate.value = "";
        filterStatus.value = null;
        fetchItems();
    }
};

const cancel = (data: any) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea cancelar este registro?",
        confirmButtonText: "Sí, cancelar",
        cancelButtonText: "No",
    }).then((result) => {
        if (result.isConfirmed) {
            form.put(route("reservations.update", data.id), {
                onSuccess: () => {
                    customToastSwal({ title: "Reservación cancelada correctamente", icon: "success" });
                    fetchItems();
                },
            });
        }
    });
};



//* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "ID", key: "id" },
    { title: "Fecha Inicio", key: "start_datetime" },
    { title: "Fecha Fin", key: "end_datetime" },
    { title: "Estatus", key: "status.name" },
    { title: "Amenidad", key: "amenity.name" },
    { title: "Recurso", key: "amenity_resource.name" },
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
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
        [`${prefix}_filter_date`]: filterDate.value,
        [`${prefix}_filter_status`]: filterStatus.value
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

watch(() => page.props.auth.currentClub, () => {
    fetchItems();
});

watch(filterDate, () => {
    options.value.page = 1;
    fetchItems();
});

watch(filterStatus, () => {
    options.value.page = 1;
    fetchItems();
    // console.log(filterStatus.value);
});

</script>

<template>
    <Head title="Dashboard"/>

    <AppLayout>
        <template #header> Reservaciones </template>
        <template #options>
            <!-- <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="create()"
                action="add"
            /> -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full pl-3">
                <div class="w-full sm:w-auto sm:flex-1 min-w-[160px]">
                    <DatePicker
                        v-model="filterDate"
                        :showIcon="false"
                        class="w-full"
                    />
                </div>

                <div class="w-full sm:w-auto sm:flex-1 min-w-[160px]">
                    <v-select
                        v-model="filterStatus"
                        :items="reservationStatus"
                        label="Estatus"
                        item-title="text"
                    ></v-select>
                </div>

                <div class="w-full sm:w-auto flex justify-end">
                    <BaseButton
                        variant="elevated"
                        :icon-only="false"
                        @click="clearFilters()"
                        color="grey"
                        text="Limpiar"
                        icon="mdi-filter-off"
                    />
                </div>

            </div>
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

                        <template v-slot:item.start_datetime="{ item }">
                            {{ formatDateTimeNoTZ(item.start_datetime)}}
                        </template>

                        <template v-slot:item.end_datetime="{ item }">
                            {{ formatDateTimeNoTZ(item.end_datetime)}}
                        </template>

                        <template v-slot:item.status.name="{ item }">
                            <v-chip :color="item.status.color" dark>
                                {{ item.status.name }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <!-- <BaseButton
                                action="edit"
                                @click="edit(item)"
                                v-if="can.includes('clubs.update')"
                            /> -->
                            <BaseButton
                                @click="cancel(item)"
                                action="cancel"
                                :disabled="item.reservation_status_id != activeStatus"
                                v-if="can.includes('reservations.update')"
                            />
                        </template>

                    </v-data-table-server>
                </v-col>
            </v-row>

            <!-- <pre>{{ reservations }}</pre> -->
        </div>

    </AppLayout>

</template>
