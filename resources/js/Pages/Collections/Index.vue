<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { customToastSwal } from "@/utils/swal";
import { nowAsLocalInput } from "@/constants/formatDates";

interface ConceptOption {
    id: number;
    code: string;
    name: string;
    default_amount: number | null;
    is_recurring: boolean;
    allows_partial_payments: boolean;
}
interface PaymentMethodItem {
    id: number;
    code: string;
    name: string;
    requires_reference: boolean;
    requires_bank_name: boolean;
    requires_check_number: boolean;
    affects_cash_cut: boolean;
    internal_key: string | null;
}
interface ClubPaymentMethodItem {
    id: number;
    code: string;
    name: string;
    payment_methods: PaymentMethodItem[];
}
interface PendingChargeRef {
    id: number;
    balance: number;
    allows_partial_payments: boolean;
    period_label: string | null;
}
interface PendingConcept {
    concept_id: number | null;
    concept_code: string | null;
    concept_name: string | null;
    rate: number | null;
    fee: number;
    class_label: string;
    unit_amount: number;
    months: number;
    balance: number;
    charges: PendingChargeRef[];
}
interface AccountInfo {
    id: number;
    membership_number: string;
    internal_account_number: string | null;
    holder_name: string;
    email: string | null;
    phone: string | null;
}
interface ClubInfo {
    id: number;
    code: string;
    name: string;
}
interface Summary {
    last_paid_period_label: string | null;
    overdue_months: number;
    lockers_count: number;
    total_due: number;
}
interface Incident {
    id: number;
    folio: string | null;
    violation_type: string | null;
    description: string | null;
    date: string | null;
}
interface NoteItem {
    id: number;
    body: string;
    author: string | null;
    created_at: string | null;
}
interface Signal {
    label: string;
    color: string;
}
interface SearchResult {
    found: boolean;
    message?: string;
    account?: AccountInfo;
    cobro_club?: ClubInfo | null;
    billing_membership_id?: number | null;
    pending_concepts?: PendingConcept[];
    summary?: Summary;
    incidents?: Incident[];
    notes?: NoteItem[];
    signals?: Signal[];
}

/** Renglón de la lista de cobros (mezcla cargos existentes y conceptos nuevos). */
interface CobroLine {
    key: string;
    type: "existing" | "new";
    concept_label: string;
    detail: string;
    amount: number;
    charges?: { charge_id: number; amount: number }[];
    concept_id?: number;
    description?: string | null;
}

interface Props {
    conceptOptions?: ConceptOption[];
    clubPaymentMethods?: ClubPaymentMethodItem[];
}

const props = withDefaults(defineProps<Props>(), {
    conceptOptions: () => [],
    clubPaymentMethods: () => [],
});

const page = usePage<any>();
const can = page.props.auth?.permissions ?? [];
const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});
const formatCurrency = (value: number | null | undefined) =>
    currencyFormatter.format(Number(value ?? 0));

// Búsqueda
const searchTerm = ref("");
const searching = ref(false);
const result = ref<SearchResult | null>(null);

const account = computed(() => result.value?.account ?? null);
const cobroClub = computed(() => result.value?.cobro_club ?? null);
const pendingConcepts = computed(() => result.value?.pending_concepts ?? []);
const summary = computed(() => result.value?.summary ?? null);
const incidents = computed(() => result.value?.incidents ?? []);
const notes = ref<NoteItem[]>([]);
const signals = computed(() => result.value?.signals ?? []);

