<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";
import { customToastSwal } from "@/utils/swal";
import {
    optionalLength,
    required,
    selectRequired,
} from "@/constants/validationRules";
import { nowAsLocalInput } from "@/constants/formatDates";

interface ClubItem {
    id: number;
    code: string | null;
    name: string | null;
}

interface ChargeSummaryItem {
    concept_name: string;
    count: number;
    balance: number;
}

interface ChargeItem {
    id: number;
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
    badges: Array<{
        label: string;
        color: string;
    }>;
    target_monthly_fee: number | null;
    monthly_fee_total: number | null;
    monthly_fee_share: number | null;
    effective_monthly_fee: number | null;
}

interface BillingAccountItem {
    id: number;
    membership_number: string;
    internal_account_number: string | null;
    holder_name: string;
    email: string | null;
    phone: string | null;
    photo: string | null;
    primary_membership_id: number | null;
    has_session_membership: boolean;
    session_membership_club_name: string | null;
    session_membership_club_code: string | null;
    outstanding_balance: number;
    pending_charges_count: number;
    next_due_date: string | null;
    clubs: ClubItem[];
    charge_summary: ChargeSummaryItem[];
    charges: ChargeItem[];
}

interface PaginatedAccounts {
    data: BillingAccountItem[];
    total: number;
}

interface BillingSummary {
    total_outstanding: number;
    overdue_outstanding: number;
    monthly_outstanding: number;
    inscription_outstanding: number;
    accounts_with_balance: number;
}

interface ClubOption {
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
    internal_key: string | null;
}

interface ClubPaymentMethodItem {
    id: number;
    code: string;
    name: string;
    payment_methods: PaymentMethodItem[];
}

interface Filters {
    search: string | null;
    club_id: number | null;
}

interface Props {
    accounts?: PaginatedAccounts;
    summary?: BillingSummary;
    clubOptions?: ClubOption[];
    clubPaymentMethods?: ClubPaymentMethodItem[];
    filters?: Filters;
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

interface PaymentDraftItem {
    charge_id: number;
    selected: boolean;
    amount: string;
}

interface AnnualPaymentFormData {
    membership_account_id: number | null;
    club_id: number | null;
    year: number;
    payment_method_id: number | null;
    paid_at: string;
    reference: string;
    bank_name: string;
    check_number: string;
    notes: string;
}

interface AnnualPaymentPreview {
    charges_count: number;
    months_covered: number[];
    total_balance: number;
    discount_rule: {
        pay_by_month: number;
        discount_months: number;
        free_month: number;
    } | null;
    discount_amount: number;
    payment_amount: number;
}

const props = withDefaults(defineProps<Props>(), {
    accounts: () => ({
        data: [],
        total: 0,
    }),
    summary: () => ({
        total_outstanding: 0,
        overdue_outstanding: 0,
        monthly_outstanding: 0,
        inscription_outstanding: 0,
        accounts_with_balance: 0,
    }),
    clubOptions: () => [],
    clubPaymentMethods: () => [],
    filters: () => ({
        search: null,
        club_id: null,
    }),
});

const page = usePage<any>();
const can = page.props.auth.permissions ?? [];

const headers = [
    { title: "No. Cuenta", key: "membership_number", sortable: false },
    { title: "Titular", key: "holder_name", sortable: false },
    { title: "Parques", key: "clubs", sortable: false },
    { title: "Conceptos pendientes", key: "charge_summary", sortable: false },
    { title: "Saldo pendiente", key: "outstanding_balance", sortable: false },
    { title: "Próximo vencimiento", key: "next_due_date", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref<BillingAccountItem[]>(props.accounts.data ?? []);
const total = ref(props.accounts.total ?? 0);
const summary = ref<BillingSummary>({ ...props.summary });
const clubPaymentMethods = ref<ClubPaymentMethodItem[]>(
    props.clubPaymentMethods ?? [],
);
const loading = ref(false);
const showPaymentModal = ref(false);
const selectedPaymentAccount = ref<BillingAccountItem | null>(null);
const selectedPaymentClubId = ref<number | null>(null);
const paymentDrafts = ref<PaymentDraftItem[]>([]);
const paymentFormRef = ref();

console.log(selectedPaymentAccount.value, "selectedPaymentAccount.value");

// ── Anualidad ──────────────────────────────────────────────────────────────
const showAnnualModal = ref(false);
const annualAccount = ref<BillingAccountItem | null>(null);
const annualFormRef = ref();
const annualPreview = ref<AnnualPaymentPreview | null>(null);
const annualPreviewLoading = ref(false);
const annualPreviewError = ref<string | null>(null);
const search = ref(props.filters.search ?? "");
const selectedClubId = ref<number | null>(props.filters.club_id ?? null);
const expanded = ref<number[]>([]);
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});
const prefix = "billing";

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const dateFormatter = new Intl.DateTimeFormat("es-MX", {
    dateStyle: "medium",
});

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

const annualForm = useForm<AnnualPaymentFormData>({
    membership_account_id: null,
    club_id: null,
    year: new Date().getFullYear(),
    payment_method_id: null,
    paid_at: nowAsLocalInput(),
    reference: "",
    bank_name: "",
    check_number: "",
    notes: "",
});

const clubFilterItems = computed(() =>
    props.clubOptions.map((club) => ({
        title: `${club.code} - ${club.name}`,
        value: club.id,
    })),
);

const hasActiveFilters = computed(
    () => Boolean(search.value) || selectedClubId.value !== null,
);

const emptyMessage = computed(() =>
    hasActiveFilters.value
        ? "No se encontraron cuentas con saldo pendiente para ese criterio"
        : "No hay cuentas con saldo pendiente para mostrar",
);

const formatCurrency = (value: number | null | undefined) =>
    currencyFormatter.format(Number(value ?? 0));

const formatDate = (value: string | null) => {
    if (!value) {
        return "Sin fecha";
    }

    return dateFormatter.format(new Date(`${value}T00:00:00`));
};

const resolveDueState = (value: string | null) => {
    if (!value) {
        return {
            label: "Sin fecha",
            color: "default",
        };
    }

    const dueDate = new Date(`${value}T00:00:00`);
    const today = new Date();
    const normalizedToday = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate(),
    );

    if (dueDate < normalizedToday) {
        return {
            label: "Vencido",
            color: "error",
        };
    }

    if (dueDate.getTime() === normalizedToday.getTime()) {
        return {
            label: "Vence hoy",
            color: "warning",
        };
    }

    return {
        label: "Por vencer",
        color: "info",
    };
};

const chargeStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        pending: "Pendiente",
        partial: "Parcial",
        paid: "Pagado",
        cancelled: "Cancelado",
    };

    return labels[status] ?? status;
};

const chargeStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        pending: "warning",
        partial: "info",
        paid: "success",
        cancelled: "default",
    };

    return colors[status] ?? "default";
};

const chargeConceptColor = (code: string | null) => {
    const colors: Record<string, string> = {
        MONTHLY_FEE: "primary",
        INSCRIPTION: "secondary",
        BUSINESS_AD: "orange",
    };

    return colors[code ?? ""] ?? "default";
};

