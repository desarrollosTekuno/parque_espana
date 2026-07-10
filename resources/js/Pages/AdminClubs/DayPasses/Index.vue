<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce } from 'lodash';
import { formatCurrency } from '@/constants/formatCurrency';
import BaseButton from "@/Components/BaseButton.vue";
import { customToastSwal } from '@/utils/swal';
import { nowAsLocalInput } from "@/constants/formatDates";
import { required, selectRequired } from "@/constants/validationRules";
import axios from 'axios';

const page = usePage();
const can = usePage().props.auth.permissions;

interface Props {
    dayPasses?: any;
    clubPaymentMethods?: any;
    guestListVariables?: Record<string, number>;
}

const props = withDefaults(defineProps<Props>(), {
    dayPasses: null,
    clubPaymentMethods: null,
    guestListVariables: () => ({}),
});
const today = new Date().toISOString().split("T")[0];

// Tabla 
const headers = [
    { title: "ID",         key: "id" },
    { title: "Usuario",      key: "member.full_name" },
    { title: "Fecha",      key: "date" },
    { title: "Visitantes", key: "total_visitors" },
    { title: "Total",      key: "amount" },
    { title: "Método",     key: "payment_method.name" },
    { title: "Ticket",     key: "ticket_sent_at", sortable: false },
    { title: "",           key: "actions",        sortable: false, align: "end" },
];

const items   = ref([]);
const total   = ref(0);
const loading = ref(false);
const search  = ref("");
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [{ key: "id", order: "desc" }] });
const prefix  = "dayPasses";

