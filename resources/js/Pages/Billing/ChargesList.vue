<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";
import { customToastSwal } from "@/utils/swal";
import {
    optionalLength,
    required,
    selectRequired,
} from "@/constants/validationRules";
import { nowAsLocalInput } from "@/constants/formatDates";

interface ClubOption {
    id: number;
    code: string;
    name: string;
}

interface ConceptOption {
    id: number;
    code: string;
    name: string;
}

interface PaymentMethodItem {
    id: number;
    code: string;
    name: string;
    requires_reference: boolean;
    requires_bank_name: boolean;
    requires_check_number: boolean;
    affects_cash_cut: boolean;
    show_in_billing: boolean;
}

interface ClubPaymentMethodItem {
    id: number;
    code: string;
    name: string;
    payment_methods: PaymentMethodItem[];
}

interface ChargeBadge {
    label: string;
    color: string;
}

interface ChargeRow {
    id: number;
    membership_number: string | null;
    internal_account_number: string | null;
    holder_name: string;
    membership_account_id: number | null;
    concept_name: string | null;
    concept_code: string | null;
    description: string | null;
    amount: number;
    balance: number;
    due_date: string | null;
    status: string;
    allows_partial_payments: boolean;
    club_id: number | null;
    club_code: string | null;
    club_name: string | null;
    membership_type_name: string | null;
    origin_code: string;
    origin_label: string;
    badges: ChargeBadge[];
    target_monthly_fee: number | null;
    monthly_fee_total: number | null;
    monthly_fee_share: number | null;
    effective_monthly_fee: number | null;
}

interface PaginatedCharges {
    data: ChargeRow[];
    total: number;
}

interface BillingSummary {
    total_outstanding: number;
    overdue_outstanding: number;
    monthly_outstanding: number;
    inscription_outstanding: number;
    accounts_with_balance: number;
}

interface Filters {
    search: string | null;
    club_id: number | null;
    concept_code: string | null;
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
    applications: Array<{ charge_id: number; amount: number }>;
}

interface Props {
    charges?: PaginatedCharges;
    summary?: BillingSummary;
    clubOptions?: ClubOption[];
    conceptOptions?: ConceptOption[];
    clubPaymentMethods?: ClubPaymentMethodItem[];
    filters?: Filters;
}

const props = withDefaults(defineProps<Props>(), {
    charges: () => ({ data: [], total: 0 }),
    summary: () => ({
        total_outstanding: 0,
        overdue_outstanding: 0,
        monthly_outstanding: 0,
        inscription_outstanding: 0,
        accounts_with_balance: 0,
    }),
    clubOptions: () => [],
    conceptOptions: () => [],
    clubPaymentMethods: () => [],
    filters: () => ({ search: null, club_id: null, concept_code: null }),
});

const prefix = "charges";

const items = ref<ChargeRow[]>(props.charges.data ?? []);
const total = ref(props.charges.total ?? 0);
const summary = ref<BillingSummary>({ ...props.summary });
const clubPaymentMethods = ref<ClubPaymentMethodItem[]>(props.clubPaymentMethods ?? []);
const loading = ref(false);
const search = ref(props.filters.search ?? "");
const selectedClubId = ref<number | null>(props.filters.club_id ?? null);
const selectedConceptCode = ref<string | null>(props.filters.concept_code ?? null);
const options = ref({ page: 1, itemsPerPage: 15 });

const showPaymentModal = ref(false);
const selectedCharge = ref<ChargeRow | null>(null);
const paymentAmount = ref("0.00");
const paymentFormRef = ref();

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

const headers = [
    { title: "No. Cuenta", key: "membership_number", sortable: false },
    { title: "Titular", key: "holder_name", sortable: false },
    { title: "Parque", key: "club_name", sortable: false },
    { title: "Concepto", key: "concept_name", sortable: false },
    { title: "Descripción", key: "description", sortable: false },
    { title: "Vencimiento", key: "due_date", sortable: false },
    { title: "Estado", key: "status", sortable: false },
    { title: "Saldo", key: "balance", sortable: false, align: "end" as const },
    { title: "Acciones", key: "actions", sortable: false, align: "center" as const },
];

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const dateFormatter = new Intl.DateTimeFormat("es-MX", { dateStyle: "medium" });

