<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce } from 'lodash';
import { formatDateTimeNoTZ } from '@/constants/formatDates';
import { formatTime } from '@/constants/formatDates';
import { formatCurrency } from '@/constants/formatCurrency';
// import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";
import { customToastSwal } from '@/utils/swal';
import { nowAsLocalInput } from "@/constants/formatDates";
import { optionalLength, required, selectRequired } from "@/constants/validationRules";

const page = usePage();
const can = usePage().props.auth.permissions;
const showModalDetail = ref(false);

interface Props {
    guestListPayments?: any;
    clubPaymentMethods?: any;
}

const props = withDefaults(defineProps<Props>(), {
    guestListPayments: null,
    clubPaymentMethods: null
});

interface GuestListPayment {
    id: number | null;
    member: string;
    title: string;
    description: string;
    total_non_billable_guests: string;
    non_billable_total: string;
    guest_list_items?: any[];
}

const form = useForm<GuestListPayment>({
    id: null,
    member: "",
    title: "",
    description: "",
    total_non_billable_guests: "",
    non_billable_total: "",
    guest_list_items: [],
});

const detail = (data: any) => {
    form.id = data.id;
    form.title = data.title;
    form.description = data.description;
    form.total_non_billable_guests = data.total_non_billable_guests;
    form.non_billable_total = data.non_billable_total;
    form.guest_list_items = data.guest_list_items;
    form.member = data.member.first_name + ' ' + data.member.last_name
    showModalDetail.value = true;
    paymentFormRef.value?.resetValidation?.();
}

interface PaymentApplicationInput {
    charge_id: number;
    amount: number;
}

interface PaymentFormData {
    membership_account_id: number | null;
    club_id: number | null;
    payment_method_id: number | null;
    paid_at: string;
    reference: string;
    bank_name: string;
    check_number: string;
    notes: string;
    applications: PaymentApplicationInput[];
}

const paymentForm = useForm<PaymentFormData>({
    membership_account_id: null,
    club_id: null,
    payment_method_id: null,
    paid_at: nowAsLocalInput(),
    reference: "",
    bank_name: "",
    check_number: "",
    notes: "",
    applications: [],
});

const Cerrar = () => {
    showModalDetail.value = false;
    form.reset();
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentFormRef.value?.resetValidation?.();
}

//* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "ID", key: "id" },
    { title: "Socio", key: "member.first_name" },
    { title: "Evento", key:"title"},
    { title: "Hora", key:"time"},
    { title: "Total Invitados", key: "total_guests" },
    { title: "Total a cobrar", key: "non_billable_subtotal" },
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
const prefix = "guestListPayments";
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

    router.get(route("guest-list-payments.index"), params, {
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


// Cobro
const paymentMethodRules = [selectRequired];
const paidAtRules = [required];
const notesRules = [optionalLength(0, 1000)];
const paymentFormRef = ref();

const paymentMethods = ref<PaymentMethodItem[]>(
    props.clubPaymentMethods ?? [],
);

const selectedPaymentMethod = computed( () =>
    paymentMethods.value.find(
        (method) => method.id === paymentForm.payment_method_id,
    ) ?? null,
);

interface PaymentMethodItem {
    id: number;
    code: string;
    name: string;
    requires_reference: boolean;
    requires_bank_name: boolean;
    requires_check_number: boolean;
    affects_cash_cut: boolean;
}

const selectedIds = ref([])

const guests = computed(() => form.guest_list_items ?? [])
const seleccionables = computed(() => guests.value.filter(g => !g.is_paid))

const seleccionados = computed(() =>
  guests.value.filter(g => selectedIds.value.includes(g.id)))
const totalSeleccionado = computed(() =>
  Math.round(seleccionados.value.reduce((s, g) => s + Number(g.price), 0) * 100) / 100)

const allSelected = computed(() =>
  seleccionables.value.length > 0 &&
  seleccionables.value.every(g => selectedIds.value.includes(g.id)))
const someSelected = computed(() =>
  selectedIds.value.length > 0 && !allSelected.value)

function toggleAll( v ) {
  selectedIds.value = v ? seleccionables.value.map(g => g.id) : []
}

const submitPayment = async () => {
    // ...
};

</script>

<template>
    <Head title="Dashboard"/>
    <AppLayout>
        <template #header> Pagos de Invitados </template>

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

                        <template v-slot:item.time="{ item }">
                            {{ formatTime(item.time) }}
                        </template>

                        <template v-slot:item.non_billable_subtotal="{ item }">
                            {{ formatCurrency(item.non_billable_subtotal)}}
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
                            <!-- <BaseButton
                                action="edit"
                                @click="edit(item)"
                                v-if="can.includes('guest-lists.update')"
                                :disabled="item.status != 'PENDIENTE'"
                            /> -->
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
            <!-- <pre>{{ guestListPayments }}</pre> -->
            <!-- <pre>{{ clubPaymentMethods }}</pre> -->
        </div>

        <v-dialog v-model="showModalDetail" max-width="800" persistent>
            <v-card class="rounded-xl">
                <v-card-title class="d-flex justify-space-between align-center">
                    <div class="d-flex align-center ga-2">
                        <v-icon color="primary">mdi-account-cash</v-icon>
                        <span class="text-h6 font-weight-bold">Cobro de invitados</span>
                    </div>
                </v-card-title>

                <v-card-text class="overflow-y-auto h-full">
                    <v-form ref="paymentFormRef" validate-on="input" @submit.prevent="submitPayment">
                        <!-- Info del evento / lista -->
                        <v-sheet class="pa-4 mb-4 rounded-lg border" color="grey-lighten-5">
                            <div class="d-flex align-start">
                                <v-icon color="primary" size="20" class="mt-1 mr-3">mdi-calendar-star</v-icon>
                                <div class="flex-grow-1">
                                    <div class="text-caption text-medium-emphasis text-uppercase">
                                        <!-- {{ form.event_id ? 'Evento' : 'Lista' }} -->
                                    </div>
                                    <div class="text-subtitle-1 font-weight-medium">{{ form.title }}</div>
                                    <div v-if="form.description" class="text-body-2 text-medium-emphasis mt-1"
                                        style="white-space: pre-wrap;">
                                        {{ form.description }}
                                    </div>
                                    <div class="d-flex ga-6 mt-2">
                                        <div>
                                            <div class="text-caption text-medium-emphasis text-uppercase">Socio</div>
                                            <div class="text-body-2 font-weight-medium">{{ form.member }}</div>
                                        </div>
                                        <div>
                                            <div class="text-caption text-medium-emphasis text-uppercase">Fecha</div>
                                            <div class="text-body-2 font-weight-medium">{{ form.date || '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </v-sheet>

                        <!-- Lista de invitados -->
                        <div style="max-height: 320px; overflow-y: auto;">
                            <v-list density="comfortable" class="rounded-lg border">
                                <v-list-item class="border-b">
                                    <template #prepend>
                                        <v-checkbox
                                            :model-value="allSelected"
                                            :indeterminate="someSelected"
                                            :disabled="seleccionables.length === 0"
                                            hide-details density="compact"
                                            @update:model-value="toggleAll" />
                                    </template>
                                    <v-list-item-title class="text-caption text-medium-emphasis text-uppercase">
                                        Seleccionar todos los pendientes
                                    </v-list-item-title>
                                </v-list-item>

                                <v-list-item
                                    v-for="item in guests"
                                    :key="item.id"
                                    :class="{ 'bg-primary-lighten-5': selectedIds.includes(item.id) }">
                                    <template #prepend>
                                        <v-checkbox
                                            v-model="selectedIds"
                                            :value="item.id"
                                            :disabled="item.is_paid"
                                            hide-details density="compact" />
                                    </template>

                                    <v-list-item-title class="font-weight-medium d-flex align-center ga-2">
                                        {{ item.name }} {{ item.last_name }}
                                    </v-list-item-title>
                                    <v-list-item-subtitle>Edad: {{ item.age }}</v-list-item-subtitle>

                                    <template #append>
                                        <div class="d-flex align-center ga-3">
                                            <v-chip size="small" :color="item.is_paid ? 'success' : 'warning'" variant="flat">
                                                {{ item.is_paid ? 'Pagado' : 'Pendiente' }}
                                            </v-chip>
                                            <span class="font-weight-medium" style="font-variant-numeric: tabular-nums;">
                                                {{ formatCurrency(item.price) }}
                                            </span>
                                        </div>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </div>

                        <!-- Resumen de seleccionados -->
                        <v-sheet class="pa-4 mt-4 rounded-lg border" color="grey-lighten-5">
                            <div class="d-flex justify-space-between align-center">
                                <div class="d-flex align-center ga-2">
                                    <v-icon color="primary">mdi-check-circle-outline</v-icon>
                                    <span class="text-body-2">
                                        <strong>{{ selectedIds.length }}</strong>
                                        {{ selectedIds.length === 1 ? 'invitado seleccionado' : 'invitados seleccionados' }}
                                    </span>
                                </div>
                                <div class="text-right">
                                    <div class="text-caption text-medium-emphasis text-uppercase">Total a cobrar</div>
                                    <div class="text-h5 font-weight-bold text-primary" style="font-variant-numeric: tabular-nums;">
                                        {{ formatCurrency(totalSeleccionado) }}
                                    </div>
                                </div>
                            </div>
                        </v-sheet>

                        <!-- Datos del cobro -->
                        <div v-if="selectedIds.length > 0" class="mt-5">
                            <div class="d-flex align-center ga-2 mb-3">
                                <v-icon color="primary" size="20">mdi-cash-register</v-icon>
                                <span class="text-subtitle-1 font-weight-bold">Datos del cobro</span>
                            </div>
                            <!-- <pre>{{ selectedPaymentMethod }}</pre> -->
                            <v-sheet class="pa-4 rounded-lg border" color="grey-lighten-5">
                                <v-row dense>
                                    <!-- Método de pago -->
                                    <v-col cols="12" md="6">
                                        <v-select
                                            v-model="paymentForm.payment_method_id"
                                            :items="clubPaymentMethods.map(m => ({ title: m.name, value: m.id }))"
                                            label="Método de pago *"
                                            prepend-inner-icon="mdi-credit-card-outline"
                                            :rules="paymentMethodRules"
                                            :error-messages="paymentForm.errors.payment_method_id"
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <!-- Fecha y hora -->
                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="paymentForm.paid_at"
                                            label="Fecha y hora *"
                                            type="datetime-local"
                                            :rules="paidAtRules"
                                            :error-messages="paymentForm.errors.paid_at"
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <!-- Banco (solo si el método lo requiere) -->
                                    <v-col v-if="selectedPaymentMethod?.requires_bank_name" cols="12" md="6">
                                        <v-text-field
                                            v-model="paymentForm.bank_name"
                                            label="Banco *"
                                            prepend-inner-icon="mdi-bank-outline"
                                            :error-messages="paymentForm.errors.bank_name"
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <!-- Referencia (solo si el método lo requiere) -->
                                    <v-col v-if="selectedPaymentMethod?.requires_reference" cols="12" md="6">
                                        <v-text-field
                                            v-model="paymentForm.reference"
                                            label="Referencia *"
                                            prepend-inner-icon="mdi-pound"
                                            :error-messages="paymentForm.errors.reference"
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <!-- Número de cheque (solo si el método lo requiere) -->
                                    <v-col v-if="selectedPaymentMethod?.requires_check_number" cols="12" md="6">
                                        <v-text-field
                                            v-model="paymentForm.check_number"
                                            label="Número de cheque *"
                                            prepend-inner-icon="mdi-checkbook"
                                            :error-messages="paymentForm.errors.check_number"
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <!-- Notas -->
                                    <v-col cols="12">
                                        <v-textarea
                                            v-model="paymentForm.notes"
                                            label="Notas (opcional)"
                                            rows="3"
                                            auto-grow
                                            prepend-inner-icon="mdi-note-text-outline"
                                            :rules="notesRules"
                                            :error-messages="paymentForm.errors.notes"
                                            hide-details="auto"
                                        />
                                    </v-col>
                                </v-row>
                            </v-sheet>
                        </div>
                    </v-form>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        text="Cerrar" variant="tonal" :icon-only="false"
                        action="cancel" @click="Cerrar()" />
                    <BaseButton
                        text="Cobrar seleccionados" :icon-only="false"
                        action="save"
                        @click="abrirCobro()" />
                </v-card-actions>
            </v-card>
        </v-dialog>

    </AppLayout>
</template>