const fetchItems = () => {
    loading.value = true;
    const params = {
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]:       options.value.page,
        [`${prefix}_per_page`]:   options.value.itemsPerPage,
        [`${prefix}_search`]:     search.value,
        [`${prefix}_sort`]:       options.value.sortBy?.[0]?.key  ?? "id",
        [`${prefix}_order`]:      options.value.sortBy?.[0]?.order ?? "desc",
    };
    router.get(route("day-passes.index"), params, {
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

// Modal para registrar pase

const showCreateModal = ref(false);

interface VisitorRow {
    first_name: string;
    last_name:  string;
    phone:      string;
    age:        number | null;
}

interface CreateForm {
    member_id:         number | null;
    date:              string;
    visitors:          VisitorRow[];
    payment_method_id: number | null;
    paid_at:           string;
    reference:         string;
    bank_name:         string;
    check_number:      string;
    notes:             string;
}

const createForm = useForm<CreateForm>({
    member_id:         null,
    date:              new Date().toISOString().slice(0, 10),
    visitors:          [emptyVisitor()],
    payment_method_id: null,
    paid_at:           nowAsLocalInput(),
    reference:         "",
    bank_name:         "",
    check_number:      "",
    notes:             "",
});

function emptyVisitor(): VisitorRow {
    return { first_name: "", last_name: "", phone: "", age: null };
}

// Estado de incidentes por visitante — separado del form para garantizar reactividad
const incidentState = ref<Array<{ incidents: any[]; checking: boolean }>>([
    { incidents: [], checking: false },
]);

// Búsqueda de usuarios
const memberSearch   = ref("");
const memberOptions  = ref<{ id: number; full_name: string; email: string }[]>([]);
const memberLoading  = ref(false);
const selectedMember = ref<{ id: number; full_name: string; email: string } | null>(null);

const doMemberSearch = debounce(async (q: string) => {
    if (!q || q.length < 2) return;
    memberLoading.value = true;
    try {
        const { data } = await axios.get(route("day-passes.members-search"), { params: { q } });
        memberOptions.value = data;
    } finally {
        memberLoading.value = false;
    }
}, 350);

watch(memberSearch, (value) => {
    if (createForm.member_id) return;
    doMemberSearch(value);
});

watch(() => createForm.member_id, (id) => {
    const found = memberOptions.value.find(m => m.id === id) ?? null;
    selectedMember.value = found;
    if (found) memberSearch.value = found.full_name;
});

// Variables del club
const maxGuests   = computed(() => props.guestListVariables?.MAX_GUESTS   ?? 0);
const normalPrice = computed(() => props.guestListVariables?.NORMAL_PRICE  ?? 0);
const specialPrice = computed(() => props.guestListVariables?.SPECIAL_PRICE ?? 0);
const minAge     = computed(() => props.guestListVariables?.MIN_AGE      ?? 0);
const maxAge     = computed(() => props.guestListVariables?.MAX_AGE      ?? 0);
console.log('minAge: ', minAge.value);
console.log('maxAge: ', maxAge.value);

/*const priceForAge = (age: number | null) => {
    if (age === null) return 0;
    return age >= 7 ? normalPrice.value : specialPrice.value;
};

const receptionTotal = computed(() =>
    createForm.visitors.reduce(
        (sum, v) => sum + (v.age >= minAge ? priceForAge(v.age) : 0),
        0
    )
);*/

const priceForAge = (age: number | null) => {
    if (age == null) return 0;

    if (age >= minAge.value && age <= maxAge.value) {
        return specialPrice.value;
    }

    if (age > maxAge.value) {
        return normalPrice.value;
    }
    return 0;
};
const receptionTotal = computed(() =>
    createForm.visitors.reduce(
        (sum, visitor) => sum + priceForAge(visitor.age),
        0
    )
);
// Método de pago seleccionado
interface PaymentMethodItem {
    id: number; code: string; name: string;
    requires_reference: boolean; requires_bank_name: boolean;
    requires_check_number: boolean; affects_cash_cut: boolean;
}

const selectedPaymentMethod = computed(() =>
    (props.clubPaymentMethods ?? []).find(
        (m: PaymentMethodItem) => m.id === createForm.payment_method_id
    ) ?? null
);

// Gestión de visitantes
const addVisitor = () => {
    if (createForm.visitors.length >= maxGuests.value) return;
    createForm.visitors.push(emptyVisitor());
    incidentState.value.push({ incidents: [], checking: false });
};

const removeVisitor = (i: number) => {
    if (createForm.visitors.length > 1) {
        createForm.visitors.splice(i, 1);
        incidentState.value.splice(i, 1);
    }
};

// Verificación de incidentes por teléfono
const doCheckIncidents = debounce(async (visitor: VisitorRow, index: number) => {
    console.log("entre aquí");
    const state = incidentState.value[index];
    if (!state) return;
    state.checking = true;
    try {
        const { data } = await axios.get(
            route("day-passes.check-incidents"),
            {
                params: {
                    first_name: visitor.first_name,
                    last_name: visitor.last_name,
                }
            }
        );
        state.incidents = data.incidents ?? [];
    } finally {
        state.checking = false;
    }
}, 500);

/*const onPhoneInput = (phone: string, index: number) => {
    doCheckIncidents(phone, index);
};*/
const onVisitorChange = (visitor: VisitorRow, index: number) => {
    if (!visitor.first_name || !visitor.last_name) return;
    doCheckIncidents(visitor, index);
};
// Conteo de visitantes con incidentes
const visitorsWithIncidents = computed(() =>
    incidentState.value.filter(s => s.incidents.length > 0).length
);

// Abrir / cerrar
const openCreateModal = () => {
    createForm.reset();
    createForm.visitors  = [emptyVisitor()];
    createForm.date      = new Date().toISOString().slice(0, 10);
    createForm.paid_at   = nowAsLocalInput();
    incidentState.value  = [{ incidents: [], checking: false }];
    memberSearch.value   = "";
    memberOptions.value  = [];
    selectedMember.value = null;
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    createForm.reset();
    createForm.clearErrors();
    incidentState.value  = [{ incidents: [], checking: false }];
    memberSearch.value   = "";
    memberOptions.value  = [];
    selectedMember.value = null;
};

const submitDayPass = () => {
    console.log('Formulario enviado:', createForm.data());
    createForm.post(route("day-passes.store"), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({ title: "Pase registrado. Ticket enviado al usuario.", icon: "success" });
            closeCreateModal();
            fetchItems();
        },
        onError: (errors) => {
            const message = errors.messageError
                ?? Object.values(errors).flat().join(" ")
                ?? "Error al registrar el pase.";
            customToastSwal({ title: message, icon: "error" });
        },
    });
};