const formatCurrency = (value: number | null | undefined) =>
    currencyFormatter.format(Number(value ?? 0));

const formatDate = (value: string | null) => {
    if (!value) return "Sin fecha";
    return dateFormatter.format(new Date(`${value}T00:00:00`));
};

const resolveDueState = (value: string | null) => {
    if (!value) return { label: "Sin fecha", color: "default" };

    const dueDate = new Date(`${value}T00:00:00`);
    const today = new Date();
    const normalizedToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());

    if (dueDate < normalizedToday) return { label: "Vencido", color: "error" };
    if (dueDate.getTime() === normalizedToday.getTime()) return { label: "Vence hoy", color: "warning" };
    return { label: "Por vencer", color: "info" };
};

const chargeStatusLabel = (status: string) =>
    ({ pending: "Pendiente", partial: "Parcial" }[status] ?? status);

const chargeStatusColor = (status: string) =>
    ({ pending: "warning", partial: "info" }[status] ?? "default");

const conceptColor = (code: string | null) =>
    ({ MONTHLY_FEE: "primary", INSCRIPTION: "secondary", BUSINESS_AD: "orange" }[code ?? ""] ?? "default");

const clubFilterItems = computed(() =>
    props.clubOptions.map((c) => ({ title: `${c.code} – ${c.name}`, value: c.id }))
);

const conceptFilterItems = computed(() =>
    props.conceptOptions.map((c) => ({ title: c.name, value: c.code }))
);

const hasActiveFilters = computed(
    () => Boolean(search.value) || selectedClubId.value !== null || selectedConceptCode.value !== null
);

const emptyMessage = computed(() =>
    hasActiveFilters.value
        ? "No se encontraron cargos para ese criterio"
        : "No hay cargos pendientes para mostrar"
);

// ── Payment modal ─────────────────────────────────────────────────────────────

const selectedPaymentMethods = computed(() => {
    if (selectedCharge.value?.club_id == null) return [];
    return (
        clubPaymentMethods.value.find((c) => c.id === selectedCharge.value!.club_id)?.payment_methods ?? []
    );
});

const selectedPaymentMethod = computed(() =>
    selectedPaymentMethods.value.find((m) => m.id === paymentForm.payment_method_id) ?? null
);

const paymentMethodRules = [selectRequired];
const paidAtRules = [required];
const notesRules = [optionalLength(0, 1000)];

const referenceRules = [
    (value: string) => (!selectedPaymentMethod.value?.requires_reference ? true : required(value)),
    optionalLength(0, 255),
];

const bankNameRules = [
    (value: string) => (!selectedPaymentMethod.value?.requires_bank_name ? true : required(value)),
    optionalLength(0, 255),
];

const checkNumberRules = [
    (value: string) => (!selectedPaymentMethod.value?.requires_check_number ? true : required(value)),
    optionalLength(0, 255),
];

const paymentAmountRules = computed(() => [
    (value: string | number) => Number(value || 0) > 0 || "Captura un importe mayor a cero",
    (value: string | number) =>
        Number(value || 0) <= (selectedCharge.value?.balance ?? 0) ||
        "El importe no puede exceder el saldo pendiente",
    (value: string | number) => {
        if (selectedCharge.value?.allows_partial_payments) return true;
        return (
            Number(Number(value || 0).toFixed(2)) ===
                Number((selectedCharge.value?.balance ?? 0).toFixed(2)) ||
            "Este cargo debe liquidarse completo"
        );
    },
]);

const openPaymentModal = (charge: ChargeRow) => {
    selectedCharge.value = charge;
    paymentAmount.value = charge.balance.toFixed(2);
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentFormRef.value?.resetValidation?.();
    paymentForm.membership_account_id = charge.membership_account_id;
    paymentForm.club_id = charge.club_id;
    paymentForm.paid_at = nowAsLocalInput();
    showPaymentModal.value = true;
};

