<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import { formatCurrency } from '@/constants/formatCurrency';

const page = usePage();

interface Props {
    incidents?: any;
}

const props = withDefaults(defineProps<Props>(), {
    incidents: null,
});

const headers = [
    { title: "Visitante",  key: "visitor_name",   sortable: false },
    { title: "Correo",     key: "visitor_email",  sortable: true  },
    { title: "Tipo",       key: "incident_type",  sortable: true  },
    { title: "Descripción",key: "description",    sortable: false },
    { title: "Monto",      key: "charged_amount", sortable: true  },
    { title: "Fecha",      key: "created_at",     sortable: true  },
    { title: "Registrado por", key: "recorded_by.name", sortable: false },
];

const items   = ref<any[]>([]);
const total   = ref(0);
const loading = ref(false);
const search  = ref("");
const options = ref({ page: 1, itemsPerPage: 15, sortBy: [{ key: "id", order: "desc" }] });
const prefix  = "incidents";

const fetchItems = () => {
    loading.value = true;
    const params = {
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]:     options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]:   search.value,
        [`${prefix}_sort`]:     options.value.sortBy?.[0]?.key  ?? "id",
        [`${prefix}_order`]:    options.value.sortBy?.[0]?.order ?? "desc",
    };
    router.get(route("day-passes.incidents.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (p) => {
            items.value   = p.props[prefix]?.data  ?? [];
            total.value   = p.props[prefix]?.total ?? 0;
            loading.value = false;
        },
    });
};

watch([options, search], debounce(fetchItems, 400), { deep: true });

const formatDate = (value: string) =>
    value ? new Date(value).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

// Modal para ver descripción completa
const showDetail   = ref(false);
const detailItem   = ref<any>(null);
const openDetail   = (item: any) => { detailItem.value = item; showDetail.value = true; };
</script>

<template>
    <Head title="Incidencias de visitantes" />
    <AppLayout>
        <template #header>Incidencias de visitantes</template>

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
                        class="elevation-1"
                        :items-per-page-options="[15, 25, 50, 100]"
                        items-per-page-text="Mostrar"
                        no-data-text="No hay incidencias registradas"
                    >
                        <template #top>
                            <div class="d-flex align-center ga-3 mx-4 mt-2 mb-1">
                                <v-text-field
                                    v-model="search"
                                    label="Buscar por visitante, teléfono o tipo"
                                    prepend-inner-icon="mdi-magnify"
                                    clearable
                                    hide-details
                                    class="flex-grow-1"
                                />
                            </div>
                        </template>

                        <template #item.visitor_name="{ item }">
                            <span class="font-weight-medium">
                                {{ item.visitor_first_name }} {{ item.visitor_last_name }}
                            </span>
                        </template>

                        <template #item.incident_type="{ item }">
                            <v-chip size="small" color="error" variant="tonal">
                                {{ item.incident_type }}
                            </v-chip>
                        </template>

                        <template #item.description="{ item }">
                            <div class="d-flex align-center ga-2">
                                <span class="text-truncate" style="max-width: 220px;">
                                    {{ item.description }}
                                </span>
                                <v-btn
                                    v-if="item.description?.length > 60"
                                    icon="mdi-eye-outline"
                                    size="x-small"
                                    variant="text"
                                    @click="openDetail(item)"
                                />
                            </div>
                        </template>

                        <template #item.charged_amount="{ item }">
                            <span v-if="item.charged_amount" class="font-weight-medium text-error">
                                {{ formatCurrency(item.charged_amount) }}
                            </span>
                            <span v-else class="text-medium-emphasis">—</span>
                        </template>

                        <template #item.created_at="{ item }">
                            {{ formatDate(item.created_at) }}
                        </template>

                        <template #item.recorded_by.name="{ item }">
                            {{ item.recorded_by?.name ?? '—' }}
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <!-- Modal: descripción completa -->
        <v-dialog v-model="showDetail" max-width="500">
            <v-card class="rounded-xl" v-if="detailItem">
                <v-card-title class="d-flex align-center ga-2 pa-4 border-b">
                    <v-icon color="error">mdi-alert-circle-outline</v-icon>
                    <span class="text-h6 font-weight-bold">Detalle del incidente</span>
                </v-card-title>
                <v-card-text class="pa-4">
                    <v-sheet class="pa-3 mb-4 rounded-lg border" color="red-lighten-5">
                        <div class="text-caption text-error text-uppercase font-weight-bold mb-1">Visitante</div>
                        <div class="text-body-2 font-weight-medium">
                            {{ detailItem.visitor_first_name }} {{ detailItem.visitor_last_name }}
                        </div>
                        <div class="text-caption text-medium-emphasis">{{ detailItem.visitor_email ?? 'Sin correo registrado' }}</div>
                    </v-sheet>

                    <div class="mb-3">
                        <div class="text-caption text-medium-emphasis text-uppercase mb-1">Tipo de incidente</div>
                        <v-chip color="error" variant="tonal" size="small">{{ detailItem.incident_type }}</v-chip>
                    </div>

                    <div class="mb-3">
                        <div class="text-caption text-medium-emphasis text-uppercase mb-1">Descripción</div>
                        <div class="text-body-2">{{ detailItem.description }}</div>
                    </div>

                    <div v-if="detailItem.charged_amount" class="mb-3">
                        <div class="text-caption text-medium-emphasis text-uppercase mb-1">Monto cargado</div>
                        <div class="text-body-1 font-weight-bold text-error">
                            {{ formatCurrency(detailItem.charged_amount) }}
                        </div>
                    </div>

                    <div class="d-flex ga-4 text-caption text-medium-emphasis mt-2">
                        <span>{{ formatDate(detailItem.created_at) }}</span>
                        <span>Registrado por: {{ detailItem.recorded_by?.name ?? '—' }}</span>
                    </div>
                </v-card-text>
                <v-card-actions class="pa-4 border-t">
                    <v-spacer />
                    <v-btn variant="tonal" @click="showDetail = false">Cerrar</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

    </AppLayout>
</template>