const shouldShowChargeFeeBreakdown = (charge: ChargeItem) =>
    charge.concept_code === "MONTHLY_FEE" &&
    (charge.monthly_fee_total !== null ||
        charge.monthly_fee_share !== null ||
        charge.effective_monthly_fee !== null);

const hasOverdueCharges = (account: BillingAccountItem) =>
    account.charges.some(
        (charge) => resolveDueState(charge.due_date).label === "Vencido",
    );

const manageAccountTooltip = (account: BillingAccountItem) => {
    if (account.primary_membership_id) {
        return "Abrir la cuenta del usuario en el parque activo";
    }

    return "Para gestionar esta cuenta, cambia la sesión al parque correspondiente.";
};

const selectedPaymentAccountClubs = computed(() => {
    if (!selectedPaymentAccount.value) {
        return [];
    }

    const clubMap = new Map<number, ClubItem>();

    selectedPaymentAccount.value.charges.forEach((charge) => {
        if (charge.club_id === null) {
            return;
        }

        if (!clubMap.has(charge.club_id)) {
            clubMap.set(charge.club_id, {
                id: charge.club_id,
                code: charge.club_code,
                name: charge.club_name,
            });
        }
    });

    return Array.from(clubMap.values());
});

const selectedPaymentCharges = computed(() => {
    if (!selectedPaymentAccount.value || selectedPaymentClubId.value === null) {
        return [];
    }

    return selectedPaymentAccount.value.charges.filter(
        (charge) => charge.club_id === selectedPaymentClubId.value,
    );
});

const selectedPaymentMethods = computed(() => {
    if (selectedPaymentClubId.value === null) {
        return [];
    }

    return (
        clubPaymentMethods.value.find(
            (club) => club.id === selectedPaymentClubId.value,
        )?.payment_methods ?? []
    );
});

const selectedPaymentMethod = computed(
    () =>
        selectedPaymentMethods.value.find(
            (method) => method.id === paymentForm.payment_method_id,
        ) ?? null,
);

const paymentTotal = computed(() =>
    paymentDrafts.value.reduce((totalAmount, draft) => {
        if (!draft.selected) {
            return totalAmount;
        }

        return totalAmount + Number(draft.amount || 0);
    }, 0),
);

const paymentClubRules = [selectRequired];
const paymentMethodRules = [selectRequired];
const paidAtRules = [required];
const notesRules = [optionalLength(0, 1000)];

const referenceRules = [
    (value: string) => {
        if (!selectedPaymentMethod.value?.requires_reference) {
            return true;
        }

        return required(value);
    },
    optionalLength(0, 255),
];

const bankNameRules = [
    (value: string) => {
        if (!selectedPaymentMethod.value?.requires_bank_name) {
            return true;
        }

        return required(value);
    },
    optionalLength(0, 255),
];

const checkNumberRules = [
    (value: string) => {
        if (!selectedPaymentMethod.value?.requires_check_number) {
            return true;
        }

        return required(value);
    },
    optionalLength(0, 255),
];

const chargeAmountRules = (
    charge: ChargeItem,
    draft: PaymentDraftItem | undefined,
) => [
    (value: string | number) => {
        if (!draft?.selected) {
            return true;
        }

        return Number(value || 0) > 0 || "Captura un importe mayor a cero";
    },
    (value: string | number) => {
        if (!draft?.selected) {
            return true;
        }

        return (
            Number(value || 0) <= charge.balance ||
            "El importe no puede exceder el saldo pendiente"
        );
    },
    (value: string | number) => {
        if (!draft?.selected || charge.allows_partial_payments) {
            return true;
        }

        return (
            Number(Number(value || 0).toFixed(2)) ===
                Number(charge.balance.toFixed(2)) ||
            "Este cargo debe liquidarse completo"
        );
    },
];

// ── Computed anualidad ─────────────────────────────────────────────────────
const annualClubs = computed(() => {
    if (!annualAccount.value) return [];
    const seen = new Set<number>();
    const result: ClubItem[] = [];
    annualAccount.value.charges.forEach((c) => {
        if (c.club_id !== null && !seen.has(c.club_id)) {
            seen.add(c.club_id);
            result.push({ id: c.club_id, code: c.club_code ?? "", name: c.club_name ?? "" });
        }
    });
    return result;
});

const annualPaymentMethods = computed(() => {
    if (annualForm.club_id === null) return [];
    return clubPaymentMethods.value.find((c) => c.id === annualForm.club_id)?.payment_methods ?? [];
});

const annualPaymentMethod = computed(
    () => annualPaymentMethods.value.find((m) => m.id === annualForm.payment_method_id) ?? null,
);

const annualReferenceRules = [
    (v: string) => !annualPaymentMethod.value?.requires_reference || !!v || "La referencia es obligatoria para este método.",
    optionalLength(0, 255),
];
const annualBankRules = [
    (v: string) => !annualPaymentMethod.value?.requires_bank_name || !!v || "El banco es obligatorio para este método.",
    optionalLength(0, 255),
];
const annualCheckRules = [
    (v: string) => !annualPaymentMethod.value?.requires_check_number || !!v || "El número de cheque es obligatorio para este método.",
    optionalLength(0, 255),
];

const monthName = (num: number) => {
    const names = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
    return names[num - 1] ?? num;
};

const fetchAnnualPreview = async () => {
    if (!annualForm.membership_account_id || !annualForm.club_id || !annualForm.year || !annualForm.paid_at) {
        annualPreview.value = null;
        return;
    }
    annualPreviewLoading.value = true;
    annualPreviewError.value = null;
    try {
        const params = new URLSearchParams({
            membership_account_id: String(annualForm.membership_account_id),
            club_id: String(annualForm.club_id),
            year: String(annualForm.year),
            paid_at: annualForm.paid_at,
        });
        const res = await fetch(route("billing.annual-payment.preview") + "?" + params.toString(), {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        });
        if (!res.ok) throw new Error("Error al obtener la previsualización.");
        annualPreview.value = await res.json();
    } catch (e: any) {
        annualPreviewError.value = e.message ?? "Error desconocido.";
        annualPreview.value = null;
    } finally {
        annualPreviewLoading.value = false;
    }
};

const openAnnualModal = (account: BillingAccountItem) => {
    annualAccount.value = account;
    annualForm.reset();
    annualForm.clearErrors();
    annualFormRef.value?.resetValidation?.();
    annualForm.membership_account_id = account.id;
    annualForm.club_id = account.clubs[0]?.id ?? null;
    annualForm.year = new Date().getFullYear();
    annualForm.paid_at = nowAsLocalInput();
    annualPreview.value = null;
    annualPreviewError.value = null;
    showAnnualModal.value = true;
    fetchAnnualPreview();
};

const closeAnnualModal = () => {
    showAnnualModal.value = false;
    annualAccount.value = null;
    annualPreview.value = null;
    annualPreviewError.value = null;
    annualForm.reset();
    annualForm.clearErrors();
    annualFormRef.value?.resetValidation?.();
};