const runSearch = async () => {
    if (!searchTerm.value || searchTerm.value.trim().length < 2) {
        customToastSwal({
            title: "Escribe al menos 2 caracteres (clave o nombre).",
            icon: "warning",
        });
        return;
    }

    searching.value = true;
    try {
        const { data } = await window.axios.get(route("collections.search"), {
            params: { query: searchTerm.value.trim() },
        });

        if (!data.found) {
            result.value = null;
            notes.value = [];
            cobros.value = [];
            customToastSwal({
                title: data.message || "Socio no encontrado.",
                icon: "info",
            });
            return;
        }

        result.value = data;
        notes.value = data.notes ?? [];
        // Al cambiar de socio, se reinicia la lista de cobros y el pago.
        cobros.value = [];
        resetNewItem();
        resetPayment();
    } catch (e: any) {
        customToastSwal({
            title:
                e?.response?.data?.message ||
                "Ocurrió un error al buscar al socio.",
            icon: "error",
        });
    } finally {
        searching.value = false;
    }
};

// Tabla 1: cargos pendientes 
const pendingHeaders = [
    { title: "Concepto", key: "concept", sortable: false },
    { title: "Tasa", key: "rate", sortable: false },
    { title: "Cuota", key: "fee", sortable: false },
    { title: "Clase", key: "class_label", sortable: false },
    { title: "Monto", key: "unit_amount", sortable: false },
    { title: "Meses", key: "months", sortable: false },
    { title: "Saldo", key: "balance", sortable: false },
    { title: "", key: "actions", sortable: false },
];

const conceptLabel = (c: {
    concept_code: string | null;
    concept_name: string | null;
}) => `${c.concept_code ?? ""} ${c.concept_name ?? ""}`.trim();

const isConceptInCobros = (conceptId: number | null) =>
    conceptId !== null &&
    cobros.value.some(
        (line) => line.type === "existing" && line.concept_id === conceptId,
    );

const addPendingToCobros = (concept: PendingConcept) => {
    if (concept.concept_id === null || isConceptInCobros(concept.concept_id)) {
        return;
    }

    cobros.value.push({
        key: `existing-${concept.concept_id}-${Date.now()}`,
        type: "existing",
        concept_id: concept.concept_id,
        concept_label: conceptLabel(concept),
        detail: `${concept.months} ${concept.months === 1 ? "cargo" : "cargos/meses"}`,
        amount: concept.balance,
        charges: concept.charges.map((ch) => ({
            charge_id: ch.id,
            amount: ch.balance,
        })),
    });
};

interface NewItemForm {
    concept_code: string;
    concept_id: number | null;
    description: string;
    importe: number | null;
    cantidad: number;
    descuento: number;
    iva: number;
}

const emptyNewItem = (): NewItemForm => ({
    concept_code: "",
    concept_id: null,
    description: "",
    importe: null,
    cantidad: 1,
    descuento: 0,
    iva: 0,
});

const newItem = ref<NewItemForm>(emptyNewItem());
const resetNewItem = () => {
    newItem.value = emptyNewItem();
};

const conceptSelectItems = computed(() =>
    props.conceptOptions.map((c) => ({
        title: `${c.code} - ${c.name}`,
        value: c.id,
    })),
);

// Escribir el código selecciona el concepto correspondiente.
watch(
    () => newItem.value.concept_code,
    (code) => {
        if (!code) return;
        const match = props.conceptOptions.find(
            (c) => c.code.toLowerCase() === code.trim().toLowerCase(),
        );
        if (match && match.id !== newItem.value.concept_id) {
            newItem.value.concept_id = match.id;
        }
    },
);

// Seleccionar el concepto rellena código e importe sugerido.
watch(
    () => newItem.value.concept_id,
    (id) => {
        const match = props.conceptOptions.find((c) => c.id === id);
        if (!match) return;
        if (newItem.value.concept_code.toLowerCase() !== match.code.toLowerCase()) {
            newItem.value.concept_code = match.code;
        }
        if (
            (newItem.value.importe === null || newItem.value.importe === 0) &&
            match.default_amount
        ) {
            newItem.value.importe = match.default_amount;
        }
    },
);

const newSubtotal = computed(
    () => Number(newItem.value.importe ?? 0) * Number(newItem.value.cantidad ?? 0),
);
const newTotal = computed(() =>
    Math.max(
        0,
        newSubtotal.value -
            Number(newItem.value.descuento ?? 0) +
            Number(newItem.value.iva ?? 0),
    ),
);

