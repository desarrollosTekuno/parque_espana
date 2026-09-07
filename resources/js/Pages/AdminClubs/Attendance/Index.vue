<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import { formatDateTimeNoTZ } from '@/constants/formatDates';
import DatePicker from '@/Components/DatePicker.vue';

const page = usePage();

interface Props {
    attendance?: any;
    reservationStatus?: any;
    amenities?: any[];
    resources?: any[];
    summary?: {
        activa: number;
        asistencia: number;
        inasistencia: number;
    };
    filterDate?: string;
}

const props = withDefaults(defineProps<Props>(), {
    attendance: null,
    reservationStatus: null,
    amenities: () => [],
    resources: () => [],
    summary: () => ({ activa: 0, asistencia: 0, inasistencia: 0 }),
    filterDate: null,
});

const filterDate = ref(props.filterDate);
const filterAmenity = ref(null);
const filterResource = ref(null);
const filterStatus = ref(null);
const search = ref('');

const headers = [
    { title: 'Hora', key: 'start_datetime' },
    { title: 'Miembro', key: 'member.full_name' },
    { title: 'Amenidad', key: 'amenity.name' },
    { title: 'Recurso', key: 'amenity_resource.name' },
    { title: 'Profesor', key: 'coach.full_name', sortable: false },
    { title: 'Estatus', key: 'status.name' },
];

const items = ref([]);
const total = ref(0);
const loading = ref(false);
const options = ref({
    page: 1,
    itemsPerPage: 25,
    sortBy: [{ key: 'start_datetime', order: 'asc' }],
});
const summary = ref(props.summary);
const prefix = 'attendance';

const fetchItems = async () => {
    loading.value = true;
    const params = {
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? 'start_datetime',
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? 'asc',
        [`${prefix}_filter_date`]: filterDate.value,
        [`${prefix}_filter_amenity`]: filterAmenity.value,
        [`${prefix}_filter_resource`]: filterResource.value,
        [`${prefix}_filter_status`]: filterStatus.value,
    };

    router.get(route('attendance.index'), params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            const data = page.props[prefix]?.data ?? [];
            const totalCount = page.props[prefix]?.total ?? 0;

            items.value = data;
            total.value = totalCount;
            summary.value = page.props.summary;
            loading.value = false;
        },
    });
};

watch([options, search], debounce(fetchItems, 400), { deep: true });

watch(() => page.props.auth.currentClub, () => {
    fetchItems();
});

watch(filterDate, () => {
    options.value.page = 1;
    fetchItems();
});

watch(filterAmenity, () => {
    filterResource.value = null;
    options.value.page = 1;
    fetchItems();
});

watch(filterResource, () => {
    options.value.page = 1;
    fetchItems();
});

watch(filterStatus, () => {
    options.value.page = 1;
    fetchItems();
});

const resourcesForAmenity = ref(props.resources);
watch(filterAmenity, (value) => {
    resourcesForAmenity.value = value
        ? props.resources.filter((r: any) => r.amenity_id === value)
        : props.resources;
});

const clearFilters = () => {
    filterDate.value = new Date().toISOString().slice(0, 10);
    filterAmenity.value = null;
    filterResource.value = null;
    filterStatus.value = null;
    search.value = '';
};
</script>

<template>
    <Head title="Asistencias" />

    <AppLayout>
        <template #header> Asistencias </template>

        <div class="pa-4 bg-grey-lighten-4 rounded-xl mt-5">
            <v-row>
                <v-col cols="12" md="4">
                    <v-card color="green" variant="tonal">
                        <v-card-text class="d-flex justify-space-between align-center">
                            <span>Activas</span>
                            <span class="text-h5">{{ summary.activa }}</span>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" md="4">
                    <v-card color="blue" variant="tonal">
                        <v-card-text class="d-flex justify-space-between align-center">
                            <span>Asistencias</span>
                            <span class="text-h5">{{ summary.asistencia }}</span>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" md="4">
                    <v-card color="red" variant="tonal">
                        <v-card-text class="d-flex justify-space-between align-center">
                            <span>Inasistencias</span>
                            <span class="text-h5">{{ summary.inasistencia }}</span>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg mt-4 pa-4">
                <v-row>
                    <v-col cols="12" md="3" class="pb-0">
                        <DatePicker v-model="filterDate" :showIcon="false" class="w-full" />
                    </v-col>
                    <v-col cols="12" md="3" class="pb-0">
                        <v-select
                            v-model="filterAmenity"
                            :items="amenities"
                            item-title="name"
                            item-value="id"
                            label="Amenidad"
                            clearable
                        />
                    </v-col>
                    <v-col cols="12" md="3" class="pb-0">
                        <v-select
                            v-model="filterResource"
                            :items="resourcesForAmenity"
                            item-title="name"
                            item-value="id"
                            label="Recurso"
                            clearable
                            no-data-text="No hay recursos"
                        />
                    </v-col>
                    <v-col cols="12" md="3" class="pb-0">
                        <v-select
                            v-model="filterStatus"
                            :items="reservationStatus"
                            item-title="text"
                            item-value="value"
                            label="Estatus"
                            clearable
                        />
                    </v-col>
                    <v-col cols="12" class="d-flex justify-end pt-0">
                        <v-btn variant="text" color="blue" @click="clearFilters">
                            Limpiar filtros
                        </v-btn>
                    </v-col>
                </v-row>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg mt-4">
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
                            no-data-text="No hay reservaciones para esta fecha"
                        >
                            <template #top>
                                <v-text-field
                                    v-model="search"
                                    label="Buscar por miembro"
                                    class="mx-4 mt-2"
                                    clearable
                                />
                            </template>

                            <template #item.start_datetime="{ item }">
                                {{ formatDateTimeNoTZ(item.start_datetime) }}
                            </template>

                            <template #item.member.full_name="{ item }">
                                {{ item.member?.full_name ?? '—' }}
                            </template>

                            <template #item.coach.full_name="{ item }">
                                {{ item.coach?.full_name ?? '—' }}
                            </template>

                            <template #item.status.name="{ item }">
                                <v-chip :color="item.status.color" dark>
                                    {{ item.status.name }}
                                </v-chip>
                            </template>
                        </v-data-table-server>
                    </v-col>
                </v-row>
            </div>
        </div>
    </AppLayout>
</template>