const submitAnnualPayment = async () => {
    const validationResult = await annualFormRef.value?.validate();
    if (validationResult && !validationResult.valid) {
        customToastSwal({ title: "Revisa los campos marcados antes de continuar.", icon: "warning" });
        return;
    }
    if (!annualPreview.value || annualPreview.value.charges_count === 0) {
        customToastSwal({ title: "No hay cargos pendientes para ese año y parque.", icon: "warning" });
        return;
    }

    annualForm.post(route("billing.annual-payment.store"), {
        preserveScroll: true,
        onSuccess: (pageResponse) => {
            customToastSwal({
                title: (pageResponse.props as any).flash?.success || "Pago de anualidad registrado.",
                icon: "success",
            });
            closeAnnualModal();
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${annualForm.errors.annual_messageError || "No se pudo registrar el pago."}`,
                text: annualForm.errors.annual_exception || "",
                icon: "error",
            });
        },
    });
};

const clearFilters = () => {
    search.value = "";
    selectedClubId.value = null;
    options.value.page = 1;
};

const buildPaymentDrafts = (clubId: number | null) => {
    if (clubId === null) {
        paymentDrafts.value = [];
        return;
    }

    paymentDrafts.value = selectedPaymentCharges.value.map((charge) => ({
        charge_id: charge.id,
        selected: false,
        amount: "0.00",
    }));
};

const openPaymentModal = (account: BillingAccountItem) => {
    selectedPaymentAccount.value = account;
    selectedPaymentClubId.value =
        account.clubs[0]?.id ?? account.charges[0]?.club_id ?? null;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentFormRef.value?.resetValidation?.();
    paymentForm.membership_account_id = account.id;
    paymentForm.club_id = selectedPaymentClubId.value;
    paymentForm.paid_at = nowAsLocalInput();
    buildPaymentDrafts(selectedPaymentClubId.value);
    showPaymentModal.value = true;
};

const closePaymentModal = () => {
    showPaymentModal.value = false;
    selectedPaymentAccount.value = null;
    selectedPaymentClubId.value = null;
    paymentDrafts.value = [];
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentFormRef.value?.resetValidation?.();
};

const toggleChargeSelection = (draft: PaymentDraftItem) => {
    const charge = selectedPaymentCharges.value.find(
        (item) => item.id === draft.charge_id,
    );

    if (!charge) {
        return;
    }

    if (draft.selected && Number(draft.amount || 0) <= 0) {
        draft.amount = charge.balance.toFixed(2);
    }

    if (!draft.selected) {
        draft.amount = "0.00";
    }
};

const submitPayment = async () => {
    if (!selectedPaymentAccount.value || selectedPaymentClubId.value === null) {
        customToastSwal({
            title: "Selecciona una cuenta y un parque para registrar el cobro.",
            icon: "warning",
        });
        return;
    }

    const validationResult = await paymentFormRef.value?.validate();

    if (validationResult && !validationResult.valid) {
        customToastSwal({
            title: "Revisa los campos marcados antes de guardar el cobro.",
            icon: "warning",
        });
        return;
    }

    const applications = paymentDrafts.value
        .filter((draft) => draft.selected && Number(draft.amount || 0) > 0)
        .map((draft) => ({
            charge_id: draft.charge_id,
            amount: Number(Number(draft.amount).toFixed(2)),
        }));

    if (!applications.length) {
        customToastSwal({
            title: "Selecciona al menos un cargo para registrar el cobro.",
            icon: "warning",
        });
        return;
    }

    paymentForm.membership_account_id = selectedPaymentAccount.value.id;
    paymentForm.club_id = selectedPaymentClubId.value;
    paymentForm.applications = applications;

    paymentForm.post(route("billing.payments.store"), {
        preserveScroll: true,
        onSuccess: (pageResponse) => {
            customToastSwal({
                title:
                    (pageResponse.props as any).flash?.success ||
                    "Cobro registrado correctamente.",
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

const fetchItems = async () => {
    loading.value = true;

    const params = {
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_club_id`]: selectedClubId.value,
    };

    router.get(route("billing.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            const accountPayload = (page.props as any).accounts ?? {
                data: [],
                total: 0,
            };

            items.value = accountPayload.data ?? [];
            total.value = accountPayload.total ?? 0;
            summary.value = (page.props as any).summary ?? summary.value;
            clubPaymentMethods.value =
                (page.props as any).clubPaymentMethods ??
                clubPaymentMethods.value;
            expanded.value = [];
            loading.value = false;
        },
        onError: () => {
            loading.value = false;
        },
    });
};

watch(
    () => props.accounts,
    (accounts) => {
        items.value = accounts.data ?? [];
        total.value = accounts.total ?? 0;
    },
    { deep: true },
);

watch(
    () => props.summary,
    (nextSummary) => {
        summary.value = { ...nextSummary };
    },
    { deep: true },
);

watch(
    () => props.clubPaymentMethods,
    (methods) => {
        clubPaymentMethods.value = methods ?? [];
    },
    { deep: true },
);

watch([search, selectedClubId], () => {
    options.value.page = 1;
});

watch(selectedPaymentClubId, (clubId) => {
    paymentForm.club_id = clubId;
    paymentForm.payment_method_id = null;
    paymentForm.reference = "";
    paymentForm.bank_name = "";
    paymentForm.check_number = "";
    buildPaymentDrafts(clubId);
});

watch([options, search, selectedClubId], debounce(fetchItems, 400), {
    deep: true,
});

watch(
    () => [annualForm.club_id, annualForm.year, annualForm.paid_at],
    debounce(fetchAnnualPreview, 500),
);
</script>

