<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce } from 'lodash';
import { formatDateTimeNoTZ } from '@/constants/formatDates';
import { formatCurrency } from '@/constants/formatCurrency';
// import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";
import { customToastSwal } from '@/utils/swal';

const page = usePage();
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;
const showModalDetail = ref(false);
const showModalApprove = ref(false);
const action = ref<'approve' | 'reject' | null>('approve')
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
    total_adults: number;
    total_children: number;
    billable_subtotal: number;
    discount_percentage: number;
    discount: number;
    total: number;
    comments: string;
    color: string;
    reservation_id: string;
    club_id: string;
    action:string;
    guest_list_items?: any[];
}

const form = useForm<GuestList>({
    id: null,
    status: "",
    total_guests: 0,
    total_adults: 0,
    total_children: 0,
    billable_subtotal: 0,
    discount_percentage: 0,
    discount: 0,
    total: 0,
    comments: "",
    color: "",
    reservation_id: "",
    club_id: "",
    action: "",
    guest_list_items: []
});

const edit = (data: any) => {
    form.id = data.id;
    form.status = data.status;
    form.total_guests = data.total_guests;
    form.billable_subtotal = data.billable_subtotal;
    form.reservation_id = data.reservation_id;
    form.guest_list_items = data.guest_list_items;
    showModalApprove.value = true;
}

const detail = (data) => {
    form.id = data.id;
    form.status = data.status;
    form.total_guests = data.total_guests;
    form.total_adults = data.total_adults;
    form.total_children = data.total_children;
    form.billable_subtotal = data.billable_subtotal;
    form.discount = data.discount;
    form.total = data.total;
    form.comments = data.comments;
    form.color = data.color;
    form.reservation_id = data.reservation_id;
    form.guest_list_items = data.guest_list_items;
    showModalDetail.value = true;
}

const Cerrar = () => {
    showModalDetail.value = false;
    form.reset();
}

const discountAmount = computed(() => {
    return (form.billable_subtotal * discount.value) / 100;
});

const totalAmount = computed(() => {
    return form.billable_subtotal - discountAmount.value;
});