const addNewItemToCobros = () => {
    const concept = props.conceptOptions.find(
        (c) => c.id === newItem.value.concept_id,
    );
    if (!concept) {
        customToastSwal({
            title: "Selecciona o escribe un concepto válido.",
            icon: "warning",
        });
        return;
    }
    if (newTotal.value <= 0) {
        customToastSwal({
            title: "El total del concepto debe ser mayor a cero.",
            icon: "warning",
        });
        return;
    }

    cobros.value.push({
        key: `new-${concept.id}-${Date.now()}`,
        type: "new",
        concept_id: concept.id,
        concept_label: `${concept.code} ${concept.name}`,
        description: newItem.value.description || concept.name,
        detail: `${newItem.value.cantidad} x ${formatCurrency(newItem.value.importe)}`,
        amount: Number(newTotal.value.toFixed(2)),
    });

    resetNewItem();
};

// Tabla 2: lista de cobros
const cobros = ref<CobroLine[]>([]);
const cobrosHeaders = [
    { title: "Concepto", key: "concept_label", sortable: false },
    { title: "Detalle", key: "detail", sortable: false },
    { title: "Importe", key: "amount", sortable: false },
    { title: "", key: "actions", sortable: false },
];

const removeCobro = (key: string) => {
    cobros.value = cobros.value.filter((line) => line.key !== key);
};

const cobrosTotal = computed(() =>
    cobros.value.reduce((sum, line) => sum + Number(line.amount ?? 0), 0),
);

interface PaymentForm {
    payment_method_id: number | null;
    paid_at: string;
    reference: string;
    bank_name: string;
    check_number: string;
    notes: string;
}

const emptyPayment = (): PaymentForm => ({
    payment_method_id: null,
    paid_at: nowAsLocalInput(),
    reference: "",
    bank_name: "",
    check_number: "",
    notes: "",
});

const payment = ref<PaymentForm>(emptyPayment());
const paying = ref(false);
const resetPayment = () => {
    payment.value = emptyPayment();
};

const availablePaymentMethods = computed<PaymentMethodItem[]>(() => {
    if (!cobroClub.value) return [];
    return (
        props.clubPaymentMethods.find((c) => c.id === cobroClub.value!.id)
            ?.payment_methods ?? []
    );
});

const selectedMethod = computed(
    () =>
        availablePaymentMethods.value.find(
            (m) => m.id === payment.value.payment_method_id,
        ) ?? null,
);

const submitPayment = async () => {
    if (!account.value || !cobroClub.value) {
        customToastSwal({ title: "Busca primero un socio.", icon: "warning" });
        return;
    }
    if (!cobros.value.length) {
        customToastSwal({
            title: "Agrega al menos un cargo o concepto a la lista de cobros.",
            icon: "warning",
        });
        return;
    }
    if (!payment.value.payment_method_id) {
        customToastSwal({ title: "Selecciona un método de pago.", icon: "warning" });
        return;
    }
    const m = selectedMethod.value;
    if (m?.requires_reference && !payment.value.reference) {
        customToastSwal({ title: "Este método requiere referencia.", icon: "warning" });
        return;
    }
    if (m?.requires_bank_name && !payment.value.bank_name) {
        customToastSwal({ title: "Este método requiere banco.", icon: "warning" });
        return;
    }
    if (m?.requires_check_number && !payment.value.check_number) {
        customToastSwal({ title: "Este método requiere número de cheque.", icon: "warning" });
        return;
    }

    const existing_charges = cobros.value
        .filter((l) => l.type === "existing")
        .flatMap((l) => l.charges ?? []);
    const new_items = cobros.value
        .filter((l) => l.type === "new")
        .map((l) => ({
            concept_id: l.concept_id,
            description: l.description,
            total: l.amount,
        }));

    paying.value = true;
    try {
        const { data } = await window.axios.post(route("collections.payment.store"), {
            membership_account_id: account.value.id,
            club_id: cobroClub.value.id,
            payment_method_id: payment.value.payment_method_id,
            paid_at: payment.value.paid_at,
            reference: payment.value.reference,
            bank_name: payment.value.bank_name,
            check_number: payment.value.check_number,
            notes: payment.value.notes,
            existing_charges,
            new_items,
        });

        customToastSwal({
            title: data.message || "Cobro registrado correctamente.",
            icon: "success",
        });
        cobros.value = [];
        resetPayment();
        // Refresca el estado de cuenta del socio.
        await runSearch();
    } catch (e: any) {
        customToastSwal({
            title: e?.response?.data?.message || "No se pudo registrar el cobro.",
            icon: "error",
        });
    } finally {
        paying.value = false;
    }
};