<template>
    <Head title="Cobranza" />

    <AppLayout>
        <template #header>Cobranza</template>

        <div class="d-flex flex-column ga-4">
            <v-alert type="info" variant="tonal">
                Este módulo concentra los cargos pendientes de ambos parques.
                Puedes filtrar por parque cuando lo necesites, pero la pantalla
                siempre te muestra la cuenta completa del usuario para evitar
                confusiones al cobrar.
            </v-alert>

            <v-row align="stretch">
                <v-col cols="12" lg="7" class="d-flex">
                    <v-row class="flex-grow-1">
                        <v-col cols="12" sm="6">
                            <v-card color="primary" variant="tonal" height="100%">
                                <v-card-text class="py-3">
                                    <div class="text-caption text-medium-emphasis">
                                        Saldo total pendiente
                                    </div>
                                    <div class="text-h6 font-weight-bold mt-2">
                                        {{ formatCurrency(summary.total_outstanding) }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-card color="error" variant="tonal" height="100%">
                                <v-card-text class="py-3">
                                    <div class="text-caption text-medium-emphasis">
                                        Saldo vencido
                                    </div>
                                    <div class="text-h6 font-weight-bold mt-2">
                                        {{
                                            formatCurrency(summary.overdue_outstanding)
                                        }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-card color="secondary" variant="tonal" height="100%">
                                <v-card-text class="py-3">
                                    <div class="text-caption text-medium-emphasis">
                                        Inscripciones pendientes
                                    </div>
                                    <div class="text-h6 font-weight-bold mt-2">
                                        {{
                                            formatCurrency(
                                                summary.inscription_outstanding,
                                            )
                                        }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-card color="success" variant="tonal" height="100%">
                                <v-card-text class="py-3">
                                    <div class="text-caption text-medium-emphasis">
                                        Cuentas con saldo
                                    </div>
                                    <div class="text-h6 font-weight-bold mt-2">
                                        {{ summary.accounts_with_balance }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis mt-1">
                                        Mensualidades:
                                        {{
                                            formatCurrency(summary.monthly_outstanding)
                                        }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-col>
                <v-col cols="12" lg="5">
                    <v-card height="100%">
                        <v-card-title
                            >Métodos disponibles por parque</v-card-title
                        >
                        <v-card-subtitle>
                            Ambos parques pueden registrar cobros, pero cada
                            cargo debe respetar los metodos habilitados para el
                            parque al que pertenece.
                        </v-card-subtitle>

                        <v-card-text class="d-flex flex-column ga-3 py-3">
                            <v-card
                                v-for="club in clubPaymentMethods"
                                :key="club.id"
                                variant="outlined"
                            >
                                <v-card-text class=" py-3">
                                    <div class="font-weight-bold">
                                        {{ club.code }} - {{ club.name }}
                                    </div>

                                    <div class="d-flex flex-wrap ga-2 mt-3">
                                        <v-chip
                                            v-for="method in club.payment_methods"
                                            :key="method.id"
                                            size="small"
                                            color="success"
                                            variant="tonal"
                                        >
                                            {{ method.internal_key ? `${method.name} (${method.internal_key})` : method.name }}
                                        </v-chip>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-row>
                <v-col cols="12" lg="12">
                    <v-card class="overflow-hidden">
                        <v-data-table-server
                            v-model:options="options"
                            v-model:expanded="expanded"
                            :headers="headers"
                            :items="items"
                            :items-length="total"
                            :loading="loading"
                            :items-per-page-options="[10, 25, 50, 100]"
                            :no-data-text="emptyMessage"
                            item-value="id"
                            hover
                            show-expand
                            class="elevation-0"
                            items-per-page-text="Mostrar"
                        >
                            <template #top>
                                <div class="pa-4">
                                    <v-row>
                                        <v-col cols="12" md="6">
                                            <v-text-field
                                                v-model="search"
                                                label="Buscar por no. cuenta, titular o concepto"
                                                prepend-inner-icon="mdi-magnify"
                                                clearable
                                                hide-details
                                            />
                                        </v-col>

                                        <v-col cols="12" md="6">
                                            <v-select
                                                v-model="selectedClubId"
                                                :items="clubFilterItems"
                                                label="Filtrar por parque del cargo"
                                                clearable
                                                hide-details
                                            />
                                        </v-col>

                                        <v-col
                                            cols="12"
                                            md="2"
                                            class="d-flex align-center"
                                        >
                                            <BaseButton
                                                v-if="hasActiveFilters"
                                                :icon-only="false"
                                                action="cancel"
                                                text="Limpiar"
                                                variant="tonal"
                                                @click="clearFilters"
                                            />
                                        </v-col>
                                    </v-row>
                                </div>
                            </template>

                            <template #item.membership_number="{ item }">
                                <div class="font-weight-bold">
                                    {{ item.membership_number }}
                                </div>
                                <div
                                    v-if="item.internal_account_number"
                                    class="text-caption text-primary font-weight-medium"
                                >
                                    <v-icon size="10" class="mr-1">mdi-pound</v-icon>{{ item.internal_account_number }}
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ item.pending_charges_count }} cargos
                                    pendientes
                                </div>
                            </template>

                            <template #item.holder_name="{ item }">
                                <div class="font-weight-medium">
                                    {{ item.holder_name }}
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ item.email || "Sin correo" }}
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ item.phone || "Sin teléfono" }}
                                </div>
                            </template>

                            <template #item.clubs="{ item }">
                                <div class="d-flex flex-wrap ga-2 py-2">
                                    <v-chip
                                        v-for="club in item.clubs"
                                        :key="club.id"
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                    >
                                        {{ club.code }} - {{ club.name }}
                                    </v-chip>
                                </div>
                            </template>

                            <template #item.charge_summary="{ item }">
                                <div class="d-flex flex-wrap ga-2 py-2">
                                    <v-chip
                                        v-for="concept in item.charge_summary"
                                        :key="`${item.id}-${concept.concept_name}`"
                                        size="small"
                                        variant="outlined"
                                    >
                                        {{ concept.concept_name }}:
                                        {{ formatCurrency(concept.balance) }}
                                    </v-chip>
                                </div>
                            </template>

                            <template #item.outstanding_balance="{ item }">
                                <div
                                    class="font-weight-bold text-high-emphasis"
                                >
                                    {{
                                        formatCurrency(item.outstanding_balance)
                                    }}
                                </div>
                                <div
                                    class="text-caption"
                                    :class="
                                        hasOverdueCharges(item)
                                            ? 'text-error'
                                            : 'text-medium-emphasis'
                                    "
                                >
                                    {{
                                        hasOverdueCharges(item)
                                            ? "Incluye cargos vencidos"
                                            : "Sin cargos vencidos"
                                    }}
                                </div>
                            </template>

                            <template #item.next_due_date="{ item }">
                                <div class="font-weight-medium">
                                    {{ formatDate(item.next_due_date) }}
                                </div>
                                <v-chip
                                    size="small"
                                    :color="
                                        resolveDueState(item.next_due_date)
                                            .color
                                    "
                                    variant="tonal"
                                    class="mt-1"
                                >
                                    {{
                                        resolveDueState(item.next_due_date)
                                            .label
                                    }}
                                </v-chip>
                            </template>

                            <template #item.actions="{ item }">
                                <div class="d-flex flex-wrap justify-end">
                                    <BaseButton
                                        v-if="can.includes('billing.store')"
                                        :icon-only="false"
                                        action="save"
                                        text="Registrar cobro"
                                        tooltip="Capturar un cobro para esta cuenta"
                                        @click="openPaymentModal(item)"
                                    />

                                    <BaseButton
                                        v-if="can.includes('billing.store')"
                                        :icon-only="false"
                                        action="add"
                                        text="Anualidad"
                                        tooltip="Registrar pago de anualidad con descuento"
                                        @click="openAnnualModal(item)"
                                    />

                                    <BaseButton
                                        :icon-only="false"
                                        action="view"
                                        text="Gestionar cuenta"
                                        :tooltip="manageAccountTooltip(item)"
                                        @click="
                                            router.visit(
                                                route(
                                                    'members.manage.show',
                                                    item.primary_membership_id,
                                                ),
                                            )
                                        "
                                        :disabled="!item.primary_membership_id"
                                    />
                                </div>
                            </template>

                            <template #expanded-row="{ columns, item }">
                                <tr>
                                    <td
                                        :colspan="columns.length"
                                        class="bg-grey-lighten-5"
                                    >
                                        <div class="pa-4">
                                            <v-row>
                                                <v-col cols="12" md="8">
                                                    <div
                                                        class="text-subtitle-1 font-weight-bold mb-3"
                                                    >
                                                        Cargos pendientes
                                                    </div>

                                                    <v-row
                                                        v-if="
                                                            item.charges.length
                                                        "
                                                    >
                                                        <v-col
                                                            v-for="charge in item.charges"
                                                            :key="charge.id"
                                                            cols="12"
                                                            md="6"
                                                        >
                                                            <v-card
                                                                variant="outlined"
                                                            >
                                                                <v-card-text>
                                                                    <div
                                                                        class="d-flex flex-wrap justify-space-between align-start ga-2"
                                                                    >
                                                                        <div>
                                                                            <div
                                                                                class="font-weight-bold"
                                                                            >
                                                                                {{
                                                                                    charge.concept_name ||
                                                                                    "Cargo"
                                                                                }}
                                                                            </div>
                                                                        <div
                                                                            class="text-caption text-medium-emphasis"
                                                                        >
                                                                            {{
                                                                                charge.origin_label
                                                                            }}
                                                                            <span
                                                                                v-if="
                                                                                    charge.membership_type_name
                                                                                "
                                                                            >
                                                                                ·
                                                                                {{
                                                                                    charge.membership_type_name
                                                                                }}
                                                                            </span>
                                                                        </div>
                                                                        <div
                                                                            class="text-caption text-medium-emphasis"
                                                                        >
                                                                            {{
                                                                                charge.description ||
                                                                                "Sin membresía asociada"
                                                                            }}
                                                                        </div>
                                                                    </div>

                                                                        <div
                                                                            class="d-flex flex-wrap ga-2"
                                                                        >
                                                                            <v-chip
                                                                                size="small"
                                                                                :color="
                                                                                    chargeConceptColor(
                                                                                        charge.concept_code,
                                                                                    )
                                                                                "
                                                                                variant="tonal"
                                                                            >
                                                                                {{
                                                                                    charge.club_code ||
                                                                                    "N/A"
                                                                                }}
                                                                            </v-chip>
                                                                            <v-chip
                                                                                size="small"
                                                                                :color="
                                                                                    chargeStatusColor(
                                                                                        charge.status,
                                                                                    )
                                                                                "
                                                                                variant="tonal"
                                                                            >
                                                                                {{
                                                                                    chargeStatusLabel(
                                                                                        charge.status,
                                                                                    )
                                                                                }}
                                                                            </v-chip>
                                                                        </div>
                                                                    </div>

                                                                    <div
                                                                        v-if="
                                                                            charge.description
                                                                        "
                                                                        class="text-body-2 mt-3"
                                                                    >
                                                                        {{
                                                                            charge.description
                                                                        }}
                                                                    </div>

                                                                    <div
                                                                        class="text-body-2 mt-3"
                                                                    >
                                                                        Parque:
                                                                        <span
                                                                            class="font-weight-medium"
                                                                        >
                                                                            {{
                                                                                charge.club_name ||
                                                                                "Sin definir"
                                                                            }}
                                                                        </span>
                                                                    </div>

                                                                    <div
                                                                        class="text-body-2 mt-1"
                                                                    >
                                                                        Vencimiento:
                                                                        <span
                                                                            class="font-weight-medium"
                                                                        >
                                                                            {{
                                                                                formatDate(
                                                                                    charge.due_date,
                                                                                )
                                                                            }}
                                                                        </span>
                                                                    </div>

                                                                    <div
                                                                        class="d-flex flex-wrap ga-2 mt-3"
                                                                    >
                                                                        <v-chip
                                                                            size="small"
                                                                            :color="
                                                                                resolveDueState(
                                                                                    charge.due_date,
                                                                                )
                                                                                    .color
                                                                            "
                                                                            variant="outlined"
                                                                        >
                                                                            {{
                                                                                resolveDueState(
                                                                                    charge.due_date,
                                                                                )
                                                                                    .label
                                                                            }}
                                                                        </v-chip>
                                                                        <v-chip
                                                                            size="small"
                                                                            :color="
                                                                                charge.allows_partial_payments
                                                                                    ? 'success'
                                                                                    : 'default'
                                                                            "
                                                                            variant="outlined"
                                                                        >
                                                                            {{
                                                                                charge.allows_partial_payments
                                                                                    ? "Admite parcialidades"
                                                                                    : "Pago completo"
                                                                            }}
                                                                        </v-chip>
                                                                        <v-chip
                                                                            v-for="badge in charge.badges"
                                                                            :key="`${charge.id}-${badge.label}`"
                                                                            size="small"
                                                                            :color="badge.color"
                                                                            variant="outlined"
                                                                        >
                                                                            {{
                                                                                badge.label
                                                                            }}
                                                                        </v-chip>
                                                                    </div>

                                                                    <div
                                                                        v-if="
                                                                            shouldShowChargeFeeBreakdown(
                                                                                charge,
                                                                            )
                                                                        "
                                                                        class="text-caption text-medium-emphasis mt-3"
                                                                    >
                                                                        <div
                                                                            v-if="
                                                                                charge.monthly_fee_total !==
                                                                                null
                                                                            "
                                                                        >
                                                                            Cuota total del esquema:
                                                                            {{
                                                                                formatCurrency(
                                                                                    charge.monthly_fee_total,
                                                                                )
                                                                            }}
                                                                        </div>
                                                                        <div
                                                                            v-if="
                                                                                charge.monthly_fee_share !==
                                                                                null
                                                                            "
                                                                        >
                                                                            Cuota de este parque:
                                                                            {{
                                                                                formatCurrency(
                                                                                    charge.monthly_fee_share,
                                                                                )
                                                                            }}
                                                                        </div>
                                                                        <div
                                                                            v-if="
                                                                                charge.effective_monthly_fee !==
                                                                                null &&
                                                                                charge.effective_monthly_fee !==
                                                                                    charge.monthly_fee_share
                                                                            "
                                                                        >
                                                                            Cargo aplicado:
                                                                            {{
                                                                                formatCurrency(
                                                                                    charge.effective_monthly_fee,
                                                                                )
                                                                            }}
                                                                        </div>
                                                                    </div>

                                                                    <div
                                                                        class="text-caption text-medium-emphasis mt-4"
                                                                    >
                                                                        Importe
                                                                        original:
                                                                        {{
                                                                            formatCurrency(
                                                                                charge.amount,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                    <div
                                                                        class="text-h6 font-weight-bold mt-1"
                                                                    >
                                                                        Saldo:
                                                                        {{
                                                                            formatCurrency(
                                                                                charge.balance,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </v-card-text>
                                                            </v-card>
                                                        </v-col>
                                                    </v-row>

                                                    <v-alert
                                                        v-else
                                                        type="info"
                                                        variant="tonal"
                                                    >
                                                        Esta cuenta no tiene
                                                        cargos pendientes para
                                                        mostrar.
                                                    </v-alert>
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-card
                                                        variant="tonal"
                                                        color="primary"
                                                    >
                                                        <v-card-text>
                                                            <div
                                                                class="text-subtitle-2 font-weight-bold"
                                                            >
                                                                Resumen rápido
                                                            </div>
                                                            <div
                                                                class="text-body-2 mt-3"
                                                            >
                                                                Titular:
                                                                <span
                                                                    class="font-weight-medium"
                                                                >
                                                                    {{
                                                                        item.holder_name
                                                                    }}
                                                                </span>
                                                            </div>
                                                            <div
                                                                class="text-body-2 mt-1"
                                                            >
                                                                No. cuenta:
                                                                <span
                                                                    class="font-weight-medium"
                                                                >
                                                                    {{
                                                                        item.membership_number
                                                                    }}
                                                                </span>
                                                            </div>
                                                            <div
                                                                class="text-body-2 mt-1"
                                                            >
                                                                Próximo
                                                                vencimiento:
                                                                <span
                                                                    class="font-weight-medium"
                                                                >
                                                                    {{
                                                                        formatDate(
                                                                            item.next_due_date,
                                                                        )
                                                                    }}
                                                                </span>
                                                            </div>
                                                            <div
                                                                class="text-body-2 mt-1"
                                                            >
                                                                Total por
                                                                cobrar:
                                                                <span
                                                                    class="font-weight-bold"
                                                                >
                                                                    {{
                                                                        formatCurrency(
                                                                            item.outstanding_balance,
                                                                        )
                                                                    }}
                                                                </span>
                                                            </div>

                                                            <BaseButton
                                                                v-if="
                                                                    can.includes(
                                                                        'billing.store',
                                                                    )
                                                                "
                                                                class="mt-3"
                                                                :icon-only="
                                                                    false
                                                                "
                                                                action="save"
                                                                text="Registrar cobro"
                                                                @click="
                                                                    openPaymentModal(
                                                                        item,
                                                                    )
                                                                "
                                                            />

                                                            <BaseButton
                                                                class="mt-3"
                                                                :icon-only="
                                                                    false
                                                                "
                                                                action="view"
                                                                text="Abrir cuenta del usuario"
                                                                :tooltip="
                                                                    manageAccountTooltip(
                                                                        item,
                                                                    )
                                                                "
                                                                @click="
                                                                    router.visit(
                                                                        route(
                                                                            'members.manage.show',
                                                                            item.primary_membership_id,
                                                                        ),
                                                                    )
                                                                "
                                                                :disabled="
                                                                    !item.primary_membership_id
                                                                "
                                                            />
                                                        </v-card-text>
                                                    </v-card>
                                                </v-col>
                                            </v-row>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </v-data-table-server>
                    </v-card>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showPaymentModal" max-width="1100" persistent scrollable>
                <v-card>
                    <v-card-title>Registrar cobro</v-card-title>
                    <v-card-subtitle>
                        Captura el pago y define exactamente a que cargos se
                        aplicará
                    </v-card-subtitle>

                    <v-card-text style="max-height:80vh" class="d-flex flex-column ga-4 overflow-y-auto">
                    <v-form
                        ref="paymentFormRef"
                        validate-on="input"
                        @submit.prevent="submitPayment"
                    >
                        <v-row v-if="selectedPaymentAccount">
                            <v-col cols="12" md="4">
                                <v-card variant="tonal" color="primary"> 
                                    <v-card-text class="py-3">
                                         <v-avatar
                                            size="100"
                                            class="mb-4"
                                        >
                                            <v-img
                                                :src="selectedPaymentAccount.photo"
                                                cover
                                            >
                                                <template #error>
                                                    <v-icon size="60">
                                                        mdi-account-circle
                                                    </v-icon>
                                                </template>
                                            </v-img>
                                        </v-avatar>
                                        <div
                                            class="text-caption text-medium-emphasis"
                                        >
                                            Titular
                                        </div>
                                        <div class="font-weight-bold">
                                            {{
                                                selectedPaymentAccount.holder_name
                                            }}
                                        </div>
                                        <div
                                            class="text-caption text-medium-emphasis mt-3"
                                        >
                                            No. cuenta
                                        </div>
                                        <div class="font-weight-medium">
                                            {{
                                                selectedPaymentAccount.membership_number
                                            }}
                                        </div>
                                        <div
                                            v-if="selectedPaymentAccount.internal_account_number"
                                            class="text-caption text-primary font-weight-medium mt-1"
                                        >
                                            <v-icon size="10" class="mr-1">mdi-pound</v-icon>{{ selectedPaymentAccount.internal_account_number }}
                                        </div>
                                        <div
                                            class="text-caption text-medium-emphasis mt-3"
                                        >
                                            Total seleccionado
                                        </div>
                                        <div class="text-h6 font-weight-bold">
                                            {{ formatCurrency(paymentTotal) }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>

                            <v-col cols="12" md="8">
                                <v-row>
                                    <v-col cols="12" md="4">
                                        <v-select
                                            v-model="selectedPaymentClubId"
                                            :items="
                                                selectedPaymentAccountClubs.map(
                                                    (club) => ({
                                                        title: `${club.code} - ${club.name}`,
                                                        value: club.id,
                                                    }),
                                                )
                                            "
                                            label="Parque del cobro"
                                            :rules="paymentClubRules"
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-select
                                            v-model="
                                                paymentForm.payment_method_id
                                            "
                                            :items="
                                                selectedPaymentMethods.map(
                                                    (method) => ({
                                                        title: method.internal_key ? `${method.name} (${method.internal_key})` : method.name,
                                                        value: method.id,
                                                    }),
                                                )
                                            "
                                            label="Método de pago"
                                            :rules="paymentMethodRules"
                                            :error-messages="
                                                paymentForm.errors
                                                    .payment_method_id
                                            "
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <v-col cols="12">
                                        <v-alert
                                            v-if="
                                                selectedPaymentMethod &&
                                                !selectedPaymentMethod.affects_cash_cut
                                            "
                                            type="info"
                                            variant="tonal"
                                        >
                                            Este pago se registrará como
                                            <strong>pago con servicios</strong>.
                                            Liquidará los cargos seleccionados,
                                            pero no impactará el corte de caja
                                            monetario.
                                        </v-alert>
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="paymentForm.paid_at"
                                            label="Fecha y hora del pago"
                                            type="datetime-local"
                                            :rules="paidAtRules"
                                            :error-messages="
                                                paymentForm.errors.paid_at
                                            "
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="paymentForm.reference"
                                            label="Referencia"
                                            :disabled="
                                                !selectedPaymentMethod?.requires_reference
                                            "
                                            :rules="referenceRules"
                                            :error-messages="
                                                paymentForm.errors.reference
                                            "
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="paymentForm.bank_name"
                                            label="Banco"
                                            :disabled="
                                                !selectedPaymentMethod?.requires_bank_name
                                            "
                                            :rules="bankNameRules"
                                            :error-messages="
                                                paymentForm.errors.bank_name
                                            "
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="paymentForm.check_number"
                                            label="Número de cheque"
                                            :disabled="
                                                !selectedPaymentMethod?.requires_check_number
                                            "
                                            :rules="checkNumberRules"
                                            :error-messages="
                                                paymentForm.errors.check_number
                                            "
                                            hide-details="auto"
                                        />
                                    </v-col>

                                    <v-col cols="12">
                                        <v-textarea
                                            v-model="paymentForm.notes"
                                            label="Notas"
                                            rows="2"
                                            auto-grow
                                            :rules="notesRules"
                                            :error-messages="
                                                paymentForm.errors.notes
                                            "
                                            hide-details="auto"
                                        />
                                    </v-col>
                                </v-row>
                            </v-col>
                        </v-row>

                        <v-alert
                            v-if="paymentForm.errors.applications"
                            type="error"
                            variant="tonal"
                        >
                            {{ paymentForm.errors.applications }}
                        </v-alert>

                        <div>
                            <div class="text-subtitle-1 font-weight-bold mb-3">
                                Cargos a aplicar
                            </div>

                            <v-row v-if="selectedPaymentCharges.length">
                                <v-col
                                    v-for="charge in selectedPaymentCharges"
                                    :key="charge.id"
                                    cols="12"
                                    md="6"
                                >
                                    <v-card variant="outlined">
                                        <v-card-text>
                                            <div
                                                class="d-flex justify-space-between align-start ga-3"
                                            >
                                                <div>
                                                    <div
                                                        class="font-weight-bold"
                                                    >
                                                        {{
                                                            charge.concept_name ||
                                                            "Cargo"
                                                        }}
                                                    </div>
                                                    <div
                                                        class="text-caption text-medium-emphasis"
                                                    >
                                                        {{
                                                            charge.origin_label
                                                        }}
                                                        <span
                                                            v-if="
                                                                charge.membership_type_name
                                                            "
                                                        >
                                                            ·
                                                            {{
                                                                charge.membership_type_name
                                                            }}
                                                        </span>
                                                    </div>
                                                    <div
                                                        class="text-caption text-medium-emphasis"
                                                    >
                                                        {{
                                                            charge.description ||
                                                            "Sin descripción"
                                                        }}
                                                    </div>
                                                </div>

                                                <v-checkbox
                                                    :model-value="
                                                        paymentDrafts.find(
                                                            (draft) =>
                                                                draft.charge_id ===
                                                                charge.id,
                                                        )?.selected || false
                                                    "
                                                    color="primary"
                                                    hide-details
                                                    @update:modelValue="
                                                        (value) => {
                                                            const draft =
                                                                paymentDrafts.find(
                                                                    (item) =>
                                                                        item.charge_id ===
                                                                        charge.id,
                                                                );
                                                            if (!draft) return;
                                                            draft.selected =
                                                                Boolean(value);
                                                            toggleChargeSelection(
                                                                draft,
                                                            );
                                                        }
                                                    "
                                                />
                                            </div>

                                            <div
                                                class="d-flex flex-wrap ga-2 mt-3"
                                            >
                                                <v-chip
                                                    size="small"
                                                    :color="
                                                        chargeStatusColor(
                                                            charge.status,
                                                        )
                                                    "
                                                    variant="tonal"
                                                >
                                                    {{
                                                        chargeStatusLabel(
                                                            charge.status,
                                                        )
                                                    }}
                                                </v-chip>
                                                <v-chip
                                                    size="small"
                                                    :color="
                                                        resolveDueState(
                                                            charge.due_date,
                                                        ).color
                                                    "
                                                    variant="outlined"
                                                >
                                                    {{
                                                        resolveDueState(
                                                            charge.due_date,
                                                        ).label
                                                    }}
                                                </v-chip>
                                                <v-chip
                                                    size="small"
                                                    :color="
                                                        charge.allows_partial_payments
                                                            ? 'success'
                                                            : 'default'
                                                    "
                                                    variant="outlined"
                                                >
                                                    {{
                                                        charge.allows_partial_payments
                                                            ? "Admite parcialidades"
                                                            : "Pago completo"
                                                    }}
                                                </v-chip>
                                                <v-chip
                                                    v-for="badge in charge.badges"
                                                    :key="`${charge.id}-${badge.label}`"
                                                    size="small"
                                                    :color="badge.color"
                                                    variant="outlined"
                                                >
                                                    {{ badge.label }}
                                                </v-chip>
                                            </div>

                                            <div
                                                v-if="
                                                    shouldShowChargeFeeBreakdown(
                                                        charge,
                                                    )
                                                "
                                                class="text-caption text-medium-emphasis mt-3"
                                            >
                                                <div
                                                    v-if="
                                                        charge.monthly_fee_total !==
                                                        null
                                                    "
                                                >
                                                    Cuota total del esquema:
                                                    {{
                                                        formatCurrency(
                                                            charge.monthly_fee_total,
                                                        )
                                                    }}
                                                </div>
                                                <div
                                                    v-if="
                                                        charge.monthly_fee_share !==
                                                        null
                                                    "
                                                >
                                                    Cuota de este parque:
                                                    {{
                                                        formatCurrency(
                                                            charge.monthly_fee_share,
                                                        )
                                                    }}
                                                </div>
                                                <div
                                                    v-if="
                                                        charge.effective_monthly_fee !==
                                                        null &&
                                                        charge.effective_monthly_fee !==
                                                            charge.monthly_fee_share
                                                    "
                                                >
                                                    Cargo aplicado:
                                                    {{
                                                        formatCurrency(
                                                            charge.effective_monthly_fee,
                                                        )
                                                    }}
                                                </div>
                                            </div>

                                            <div class="text-body-2 mt-3">
                                                Saldo pendiente:
                                                <span class="font-weight-bold">
                                                    {{
                                                        formatCurrency(
                                                            charge.balance,
                                                        )
                                                    }}
                                                </span>
                                            </div>

                                            <v-text-field
                                                :model-value="
                                                    paymentDrafts.find(
                                                        (draft) =>
                                                            draft.charge_id ===
                                                            charge.id,
                                                    )?.amount || '0.00'
                                                "
                                                label="Importe a aplicar"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="mt-3"
                                                :rules="
                                                    chargeAmountRules(
                                                        charge,
                                                        paymentDrafts.find(
                                                            (draft) =>
                                                                draft.charge_id ===
                                                                charge.id,
                                                        ),
                                                    )
                                                "
                                                :disabled="
                                                    !paymentDrafts.find(
                                                        (draft) =>
                                                            draft.charge_id ===
                                                            charge.id,
                                                    )?.selected ||
                                                    !charge.allows_partial_payments
                                                "
                                                @update:modelValue="
                                                    (value) => {
                                                        const draft =
                                                            paymentDrafts.find(
                                                                (item) =>
                                                                    item.charge_id ===
                                                                    charge.id,
                                                            );
                                                        if (!draft) return;
                                                        draft.amount = String(
                                                            value ?? '',
                                                        );
                                                    }
                                                "
                                            />

                                            <div
                                                v-if="
                                                    !charge.allows_partial_payments
                                                "
                                                class="text-caption text-medium-emphasis"
                                            >
                                                Este cargo debe liquidarse
                                                completo.
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <v-alert v-else type="info" variant="tonal">
                                No hay cargos pendientes para el parque
                                seleccionado.
                            </v-alert>
                        </div>
                    </v-form>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        <BaseButton
                            :icon-only="false"
                            action="cancel"
                            variant="tonal"
                            @click="closePaymentModal"
                        />
                        <BaseButton
                            :icon-only="false"
                            action="save"
                            text="Guardar cobro"
                            :loading="paymentForm.processing"
                            @click="submitPayment"
                        />
                    </v-card-actions>
                </v-card>
        </v-dialog>

        <!-- ── Modal: Pago de Anualidad ──────────────────────────────────── -->
        <v-dialog v-model="showAnnualModal" max-width="700" persistent scrollable>
            <v-card>
                <v-card-title>Pago de anualidad</v-card-title>
                <v-card-subtitle>
                    El sistema calcula el descuento automáticamente según el mes de pago.
                </v-card-subtitle>

                <v-card-text class="d-flex flex-column ga-4">
                    <v-form ref="annualFormRef" validate-on="input" @submit.prevent="submitAnnualPayment">
                        <!-- Titular -->
                        <v-card variant="tonal" color="primary" class="mb-4">
                            <v-card-text class="py-3">
                                <div class="text-caption text-medium-emphasis">Titular</div>
                                <div class="font-weight-bold">{{ annualAccount?.holder_name }}</div>
                                <div class="text-caption text-medium-emphasis mt-2">No. cuenta</div>
                                <div class="font-weight-medium">{{ annualAccount?.membership_number }}</div>
                            </v-card-text>
                        </v-card>

                        <v-row>
                            <!-- Parque -->
                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="annualForm.club_id"
                                    :items="annualClubs.map((c) => ({ title: `${c.code} - ${c.name}`, value: c.id }))"
                                    label="Parque"
                                    :rules="[selectRequired]"
                                    hide-details="auto"
                                />
                            </v-col>

                            <!-- Año -->
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model.number="annualForm.year"
                                    label="Año a cubrir"
                                    type="number"
                                    min="2020"
                                    max="2035"
                                    :rules="[required]"
                                    hide-details="auto"
                                />
                            </v-col>

                            <!-- Fecha del pago -->
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="annualForm.paid_at"
                                    label="Fecha y hora del pago"
                                    type="datetime-local"
                                    :rules="[required]"
                                    hide-details="auto"
                                />
                            </v-col>

                            <!-- Método de pago -->
                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="annualForm.payment_method_id"
                                    :items="annualPaymentMethods.map((m) => ({ title: m.internal_key ? `${m.name} (${m.internal_key})` : m.name, value: m.id }))"
                                    label="Método de pago"
                                    :rules="[selectRequired]"
                                    :error-messages="annualForm.errors.payment_method_id"
                                    hide-details="auto"
                                />
                            </v-col>

                            <!-- Referencia -->
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="annualForm.reference"
                                    label="Referencia"
                                    :disabled="!annualPaymentMethod?.requires_reference"
                                    :rules="annualReferenceRules"
                                    :error-messages="annualForm.errors.reference"
                                    hide-details="auto"
                                />
                            </v-col>

                            <!-- Banco -->
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="annualForm.bank_name"
                                    label="Banco"
                                    :disabled="!annualPaymentMethod?.requires_bank_name"
                                    :rules="annualBankRules"
                                    :error-messages="annualForm.errors.bank_name"
                                    hide-details="auto"
                                />
                            </v-col>

                            <!-- Cheque -->
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="annualForm.check_number"
                                    label="Número de cheque"
                                    :disabled="!annualPaymentMethod?.requires_check_number"
                                    :rules="annualCheckRules"
                                    :error-messages="annualForm.errors.check_number"
                                    hide-details="auto"
                                />
                            </v-col>

                            <!-- Notas -->
                            <v-col cols="12">
                                <v-textarea
                                    v-model="annualForm.notes"
                                    label="Notas"
                                    rows="2"
                                    auto-grow
                                    :rules="[optionalLength(0, 1000)]"
                                    :error-messages="annualForm.errors.notes"
                                    hide-details="auto"
                                />
                            </v-col>
                        </v-row>

                        <!-- Preview del descuento -->
                        <div class="mt-2">
                            <v-progress-linear v-if="annualPreviewLoading" indeterminate color="primary" class="mb-3" />

                            <v-alert v-if="annualPreviewError" type="error" variant="tonal" class="mb-3">
                                {{ annualPreviewError }}
                            </v-alert>

                            <v-card v-if="annualPreview && !annualPreviewLoading" variant="outlined">
                                <v-card-text>
                                    <div class="text-subtitle-2 font-weight-bold mb-3">
                                        Resumen del pago
                                    </div>

                                    <v-alert v-if="annualPreview.charges_count === 0" type="warning" variant="tonal">
                                        No se encontraron cargos de mensualidad pendientes para ese año y parque.
                                    </v-alert>

                                    <template v-else>
                                        <div class="text-body-2">
                                            Meses cubiertos:
                                            <span class="font-weight-medium">
                                                {{ annualPreview.months_covered.map(monthName).join(" · ") }}
                                                ({{ annualPreview.charges_count }} cargos)
                                            </span>
                                        </div>

                                        <div class="text-body-2 mt-1">
                                            Total de cargos pendientes:
                                            <span class="font-weight-medium">{{ formatCurrency(annualPreview.total_balance) }}</span>
                                        </div>

                                        <template v-if="annualPreview.discount_rule">
                                            <v-divider class="my-3" />
                                            <div class="text-body-2 text-success font-weight-medium">
                                                Descuento aplicado (pagando antes del mes
                                                {{ monthName(annualPreview.discount_rule.pay_by_month) }}):
                                                {{ annualPreview.discount_rule.discount_months === 1
                                                    ? "1 mes libre (diciembre)"
                                                    : `${annualPreview.discount_rule.discount_months} mes(es)` }}
                                            </div>
                                            <div class="text-body-2 text-success">
                                                Monto del descuento:
                                                <span class="font-weight-bold">- {{ formatCurrency(annualPreview.discount_amount) }}</span>
                                            </div>
                                            <div v-if="annualPreview.discount_rule.discount_months < 1" class="text-caption text-medium-emphasis mt-1">
                                                El descuento se genera como saldo a favor acumulable.
                                                Diciembre quedará con saldo pendiente de
                                                {{ formatCurrency(annualPreview.total_balance / annualPreview.charges_count - annualPreview.discount_amount) }}.
                                            </div>
                                        </template>

                                        <v-alert v-else type="info" variant="tonal" class="mt-3" density="compact">
                                            No aplica descuento de anualidad para el mes de pago seleccionado.
                                        </v-alert>

                                        <v-divider class="my-3" />
                                        <div class="text-subtitle-2 d-flex justify-space-between">
                                            <span>Total a cobrar</span>
                                            <span class="font-weight-bold text-primary">
                                                {{ formatCurrency(annualPreview.payment_amount) }}
                                            </span>
                                        </div>
                                    </template>
                                </v-card-text>
                            </v-card>
                        </div>
                    </v-form>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <BaseButton :icon-only="false" action="cancel" variant="tonal" @click="closeAnnualModal" />
                    <BaseButton
                        :icon-only="false"
                        action="save"
                        text="Registrar pago de anualidad"
                        :loading="annualForm.processing"
                        :disabled="!annualPreview || annualPreview.charges_count === 0 || annualPreviewLoading"
                        @click="submitAnnualPayment"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
