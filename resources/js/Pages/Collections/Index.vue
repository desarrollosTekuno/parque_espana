<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import PaymentMethodsDialog, {
    type PaymentConfirmPayload,
} from "@/Components/Collections/PaymentMethodsDialog.vue";
import CustomFileUploadField from "@/Components/CustomFileUploadField.vue";
import {
    fileMaxSizeRule,
    fileTypeRule,
    requiredFileRule,
} from "@/constants/validationRules";
import { Head, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import Swal from "sweetalert2";

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
    club_id: number | null;
    club_code: string | null;
}
interface ClubBreakdownItem {
    club_id: number | null;
    club_code: string | null;
    club_name: string | null;
    amount: number;
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
    is_multi_club: boolean;
    club_breakdown: ClubBreakdownItem[];
    charges: PendingChargeRef[];
}
interface AccountInfo {
    id: number;
    membership_number: string;
    internal_account_number: string | null;
    holder_name: string;
    holder_member_id: number | null;
    email: string | null;
    phone: string | null;
    photo: string | null;
}
interface ClubInfo {
    id: number;
    code: string;
    name: string;
}
interface ClubMembershipInfo {
    club_id: number | null;
    club_code: string | null;
    club_name: string | null;
    membership_number: string | null;
    is_cobro_club: boolean;
}
interface AccountMemberOption {
    id: number;
    name: string;
    has_locker: boolean;
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
    club_memberships?: ClubMembershipInfo[];
    account_members?: AccountMemberOption[];
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
    is_multi_club?: boolean;
    club_breakdown?: ClubBreakdownItem[];
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
const clubMemberships = computed(() => result.value?.club_memberships ?? []);
const accountMembers = computed(() => result.value?.account_members ?? []);
const billingMembershipId = computed(
    () => result.value?.billing_membership_id ?? null,
);
const pendingConcepts = computed(() => result.value?.pending_concepts ?? []);
const summary = computed(() => result.value?.summary ?? null);
const incidents = computed(() => result.value?.incidents ?? []);
const notes = ref<NoteItem[]>([]);
const signals = computed(() => result.value?.signals ?? []);
const paymentDialog = ref(false);
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
        paymentDialog.value = false;
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
    { title: "Saldo", key: "balance", sortable: false, align: "end" },
    { title: "", key: "actions", sortable: false, align: "end" },
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
        is_multi_club: concept.is_multi_club,
        club_breakdown: concept.club_breakdown,
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

// Función para llenar el concepto e importe
const applyConcept = (concept) => {
    console.log(concept);
    if (!concept) return;

    newItem.value.concept_id = concept.id;
    newItem.value.concept_code = concept.code;
    newItem.value.importe = concept.default_amount ?? 0;
};
watch(
    () => newItem.value.concept_code,
    (code) => {
        if (!code) return;

        const match = props.conceptOptions.find(
            c => c.code.toLowerCase() === code.trim().toLowerCase()
        );

        applyConcept(match);
    },
);

watch(
    () => newItem.value.concept_id,
    (id) => {
        const match = props.conceptOptions.find(c => c.id === id);

        applyConcept(match);
    },
);

// ── Asignación de casilleros desde "Agregar concepto de cobro" ──
// Al capturar el concepto LOCKERS, en vez del alta genérica de un cargo, se
// arma el mismo flujo que Members/Lockers/Create.vue (integrante, categoría,
// casillero disponible, comprobante) y el importe se llena solo con la cuota
// prorrateada del club hasta diciembre.
const selectedConcept = computed(() =>
    props.conceptOptions.find((c) => c.id === newItem.value.concept_id) ?? null,
);
const isLockerConcept = computed(
    () => selectedConcept.value?.code?.toUpperCase() === "LOCKERS",
);

const lockerMemberId = ref<number | null>(null);
const lockerCategory = ref<string | null>(null);
const lockerFile = ref<File[] | null>(null);
const lockerQuoteAmount = ref<number | null>(null);
const lockerLoadingQuote = ref(false);
const lockerLoadingAvailable = ref(false);
const lockerAvailable = ref<{ id: number; number: number }[]>([]);
const lockerSelectedId = ref<number | null>(null);
const lockerSearch = ref("");
const lockerPage = ref(1);
const lockerTotalPages = ref(1);
const lockerAssigning = ref(false);

const lockerFileRules = [
    requiredFileRule,
    fileTypeRule(["pdf", "jpg", "jpeg", "png"]),
    fileMaxSizeRule(2),
];
const lockerCategoryOptions = [
    { title: "Niños", value: "ninos" },
    { title: "Niñas", value: "ninas" },
    { title: "Caballeros", value: "caballeros" },
    { title: "Damas", value: "damas" },
];
const lockerMemberOptions = computed(() =>
    accountMembers.value.map((m) => ({
        title: m.name,
        value: m.id,
        props: { subtitle: m.has_locker ? "Ya tiene casillero" : null },
    })),
);

const resetLockerForm = () => {
    lockerMemberId.value = null;
    lockerCategory.value = null;
    lockerFile.value = null;
    lockerAvailable.value = [];
    lockerSelectedId.value = null;
    lockerSearch.value = "";
    lockerPage.value = 1;
    lockerTotalPages.value = 1;
};

const loadLockerQuote = async () => {
    if (!cobroClub.value) return;
    lockerLoadingQuote.value = true;
    try {
        const { data } = await window.axios.get(route("lockers.quote"), {
            params: { club_id: cobroClub.value.id },
        });
        lockerQuoteAmount.value = data.amount ?? 0;
        newItem.value.importe = data.amount ?? 0;
    } catch (e: any) {
        customToastSwal({
            title: e?.response?.data?.message || "No se pudo calcular la cuota del casillero.",
            icon: "error",
        });
    } finally {
        lockerLoadingQuote.value = false;
    }
};

const loadAvailableLockers = async () => {
    if (!cobroClub.value || !lockerCategory.value) {
        lockerAvailable.value = [];
        return;
    }
    lockerLoadingAvailable.value = true;
    try {
        const { data } = await window.axios.get(route("lockers.available"), {
            params: {
                club_id: cobroClub.value.id,
                category: lockerCategory.value,
                lockers_search: lockerSearch.value,
                page: lockerPage.value,
                lockers_per_page: 30,
            },
        });
        lockerAvailable.value = data.data;
        lockerTotalPages.value = data.last_page;
    } catch (e: any) {
        customToastSwal({
            title: "No se pudieron cargar los casilleros disponibles.",
            icon: "error",
        });
    } finally {
        lockerLoadingAvailable.value = false;
    }
};

watch(isLockerConcept, (isLocker) => {
    if (isLocker) {
        resetLockerForm();
        loadLockerQuote();
    } else {
        resetLockerForm();
    }
});
watch(lockerCategory, () => {
    lockerSelectedId.value = null;
    lockerPage.value = 1;
    loadAvailableLockers();
});
watch(lockerSearch, () => {
    lockerPage.value = 1;
    loadAvailableLockers();
});
watch(lockerPage, () => {
    loadAvailableLockers();
});

const canAssignLocker = computed(
    () =>
        !!lockerMemberId.value &&
        !!lockerCategory.value &&
        !!lockerSelectedId.value &&
        !!lockerFile.value?.length,
);

const assignLocker = async () => {
    if (!account.value || !canAssignLocker.value) {
        customToastSwal({
            title: "Completa integrante, categoría, casillero y comprobante.",
            icon: "warning",
        });
        return;
    }

    lockerAssigning.value = true;
    try {
        const formData = new FormData();
        formData.append("locker_id", String(lockerSelectedId.value));
        formData.append("member_id", String(lockerMemberId.value));
        if (billingMembershipId.value) {
            formData.append("membership_id", String(billingMembershipId.value));
        }
        formData.append("account_id", String(account.value.id));
        formData.append("as_json", "1");
        formData.append(
            "file",
            Array.isArray(lockerFile.value) ? lockerFile.value[0] : lockerFile.value,
        );

        const { data } = await window.axios.post(
            route("members.lockers.reserve"),
            formData,
        );

        cobros.value.push({
            key: `locker-${data.charge.id}-${Date.now()}`,
            type: "existing",
            concept_id: selectedConcept.value?.id,
            concept_label: `${selectedConcept.value?.code ?? "LOCKERS"} ${selectedConcept.value?.name ?? "Casillero"}`,
            detail: "Casillero (prorrateado a diciembre)",
            amount: data.charge.amount,
            charges: [{ charge_id: data.charge.id, amount: data.charge.amount }],
        });

        customToastSwal({ title: "Casillero asignado correctamente.", icon: "success" });
        resetNewItem();
        resetLockerForm();
    } catch (e: any) {
        customToastSwal({
            title:
                e?.response?.data?.message ||
                e?.response?.data?.errors?.locker?.[0] ||
                "No se pudo asignar el casillero.",
            icon: "error",
        });
    } finally {
        lockerAssigning.value = false;
    }
};

// ── Pase por día desde "Agregar concepto de cobro" ──
// Al capturar el concepto GUEST_LIST se arma el mismo flujo que
// AdminClubs/DayPasses/Index.vue (fecha + lista de visitantes), pero el
// socio responsable ya no se pregunta: se usa el titular de la cuenta que se
// está cobrando.
const isDayPassConcept = computed(
    () => selectedConcept.value?.code?.toUpperCase() === "GUEST_LIST",
);

interface DayPassVisitorForm {
    first_name: string;
    last_name: string;
    age: number | null;
    email: string;
}
const emptyDayPassVisitor = (): DayPassVisitorForm => ({
    first_name: "",
    last_name: "",
    age: null,
    email: "",
});

const dayPassDate = ref("");
const dayPassVisitors = ref<DayPassVisitorForm[]>([emptyDayPassVisitor()]);
const dayPassSubmitting = ref(false);

const resetDayPassForm = () => {
    dayPassDate.value = new Date().toISOString().slice(0, 10);
    dayPassVisitors.value = [emptyDayPassVisitor()];
};

const addDayPassVisitor = () => {
    dayPassVisitors.value.push(emptyDayPassVisitor());
};
const removeDayPassVisitor = (index: number) => {
    if (dayPassVisitors.value.length <= 1) return;
    dayPassVisitors.value.splice(index, 1);
};

const canSubmitDayPass = computed(
    () =>
        !!account.value?.holder_member_id &&
        !!dayPassDate.value &&
        dayPassVisitors.value.length > 0 &&
        dayPassVisitors.value.every(
            (v) => v.first_name.trim() && v.last_name.trim() && v.age !== null && v.age >= 0,
        ),
);

const submitDayPass = async () => {
    if (!account.value?.holder_member_id || !canSubmitDayPass.value) {
        customToastSwal({
            title: "Completa la fecha y los datos de todos los visitantes.",
            icon: "warning",
        });
        return;
    }

    dayPassSubmitting.value = true;
    try {
        const { data } = await window.axios.post(route("day-passes.store"), {
            member_id: account.value.holder_member_id,
            date: dayPassDate.value,
            visitors: dayPassVisitors.value.map((v) => ({
                first_name: v.first_name,
                last_name: v.last_name,
                age: v.age,
                email: v.email || null,
            })),
            as_json: 1,
        });

        cobros.value.push({
            key: `daypass-${data.charge.id}-${Date.now()}`,
            type: "existing",
            concept_id: selectedConcept.value?.id,
            concept_label: `${selectedConcept.value?.code ?? "GUEST_LIST"} ${selectedConcept.value?.name ?? "Pase por día"}`,
            detail: `${data.charge.total_visitors} visitante(s) — ${dayPassDate.value}`,
            amount: data.charge.amount,
            charges: [{ charge_id: data.charge.id, amount: data.charge.amount }],
        });

        customToastSwal({ title: "Pase por día registrado.", icon: "success" });
        resetNewItem();
        resetDayPassForm();
    } catch (e: any) {
        customToastSwal({
            title: e?.response?.data?.message || "No se pudo registrar el pase por día.",
            icon: "error",
        });
    } finally {
        dayPassSubmitting.value = false;
    }
};

watch(isDayPassConcept, (isDayPass) => {
    if (isDayPass) resetDayPassForm();
});

// ── Cafetería desde "Agregar concepto de cobro" ──
// Al capturar el concepto CAFETERIA_PASS se arma el mismo flujo de dos pasos
// que AdminClubs/CafeteriaVisits/Index.vue: entrada (se retiene una
// identificación, no genera cobro) y salida (se captura el consumo; si no
// alcanzó el mínimo, se agrega el cobro de acceso a la lista de cobros).
const isCafeteriaConcept = computed(
    () => selectedConcept.value?.code?.toUpperCase() === "CAFETERIA_PASS",
);
const cafeteriaMode = ref<"entrada" | "salida">("entrada");
const cafeteriaDocumentTypes = [
    { title: "INE", value: "INE" },
    { title: "Pasaporte", value: "pasaporte" },
    { title: "Licencia", value: "licencia" },
    { title: "Credencial de trabajo", value: "credencial_trabajo" },
    { title: "Otro", value: "otro" },
];

const cafeteriaVisitorName = ref("");
const cafeteriaDocumentType = ref<string | null>(null);
const cafeteriaDocumentNumber = ref("");
const cafeteriaNotes = ref("");
const cafeteriaCheckingIn = ref(false);

const cafeteriaOpenVisits = ref<
    { id: number; visitor_name: string; expires_at: string; min_consumption: number }[]
>([]);
const cafeteriaLoadingOpenVisits = ref(false);
const cafeteriaSelectedVisitId = ref<number | null>(null);
const cafeteriaConsumption = ref<number | null>(null);
const cafeteriaCheckingOut = ref(false);

const resetCafeteriaForm = () => {
    cafeteriaMode.value = "entrada";
    cafeteriaVisitorName.value = "";
    cafeteriaDocumentType.value = null;
    cafeteriaDocumentNumber.value = "";
    cafeteriaNotes.value = "";
    cafeteriaOpenVisits.value = [];
    cafeteriaSelectedVisitId.value = null;
    cafeteriaConsumption.value = null;
};

const loadOpenCafeteriaVisits = async () => {
    if (!cobroClub.value) return;
    cafeteriaLoadingOpenVisits.value = true;
    try {
        const { data } = await window.axios.get(route("cafeteria-visits.open"), {
            params: { club_id: cobroClub.value.id },
        });
        cafeteriaOpenVisits.value = data;
    } catch (e: any) {
        customToastSwal({
            title: "No se pudieron cargar las visitas de cafetería abiertas.",
            icon: "error",
        });
    } finally {
        cafeteriaLoadingOpenVisits.value = false;
    }
};

watch(isCafeteriaConcept, (isCafeteria) => {
    resetCafeteriaForm();
    if (isCafeteria) loadOpenCafeteriaVisits();
});
watch(cafeteriaMode, (mode) => {
    if (mode === "salida") loadOpenCafeteriaVisits();
});

const selectedCafeteriaVisit = computed(() =>
    cafeteriaOpenVisits.value.find((v) => v.id === cafeteriaSelectedVisitId.value) ?? null,
);

const canCheckInCafeteria = computed(
    () =>
        !!cafeteriaVisitorName.value.trim() &&
        !!cafeteriaDocumentType.value &&
        !!cafeteriaDocumentNumber.value.trim(),
);
const canCheckOutCafeteria = computed(
    () => !!cafeteriaSelectedVisitId.value && cafeteriaConsumption.value !== null && cafeteriaConsumption.value >= 0,
);

const checkInCafeteria = async () => {
    if (!canCheckInCafeteria.value) {
        customToastSwal({
            title: "Completa el nombre y el documento del visitante.",
            icon: "warning",
        });
        return;
    }

    cafeteriaCheckingIn.value = true;
    try {
        await window.axios.post(route("cafeteria-visits.store"), {
            visitor_name: cafeteriaVisitorName.value,
            document_type: cafeteriaDocumentType.value,
            document_number: cafeteriaDocumentNumber.value,
            notes: cafeteriaNotes.value || null,
            as_json: 1,
        });

        customToastSwal({ title: "Entrada de cafetería registrada.", icon: "success" });
        cafeteriaVisitorName.value = "";
        cafeteriaDocumentType.value = null;
        cafeteriaDocumentNumber.value = "";
        cafeteriaNotes.value = "";
    } catch (e: any) {
        customToastSwal({
            title: e?.response?.data?.message || "No se pudo registrar la entrada.",
            icon: "error",
        });
    } finally {
        cafeteriaCheckingIn.value = false;
    }
};

const checkOutCafeteria = async () => {
    if (!canCheckOutCafeteria.value) {
        customToastSwal({
            title: "Selecciona la visita y captura el consumo.",
            icon: "warning",
        });
        return;
    }

    cafeteriaCheckingOut.value = true;
    try {
        const { data } = await window.axios.post(
            route("cafeteria-visits.checkout", { cafeteriaVisit: cafeteriaSelectedVisitId.value }),
            {
                consumption_amount: cafeteriaConsumption.value,
                as_json: 1,
            },
        );

        if (data.charge) {
            cobros.value.push({
                key: `cafeteria-${data.charge.id}-${Date.now()}`,
                type: "existing",
                concept_id: selectedConcept.value?.id,
                concept_label: `${selectedConcept.value?.code ?? "CAFETERIA_PASS"} ${selectedConcept.value?.name ?? "Cafetería"}`,
                detail: `Acceso — ${selectedCafeteriaVisit.value?.visitor_name ?? "visitante"}`,
                amount: data.charge.amount,
                charges: [{ charge_id: data.charge.id, amount: data.charge.amount }],
            });
        }

        customToastSwal({ title: data.message, icon: "success" });
        resetNewItem();
        cafeteriaSelectedVisitId.value = null;
        cafeteriaConsumption.value = null;
        loadOpenCafeteriaVisits();
    } catch (e: any) {
        customToastSwal({
            title: e?.response?.data?.message || "No se pudo registrar la salida.",
            icon: "error",
        });
    } finally {
        cafeteriaCheckingOut.value = false;
    }
};

/*watch(
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
);*/

// Seleccionar el concepto rellena código e importe sugerido.
/*watch(
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
);*/

/*const newSubtotal = computed(
    () => Number(newItem.value.importe ?? 0) * Number(newItem.value.cantidad ?? 0),
);*/
const newIva = computed(() => {
    const total =
        Number(newItem.value.importe ?? 0) *
        Number(newItem.value.cantidad ?? 0);
    return total * (16 / 116);
});

const newSubtotal = computed(() => {
    const total =
        Number(newItem.value.importe ?? 0) *
        Number(newItem.value.cantidad ?? 0);

    return total - newIva.value;
});
const newTotal = computed(() =>
    Math.max(
        0,
        newSubtotal.value -
            Number(newItem.value.descuento ?? 0) +
            Number(newIva.value ?? 0),
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
    { title: "Importe", key: "amount", sortable: false, align: "end" },
    { title: "", key: "actions", sortable: false, align: "end", width: 56 },
];

const removeCobro = (key: string) => {
    cobros.value = cobros.value.filter((line) => line.key !== key);
};

const cobrosTotal = computed(() =>
    cobros.value.reduce((sum, line) => sum + Number(line.amount ?? 0), 0), 
);

const paying = ref(false);

const availablePaymentMethods = computed<PaymentMethodItem[]>(() => {
    if (!cobroClub.value) return [];
    return (
        props.clubPaymentMethods.find((c) => c.id === cobroClub.value!.id)
            ?.payment_methods ?? []
    );
});

/** Desglose por parque a mostrar en el modal, combinando todas las líneas
 *  de cobros que ya traigan un club_breakdown (mensualidad dividida). */
const dialogClubBreakdown = computed<ClubBreakdownItem[]>(() => {
    const byClub = new Map<string, ClubBreakdownItem>();

    cobros.value
        .filter((line) => line.type === "existing" && line.club_breakdown?.length)
        .forEach((line) => {
            line.club_breakdown!.forEach((club) => {
                const key = String(club.club_id ?? club.club_code ?? "");
                const current = byClub.get(key);
                if (current) {
                    current.amount = Number((current.amount + club.amount).toFixed(2));
                } else {
                    byClub.set(key, { ...club });
                }
            });
        });

    return Array.from(byClub.values());
});

const openPaymentDialog = () => {
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
    paymentDialog.value = true;
};

const submitPayment = async (payload: PaymentConfirmPayload) => {
    if (!account.value || !cobroClub.value) return;

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
        await window.axios.post(route("collections.payment.store"), {
            membership_account_id: account.value.id,
            club_id: cobroClub.value.id,
            payment_method_id: payload.payment_method_id,
            paid_at: payload.paid_at,
            reference: payload.reference,
            bank_name: payload.bank_name,
            check_number: payload.check_number,
            existing_charges,
            new_items,
        });

        const paymentMethod = availablePaymentMethods.value.find(
            (m) => m.id === payload.payment_method_id,
        );
        const isCardPayment = paymentMethod?.code
            ?.toUpperCase()
            .includes("CARD");

        paymentDialog.value = false;
        cobros.value = [];
        // Refresca el estado de cuenta del socio.
        await runSearch();

        await Swal.fire({
            icon: "success",
            title: "Pago registrado",
            html: isCardPayment ? "Entregue la tarjeta al socio." : "",
            confirmButtonText: "Continuar",
        });

        // La impresión de ticket se conecta más adelante; por ahora solo
        // se pregunta y se cierra el flujo sin efecto (no hay preConfirm
        // asíncrono, así que se desactiva el loader del botón de confirmar).
        await customConfirmSwal({
            title: "¿Desea imprimir ticket?",
            text: "",
            icon: "question",
            confirmText: "Sí, imprimir",
            cancelText: "No",
            showLoaderOnConfirm: false,
        });
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
                                    <v-avatar size="100" class="mb-4">
                                        <v-img :src="account.photo" cover>
                                            <template #error>
                                                <v-icon size="60">
                                                    mdi-account-circle
                                                </v-icon>
                                            </template>
                                        </v-img>
                                    </v-avatar>
                                <div class="text-h6 font-weight-bold">
                                    {{ account.holder_name }}
                                </div>
                                <div v-if="clubMemberships.length > 1" class="text-body-2">
                                    <div
                                        v-for="cm in clubMemberships"
                                        :key="cm.club_id ?? cm.club_code"
                                    >
                                        No. cuenta ({{ cm.club_code }}):
                                        <strong>{{ cm.membership_number }}</strong>
                                    </div>
                                    <span v-if="account.internal_account_number">
                                        Interna:
                                        <strong>{{ account.internal_account_number }}</strong>
                                    </span>
                                </div>
                                <div v-else class="text-body-2">
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
                                <div
                                    v-if="clubMemberships.length > 1"
                                    class="d-flex flex-wrap justify-end ga-2 mb-2"
                                >
                                    <v-chip
                                        v-for="cm in clubMemberships"
                                        :key="cm.club_id ?? cm.club_code"
                                        :color="cm.is_cobro_club ? 'primary' : undefined"
                                        :variant="cm.is_cobro_club ? 'flat' : 'outlined'"
                                    >
                                        {{ cm.club_code }} - {{ cm.club_name }}
                                    </v-chip>
                                </div>
                                <v-chip
                                    v-else-if="cobroClub"
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
                    <v-card-title>Cargos </v-card-title>
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
                            <v-chip
                                v-if="item.is_multi_club"
                                size="x-small"
                                class="ml-2"
                                color="warning"
                                variant="tonal"
                            >
                                Dividido entre parques
                            </v-chip>
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
                        <v-row no-gutters class="ga-2">
                            <v-col cols="6" md="1">
                                <v-text-field
                                    v-model="newItem.concept_code"
                                    label="Código"
                                    placeholder="Ej. MONTHLY_FEE"
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="12" md="2">
                                <v-select
                                    v-model="newItem.concept_id"
                                    :items="conceptSelectItems"
                                    label="Concepto"
                                    hide-details="auto"
                                />
                            </v-col>
                            <template v-if="!isLockerConcept && !isDayPassConcept && !isCafeteriaConcept">
                                <v-col cols="6" style="flex-basis: 150px; max-width: 120px;">
                                    <v-text-field
                                        v-model.number="newItem.importe"
                                        label="Importe"
                                        type="number"
                                        min="0"
                                        prefix="$"
                                        hide-details="auto"
                                    />
                                </v-col>
                                <v-col cols="6" md="1">
                                    <v-text-field
                                        v-model.number="newItem.cantidad"
                                        label="Cantidad"
                                        type="number"
                                        min="1"
                                        hide-details="auto"
                                    />
                                </v-col>

                                <v-col cols="6" md="1">
                                    <v-text-field
                                        :model-value="formatCurrency(newSubtotal)"
                                        label="Subtotal"
                                        readonly
                                        hide-details="auto"
                                    />
                                </v-col>
                                <v-col cols="6" md="1">
                                    <v-text-field
                                        v-model.number="newItem.descuento"
                                        label="Descuento ($)"
                                        type="number"
                                        min="0"
                                        prefix="$"
                                        hide-details="auto"
                                    />
                                </v-col>
                                <v-col cols="6" md="1">
                                    <v-text-field
                                        :model-value="newIva"
                                        label="IVA ($)"
                                        type="number"
                                        min="0"
                                        prefix="$"
                                        hide-details="auto"
                                    />
                                </v-col>
                                <v-col cols="6" md="1">
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
                            </template>

                            <!-- Asignación de casillero: al capturar el
                                 concepto LOCKERS se reemplaza la captura
                                 genérica por el mismo flujo de
                                 Members/Lockers/Create.vue, embebido aquí. -->
                            <template v-else-if="isLockerConcept">
                                <v-col cols="6" md="2">
                                    <v-text-field
                                        :model-value="formatCurrency(newItem.importe)"
                                        label="Importe (prorrateado a diciembre)"
                                        readonly
                                        :loading="lockerLoadingQuote"
                                        hide-details="auto"
                                    />
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-select
                                        v-model="lockerMemberId"
                                        :items="lockerMemberOptions"
                                        item-title="title"
                                        item-value="value"
                                        label="Integrante"
                                        hide-details="auto"
                                    />
                                </v-col>
                                <v-col cols="12" md="2">
                                    <v-select
                                        v-model="lockerCategory"
                                        :items="lockerCategoryOptions"
                                        label="Categoría"
                                        hide-details="auto"
                                    />
                                </v-col>

                                <v-col cols="12" md="7" v-if="lockerCategory">
                                    <v-progress-linear
                                        v-if="lockerLoadingAvailable"
                                        indeterminate
                                        color="primary"
                                        class="mb-2"
                                    />
                                    <v-alert
                                        v-else-if="!lockerAvailable.length"
                                        type="info"
                                        variant="tonal"
                                        density="compact"
                                        class="mb-2"
                                    >
                                        No hay casilleros disponibles en esta categoría.
                                    </v-alert>
                                    <template v-else>
                                        <v-text-field
                                            v-model="lockerSearch"
                                            label="Buscar casillero…"
                                            prepend-inner-icon="mdi-magnify"
                                            density="compact"
                                            hide-details="auto"
                                            class="mb-3"
                                            style="max-width: 320px"
                                        />
                                        <div class="locker-grid mb-2">
                                            <v-card
                                                v-for="locker in lockerAvailable"
                                                :key="locker.id"
                                                class="text-center cursor-pointer pa-3"
                                                :elevation="lockerSelectedId === locker.id ? 6 : 1"
                                                :color="lockerSelectedId === locker.id ? 'primary' : undefined"
                                                :variant="lockerSelectedId === locker.id ? 'flat' : 'outlined'"
                                                @click="lockerSelectedId = lockerSelectedId === locker.id ? null : locker.id"
                                            >
                                                <div class="text-subtitle-2 font-weight-bold">
                                                    {{ locker.number }}
                                                </div>
                                                <v-icon
                                                    v-if="lockerSelectedId === locker.id"
                                                    icon="mdi-check-circle"
                                                    size="small"
                                                />
                                            </v-card>
                                        </div>
                                        <v-pagination
                                            v-if="lockerTotalPages > 1"
                                            v-model="lockerPage"
                                            :length="lockerTotalPages"
                                            density="comfortable"
                                            :total-visible="7"
                                        />
                                    </template>
                                </v-col>

                                <v-col cols="12" md="4" class="mt-8">
                                    <div class="text-caption font-weight-medium mb-1">
                                        Comprobante de asignación de casillero
                                        <span class="text-error">*</span>
                                    </div>
                                    <CustomFileUploadField
                                        v-model="lockerFile"
                                        label="Seleccionar comprobante"
                                        hint="PDF, JPG o PNG · máx. 2 MB"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        :rules="lockerFileRules"
                                    />
                                </v-col>
                                <v-col cols="12" md="6" class="d-flex align-end">
                                    <BaseButton
                                        :icon-only="false"
                                        action="save"
                                        icon="mdi-locker"
                                        text="Asignar casillero"
                                        variant="tonal"
                                        :loading="lockerAssigning"
                                        :disabled="!canAssignLocker"
                                        @click="assignLocker"
                                    />
                                </v-col>
                            </template>

                            <!-- Pase por día: al capturar el concepto
                                 GUEST_LIST se reemplaza la captura genérica
                                 por fecha + lista de visitantes, igual que
                                 AdminClubs/DayPasses/Index.vue, sin pedir
                                 socio responsable (se usa el titular). -->
                            <template v-else-if="isDayPassConcept">
                                <v-col cols="12" md="5">
                                    <v-alert type="info" variant="tonal" density="compact">
                                        Responsable: <strong>{{ account?.holder_name }}</strong>
                                    </v-alert>
                                </v-col>
                                <v-col cols="12" md="3">
                                    <v-text-field
                                        v-model="dayPassDate"
                                        label="Fecha"
                                        type="date"
                                        hide-details="auto"
                                    />
                                </v-col>
                                <v-col cols="12" md="6" class="d-flex align-center">
                                    <BaseButton
                                        :icon-only="false"
                                        action="add"
                                        icon="mdi-account-plus"
                                        text="Agregar visitante"
                                        variant="tonal"
                                        @click="addDayPassVisitor"
                                    />
                                </v-col>

                                <v-col cols="12" v-for="(visitor, idx) in dayPassVisitors" :key="idx">
                                    <v-row no-gutters class="ga-2 align-center">
                                        <v-col cols="6" md="3">
                                            <v-text-field
                                                v-model="visitor.first_name"
                                                label="Nombre"
                                                hide-details="auto"
                                            />
                                        </v-col>
                                        <v-col cols="6" md="3">
                                            <v-text-field
                                                v-model="visitor.last_name"
                                                label="Apellido"
                                                hide-details="auto"
                                            />
                                        </v-col>
                                        <v-col cols="6" md="2">
                                            <v-text-field
                                                v-model.number="visitor.age"
                                                label="Edad"
                                                type="number"
                                                min="0"
                                                hide-details="auto"
                                            />
                                        </v-col>
                                        <v-col cols="6" md="3">
                                            <v-text-field
                                                v-model="visitor.email"
                                                label="Correo (opcional)"
                                                hide-details="auto"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="1">
                                            <BaseButton
                                                action="delete"
                                                icon="mdi-close"
                                                color="error"
                                                variant="text"
                                                size="small"
                                                :disabled="dayPassVisitors.length <= 1"
                                                tooltip="Quitar visitante"
                                                @click="removeDayPassVisitor(idx)"
                                            />
                                        </v-col>
                                    </v-row>
                                </v-col>

                                <v-col cols="12" class="d-flex justify-end">
                                    <BaseButton
                                        :icon-only="false"
                                        action="save"
                                        icon="mdi-ticket-confirmation"
                                        text="Registrar pase"
                                        variant="tonal"
                                        :loading="dayPassSubmitting"
                                        :disabled="!canSubmitDayPass"
                                        @click="submitDayPass"
                                    />
                                </v-col>
                            </template>

                            <!-- Cafetería: al capturar el concepto
                                 CAFETERIA_PASS se replica el flujo de dos
                                 pasos de AdminClubs/CafeteriaVisits/Index.vue
                                 (entrada / salida) embebido aquí. -->
                            <template v-else-if="isCafeteriaConcept">
                                <v-col cols="12" class="mt-6 mb-4">
                                    <v-btn-toggle v-model="cafeteriaMode" color="primary" density="comfortable" mandatory>
                                        <v-btn value="entrada">Entrada</v-btn>
                                        <v-btn value="salida">Salida</v-btn>
                                    </v-btn-toggle>
                                </v-col>

                                <template v-if="cafeteriaMode === 'entrada'">
                                    <v-row>
                                        <v-col cols="12" md="3">
                                            <v-text-field
                                                v-model="cafeteriaVisitorName"
                                                label="Nombre del visitante"
                                                hide-details="auto"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="3">
                                            <v-select
                                                v-model="cafeteriaDocumentType"
                                                :items="cafeteriaDocumentTypes"
                                                label="Tipo de documento"
                                                hide-details="auto"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="3">
                                            <v-text-field
                                                v-model="cafeteriaDocumentNumber"
                                                label="Número de documento"
                                                hide-details="auto"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="3">
                                            <v-text-field
                                                v-model="cafeteriaNotes"
                                                label="Notas (opcional)"
                                                hide-details="auto"
                                            />
                                        </v-col>
                                        <v-col cols="12" class="d-flex justify-end">
                                            <BaseButton
                                                :icon-only="false"
                                                action="save"
                                                icon="mdi-food"
                                                text="Registrar entrada"
                                                variant="tonal"
                                                :loading="cafeteriaCheckingIn"
                                                :disabled="!canCheckInCafeteria"
                                                @click="checkInCafeteria"
                                            />
                                        </v-col>
                                    </v-row>
                                </template>

                                <template v-else>
                                    <v-col cols="12" md="6">
                                        <v-progress-linear
                                            v-if="cafeteriaLoadingOpenVisits"
                                            indeterminate
                                            color="primary"
                                            class="mb-2"
                                        />
                                        <v-alert
                                            v-else-if="!cafeteriaOpenVisits.length"
                                            type="info"
                                            variant="tonal"
                                            density="compact"
                                        >
                                            No hay visitas de cafetería abiertas en este parque.
                                        </v-alert>
                                        <v-select
                                            v-else
                                            v-model="cafeteriaSelectedVisitId"
                                            :items="cafeteriaOpenVisits"
                                            item-title="visitor_name"
                                            item-value="id"
                                            label="Visita a cerrar"
                                            hide-details="auto"
                                        />
                                    </v-col>
                                    <v-col cols="12" md="3">
                                        <v-text-field
                                            v-model.number="cafeteriaConsumption"
                                            label="Consumo ($)"
                                            type="number"
                                            min="0"
                                            prefix="$"
                                            hide-details="auto"
                                        />
                                    </v-col>
                                    <v-col cols="12" md="3" v-if="selectedCafeteriaVisit">
                                        <div class="text-caption text-medium-emphasis">
                                            Mínimo de consumo para exentar el acceso
                                        </div>
                                        <div class="text-subtitle-1 font-weight-bold">
                                            {{ formatCurrency(selectedCafeteriaVisit.min_consumption) }}
                                        </div>
                                    </v-col>
                                    <v-col cols="12" class="d-flex justify-end">
                                        <BaseButton
                                            :icon-only="false"
                                            action="save"
                                            icon="mdi-food-off"
                                            text="Registrar salida"
                                            variant="tonal"
                                            :loading="cafeteriaCheckingOut"
                                            :disabled="!canCheckOutCafeteria"
                                            @click="checkOutCafeteria"
                                        />
                                    </v-col>
                                </template>
                            </template>
                        </v-row>
                         <v-row>
                            <v-col cols="12" md="12">
                                <v-card height="100%">
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
                                            <div class="d-flex justify-end">
                                                <BaseButton
                                                    action="delete"
                                                    icon="mdi-close"
                                                    color="error"
                                                    variant="text"
                                                    size="small"
                                                    tooltip="Quitar de la lista"
                                                    @click="removeCobro(item.key)"
                                                />
                                            </div>
                                        </template>
                                    </v-data-table>
                                    <v-divider />
                                    <v-card-text>
                                        <div class="d-flex flex-wrap justify-space-between align-center ga-4">
                                            <div class="d-flex align-center ga-2">
                                                <span class="text-subtitle-1 font-weight-bold">
                                                    Total a cobrar
                                                </span>
                                                <span class="text-h5 font-weight-bold text-primary">
                                                    {{ formatCurrency(cobrosTotal) }}
                                                </span>
                                            </div>

                                            <BaseButton
                                                v-if="can.includes('collections.store')"
                                                :icon-only="false"
                                                action="save"
                                                icon="mdi-cash-check"
                                                text="Efectuar cobro"
                                                :loading="paying"
                                                :disabled="!cobros.length"
                                                @click="openPaymentDialog"
                                            />
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>

                <PaymentMethodsDialog
                    v-model="paymentDialog"
                    :total="cobrosTotal"
                    :club-breakdown="dialogClubBreakdown"
                    :payment-methods="availablePaymentMethods"
                    :loading="paying"
                    @confirm="submitPayment"
                />

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

<style scoped>
.locker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(64px, 1fr));
    gap: 8px;
    max-width: 640px;
}
.cursor-pointer {
    cursor: pointer;
}
</style>