const closePaymentModal = () => {
    showPaymentModal.value = false;
    selectedCharge.value = null;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentFormRef.value?.resetValidation?.();
};

const submitPayment = async () => {
    if (!selectedCharge.value) return;

    const validationResult = await paymentFormRef.value?.validate();
    if (validationResult && !validationResult.valid) {
        customToastSwal({ title: "Revisa los campos marcados antes de guardar el cobro.", icon: "warning" });
        return;
    }

    const amount = Number(Number(paymentAmount.value).toFixed(2));
    if (amount <= 0) {
        customToastSwal({ title: "Captura un importe mayor a cero.", icon: "warning" });
        return;
    }

    paymentForm.applications = [{ charge_id: selectedCharge.value.id, amount }];

    paymentForm.post(route("billing.payments.store"), {
        preserveScroll: true,
        onSuccess: (pageResponse) => {
            customToastSwal({
                title: (pageResponse.props as any).flash?.success || "Cobro registrado correctamente.",
                icon: "success",
            });
            closePaymentModal();
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${paymentForm.errors.messageError || "No se pudo registrar el cobro."}`,
                text: `${paymentForm.errors.exception || ""}`,
                icon: "error",
            });
        },
    });
};

// ── Data fetching ─────────────────────────────────────────────────────────────

const fetchItems = () => {
    loading.value = true;

    router.get(
        route("billing.charges.index"),
        {
            [`${prefix}_page`]: options.value.page,
            [`${prefix}_per_page`]: options.value.itemsPerPage,
            [`${prefix}_search`]: search.value || undefined,
            [`${prefix}_club_id`]: selectedClubId.value ?? undefined,
            [`${prefix}_concept_code`]: selectedConceptCode.value ?? undefined,
        },
        {
            preserveState: true,
            replace: true,
            onSuccess: (page) => {
                const payload = (page.props as any).charges ?? { data: [], total: 0 };
                items.value = payload.data ?? [];
                total.value = payload.total ?? 0;
                summary.value = (page.props as any).summary ?? summary.value;
                clubPaymentMethods.value = (page.props as any).clubPaymentMethods ?? clubPaymentMethods.value;
                loading.value = false;
            },
            onError: () => {
                loading.value = false;
            },
        }
    );
};

const debouncedFetch = debounce(fetchItems, 350);

const clearFilters = () => {
    search.value = "";
    selectedClubId.value = null;
    selectedConceptCode.value = null;
    options.value.page = 1;
};

watch(
    () => props.charges,
    (charges) => {
        items.value = charges.data ?? [];
        total.value = charges.total ?? 0;
    },
    { deep: true }
);

watch(
    () => props.summary,
    (nextSummary) => { summary.value = { ...nextSummary }; },
    { deep: true }
);

watch(
    () => props.clubPaymentMethods,
    (methods) => { clubPaymentMethods.value = methods ?? []; },
    { deep: true }
);

watch(search, () => {
    options.value.page = 1;
    debouncedFetch();
});

watch([selectedClubId, selectedConceptCode], () => {
    options.value.page = 1;
    fetchItems();
});
</script>

<template>
    <Head title="Desglose de cargos" />

    <AppLayout>
        <v-container fluid class="pa-4">
            <!-- ── Encabezado ── -->
            <div class="d-flex align-center justify-space-between mb-4 flex-wrap gap-2">
                <div>
                    <h1 class="text-h5 font-weight-bold">Desglose de cargos pendientes</h1>
                    <p class="text-body-2 text-medium-emphasis mt-1">
                        Lista individual de todos los cargos con saldo pendiente
                    </p>
                </div>
            </div>

            <!-- ── Tarjetas resumen ── -->
            <v-row class="mb-4" dense>
                <v-col cols="12" sm="6" md="3">
                    <v-card rounded="lg" border elevation="0">
                        <v-card-text class="pa-4">
                            <div class="text-caption text-medium-emphasis mb-1">Total pendiente</div>
                            <div class="text-h6 font-weight-bold">{{ formatCurrency(summary.total_outstanding) }}</div>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-card rounded="lg" border elevation="0" color="error" variant="tonal">
                        <v-card-text class="pa-4">
                            <div class="text-caption text-medium-emphasis mb-1">Vencido</div>
                            <div class="text-h6 font-weight-bold">{{ formatCurrency(summary.overdue_outstanding) }}</div>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-card rounded="lg" border elevation="0" color="primary" variant="tonal">
                        <v-card-text class="pa-4">
                            <div class="text-caption text-medium-emphasis mb-1">Mensualidades</div>
                            <div class="text-h6 font-weight-bold">{{ formatCurrency(summary.monthly_outstanding) }}</div>
                        </v-card-text>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-card rounded="lg" border elevation="0" color="secondary" variant="tonal">
                        <v-card-text class="pa-4">
                            <div class="text-caption text-medium-emphasis mb-1">Inscripciones</div>
                            <div class="text-h6 font-weight-bold">{{ formatCurrency(summary.inscription_outstanding) }}</div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <!-- ── Filtros ── -->
            <v-card rounded="lg" border elevation="0" class="mb-4">
                <v-card-text class="pa-4">
                    <v-row dense align="center">
                        <v-col cols="12" sm="5">
                            <v-text-field
                                v-model="search"
                                prepend-inner-icon="mdi-magnify"
                                label="Buscar por cuenta, titular o concepto"
                                density="compact"
                                variant="outlined"
                                clearable
                                hide-details
                                @click:clear="search = ''"
                            />
                        </v-col>
                        <v-col cols="12" sm="3">
                            <v-select
                                v-model="selectedClubId"
                                :items="clubFilterItems"
                                label="Parque"
                                density="compact"
                                variant="outlined"
                                clearable
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12" sm="3">
                            <v-select
                                v-model="selectedConceptCode"
                                :items="conceptFilterItems"
                                label="Tipo de concepto"
                                density="compact"
                                variant="outlined"
                                clearable
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12" sm="1" class="d-flex justify-end">
                            <v-btn
                                v-if="hasActiveFilters"
                                variant="text"
                                color="error"
                                size="small"
                                @click="clearFilters"
                            >
                                Limpiar
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <!-- ── Tabla de cargos ── -->
            <v-card rounded="lg" border elevation="0">
                <v-data-table-server
                    v-model:options="options"
                    :headers="headers"
                    :items="items"
                    :items-length="total"
                    :loading="loading"
                    :items-per-page-options="[15, 25, 50]"
                    :no-data-text="emptyMessage"
                    density="comfortable"
                    @update:options="fetchItems"
                >
                    <!-- No. Cuenta -->
                    <template #item.membership_number="{ item }">
                        <div class="font-weight-medium">{{ item.membership_number ?? '—' }}</div>
                        <div
                            v-if="item.internal_account_number"
                            class="text-caption text-primary font-weight-medium"
                        >
                            <v-icon size="10" class="mr-1">mdi-pound</v-icon>{{ item.internal_account_number }}
                        </div>
                    </template>

                    <!-- Titular -->
                    <template #item.holder_name="{ item }">
                        <div class="text-body-2">{{ item.holder_name }}</div>
                        <div v-if="item.membership_type_name" class="text-caption text-medium-emphasis">
                            {{ item.membership_type_name }}
                        </div>
                    </template>

                    <!-- Parque -->
                    <template #item.club_name="{ item }">
                        <v-chip
                            v-if="item.club_code"
                            size="small"
                            variant="tonal"
                            color="primary"
                        >
                            {{ item.club_code }}
                        </v-chip>
                        <span v-else class="text-medium-emphasis">—</span>
                    </template>

                    <!-- Concepto -->
                    <template #item.concept_name="{ item }">
                        <div class="d-flex flex-column gap-1">
                            <v-chip
                                size="small"
                                :color="conceptColor(item.concept_code)"
                                variant="tonal"
                            >
                                {{ item.concept_name ?? '—' }}
                            </v-chip>
                            <div class="d-flex gap-1 flex-wrap">
                                <v-chip
                                    v-for="badge in item.badges"
                                    :key="badge.label"
                                    size="x-small"
                                    :color="badge.color"
                                    variant="tonal"
                                >
                                    {{ badge.label }}
                                </v-chip>
                            </div>
                        </div>
                    </template>

                    <!-- Descripción -->
                    <template #item.description="{ item }">
                        <div class="text-body-2 text-truncate" style="max-width: 200px;">
                            {{ item.description ?? item.origin_label }}
                        </div>
                        <template v-if="item.concept_code === 'MONTHLY_FEE' && item.monthly_fee_total">
                            <div class="text-caption text-medium-emphasis">
                                Total: {{ formatCurrency(item.monthly_fee_total) }}
                                <template v-if="item.monthly_fee_share">
                                    · Parte: {{ formatCurrency(item.monthly_fee_share) }}
                                </template>
                            </div>
                        </template>
                    </template>

                    <!-- Vencimiento -->
                    <template #item.due_date="{ item }">
                        <div class="d-flex flex-column gap-1">
                            <span class="text-body-2">{{ formatDate(item.due_date) }}</span>
                            <v-chip
                                size="x-small"
                                :color="resolveDueState(item.due_date).color"
                                variant="tonal"
                            >
                                {{ resolveDueState(item.due_date).label }}
                            </v-chip>
                        </div>
                    </template>

                    <!-- Estado -->
                    <template #item.status="{ item }">
                        <v-chip
                            size="small"
                            :color="chargeStatusColor(item.status)"
                            variant="tonal"
                        >
                            {{ chargeStatusLabel(item.status) }}
                        </v-chip>
                    </template>

                    <!-- Saldo -->
                    <template #item.balance="{ item }">
                        <div class="text-body-2 font-weight-medium text-end">
                            {{ formatCurrency(item.balance) }}
                        </div>
                        <div
                            v-if="item.balance < item.amount"
                            class="text-caption text-medium-emphasis text-end"
                        >
                            de {{ formatCurrency(item.amount) }}
                        </div>
                        <div class="d-flex justify-end mt-1">
                            <v-chip
                                size="x-small"
                                :color="item.allows_partial_payments ? 'info' : 'default'"
                                variant="tonal"
                            >
                                {{ item.allows_partial_payments ? 'Parcial' : 'Pago total' }}
                            </v-chip>
                        </div>
                    </template>

                    <!-- Acciones -->
                    <template #item.actions="{ item }">
                        <v-btn
                            size="small"
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-cash-plus"
                            @click="openPaymentModal(item)"
                        >
                            Cobrar
                        </v-btn>
                    </template>
                </v-data-table-server>
            </v-card>
        </v-container>

        <!-- ── Modal de pago ── -->
        <v-dialog v-model="showPaymentModal" max-width="560" persistent>
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center justify-space-between pa-4 pb-2">
                    <span class="text-h6 font-weight-bold">Registrar cobro</span>
                    <v-btn icon="mdi-close" variant="text" density="compact" @click="closePaymentModal" />
                </v-card-title>

                <v-divider />

                <v-card-text class="pa-4">
                    <!-- Detalle del cargo -->
                    <v-sheet
                        v-if="selectedCharge"
                        rounded="lg"
                        color="surface-variant"
                        class="pa-3 mb-4"
                    >
                        <div class="d-flex justify-space-between align-start mb-1">
                            <div>
                                <div class="text-body-2 font-weight-medium">{{ selectedCharge.holder_name }}</div>
                                <div class="text-caption">{{ selectedCharge.membership_number }}</div>
                                <div
                                    v-if="selectedCharge.internal_account_number"
                                    class="text-caption text-primary font-weight-medium"
                                >
                                    <v-icon size="10" class="mr-1">mdi-pound</v-icon>{{ selectedCharge.internal_account_number }}
                                </div>
                            </div>
                            <v-chip size="small" :color="conceptColor(selectedCharge.concept_code)" variant="tonal">
                                {{ selectedCharge.concept_name }}
                            </v-chip>
                        </div>
                        <div v-if="selectedCharge.description" class="text-caption">
                            {{ selectedCharge.description }}
                        </div>
                        <div class="d-flex justify-space-between align-center mt-2">
                            <div class="text-caption">
                                Vencimiento:
                                <v-chip size="x-small" :color="resolveDueState(selectedCharge.due_date).color" variant="tonal" class="ml-1">
                                    {{ formatDate(selectedCharge.due_date) }}
                                </v-chip>
                            </div>
                            <div class="text-body-2 font-weight-bold">
                                Saldo: {{ formatCurrency(selectedCharge.balance) }}
                            </div>
                        </div>
                    </v-sheet>

                    <!-- Formulario -->
                    <v-form ref="paymentFormRef">
                        <v-row dense>
                            <!-- Importe a cobrar -->
                            <v-col cols="12">
                                <v-text-field
                                    v-model="paymentAmount"
                                    label="Importe a cobrar"
                                    type="number"
                                    density="compact"
                                    variant="outlined"
                                    prefix="$"
                                    :rules="paymentAmountRules"
                                    :readonly="!selectedCharge?.allows_partial_payments"
                                    :hint="
                                        selectedCharge?.allows_partial_payments
                                            ? 'Puedes capturar un importe parcial'
                                            : 'Este concepto debe liquidarse por el monto completo'
                                    "
                                    persistent-hint
                                />
                            </v-col>

                            <!-- Método de pago -->
                            <v-col cols="12">
                                <v-select
                                    v-model="paymentForm.payment_method_id"
                                    :items="selectedPaymentMethods.map((m) => ({
                                        title: m.name,
                                        value: m.id,
                                    }))"
                                    label="Método de pago"
                                    density="compact"
                                    variant="outlined"
                                    :rules="paymentMethodRules"
                                />
                                <v-alert
                                    v-if="selectedPaymentMethod && !selectedPaymentMethod.affects_cash_cut"
                                    type="info"
                                    variant="tonal"
                                    density="compact"
                                    class="mt-2"
                                    icon="mdi-cash-register"
                                >
                                    Este método de pago <strong>no impacta</strong> en el corte de caja.
                                </v-alert>
                            </v-col>

                            <!-- Fecha de pago -->
                            <v-col cols="12">
                                <v-text-field
                                    v-model="paymentForm.paid_at"
                                    label="Fecha de pago"
                                    type="datetime-local"
                                    density="compact"
                                    variant="outlined"
                                    :rules="paidAtRules"
                                />
                            </v-col>

                            <!-- Referencia (condicional) -->
                            <v-col v-if="selectedPaymentMethod?.requires_reference" cols="12">
                                <v-text-field
                                    v-model="paymentForm.reference"
                                    label="Referencia"
                                    density="compact"
                                    variant="outlined"
                                    :rules="referenceRules"
                                />
                            </v-col>

                            <!-- Banco (condicional) -->
                            <v-col v-if="selectedPaymentMethod?.requires_bank_name" cols="12">
                                <v-text-field
                                    v-model="paymentForm.bank_name"
                                    label="Banco"
                                    density="compact"
                                    variant="outlined"
                                    :rules="bankNameRules"
                                />
                            </v-col>

                            <!-- No. Cheque (condicional) -->
                            <v-col v-if="selectedPaymentMethod?.requires_check_number" cols="12">
                                <v-text-field
                                    v-model="paymentForm.check_number"
                                    label="No. de cheque"
                                    density="compact"
                                    variant="outlined"
                                    :rules="checkNumberRules"
                                />
                            </v-col>

                            <!-- Notas -->
                            <v-col cols="12">
                                <v-textarea
                                    v-model="paymentForm.notes"
                                    label="Notas (opcional)"
                                    density="compact"
                                    variant="outlined"
                                    rows="2"
                                    :rules="notesRules"
                                />
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>

                <v-divider />

                <v-card-actions class="pa-4 gap-2">
                    <v-spacer />
                    <v-btn variant="text" @click="closePaymentModal">Cancelar</v-btn>
                    <v-btn
                        color="primary"
                        variant="flat"
                        :loading="paymentForm.processing"
                        prepend-icon="mdi-check"
                        @click="submitPayment"
                    >
                        Registrar cobro
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
