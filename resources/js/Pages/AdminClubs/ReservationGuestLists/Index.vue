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
    title: string;
    description: string;
    status: string;
    total_guests: number;
    total_adults: number;
    total_children: number;
    total_billable_guests: number;
    billable_subtotal: number;
    non_billable_subtotal: number;
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
    title: "",
    description: "",
    status: "",
    total_guests: 0,
    total_adults: 0,
    total_children: 0,
    total_billable_guests: 0,
    billable_subtotal: 0,
    non_billable_subtotal: 0,
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
    form.title = data.title;
    form.description = data.description;
    form.status = data.status;
    form.total_guests = data.total_guests;
    form.total_billable_guests = data.total_billable_guests;
    form.billable_subtotal = data.billable_subtotal;
    form.non_billable_subtotal = data.non_billable_subtotal;
    form.reservation_id = data.reservation_id;
    form.guest_list_items = data.guest_list_items;
    showModalApprove.value = true;
}

const detail = (data: any) => {
    form.id = data.id;
    form.title = data.title;
    form.description = data.description;
    form.status = data.status;
    form.total_guests = data.total_guests;
    form.total_adults = data.total_adults;
    form.total_children = data.total_children;
    form.total_billable_guests = data.total_billable_guests;
    form.billable_subtotal = data.billable_subtotal;
    form.non_billable_subtotal = data.non_billable_subtotal;
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

    // console.log(form);

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
    discount.value = 0;
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
    { title: "Evento", key:"title"},
    { title: "Total Invitados", key: "total_guests" },
    { title: "Subtotal Socio", key: "billable_subtotal" },
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

                        <template v-slot:item.subtotal="{ item }">
                            {{ formatCurrency(item.billable_subtotal)}}
                        </template>

                        <template v-slot:item.discount="{ item }">
                            {{ formatCurrency(item.discount) }}
                        </template>

                        <template v-slot:item.total="{ item }">
                            {{ formatCurrency(item.total) }}
                        </template>

                        <template v-slot:item.status="{ item }">
                            <v-chip :color="item.color" dark>
                                {{ item.status }}
                            </v-chip>
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
                <v-card-title class="d-flex justify-space-between align-center flex-wrap gap-2">
                    <div class="d-flex align-center gap-2">
                        <v-icon color="primary">mdi-account-check</v-icon>
                        <span class="text-h6 font-weight-bold">
                            Lista de invitados
                        </span>
                    </div>
                    <v-btn-toggle v-model="action" mandatory density="comfortable">
                        <v-btn value="approve" color="success" variant="flat">Aprobar</v-btn>
                        <v-btn value="reject" color="error" variant="flat">Rechazar</v-btn>
                    </v-btn-toggle>
                </v-card-title>

                <v-card-text class="overflow-y-auto h-full">
                    <!-- Evento + Totales -->
                    <v-sheet class="pa-4 mb-4 rounded-lg border" color="grey-lighten-5">
                        <div class="d-flex align-start mb-3">
                            <v-icon color="primary" size="20" class="mt-1 mr-3">mdi-calendar-star</v-icon>
                            <div class="flex-grow-1">
                                <div class="text-caption text-medium-emphasis text-uppercase">Evento</div>
                                <div class="text-subtitle-1 font-weight-medium">{{ form.title }}</div>
                            </div>
                        </div>

                        <v-divider class="my-3"></v-divider>

                        <div class="d-flex justify-space-around text-center">
                            <div>
                                <div class="text-h6 font-weight-bold text-primary">
                                    {{ form.total_guests }}
                                </div>
                                <div class="text-caption text-medium-emphasis">Total invitados</div>
                            </div>
                            <v-divider vertical></v-divider>
                            <div>
                                <div class="text-h6 font-weight-bold">{{ form.total_billable_guests }}</div>
                                <div class="text-caption text-medium-emphasis">A cargo del socio</div>
                            </div>
                            <v-divider vertical></v-divider>
                            <div>
                                <div class="text-h6 font-weight-bold">
                                    {{ form.total_guests - form.total_billable_guests }}
                                </div>
                                <div class="text-caption text-medium-emphasis">Pago directo</div>
                            </div>
                        </div>
                    </v-sheet>

                    <!-- Lista de invitados con resaltado -->
                    <div style="max-height: 350px; overflow-y: auto;">
                        <v-list density="comfortable" class="rounded-lg border">
                            <v-list-item
                                v-for="(item, index) in form.guest_list_items"
                                :key="index"
                                :class="{ 'bg-primary-lighten-5': item.is_billable_to_member }">
                                <template #prepend>
                                    <v-avatar :color="item.is_billable_to_member ? 'primary' : 'grey'" size="34">
                                        {{ item.name.charAt(0).toUpperCase() }}
                                    </v-avatar>
                                </template>
                                <v-list-item-title class="font-weight-medium d-flex align-center gap-2">
                                    {{ item.name }}
                                    <v-tooltip v-if="item.is_billable_to_member" text="A cargo del socio" location="top">
                                        <template #activator="{ props }">
                                            <v-icon v-bind="props" size="14" color="primary">
                                                mdi-account-cash
                                            </v-icon>
                                        </template>
                                    </v-tooltip>
                                </v-list-item-title>
                                <v-list-item-subtitle>
                                    Edad: {{ item.age }}
                                </v-list-item-subtitle>
                                <template #append>
                                    <span :class="item.is_billable_to_member ? 'text-primary font-weight-medium' : ''">
                                        {{ formatCurrency(item.price) }}
                                    </span>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>

                    <!-- Acción: Aprobar -->
                    <div v-if="action === 'approve'" class="mt-5">
                        <v-alert type="info" variant="tonal" density="compact" class="mb-3">
                            El descuento se aplica únicamente sobre el subtotal a cargo del socio.
                        </v-alert>
                        <v-text-field
                            v-model="discount"
                            label="Descuento (%)"
                            type="number"
                            suffix="%"
                            density="comfortable"
                            prepend-inner-icon="mdi-tag-outline"
                            class="mb-2"
                        />

                        <v-sheet class="pa-4 rounded-lg border" color="grey-lighten-5">
                            <!-- A cargo de los invitados -->
                            <div class="mb-3">
                                <div class="text-caption text-medium-emphasis text-uppercase mb-1">
                                    A cargo de los invitados
                                </div>
                                <div class="d-flex justify-space-between align-center">
                                    <span class="text-body-2">
                                        <v-icon size="16" class="mr-1">mdi-account-group-outline</v-icon>
                                        Pago directo ({{ form.total_guests - form.total_billable_guests }} invitados)
                                    </span>
                                    <span class="text-body-2 font-weight-medium">
                                        {{ formatCurrency(form.non_billable_subtotal) }}
                                    </span>
                                </div>
                            </div>

                            <v-divider class="my-3"></v-divider>

                            <!-- A cargo del socio -->
                            <div>
                                <div class="d-flex justify-space-between align-center mb-1">
                                    <span class="text-caption text-medium-emphasis text-uppercase">
                                        A cargo del socio
                                    </span>
                                    <v-chip size="x-small" color="primary" variant="tonal">
                                        {{ form.total_billable_guests }} invitados
                                    </v-chip>
                                </div>
                                <div class="d-flex justify-space-between">
                                    <span class="text-body-2">Subtotal</span>
                                    <span class="text-body-2">{{ formatCurrency(form.billable_subtotal) }}</span>
                                </div>
                                <div class="d-flex justify-space-between text-success">
                                    <span class="text-body-2">Descuento ({{ discount || 0 }}%)</span>
                                    <span class="text-body-2">-{{ formatCurrency(discountAmount) }}</span>
                                </div>

                                <v-divider class="my-2"></v-divider>

                                <div class="d-flex justify-space-between align-center">
                                    <span class="text-subtitle-1 font-weight-bold">Total a pagar</span>
                                    <span class="text-h6 font-weight-bold text-primary">
                                        {{ formatCurrency(totalAmount) }}
                                    </span>
                                </div>
                            </div>
                        </v-sheet>
                    </div>

                    <!-- Acción: Rechazar -->
                    <div v-if="action === 'reject'" class="mt-4">
                        <v-alert type="warning" variant="tonal" density="compact" class="mb-3">
                            Ingresa el motivo del rechazo.
                        </v-alert>
                        <v-textarea
                            v-model="form.comments"
                            label="Motivo del rechazo"
                            rows="3"
                            variant="outlined"
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
                        @click="approveOrRejectList()">
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
                    <v-sheet class="pa-4 mb-4 rounded-lg border" color="grey-lighten-5">
                        <!-- Evento + Descripción -->
                        <div class="d-flex align-start mb-2">
                            <v-icon color="primary" size="20" class="mt-1 mr-3">mdi-calendar-star</v-icon>
                            <div class="flex-grow-1">
                                <div class="text-caption text-medium-emphasis text-uppercase">Evento</div>
                                <div class="text-subtitle-1 font-weight-medium">{{ form.title }}</div>
                                <div class="text-body-2 text-medium-emphasis mt-1" style="white-space: pre-wrap;">
                                    {{ form.description || '—' }}
                                </div>
                            </div>
                        </div>

                        <v-divider class="my-3"></v-divider>

                        <!-- Totales de invitados -->
                        <div class="d-flex justify-space-around text-center">
                            <div>
                                <div class="text-h6 font-weight-bold text-primary">{{ form.total_guests }}</div>
                                <div class="text-caption text-medium-emphasis">Total invitados</div>
                            </div>
                            <v-divider vertical></v-divider>
                            <div>
                                <div class="text-h6 font-weight-bold">{{ form.total_adults }}</div>
                                <div class="text-caption text-medium-emphasis">Mayores a 6 años</div>
                            </div>
                            <v-divider vertical></v-divider>
                            <div>
                                <div class="text-h6 font-weight-bold">{{ form.total_children }}</div>
                                <div class="text-caption text-medium-emphasis">De 3 a 6 años</div>
                            </div>
                        </div>
                    </v-sheet>
                    <div style="max-height: 350px; overflow-y: auto;">
                        <v-list density="comfortable" class="rounded-lg border">
                            <v-list-item
                                v-for="(item, index) in form.guest_list_items"
                                :key="index"
                                :class="{ 'bg-primary-lighten-5': item.is_billable }">
                                <template #prepend>
                                    <v-avatar :color="item.is_billable_to_member ? 'primary' : 'grey'" size="34">
                                        {{ item.name.charAt(0).toUpperCase() }}
                                    </v-avatar>
                                </template>
                                <v-list-item-title class="font-weight-medium d-flex align-center gap-2">
                                    {{ item.name }}
                                    <v-tooltip v-if="item.is_billable_to_member" text="A cargo del socio" location="top">
                                        <template #activator="{ props }">
                                            <v-icon v-bind="props" size="14" color="primary">
                                                mdi-account-cash
                                            </v-icon>
                                        </template>
                                    </v-tooltip>
                                </v-list-item-title>
                                <v-list-item-subtitle>
                                    Edad: {{ item.age }}
                                </v-list-item-subtitle>
                                <template #append>
                                    <span :class="item.is_billable_to_member ? 'text-primary font-weight-medium' : ''">
                                        {{ formatCurrency(item.price) }}
                                    </span>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>
                    <v-alert type="info" variant="tonal" :color="form.color" class="mt-4">
                        Estatus: {{ form.status }}
                    </v-alert>

                    <div class="mt-5">
                        <v-sheet class="pa-4 rounded-lg border" color="grey-lighten-5">
                            <!-- A cargo de los invitados -->
                            <div class="mb-3">
                                <div class="text-caption text-medium-emphasis text-uppercase mb-1">
                                    A cargo de los invitados
                                </div>
                                <div class="d-flex justify-space-between align-center">
                                    <span class="text-body-2">
                                        <v-icon size="16" class="mr-1">mdi-account-group-outline</v-icon>
                                        Pago directo ({{ form.total_guests - form.total_billable_guests }} invitados)
                                    </span>
                                    <span class="text-body-2 font-weight-medium">
                                        {{ formatCurrency(form.non_billable_subtotal) }}
                                    </span>
                                </div>
                            </div>

                            <v-divider class="my-3"></v-divider>

                            <!-- A cargo del socio -->
                            <div>
                                <div class="d-flex justify-space-between align-center mb-1">
                                    <span class="text-caption text-medium-emphasis text-uppercase">
                                        A cargo del socio
                                    </span>
                                    <v-chip size="x-small" color="primary" variant="tonal">
                                        {{ form.total_billable_guests }} invitados
                                    </v-chip>
                                </div>
                                <div class="d-flex justify-space-between">
                                    <span class="text-body-2">Subtotal</span>
                                    <span class="text-body-2">{{ formatCurrency(form.billable_subtotal) }}</span>
                                </div>
                                <div class="d-flex justify-space-between text-success">
                                    <span class="text-body-2">Descuento aplicado</span>
                                    <span class="text-body-2">-{{ formatCurrency(form.discount) }}</span>
                                </div>

                                <v-divider class="my-2"></v-divider>

                                <div class="d-flex justify-space-between align-center">
                                    <span class="text-subtitle-1 font-weight-bold">Total a pagar</span>
                                    <span class="text-h6 font-weight-bold text-primary">
                                        {{ formatCurrency(form.total) }}
                                    </span>
                                </div>
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