const canSubmit = computed(() =>
    !!createForm.member_id &&
    !!createForm.date &&
    !!createForm.payment_method_id &&
    createForm.visitors.length > 0 &&
    createForm.visitors.every(v => v.first_name && v.last_name && v.phone && v.age !== null) &&
    receptionTotal.value > 0
);

// Modal para ver detalles del pase

const showDetailModal = ref(false);
const selectedPass    = ref<any>(null);

const openDetailModal = (pass: any) => {
    selectedPass.value    = pass;
    showDetailModal.value = true;

    console.log('PASS: ', pass);
};

const formatDate = (date: string) => {
    return date?.split('T')[0].split('-').reverse().join('/') ?? '';
};

// Incidentes

const showIncidentModal = ref(false);
const selectedVisitor   = ref<any>(null);

const incidentForm = useForm({
    day_pass_visitor_id: null as number | null,
    incident_type:       "",
    description:         "",
    charged_amount:      "" as string | number,
});

const openIncidentModal = (visitor: any) => {
    selectedVisitor.value            = visitor;
    incidentForm.day_pass_visitor_id = visitor.id;
    incidentForm.incident_type       = "";
    incidentForm.description         = "";
    incidentForm.charged_amount      = "";
    incidentForm.clearErrors();
    showIncidentModal.value = true;
};

const closeIncidentModal = () => {
    showIncidentModal.value = false;
    selectedVisitor.value   = null;
    incidentForm.reset();
    incidentForm.clearErrors();
};

const submitIncident = () => {
    incidentForm.post(route("day-passes.incidents.store"), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({ title: "Incidente registrado correctamente.", icon: "success" });
            closeIncidentModal();
        },
        onError: (errors) => {
            const message = errors.messageError
                ?? Object.values(errors).flat().join(" ")
                ?? "Error al registrar el incidente.";
            customToastSwal({ title: message, icon: "error" });
        },
    });
};
</script>

