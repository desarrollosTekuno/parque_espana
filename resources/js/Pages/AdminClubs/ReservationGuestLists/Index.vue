<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce } from 'lodash';
import { formatDateTimeNoTZ } from '@/constants/formatDates';
import { formatCurrency } from '@/constants/formatCurrency';
// import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";

const page = usePage();
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;
const showModalDetail = ref(false);
const showModalApprove = ref(false);
const action = ref<'approve' | 'reject' | null>(null)
const discount = ref(0);

interface Props {
    guestLists?: any;
}

const props = withDefaults(defineProps<Props>(), {
    guestLists: null
});

interface GuestList {
    id: number | null;
    status: string;
    total_guests: number;
    subtotal: number;
    discount_percentage: number;
    discount: number;
    total: number;
    comments: string;
    reservation_id: string;
    club_id: string;
    guest_list_items?: any[];
}

const form = useForm<GuestList>({
    id: null,
    status: "",
    total_guests: 0,
    subtotal: 0,
    discount_percentage: 0,
    discount: 0,
    total: 0,
    comments: "",
    reservation_id: "",
    club_id: "",
    guest_list_items: []
});

const edit = (data: any) => {
    form.id = data.id;
    form.status = data.status;
    form.total_guests = data.total_guests;
    form.subtotal = data.subtotal;
    form.reservation_id = data.reservation_id;
    form.guest_list_items = data.guest_list_items;
    showModalApprove.value = true;
}

const discountAmount = computed(() => {
    return (form.subtotal * discount.value) / 100;
});

const totalAmount = computed(() => {
    return form.subtotal - discountAmount.value;
});

function approveList(){

}

function rejectList(){

}

function resetModal() {
    showModalApprove.value = false;
}

watch(discount, (val) => {
    if (val < 0) {
        discount.value = 0;
    } else if (val > 100) {
        discount.value = 100;
    }
})


//* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "ID", key: "id" },
    { title: "Recurso", key: "reservation.amenity_resource.name" },
    { title: "Fecha Reserva", key: "reservation.start_datetime" },
    { title: "Total Invitados", key: "total_guests" },
    { title: "Subtotal", key: "subtotal" },
    { title: "Estatus", key: "status" },
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
const prefix = "guestLists";
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
        // [`${prefix}_filter_date`]: filterDate.value,
        // [`${prefix}_filter_status`]: filterStatus.value
    };

    router.get(route("guest-lists.index"), params, {
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
        <template #header> Listas de Invitados </template>
        <template #options>
            <!-- <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="create()"
                action="add"
                v-if="can.includes('system-variables.store')"
            /> -->
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
                                label="Buscar"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template v-slot:item.reservation.start_datetime="{ item }">
                            {{ formatDateTimeNoTZ(item.reservation.start_datetime)}}
                        </template>

                        <template v-slot:item.status="{ item }">
                            <v-chip :color="item.color" dark>
                                {{ item.status }}
                            </v-chip>
                        </template>

                        <template v-slot:item.subtotal="{ item }">
                            {{ formatCurrency(item.subtotal)}}
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                action="edit"
                                @click="edit(item)"
                                v-if="can.includes('guest-lists.update')"
                            />
                            <!-- <BaseButton
                                @click="destroy(item)"
                                action="delete"
                                v-if="can.includes('system-variables.destroy')"
                            /> -->
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
            <!-- <pre>{{ can }}</pre> -->
        </div>

        <v-dialog v-model="showModalApprove" max-width="700" persistent>
            <v-card class="rounded-xl">

                <!-- HEADER -->
                <v-card-title class="d-flex justify-space-between align-center">
                    <div class="d-flex align-center gap-2">
                        <v-icon color="primary">mdi-account-check</v-icon>
                        <span class="text-h6 font-weight-bold">
                            Lista de invitados
                        </span>
                    </div>

                    <v-btn-toggle v-model="action" mandatory>
                        <v-btn value="approve" color="success" variant="flat">Aprobar</v-btn>
                        <v-btn value="reject" color="error" variant="flat">Rechazar</v-btn>
                    </v-btn-toggle>
                </v-card-title>

                <v-divider />

                <v-card-text>
                    <!-- RESUMEN SUPERIOR -->
                    <v-sheet
                        class="pa-3 mb-4 d-flex justify-space-between align-center"
                        color="grey-lighten-4"
                        rounded
                    >
                        <span class="text-subtitle-2">Total de invitados</span>
                        <v-chip color="primary" variant="flat">
                            {{ form.guest_list_items.length }}
                        </v-chip>
                    </v-sheet>
                    <!-- LISTA -->
                    <div style="max-height: 250px; overflow-y: auto;">
                        <v-list density="comfortable" class="rounded-lg border">
                            <v-list-item
                                v-for="(item, index) in form.guest_list_items"
                                :key="index"
                            >
                                <v-list-item-title>
                                    {{ item.name }}
                                </v-list-item-title>

                                <v-list-item-subtitle>
                                    Edad: {{ item.age }}
                                </v-list-item-subtitle>

                                <!-- <template #append>
                                  <span>
                                    ${{ item.age <= 7 ? 150 : 300 }}
                                  </span>
                                </template> -->
                            </v-list-item>
                        </v-list>
                    </div>

                    <!-- ===================== -->
                    <!-- APROBAR -->
                    <!-- ===================== -->
                    <div v-if="action === 'approve'" class="mt-4">

                      <v-text-field
                        v-model="discount"
                        label="Descuento (%)"
                        type="number"
                        suffix="%"
                      />

                      <v-sheet class="pa-3 mt-2 rounded" color="grey-lighten-4">

                        <div class="d-flex justify-space-between">
                          <span>Subtotal</span>
                          <span>${{ form.subtotal }}</span>
                        </div>

                        <div class="d-flex justify-space-between">
                          <span>Descuento ({{ discount }}%)</span>
                          <span>-${{ discountAmount.toFixed(2) }}</span>
                        </div>

                        <v-divider class="my-2" />

                        <div class="d-flex justify-space-between font-weight-bold">
                          <span>Total</span>
                          <span>${{ totalAmount.toFixed(2) }}</span>
                        </div>

                      </v-sheet>

                    </div>

                    <!-- ===================== -->
                    <!-- RECHAZAR -->
                    <!-- ===================== -->
                    <div v-if="action === 'reject'" class="mt-4">
                      <v-textarea
                        v-model="form.comments"
                        label="Motivo del rechazo"
                        rows="3"
                      />
                    </div>

                </v-card-text>

                <v-divider />

                <!-- ACTIONS -->
                <v-card-actions class="justify-end">

                    <v-btn variant="text" @click="resetModal()">
                      Cancelar
                    </v-btn>

                    <v-btn
                      v-if="action === 'approve'"
                      color="success"
                      @click="approveList"
                    >
                      Confirmar aprobación
                    </v-btn>

                    <v-btn
                      v-if="action === 'reject'"
                      color="error"
                      @click="rejectList"
                    >
                      Confirmar rechazo
                    </v-btn>

                </v-card-actions>

            </v-card>
        </v-dialog>

    </AppLayout>
</template>