function approveOrRejectList(){

    form.discount_percentage = discount.value;
    form.action = action.value;

    form.put(route("guest-lists.update", form.id), {
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "",
                icon: "success",
            });
            showModalApprove.value = false;
            discount.value = 0;
            form.reset();
            fetchItems();
        },
        onError: (err) => {
            console.error(err);
            customToastSwal({
                title: `Error: ${form.errors.messageError}`,
                text: `${form.errors.exception}`,
                icon: "error",
            });
        }
    });
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
    // { title: "Recurso", key: "reservation.amenity_resource.name" },
    // { title: "Fecha Reserva", key: "reservation.start_datetime" },
    { title: "Total Invitados", key: "total_guests" },
    { title: "Subtotal", key: "billable_subtotal" },
    { title: "Descuento", key: "discount"},
    { title: "Total", key: "total"},
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
                            {{ formatCurrency(item.billable_subtotal)}}
                        </template>

                        <template v-slot:item.discount="{ item }">
                            {{ formatCurrency(item.discount) }}
                        </template>

                        <template v-slot:item.total="{ item }">
                            {{ formatCurrency(item.total) }}
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                @click="detail(item)"
                                action="view"
                            />
                            <BaseButton
                                action="edit"
                                @click="edit(item)"
                                v-if="can.includes('guest-lists.update')"
                                :disabled="item.status != 'PENDIENTE'"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
            <!-- <pre>{{ guestLists }}</pre> -->
        </div>

        <v-dialog v-model="showModalApprove" max-width="700" persistent>
            <v-card class="rounded-xl">
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

                <v-card-text class="overflow-y-auto h-full">
                    <v-sheet
                        class="pa-3 mb-4 d-flex justify-space-between align-center"
                        color="grey-lighten-4"
                        rounded>
                        <span class="text-subtitle-2">Total de invitados</span>
                        <v-chip color="primary" variant="flat">
                            {{ form.guest_list_items.length }}
                        </v-chip>
                    </v-sheet>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <v-list density="comfortable" class="rounded-lg border">
                            <v-list-item v-for="(item, index) in form.guest_list_items" :key="index">
                                <template #prepend>
                                    <v-avatar color="primary" size="34">
                                        {{ item.name.charAt(0).toUpperCase() }}
                                    </v-avatar>
                                </template>
                                <v-list-item-title class="font-weight-medium">
                                    {{ item.name }}
                                </v-list-item-title>
                                <v-list-item-subtitle>
                                    Edad: {{ item.age }}
                                </v-list-item-subtitle>
                                <template #append>
                                    <span> {{ formatCurrency(item.age <= 7 ? 150 : 300) }} </span>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>
                    <div v-if="action === 'approve'" class="mt-5">
                        <v-alert type="info" variant="tonal" class="mb-3">
                            Puedes aplicar un descuento sobre el subtotal.
                        </v-alert>
                        <v-text-field
                            v-model="discount"
                            label="Descuento (%)"
                            type="number"
                            suffix="%"
                            density="comfortable"
                        />
                        <v-sheet class="pa-3 mt-2 rounded" color="grey-lighten-4">
                            <div class="d-flex justify-space-between">
                                <span>Subtotal</span>
                                <span>{{ formatCurrency(form.billable_subtotal) }}</span>
                            </div>
                            <div class="d-flex justify-space-between">
                                <span>Descuento ({{ discount }}%)</span>
                                <span>-{{ formatCurrency(discountAmount) }}</span>
                            </div>
                            <v-divider class="my-2" />
                            <div class="d-flex justify-space-between font-weight-bold">
                                <span>Total</span>
                                <span>{{ formatCurrency(totalAmount) }}</span>
                            </div>
                        </v-sheet>
                    </div>
                    <div v-if="action === 'reject'" class="mt-4">
                        <v-alert type="warning" variant="tonal" class="mb-3">
                            Ingresa el motivo del rechazo
                        </v-alert>
                        <v-textarea
                            v-model="form.comments"
                            label="Motivo del rechazo"
                            rows="3"
                        />
                    </div>
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>
                    <BaseButton
                        text="Cancelar"
                        variant="tonal"
                        :icon-only="false"
                        action="cancel"
                        @click="resetModal()">
                    </BaseButton>
                    <BaseButton
                        v-if="action === 'approve'"
                        text="Confirmar aprobación"
                        variant="flat"
                        :icon-only="false"
                        icon="mdi-check-bold"
                        action="save"
                        @click="approveOrRejectList()">
                    </BaseButton>
                    <BaseButton
                        v-if="action === 'reject'"
                        :disabled="!form.comments"
                        text="Confirmar rechazo"
                        variant="flat"
                        :icon-only="false"
                        icon="mdi-close-thick"
                        action="delete"
                        @click="approveOrRejectList()"
                    >
                    </BaseButton>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showModalDetail" max-width="700" persistent>
            <v-card class="rounded-xl">
                <v-card-title class="d-flex justify-space-between align-center">
                    <div class="d-flex align-center gap-2">
                        <v-icon color="primary">mdi-account-check</v-icon>
                        <span class="text-h6 font-weight-bold">
                            Detalles de la lista de invitados
                        </span>
                    </div>
                </v-card-title>

                <v-card-text class="overflow-y-auto h-full">
                    <v-sheet class="pa-3 mb-4" color="grey-lighten-4" rounded>
                        <div class="d-flex justify-space-between">
                            <span class="text-subtitle-2">Total de invitados</span>
                            <v-chip color="primary" variant="flat">
                                {{ form.total_guests }}
                            </v-chip>
                        </div>
                        <div class="d-flex justify-space-between pr-3">
                            <span class="text-subtitle-2">Total invitados mayores a 7 años</span>
                                {{ form.total_adults }}
                        </div>
                        <div class="d-flex justify-space-between pr-3">
                            <span class="text-subtitle-2">Total invitados de 3 a 7 años</span>
                                {{ form.total_children }}
                        </div>
                    </v-sheet>
                    <div style="max-height: 310px; overflow-y: auto;">
                        <v-list density="comfortable" class="rounded-lg border">
                            <v-list-item v-for="(item, index) in form.guest_list_items" :key="index">
                                <template #prepend>
                                    <v-avatar color="primary" size="34">
                                        {{ item.name.charAt(0).toUpperCase() }}
                                    </v-avatar>
                                </template>
                                <v-list-item-title class="font-weight-medium">
                                    {{ item.name }}
                                </v-list-item-title>
                                <v-list-item-subtitle>
                                    Edad: {{ item.age }}
                                </v-list-item-subtitle>
                                <template #append>
                                  <span>
                                    {{ formatCurrency(item.age <= 7 ? 150 : 300) }}
                                  </span>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>
                    <v-alert type="info" variant="tonal" :color="form.color" class="mt-4">
                        Estatus: {{ form.status }}
                    </v-alert>
                    <div class="mt-5">
                        <v-sheet class="pa-3 mt-2 rounded" color="grey-lighten-4">
                            <div class="d-flex justify-space-between">
                                <span>Subtotal</span>
                                <span>{{ formatCurrency(form.billable_subtotal) }}</span>
                            </div>
                            <div class="d-flex justify-space-between">
                                <span>Descuento</span>
                                <span>-{{ formatCurrency(form.discount)  }}</span>
                            </div>
                            <v-divider class="my-2" />
                            <div class="d-flex justify-space-between font-weight-bold">
                                <span>Total</span>
                                <span>{{ formatCurrency(form.total)  }}</span>
                            </div>
                        </v-sheet>
                    </div>
                    <div v-if="form.status === 'RECHAZADA'" class="mt-4">
                        <span>Comentarios</span>
                        <v-sheet class="pa-3 mt-2 rounded" color="grey-lighten-4">
                            {{ form.comments }}
                        </v-sheet>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <BaseButton
                        text="Cerrar"
                        variant="tonal"
                        :icon-only="false"
                        action="cancel"
                        @click="Cerrar()">
                    </BaseButton>
                </v-card-actions>
            </v-card>
        </v-dialog>

    </AppLayout>
</template>