<template>
    <Head title="Pase por Día" />
    <AppLayout>
        <template #header>Pase por Día</template>

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
                        no-data-text="No hay pases registrados"
                    >
                        <template #top>
                            <div class="d-flex align-center ga-3 mx-4 mt-2 mb-1">
                                <v-text-field
                                    v-model="search"
                                    label="Buscar por usuario"
                                    clearable
                                    hide-details
                                    class="flex-grow-1"
                                />
                                <BaseButton
                                    v-if="can.includes('day-passes.store')"
                                    action="create"
                                    text="Registrar Pase"
                                    icon="mdi-ticket-account"
                                    :icon-only="false"
                                    @click="openCreateModal"
                                />
                            </div>
                        </template>

                        <template #item.amount="{ item }">
                            {{ formatCurrency(item.amount) }}
                        </template>

                        <template #item.date="{ item }">
                            {{ formatDate(item.date) }}
                        </template>

                        <template #item.ticket_sent_at="{ item }">
                            <v-chip
                                size="small"
                                :color="item.ticket_sent_at ? 'success' : 'warning'"
                                variant="flat"
                            >
                                {{ item.ticket_sent_at ? 'Enviado' : 'Pendiente' }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton action="view" @click="openDetailModal(item)" />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <!-- Modal: Registrar Pase -->
        <v-dialog v-model="showCreateModal" max-width="900" persistent scrollable>
            <v-card class="rounded-xl">
                <v-card-title class="d-flex align-center ga-2 pa-4 border-b">
                    <v-icon color="primary">mdi-ticket-account</v-icon>
                    <span class="text-h6 font-weight-bold">Registrar Pase por Día</span>
                </v-card-title>

                <v-card-text class="pa-4">

                    <!-- Tarifas vigentes -->
                    <v-sheet class="pa-3 mb-5 rounded-lg border" color="blue-lighten-5">
                        <div class="d-flex align-center ga-2 mb-2">
                            <v-icon color="blue-darken-2" size="18">mdi-information-outline</v-icon>
                            <span class="text-caption font-weight-bold text-blue-darken-2 text-uppercase">
                                Tarifas vigentes del club
                            </span>
                        </div>
                        <div class="d-flex flex-wrap ga-6">
                            <div>
                                <div class="text-caption text-medium-emphasis">Máx. visitantes</div>
                                <div class="text-body-1 font-weight-bold">{{ maxGuests }}</div>
                            </div>
                            <div>
                                <div class="text-caption text-medium-emphasis">Adultos ({{ maxAge }}+ años)</div>
                                <div class="text-body-1 font-weight-bold text-primary">{{ formatCurrency(normalPrice) }}</div>
                            </div>
                            <div>
                                <div class="text-caption text-medium-emphasis">Menores ({{ minAge }}-{{ maxAge }} años)</div>
                                <div class="text-body-1 font-weight-bold text-primary">{{ formatCurrency(specialPrice) }}</div>
                            </div>
                        </div>
                    </v-sheet>

                    <!-- usuario responsable -->
                    <div class="d-flex align-center ga-2 mb-3">
                        <v-icon color="primary" size="18">mdi-account-circle-outline</v-icon>
                        <span class="text-subtitle-2 font-weight-bold text-uppercase text-medium-emphasis">Usuario responsable</span>
                    </div>

                    <v-row dense class="mb-5">
                        <v-col cols="12" md="8">
                            <v-autocomplete
                                v-model="createForm.member_id"
                                v-model:search="memberSearch"
                                :items="memberOptions"
                                item-title="full_name"
                                item-value="id"
                                label="Buscar usuario *"
                                prepend-inner-icon="mdi-account-search-outline"
                                :loading="memberLoading"
                                no-data-text="Escribe al menos 2 caracteres"
                                hide-no-data
                                clearable
                                hide-details="auto"
                                :error-messages="createForm.errors.member_id"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="createForm.date"
                                label="Fecha del pase *"
                                type="date"
                                prepend-inner-icon="mdi-calendar"
                                hide-details="auto"
                                :error-messages="createForm.errors.date"
                                :min="today"
                            />
                        </v-col>
                        <v-col v-if="selectedMember?.email" cols="12">
                            <v-sheet class="pa-2 rounded border d-flex align-center ga-2" color="grey-lighten-5">
                                <v-icon size="16" color="primary">mdi-email-outline</v-icon>
                                <span class="text-body-2 text-medium-emphasis">
                                    El ticket se enviará a:
                                    <strong class="text-primary">{{ selectedMember.email }}</strong>
                                </span>
                            </v-sheet>
                        </v-col>
                    </v-row>

                    <!-- Visitantes -->
                    <div class="d-flex align-center justify-space-between mb-3">
                        <div class="d-flex align-center ga-2">
                            <v-icon color="primary" size="18">mdi-account-group-outline</v-icon>
                            <span class="text-subtitle-2 font-weight-bold text-uppercase text-medium-emphasis">
                                Visitantes
                            </span>
                            <v-chip size="x-small" color="primary" variant="tonal">
                                {{ createForm.visitors.length }} / {{ maxGuests }}
                            </v-chip>
                            <v-chip
                                v-if="visitorsWithIncidents > 0"
                                size="x-small"
                                color="error"
                                variant="flat"
                                prepend-icon="mdi-alert"
                            >
                                {{ visitorsWithIncidents }} con incidentes
                            </v-chip>
                        </div>
                        <v-btn
                            size="small"
                            variant="tonal"
                            color="primary"
                            prepend-icon="mdi-plus"
                            :disabled="createForm.visitors.length >= maxGuests"
                            @click="addVisitor"
                        >
                            Agregar visitante
                        </v-btn>
                    </div>

                    <div class="mb-4">
                        <div
                            v-for="(visitor, index) in createForm.visitors"
                            :key="index"
                            class="mb-3"
                        >
                            <!-- Alerta de incidentes -->
                            <v-alert
                                v-if="(incidentState[index]?.incidents.length ?? 0) > 0"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                class="mb-2"
                                icon="mdi-alert-circle"
                            >
                                <div class="font-weight-bold mb-1">
                                    Este visitante tiene {{ incidentState[index].incidents.length }}
                                    {{ incidentState[index].incidents.length === 1 ? 'incidente previo' : 'incidentes previos' }}
                                    en el club.
                                </div>
                                <div
                                    v-for="inc in incidentState[index].incidents"
                                    :key="inc.id"
                                    class="text-body-2"
                                >
                                    · {{ inc.incident_type }}: {{ inc.description }}
                                    <span v-if="inc.charged_amount" class="font-weight-bold">
                                        — Multa: {{ formatCurrency(inc.charged_amount) }}
                                    </span>
                                </div>
                            </v-alert>

                            <v-row dense align="center">
                                <v-col cols="12" sm="3">
                                    <v-text-field
                                        v-model="visitor.first_name"
                                        :label="`Nombre ${index + 1} *`"
                                        density="compact"
                                        hide-details="auto"
                                        :error-messages="createForm.errors[`visitors.${index}.first_name`]"
                                    />
                                </v-col>
                                <v-col cols="12" sm="3">
                                    <v-text-field
                                        v-model="visitor.last_name"
                                        :label="`Apellidos ${index + 1} *`"
                                        density="compact"
                                        hide-details="auto"
                                        :error-messages="createForm.errors[`visitors.${index}.last_name`]"
                                        :loading="incidentState[index]?.checking"
                                        :append-inner-icon="(incidentState[index]?.incidents.length ?? 0) > 0 ? 'mdi-alert-circle' : undefined"
                                        :base-color="(incidentState[index]?.incidents.length ?? 0) > 0 ? 'warning' : undefined"
                                        @blur="onVisitorChange(visitor, index)"
                                    />
                                </v-col>
                                <v-col cols="12" sm="3">
                                    <v-text-field
                                        v-model="visitor.phone"
                                        :label="`Teléfono ${index + 1} *`"
                                        density="compact"
                                        hide-details="auto"
                                        :error-messages="createForm.errors[`visitors.${index}.phone`]"
                                        @input="visitor.phone = visitor.phone?.replace(/\D/g, '').slice(0, 10)"
                                    />
                                </v-col>
                                <v-col cols="5" sm="2">
                                    <v-text-field
                                        v-model.number="visitor.age"
                                        label="Edad *"
                                        type="number"
                                        min="0"
                                        max="120"
                                        density="compact"
                                        hide-details="auto"
                                        :error-messages="createForm.errors[`visitors.${index}.age`]"
                                        @input="visitor.age = Math.min(Number(visitor.age?.toString().replace(/\D/g, '') || 0), 120)"
                                    />
                                </v-col>
                                <v-col cols="5" sm="1" class="text-center">
                                    <span class="text-body-2 font-weight-medium text-primary" style="font-variant-numeric: tabular-nums;">
                                        {{ formatCurrency(priceForAge(visitor.age)) }}
                                    </span>
                                </v-col>
                                <v-col cols="2" sm="auto" class="d-flex justify-center">
                                    <v-btn
                                        icon="mdi-close"
                                        size="x-small"
                                        variant="text"
                                        color="error"
                                        :disabled="createForm.visitors.length === 1"
                                        @click="removeVisitor(index)"
                                    />
                                </v-col>
                            </v-row>
                        </div>
                    </div>

                    <!-- Total -->
                    <v-sheet class="pa-3 mb-5 rounded-lg border" color="grey-lighten-5">
                        <div class="d-flex justify-space-between align-center">
                            <span class="text-body-2 text-medium-emphasis">
                                {{ createForm.visitors.length }}
                                {{ createForm.visitors.length === 1 ? 'visitante' : 'visitantes' }}
                            </span>
                            <div class="text-right">
                                <div class="text-caption text-medium-emphasis text-uppercase">Total a cobrar</div>
                                <div class="text-h6 font-weight-bold text-primary" style="font-variant-numeric: tabular-nums;">
                                    {{ formatCurrency(receptionTotal) }}
                                </div>
                            </div>
                        </div>
                    </v-sheet>

                    <!-- Pago -->
                    <div class="d-flex align-center ga-2 mb-3">
                        <v-icon color="primary" size="18">mdi-cash-register</v-icon>
                        <span class="text-subtitle-2 font-weight-bold text-uppercase text-medium-emphasis">Datos del pago</span>
                    </div>

                    <v-sheet class="pa-4 rounded-lg border" color="grey-lighten-5">
                        <v-row dense>
                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="createForm.payment_method_id"
                                    :items="(clubPaymentMethods ?? []).map(m => ({ title: m.name, value: m.id }))"
                                    label="Método de pago *"
                                    prepend-inner-icon="mdi-credit-card-outline"
                                    :rules="[selectRequired]"
                                    hide-details="auto"
                                    :error-messages="createForm.errors.payment_method_id"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="createForm.paid_at"
                                    label="Fecha y hora de pago *"
                                    type="datetime-local"
                                    prepend-inner-icon="mdi-clock-check-outline"
                                    :rules="[required]"
                                    hide-details="auto"
                                    :error-messages="createForm.errors.paid_at"
                                />
                            </v-col>
                            <v-col v-if="selectedPaymentMethod?.requires_bank_name" cols="12" md="6">
                                <v-text-field
                                    v-model="createForm.bank_name"
                                    label="Banco *"
                                    prepend-inner-icon="mdi-bank-outline"
                                    hide-details="auto"
                                    :error-messages="createForm.errors.bank_name"
                                />
                            </v-col>
                            <v-col v-if="selectedPaymentMethod?.requires_reference" cols="12" md="6">
                                <v-text-field
                                    v-model="createForm.reference"
                                    label="Referencia *"
                                    prepend-inner-icon="mdi-pound"
                                    hide-details="auto"
                                    :error-messages="createForm.errors.reference"
                                />
                            </v-col>
                            <v-col v-if="selectedPaymentMethod?.requires_check_number" cols="12" md="6">
                                <v-text-field
                                    v-model="createForm.check_number"
                                    label="Número de cheque *"
                                    prepend-inner-icon="mdi-checkbook"
                                    hide-details="auto"
                                    :error-messages="createForm.errors.check_number"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-textarea
                                    v-model="createForm.notes"
                                    label="Notas (opcional)"
                                    prepend-inner-icon="mdi-note-text-outline"
                                    rows="2"
                                    auto-grow
                                    maxlength="500"
                                    hide-details="auto"
                                    :error-messages="createForm.errors.notes"
                                />
                            </v-col>
                        </v-row>
                    </v-sheet>

                </v-card-text>

                <v-card-actions class="pa-4 border-t">
                    <v-spacer />
                    <BaseButton
                        text="Cancelar"
                        variant="tonal"
                        :icon-only="false"
                        action="cancel"
                        @click="closeCreateModal"
                    />
                    <BaseButton
                        text="Registrar y cobrar"
                        :icon-only="false"
                        action="save"
                        :loading="createForm.processing"
                        :disabled="!canSubmit"
                        @click="submitDayPass"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Modal: Detalle del pase -->
        <v-dialog v-model="showDetailModal" max-width="600">
            <v-card class="rounded-xl" v-if="selectedPass">
                <v-card-title class="d-flex align-center ga-2 pa-4 border-b">
                    <v-icon color="primary">mdi-ticket-account</v-icon>
                    <span class="text-h6 font-weight-bold">Detalle del Pase</span>
                </v-card-title>

                <v-card-text class="pa-4">
                    <v-sheet class="pa-3 mb-4 rounded-lg border" color="grey-lighten-5">
                        <div class="d-flex flex-wrap ga-4">
                            <div>
                                <div class="text-caption text-medium-emphasis">usuario</div>
                                <div class="text-body-2 font-weight-medium">
                                    {{ selectedPass.member?.first_name }} {{ selectedPass.member?.last_name }}
                                </div>
                            </div>
                            <div>
                                <div class="text-caption text-medium-emphasis">Fecha</div>
                                <div class="text-body-2 font-weight-medium">{{ formatDate(selectedPass.date) }}</div>
                            </div>
                            <div>
                                <div class="text-caption text-medium-emphasis">Total cobrado</div>
                                <div class="text-body-2 font-weight-bold text-primary">{{ formatCurrency(selectedPass.amount) }}</div>
                            </div>
                        </div>
                    </v-sheet>

                    <div class="text-subtitle-2 font-weight-bold mb-2 text-uppercase text-medium-emphasis">Visitantes</div>
                    <v-list density="compact" class="rounded-lg border">
                        <v-list-item
                            v-for="v in selectedPass.visitors"
                            :key="v.id"
                        >
                            <v-list-item-title class="font-weight-medium">
                                {{ v.first_name }} {{ v.last_name }}
                            </v-list-item-title>
                            <v-list-item-subtitle>
                                Edad: {{ v.age }} · Tel: {{ v.phone }}
                            </v-list-item-subtitle>
                            <template #append>
                                <div class="d-flex align-center ga-3">
                                    <span class="text-body-2 font-weight-medium text-primary">
                                        {{ formatCurrency(v.price) }}
                                    </span>
                                    <v-btn
                                        v-if="can.includes('day-passes.incidents.store')"
                                        size="x-small"
                                        variant="tonal"
                                        color="error"
                                        prepend-icon="mdi-alert-plus-outline"
                                        @click="openIncidentModal(v)"
                                    >
                                        Incidente
                                    </v-btn>
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card-text>

                <v-card-actions class="pa-4 border-t">
                    <v-spacer />
                    <BaseButton
                        text="Cerrar"
                        variant="tonal"
                        :icon-only="false"
                        action="cancel"
                        @click="showDetailModal = false"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Modal: Registrar Incidente -->
        <v-dialog v-model="showIncidentModal" max-width="520" persistent>
            <v-card class="rounded-xl">
                <v-card-title class="d-flex align-center ga-2 pa-4 border-b">
                    <v-icon color="error">mdi-alert-plus-outline</v-icon>
                    <span class="text-h6 font-weight-bold">Registrar Incidente</span>
                </v-card-title>

                <v-card-text class="pa-4">
                    <!-- Visitante involucrado -->
                    <v-sheet class="pa-3 mb-5 rounded-lg border" color="red-lighten-5">
                        <div class="d-flex align-center ga-2 mb-1">
                            <v-icon color="error" size="16">mdi-account-outline</v-icon>
                            <span class="text-caption font-weight-bold text-error text-uppercase">
                                Visitante involucrado
                            </span>
                        </div>
                        <div class="text-body-2 font-weight-medium">
                            {{ selectedVisitor?.first_name }} {{ selectedVisitor?.last_name }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            Tel: {{ selectedVisitor?.phone }}
                        </div>
                    </v-sheet>

                    <v-row dense>
                        <v-col cols="12">
                            <v-text-field
                                v-model="incidentForm.incident_type"
                                label="Tipo de incidente *"
                                prepend-inner-icon="mdi-tag-outline"
                                placeholder="Ej. Comportamiento inadecuado, daño a instalaciones…"
                                maxlength="200"
                                counter="200"
                                hide-details="auto"
                                :error-messages="incidentForm.errors.incident_type"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-textarea
                                v-model="incidentForm.description"
                                label="Descripción del incidente *"
                                prepend-inner-icon="mdi-text-box-outline"
                                rows="3"
                                auto-grow
                                maxlength="2000"
                                counter="2000"
                                hide-details="auto"
                                :error-messages="incidentForm.errors.description"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-text-field
                                v-model="incidentForm.charged_amount"
                                label="Monto cargado (opcional)"
                                prepend-inner-icon="mdi-currency-usd"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                hide-details="auto"
                                :error-messages="incidentForm.errors.charged_amount"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="pa-4 border-t">
                    <v-spacer />
                    <BaseButton
                        text="Cancelar"
                        variant="tonal"
                        :icon-only="false"
                        action="cancel"
                        @click="closeIncidentModal"
                    />
                    <v-btn
                        color="error"
                        variant="flat"
                        prepend-icon="mdi-alert-plus-outline"
                        :loading="incidentForm.processing"
                        :disabled="!incidentForm.incident_type || !incidentForm.description"
                        @click="submitIncident"
                    >
                        Registrar incidente
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

    </AppLayout>
</template>