const noteBody = ref("");
const savingNote = ref(false);

const saveNote = async () => {
    if (!account.value) return;
    if (!noteBody.value.trim()) {
        customToastSwal({ title: "Escribe una nota.", icon: "warning" });
        return;
    }
    savingNote.value = true;
    try {
        const { data } = await window.axios.post(route("collections.notes.store"), {
            membership_account_id: account.value.id,
            body: noteBody.value.trim(),
        });
        notes.value.unshift(data.note);
        noteBody.value = "";
        customToastSwal({ title: "Nota guardada.", icon: "success" });
    } catch (e: any) {
        customToastSwal({
            title: e?.response?.data?.message || "No se pudo guardar la nota.",
            icon: "error",
        });
    } finally {
        savingNote.value = false;
    }
};
</script>

<template>
    <Head title="Registro de cobros" />

    <AppLayout>
        <template #header>Registro de cobros</template>

        <div class="d-flex flex-column ga-4">
            <!-- Buscador -->
            <v-card>
                <v-card-text>
                    <v-row align="center">
                        <v-col cols="12" md="8">
                            <v-text-field
                                v-model="searchTerm"
                                label="Clave o nombre del socio"
                                placeholder="No. de cuenta, cuenta interna o nombre del titular"
                                prepend-inner-icon="mdi-account-search"
                                clearable
                                hide-details
                                @keyup.enter="runSearch"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <BaseButton
                                :icon-only="false"
                                action="search"
                                icon="mdi-magnify"
                                text="Buscar socio"
                                :loading="searching"
                                @click="runSearch"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <template v-if="account">
                <!-- Encabezado del socio -->
                <v-card color="primary" variant="tonal">
                    <v-card-text>
                        <div class="d-flex flex-wrap justify-space-between align-center ga-3">
                            <div>
                                <div class="text-caption text-medium-emphasis">Titular</div>
                                <div class="text-h6 font-weight-bold">
                                    {{ account.holder_name }}
                                </div>
                                <div class="text-body-2">
                                    No. cuenta:
                                    <strong>{{ account.membership_number }}</strong>
                                    <span v-if="account.internal_account_number">
                                        · Interna:
                                        <strong>{{ account.internal_account_number }}</strong>
                                    </span>
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ account.email || "Sin correo" }} ·
                                    {{ account.phone || "Sin teléfono" }}
                                </div>
                            </div>
                            <div class="text-right">
                                <v-chip
                                    v-if="cobroClub"
                                    color="primary"
                                    variant="flat"
                                    class="mb-2"
                                >
                                    {{ cobroClub.code }} - {{ cobroClub.name }}
                                </v-chip>
                                <div class="d-flex flex-wrap justify-end ga-2">
                                    <v-chip
                                        v-for="(s, i) in signals"
                                        :key="i"
                                        :color="s.color"
                                        size="small"
                                        variant="flat"
                                    >
                                        {{ s.label }}
                                    </v-chip>
                                </div>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Tabla 1: cargos pendientes -->
                <v-card>
                    <v-card-title>Cargos pendientes</v-card-title>
                    <v-data-table
                        :headers="pendingHeaders"
                        :items="pendingConcepts"
                        :items-per-page="-1"
                        hide-default-footer
                        no-data-text="El socio no tiene cargos pendientes en este parque."
                        class="elevation-0"
                    >
                        <template #item.concept="{ item }">
                            <span class="font-weight-medium">{{ conceptLabel(item) }}</span>
                        </template>
                        <template #item.rate="{ item }">
                            <span class="text-medium-emphasis">{{ item.rate ?? "—" }}</span>
                        </template>
                        <template #item.fee="{ item }">
                            {{ formatCurrency(item.fee) }}
                        </template>
                        <template #item.class_label="{ item }">
                            <v-chip
                                size="small"
                                variant="tonal"
                                :color="item.class_label === 'A meses' ? 'info' : 'secondary'"
                            >
                                {{ item.class_label }}
                            </v-chip>
                        </template>
                        <template #item.unit_amount="{ item }">
                            {{ formatCurrency(item.unit_amount) }}
                        </template>
                        <template #item.months="{ item }">
                            {{ item.months }}
                        </template>
                        <template #item.balance="{ item }">
                            <span class="font-weight-bold">{{ formatCurrency(item.balance) }}</span>
                        </template>
                        <template #item.actions="{ item }">
                            <BaseButton
                                :icon-only="false"
                                size="small"
                                action="add"
                                icon="mdi-plus"
                                text="Agregar"
                                variant="tonal"
                                :disabled="isConceptInCobros(item.concept_id)"
                                tooltip="Agregar este adeudo a la lista de cobros"
                                @click="addPendingToCobros(item)"
                            />
                        </template>
                    </v-data-table>

                    <!-- Resumen del socio -->
                    <v-divider />
                    <v-card-text>
                        <v-row>
                            <v-col cols="6" md="3">
                                <div class="text-caption text-medium-emphasis">Último mes pagado</div>
                                <div class="text-subtitle-1 font-weight-bold">
                                    {{ summary?.last_paid_period_label || "Sin registro" }}
                                </div>
                            </v-col>
                            <v-col cols="6" md="3">
                                <div class="text-caption text-medium-emphasis">Meses vencidos</div>
                                <div
                                    class="text-subtitle-1 font-weight-bold"
                                    :class="summary && summary.overdue_months > 0 ? 'text-error' : ''"
                                >
                                    {{ summary?.overdue_months ?? 0 }}
                                </div>
                            </v-col>
                            <v-col cols="6" md="3">
                                <div class="text-caption text-medium-emphasis">Casilleros del socio</div>
                                <div class="text-subtitle-1 font-weight-bold">
                                    {{ summary?.lockers_count ?? 0 }}
                                </div>
                            </v-col>
                            <v-col cols="6" md="3">
                                <div class="text-caption text-medium-emphasis">Adeudo al día de hoy</div>
                                <div class="text-subtitle-1 font-weight-bold text-error">
                                    {{ formatCurrency(summary?.total_due) }}
                                </div>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>

                <!-- Captura de concepto nuevo -->
                <v-card>
                    <v-card-title>Agregar concepto de cobro</v-card-title>
                    <v-card-text>
                        <v-row>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model="newItem.concept_code"
                                    label="Código"
                                    placeholder="Ej. MONTHLY_FEE"
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="newItem.concept_id"
                                    :items="conceptSelectItems"
                                    label="Concepto"
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="6" md="3">
                                <v-text-field
                                    v-model.number="newItem.importe"
                                    label="Importe"
                                    type="number"
                                    min="0"
                                    prefix="$"
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="6" md="3">
                                <v-text-field
                                    v-model.number="newItem.cantidad"
                                    label="Cantidad"
                                    type="number"
                                    min="1"
                                    hide-details="auto"
                                />
                            </v-col>

                            <v-col cols="6" md="2">
                                <v-text-field
                                    :model-value="formatCurrency(newSubtotal)"
                                    label="Subtotal"
                                    readonly
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    label="Bonificación"
                                    placeholder="—"
                                    disabled
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model.number="newItem.descuento"
                                    label="Descuento ($)"
                                    type="number"
                                    min="0"
                                    prefix="$"
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    v-model.number="newItem.iva"
                                    label="IVA ($)"
                                    type="number"
                                    min="0"
                                    prefix="$"
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="6" md="2">
                                <v-text-field
                                    :model-value="formatCurrency(newTotal)"
                                    label="Total"
                                    readonly
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="6" md="2" class="d-flex align-center">
                                <BaseButton
                                    :icon-only="false"
                                    action="add"
                                    icon="mdi-plus"
                                    text="Agregar"
                                    @click="addNewItemToCobros"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="newItem.description"
                                    label="Descripción / referencia (opcional)"
                                    hide-details="auto"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>

                <!-- Tabla 2: lista de cobros + pago -->
                <v-row>
                    <v-col cols="12" md="7">
                        <v-card height="100%">
                            <v-card-title>Cobros</v-card-title>
                            <v-data-table
                                :headers="cobrosHeaders"
                                :items="cobros"
                                :items-per-page="-1"
                                hide-default-footer
                                no-data-text="Aún no has agregado cobros."
                                class="elevation-0"
                            >
                                <template #item.concept_label="{ item }">
                                    <span class="font-weight-medium">{{ item.concept_label }}</span>
                                    <v-chip
                                        size="x-small"
                                        class="ml-2"
                                        :color="item.type === 'new' ? 'success' : 'info'"
                                        variant="tonal"
                                    >
                                        {{ item.type === "new" ? "Nuevo" : "Adeudo" }}
                                    </v-chip>
                                </template>
                                <template #item.amount="{ item }">
                                    <span class="font-weight-bold">{{ formatCurrency(item.amount) }}</span>
                                </template>
                                <template #item.actions="{ item }">
                                    <BaseButton
                                        action="delete"
                                        icon="mdi-close"
                                        color="error"
                                        variant="text"
                                        size="small"
                                        tooltip="Quitar de la lista"
                                        @click="removeCobro(item.key)"
                                    />
                                </template>
                            </v-data-table>
                            <v-divider />
                            <v-card-text class="d-flex justify-space-between align-center">
                                <span class="text-subtitle-1 font-weight-bold">Total a cobrar</span>
                                <span class="text-h5 font-weight-bold text-primary">
                                    {{ formatCurrency(cobrosTotal) }}
                                </span>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="5">
                        <v-card height="100%">
                            <v-card-title>Efectuar cobro</v-card-title>
                            <v-card-text class="d-flex flex-column ga-3">
                                <v-select
                                    v-model="payment.payment_method_id"
                                    :items="
                                        availablePaymentMethods.map((m) => ({
                                            title: m.internal_key
                                                ? `${m.name} (${m.internal_key})`
                                                : m.name,
                                            value: m.id,
                                        }))
                                    "
                                    label="Método de pago"
                                    hide-details="auto"
                                />
                                <v-alert
                                    v-if="selectedMethod && !selectedMethod.affects_cash_cut"
                                    type="info"
                                    variant="tonal"
                                    density="compact"
                                >
                                    Pago con servicios: liquida los cargos pero no
                                    impacta el corte de caja monetario.
                                </v-alert>
                                <v-text-field
                                    v-model="payment.paid_at"
                                    label="Fecha y hora del pago"
                                    type="datetime-local"
                                    hide-details="auto"
                                />
                                <v-text-field
                                    v-model="payment.reference"
                                    label="Referencia"
                                    :disabled="!selectedMethod?.requires_reference"
                                    hide-details="auto"
                                />
                                <v-text-field
                                    v-model="payment.bank_name"
                                    label="Banco"
                                    :disabled="!selectedMethod?.requires_bank_name"
                                    hide-details="auto"
                                />
                                <v-text-field
                                    v-model="payment.check_number"
                                    label="Número de cheque"
                                    :disabled="!selectedMethod?.requires_check_number"
                                    hide-details="auto"
                                />
                                <v-textarea
                                    v-model="payment.notes"
                                    label="Notas del pago"
                                    rows="2"
                                    auto-grow
                                    hide-details="auto"
                                />
                                <BaseButton
                                    v-if="can.includes('collections.store')"
                                    :icon-only="false"
                                    action="save"
                                    icon="mdi-cash-check"
                                    text="Efectuar cobro"
                                    :loading="paying"
                                    :disabled="!cobros.length"
                                    @click="submitPayment"
                                />
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <!-- Comentarios / incidencias -->
                <v-card>
                    <v-card-title>Comentarios e incidencias</v-card-title>
                    <v-card-text>
                        <v-row>
                            <v-col cols="12" md="6">
                                <div class="text-subtitle-2 font-weight-bold mb-2">
                                    Incidencias registradas (actas)
                                </div>
                                <v-alert
                                    v-if="!incidents.length"
                                    type="success"
                                    variant="tonal"
                                    density="compact"
                                >
                                    Sin incidencias registradas.
                                </v-alert>
                                <v-list v-else density="compact" class="pa-0">
                                    <v-list-item
                                        v-for="inc in incidents"
                                        :key="inc.id"
                                        class="px-0"
                                    >
                                        <template #prepend>
                                            <v-icon color="warning" class="mr-2">
                                                mdi-alert-circle-outline
                                            </v-icon>
                                        </template>
                                        <v-list-item-title class="text-wrap">
                                            {{ inc.violation_type || "Incidencia" }}
                                            <span
                                                v-if="inc.folio"
                                                class="text-caption text-medium-emphasis"
                                            >
                                                · Folio {{ inc.folio }}
                                            </span>
                                        </v-list-item-title>
                                        <v-list-item-subtitle class="text-wrap">
                                            {{ inc.description }}
                                            <span class="text-caption">
                                                ({{ inc.date }})
                                            </span>
                                        </v-list-item-subtitle>
                                    </v-list-item>
                                </v-list>
                            </v-col>

                            <v-col cols="12" md="6">
                                <div class="text-subtitle-2 font-weight-bold mb-2">
                                    Notas de cobranza
                                </div>
                                <div class="d-flex ga-2 mb-3">
                                    <v-textarea
                                        v-model="noteBody"
                                        label="Escribir una nota…"
                                        rows="2"
                                        auto-grow
                                        hide-details
                                    />
                                    <BaseButton
                                        action="save"
                                        icon="mdi-content-save"
                                        color="primary"
                                        tooltip="Guardar nota"
                                        :loading="savingNote"
                                        @click="saveNote"
                                    />
                                </div>
                                <v-alert
                                    v-if="!notes.length"
                                    type="info"
                                    variant="tonal"
                                    density="compact"
                                >
                                    Aún no hay notas para este socio.
                                </v-alert>
                                <v-list v-else density="compact" class="pa-0">
                                    <v-list-item
                                        v-for="note in notes"
                                        :key="note.id"
                                        class="px-0"
                                    >
                                        <template #prepend>
                                            <v-icon class="mr-2">mdi-note-text-outline</v-icon>
                                        </template>
                                        <v-list-item-title class="text-wrap">
                                            {{ note.body }}
                                        </v-list-item-title>
                                        <v-list-item-subtitle>
                                            {{ note.author || "—" }} ·
                                            {{ note.created_at }}
                                        </v-list-item-subtitle>
                                    </v-list-item>
                                </v-list>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </template>

            <v-alert v-else type="info" variant="tonal">
                Busca un socio por su clave (número de cuenta o cuenta interna) o
                por su nombre para comenzar a cobrar.
            </v-alert>
        </div>
    </AppLayout>
</template>
