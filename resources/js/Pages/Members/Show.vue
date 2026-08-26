<script setup lang="ts">
import AccountTreeNode from "@/Components/AccountTreeNode.vue";
import BaseButton from "@/Components/BaseButton.vue";
import CustomFileUploadField from "@/Components/CustomFileUploadField.vue";
import MonthPicker from "@/Components/MonthPicker.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { fileExactCountRule, fileMaxSizeRule, fileTypeRule, requiredFileRule, required, selectRequired, optionalLength } from "@/constants/validationRules";
import { Head, router, useForm, usePage  } from "@inertiajs/vue3";
import { nowAsLocalInput } from "@/constants/formatDates";
import Swal from "sweetalert2";
import { customToastSwal } from "@/utils/swal";
import { computed, onMounted, ref, watch } from "vue";

interface SourceMembership {
    id: number;
    membership_number: string | null;
    holder_name: string;
    membership_type_name: string | null;
    membership_type_code: string | null;
    club_name: string | null;
    club_code: string | null;
    status: string;
    start_date: string | null;
}

interface ActiveMembershipItem {
    id: number;
    membership_type_name: string | null;
    membership_type_code: string | null;
    club_name: string | null;
    club_code: string | null;
    monthly_fee: number;
    monthly_fee_total: number;
    monthly_fee_share: number;
    billing_split_mode: string | null;
    is_billable: boolean;
    status: string;
    start_date: string | null;
    end_date: string | null;
}

interface AbsencePermitItem {
    id: number;
    start_date: string;
    end_date: string;
    charge_percentage: number;
    status: string;
    blocks_facility_access: boolean;
    blocks_reservations: boolean;
    notes: string | null;
    approved_at: string | null;
}

interface MemberAddress {
    street: string | null;
    neighborhood: string | null;
    postal_code: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    years_in_city: number | null;
}

interface MemberEmployment {
    company_name: string | null;
    company_address: string | null;
    company_phone: string | null;
}

interface UploadedDoc {
    id: number | string;
    uploaded_at: string | null;
    url?: string | null;
}

interface MemberDocumentItem {
    document_type_id: number | string;
    name: string;
    allowed_extensions: string[];
    max_file_size_kb: number | null;
    is_required: boolean;
    is_club_specific?: boolean;
    allow_multiple: boolean;
    number_files: number;
    already_uploaded: boolean;
    uploaded_docs: UploadedDoc[];
}

interface AccountMemberItem {
    member_id: number;
    full_name: string;
    relationship_id: number | null;
    relationship_name: string | null;
    email: string | null;
    phone: string | null;
    is_primary_holder: boolean;
    birthdate: string | null;
    age: number | null;
    birth_place: string | null;
    city: string | null;
    state: string | null;
    nationality: string | null;
    marital_status: string | null;
    occupation: string | null;
    school_name: string | null;
    address: MemberAddress;
    employment: MemberEmployment;
    documents: MemberDocumentItem[];
}

interface MembershipAccount {
    id: number;
    membership_number: string | null;
    internal_account_number: string | null;
    account_club_name: string | null;
    account_club_code: string | null;
    account_type: string | null;
    status: string | null;
    current_monthly_fee: number;
    spans_multiple_clubs: boolean;
    absence_permit_preview_fee: number | null;
    current_absence_permit: AbsencePermitItem | null;
    absence_permits: AbsencePermitItem[];
    primary_holder: AccountMemberItem | null;
    members: AccountMemberItem[];
    active_memberships: ActiveMembershipItem[];
}

interface MembershipHistoryItem {
    id: number;
    effective_date: string;
    reason: string | null;
    previous_monthly_fee: number | null;
    new_monthly_fee: number | null;
    old_membership_type_name: string | null;
    new_membership_type_name: string;
    changed_by_name: string | null;
}

interface AccountTreeNode {
    id: number;
    membership_id: number | null;
    membership_number: string | null;
    holder_name: string;
    membership_type_name: string | null;
    status: string;
    separation_reason: string | null;
    derived?: AccountTreeNode[];
}

interface AccountTree {
    origin: AccountTreeNode | null;
    derived: AccountTreeNode[];
}

interface Props {
    membership: SourceMembership;
    account: MembershipAccount;
    accountTree?: AccountTree | null;
    canAddFamilyMembers?: boolean;
    canChangePrimaryHolder?: boolean;
    canSeparateMembers?: boolean;
}

const can = usePage().props.auth.permissions;

const props = withDefaults(defineProps<Props>(), {
    accountTree: null,
    canAddFamilyMembers: false,
    canChangePrimaryHolder: false,
    canSeparateMembers: false,
});

// Membership history (server-side paginated)
const historyItems = ref<MembershipHistoryItem[]>([]);
const historyTotal = ref(0);
const historyLoading = ref(false);
const historyPage = ref(1);
const historyPerPage = ref(10);

const fetchHistory = async () => {
    historyLoading.value = true;
    try {
        const res = await axios.get(
            route('members.manage.history', props.membership.id),
            { params: { page: historyPage.value, per_page: historyPerPage.value } }
        );
        historyItems.value = res.data.data;
        historyTotal.value  = res.data.total;
    } catch {
        // silent — table will stay empty
    } finally {
        historyLoading.value = false;
    }
};

onMounted(() => {
    fetchHistory();
    if (membersWithDocs.value.length > 0) {
        docsSelectedMemberId.value = membersWithDocs.value[0].member_id;
    }
});

const activeTab = ref('cuenta');

const docsSelectedMemberId = ref<number | null>(null);
const membersWithDocs = computed(() =>
    props.account.members.filter(m => m.documents?.length)
);
const currentDocsMember = computed(() =>
    membersWithDocs.value.find(m => m.member_id === docsSelectedMemberId.value) ?? null
);
// ─── Dialog: editar número de cuenta interno ──────────────────────────────────
const showInternalNumberDialog = ref(false);
const internalNumberForm = useForm({
    internal_account_number: props.account.internal_account_number ?? '',
});

const openInternalNumberDialog = () => {
    internalNumberForm.internal_account_number = props.account.internal_account_number ?? '';
    internalNumberForm.clearErrors();
    showInternalNumberDialog.value = true;
};

const saveInternalNumber = () => {
    internalNumberForm
        .transform((data) => ({
            internal_account_number: data.internal_account_number?.trim() || null,
        }))
        .patch(route('members.internal-account-number.update', props.membership.id), {
            onSuccess: () => {
                showInternalNumberDialog.value = false;
                customToastSwal({ title: 'Número de cuenta interno actualizado', icon: 'success' });
            },
        });
};

const showAbsencePermitDialog = ref(false);
const absencePermitFormRef = ref<{ validate(): Promise<{ valid: boolean }> } | null>(null);
const permitFiles = ref<File[] | null>(null);
const absencePermitForm = useForm({
    start_month: "",
    end_month: "",
    charge_percentage: 25,
    notes: "",
    absence_permit_document: null as File | null,
});

const permitDocRules = [
    requiredFileRule,
    fileTypeRule(["pdf", "jpg", "jpeg", "png"]),
    fileMaxSizeRule(2),
];

const currentMonth = computed(() => {
    const now = new Date();
    const mm = String(now.getMonth() + 1).padStart(2, "0");
    return `${now.getFullYear()}-${mm}`;
});

const minEndMonth = computed(() =>
    absencePermitForm.start_month || currentMonth.value
);

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const accountTypeLabel = computed(() =>
    props.account.account_type === "family" ? "Familiar" : "Individual",
);

const statusLabel = (status: string | null) => {
    const map: Record<string, string> = {
        active: "Activa",
        approved: "Programado",
        finished: "Finalizado",
        pending: "Pendiente",
        suspended: "Suspendida",
        cancelled: "Cancelada",
    };

    return status ? map[status] ?? status : "-";
};

const statusColor = (status: string | null) => {
    const map: Record<string, string> = {
        active: "success",
        approved: "info",
        finished: "default",
        pending: "warning",
        suspended: "orange",
        cancelled: "error",
    };

    return status ? map[status] ?? "default" : "default";
};

const formatDate = (value: string | null) => {
    if (!value) return "-";

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    }).format(date);
};

const addressSummary = (member: AccountMemberItem) => {
    const address = member.address;

    return [
        address.street,
        address.neighborhood,
        address.city,
        address.state,
        address.country,
    ]
        .filter(Boolean)
        .join(", ");
};

const openAbsencePermitDialog = () => {
    absencePermitForm.reset();
    absencePermitForm.clearErrors();
    absencePermitForm.charge_percentage = 25;
    permitFiles.value = null;
    absencePermitFormRef.value = null;
    showAbsencePermitDialog.value = true;
};

const submitAbsencePermit = async () => {
    const result = await absencePermitFormRef.value?.validate();
    if (!result?.valid) return;

    absencePermitForm.absence_permit_document = permitFiles.value?.[0] ?? null;

    absencePermitForm.post(route("members.absence-permits.store", props.membership.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showAbsencePermitDialog.value = false;
            absencePermitForm.reset();
            absencePermitForm.charge_percentage = 25;
            permitFiles.value = null;
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${absencePermitForm.errors.messageError || "No se pudo registrar el permiso."}`,
                text: absencePermitForm.errors.exception || "",
                icon: "error",
            });
        },
    });
};

const cancelAbsencePermit = (absencePermitId: number) => {
    router.patch(
        route("members.absence-permits.cancel", {
            membership: props.membership.id,
            absencePermit: absencePermitId,
        }),
        {},
        {
            preserveScroll: true,
        },
    );
};
// ── Document upload ───────────────────────────────────────────────────────────
const showDocumentModal = ref(false);
const documentModalMember = ref<AccountMemberItem | null>(null);
const documentModalDoc = ref<MemberDocumentItem | null>(null);
// Id del archivo puntual que se está reemplazando (ver botón "Reemplazar"
// junto a "Ver" en cada archivo ya cargado) — null cuando el modal se abrió
// para subir uno nuevo (o el primero de un tipo aún no completo).
const documentModalReplaceId = ref<number | null>(null);
const documentFiles = ref<File[] | null>(null);
const documentFormRef = ref<{ validate(): Promise<{ valid: boolean }> } | null>(null);
const documentForm = useForm({
    member_id: null as number | null,
    document_type_id: null as number | null,
    files: [] as File[],
    replace_document_id: null as number | null,
});

const DEFAULT_MAX_FILE_SIZE_KB = 2048;

const documentFileRules = computed(() => {
    const doc = documentModalDoc.value;
    const rules = [
        requiredFileRule,
        fileTypeRule(doc?.allowed_extensions ?? ["pdf", "jpg", "jpeg", "png"]),
        fileMaxSizeRule((doc?.max_file_size_kb ?? DEFAULT_MAX_FILE_SIZE_KB) / 1024),
    ];
    // Al reemplazar un archivo puntual siempre es 1 x 1, aunque el tipo de
    // documento admita varios (ver openDocumentModal).
    if (documentModalReplaceId.value === null && doc?.allow_multiple && (doc.number_files ?? 0) > 0) {
        rules.push(fileExactCountRule(doc.number_files));
    } else if (documentModalReplaceId.value !== null) {
        rules.push(fileExactCountRule(1));
    }
    return rules;
});

const viewDocument = async (docId: number) => {
    try {
        const res = await axios.get(route("member-documents.url", docId));
        window.open(res.data.url, "_blank");
    } catch {
        customToastSwal({ title: "No se pudo obtener el documento.", icon: "error" });
    }
};

// replaceId: cuando se le da "Reemplazar" a un archivo YA cargado en
// particular (ver el botón junto a "Ver" en cada archivo), en vez de
// "Subir"/"Reemplazar" a nivel de tipo de documento (que solo agrega un
// archivo más, ver storeDocument en el backend).
const openDocumentModal = (member: AccountMemberItem, doc: MemberDocumentItem, replaceId: number | null = null) => {
    documentModalMember.value = member;
    documentModalDoc.value = doc;
    documentModalReplaceId.value = replaceId;
    documentFiles.value = null;
    documentForm.reset();
    documentForm.clearErrors();
    documentFormRef.value = null;
    showDocumentModal.value = true;
};

const closeDocumentModal = () => {
    showDocumentModal.value = false;
    documentModalMember.value = null;
    documentModalDoc.value = null;
    documentModalReplaceId.value = null;
    documentFiles.value = null;
    documentForm.reset();
};

const submitDocument = async () => {
    const result = await documentFormRef.value?.validate();
    if (!result?.valid) return;

    documentForm.member_id = documentModalMember.value?.member_id ?? null;
    documentForm.document_type_id = documentModalDoc.value?.document_type_id ?? null;
    documentForm.files = documentFiles.value ?? [];
    documentForm.replace_document_id = documentModalReplaceId.value;

    documentForm.post(route("members.documents.store", props.membership.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: documentModalReplaceId.value ? "Documento reemplazado correctamente." : "Documento cargado correctamente.",
                icon: "success",
            });
            closeDocumentModal();
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${documentForm.errors.messageError || "No se pudo subir el documento."}`,
                text: documentForm.errors.exception || "",
                icon: "error",
            });
        },
    });
};

// Locker actions
// Locker actions
const showEditLockerModal = ref(false);
const editLockerFile =  ref<File[] | null>(null);
const editingMember = ref(null);
const editSelectedLocker = ref(null);
const availableEditLockers = ref([]);
const editLockerSearch = ref('');
const editCurrentPage = ref(1);
const editPerPage = ref(30);
const editTotal = ref(0);
const editTotalPages = ref(1);

const editLocker = async (member: any, locker: any) => {
    editingMember.value = {
        ...member,
        currentLocker: locker
    };
    editSelectedLocker.value = null;
    editCurrentPage.value = 1;
    showEditLockerModal.value = true;
    await loadAvailableEditLockers();
};
const loadAvailableEditLockers = async () => {
    try {
        const res = await axios.get(
            route('lockers.available.for.change'),
            {
                params: {
                    membership_id: props.membership.id,
                    current_locker_id: editingMember.value.locker.id,
                    category: editingMember.value.locker?.[0]?.category,
                    page: editCurrentPage.value,
                    lockers_per_page: editPerPage.value,
                    lockers_search: editLockerSearch.value,
                }
            }
        );
        availableEditLockers.value = res.data.data;
        editTotal.value = res.data.total;
        editTotalPages.value = res.data.last_page;
    } catch (e) {
        console.error(e);
    }
};
const updateLocker = async () => {
    if (!editSelectedLocker.value) {
        customToastSwal({
            title: 'Selecciona un casillero',
            icon: 'warning'
        });
        return;
    }

    if (!editLockerFile.value) {
        customToastSwal({
            title: 'Adjunta el comprobante',
            icon: 'warning'
        });
        return;
    }
console.log(editLockerFile.value);
    const result = await Swal.fire({
        title: '¿Cambiar casillero?',
        text: 'El integrante será asignado al nuevo casillero seleccionado.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        allowOutsideClick: false
    });

    if (!result.isConfirmed) return;

    const formData = new FormData();

    formData.append('member_id', editingMember.value.member_id);
    formData.append('old_locker_id', editingMember.value.currentLocker.locker_id);
    formData.append('new_locker_id', editSelectedLocker.value);
    formData.append(
        'file',
        Array.isArray(editLockerFile.value)
            ? editLockerFile.value[0]
            : editLockerFile.value
    );
console.log(editLockerFile.value);
    router.post(route('members.lockers.change'), formData, {
        forceFormData: true,
        preserveScroll: true,

        onSuccess: () => {
            showEditLockerModal.value = false;

            editLockerFile.value = null;
            editSelectedLocker.value = null;

            customToastSwal({
                title: 'Casillero actualizado',
                icon: 'success'
            });

            router.reload({ only: ['account'] });
        },

        onError: (errors) => {
            console.error(errors);

            const firstError =
                Object.values(errors)[0];

            customToastSwal({
                title: firstError || 'No se pudo actualizar el casillero',
                icon: 'error'
            });
        }
    });
};
const removeLocker = async (id: number) => {
    const result = await Swal.fire({
        title: '¿Dar de baja el casillero?',
        text: 'El casillero volverá a estar disponible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, dar de baja',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    });
    if (!result.isConfirmed) {
        return;
    }
    router.delete(route('members.lockers.remove', { assignment: id }), {
        onSuccess: () => {
            customToastSwal({
                title: 'Casillero dado de baja',
                icon: 'success'
            });

           // loadMyLockers();
        },
        onError: () => {
            customToastSwal({
                title: 'No se pudo dar de baja',
                icon: 'error'
            });
        }
    });
};

// ── Historia Clínica ──────────────────────────────────────────────────────────
interface ClinicalHistoryData {
    blood_type: string | null;
    blood_rh: string | null;
    has_diabetes: boolean;
    diabetes_type: string | null;
    has_heart_condition: boolean;
    has_epilepsy: boolean;
    has_asthma: boolean;
    has_allergy: boolean;
    takes_medication: boolean;
    medication_details: string | null;
    has_allergens: boolean;
    allergen_details: string | null;
    normal_blood_pressure: boolean | null;
    has_hypertension: boolean;
    special_conditions: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    emergency_contact_mobile: string | null;
    emergency_notify_name: string | null;
    treating_physician: string | null;
    treating_physician_phone: string | null;
    social_security_number: string | null;
    medical_insurance: string | null;
    insurance_company: string | null;
    insurance_policy_number: string | null;
    insurance_mobile: string | null;
}

interface ClinicalMember {
    member_id: number;
    member_name: string;
    is_primary_holder: boolean;
    history: ClinicalHistoryData | null;
}

const emptyForm = (): ClinicalHistoryData => ({
    blood_type: null,
    blood_rh: null,
    has_diabetes: false,
    diabetes_type: null,
    has_heart_condition: false,
    has_epilepsy: false,
    has_asthma: false,
    has_allergy: false,
    takes_medication: false,
    medication_details: null,
    has_allergens: false,
    allergen_details: null,
    normal_blood_pressure: null,
    has_hypertension: false,
    special_conditions: null,
    emergency_contact_name: null,
    emergency_contact_phone: null,
    emergency_contact_mobile: null,
    emergency_notify_name: null,
    treating_physician: null,
    treating_physician_phone: null,
    social_security_number: null,
    medical_insurance: null,
    insurance_company: null,
    insurance_policy_number: null,
    insurance_mobile: null,
});

const clinicalMembers = ref<ClinicalMember[]>([]);
const clinicalHistoryLoading = ref(false);
const clinicalSelectedMemberId = ref<number | null>(null);
const clinicalSaving = ref(false);
const clinicalFormRef = ref<{ validate(): Promise<{ valid: boolean }> } | null>(null);
const clinicalForm = ref<ClinicalHistoryData>(emptyForm());

const currentClinicalMember = computed(() =>
    clinicalMembers.value.find(m => m.member_id === clinicalSelectedMemberId.value) ?? null,
);

watch(clinicalSelectedMemberId, (memberId) => {
    const member = clinicalMembers.value.find(m => m.member_id === memberId);
    clinicalForm.value = member?.history ? { ...member.history } : emptyForm();
});

watch(activeTab, async (tab) => {
    if (tab === 'historia-clinica' && clinicalMembers.value.length === 0) {
        await loadClinicalHistories();
    }
    if (tab === 'historial-casilleros') {
        await loadLockerHistory();
    }
    if (tab === 'cargos-pendientes' && accountCharges.value.length === 0) {
        await loadAccountCharges();
    }
});

const loadClinicalHistories = async () => {
    clinicalHistoryLoading.value = true;
    try {
        const res = await axios.get(
            route('members.clinical-history.index', props.membership.id),
        );
        clinicalMembers.value = res.data;
        if (res.data.length > 0) {
            clinicalSelectedMemberId.value = res.data[0].member_id;
        }
    } catch {
        customToastSwal({ title: 'No se pudo cargar la historia clínica.', icon: 'error' });
    } finally {
        clinicalHistoryLoading.value = false;
    }
};

// ── Reglas de validación clínica ─────────────────────────────────────────────
const clinicalNameRule = (v: string | null) =>
    !v || /^[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s.'`\-]+$/.test(v) || 'Solo se permiten letras y espacios';

const clinicalPhoneRule = (v: string | null) =>
    !v || /^\d{10}$/.test(v) || 'Debe tener exactamente 10 dígitos';

const clinicalNssRule = (v: string | null) =>
    !v || /^\d{11}$/.test(v) || 'El NSS debe tener exactamente 11 dígitos';

// Bloquea cualquier tecla que no sea dígito (permite teclas de control)
const ALLOWED_CONTROL_KEYS = [
    'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight',
    'Tab', 'Enter', 'Home', 'End', 'Control', 'Meta', 'Shift',
];
const blockNonDigit = (e: KeyboardEvent) => {
    if (e.ctrlKey || e.metaKey) return; // permitir Ctrl+C, Ctrl+V, Ctrl+A
    if (!ALLOWED_CONTROL_KEYS.includes(e.key) && !/^\d$/.test(e.key)) {
        e.preventDefault();
    }
};

// Limpia pegado: solo extrae dígitos hasta el máximo permitido
const handleDigitPaste = (e: ClipboardEvent, field: keyof ClinicalHistoryData, maxLen: number) => {
    e.preventDefault();
    const raw = e.clipboardData?.getData('text/plain') ?? '';
    const clean = raw.replace(/\D/g, '').slice(0, maxLen);
    clinicalForm.value[field] = clean as any;
};

const saveClinicalHistory = async () => {
    if (!clinicalSelectedMemberId.value) return;

    const result = await clinicalFormRef.value?.validate();
    if (!result?.valid) return;

    clinicalSaving.value = true;
    try {
        await axios.put(
            route('members.clinical-history.upsert', {
                membership: props.membership.id,
                member: clinicalSelectedMemberId.value,
            }),
            clinicalForm.value,
        );

        const idx = clinicalMembers.value.findIndex(
            m => m.member_id === clinicalSelectedMemberId.value,
        );
        if (idx !== -1) {
            clinicalMembers.value[idx].history = { ...clinicalForm.value };
        }

        customToastSwal({ title: 'Historia clínica guardada correctamente.', icon: 'success' });
    } catch (err: any) {
        const msg = err?.response?.data?.message ?? 'No se pudo guardar la historia clínica.';
        customToastSwal({ title: msg, icon: 'error' });
    } finally {
        clinicalSaving.value = false;
    }
};

// Historico de casilleros
const showLockerHistoryModal = ref(false);
const historySearch = ref('');
const dateFrom = ref(null);
const dateTo = ref(null);
const lockerHistory = ref([]);
const loadingHistory = ref(false);

const options = ref({
    page: 1,
    itemsPerPage: 5,
    sortBy: ['created_at'],
    sortDesc: [true],
});

const headers = [
    { title: 'Fecha', key: 'created_at', sortable: true },
    { title: 'Integrante', key: 'member_name' },
    { title: 'Cambio', key: 'change', sortable: false },
    { title: 'Usuario', key: 'user', sortable: true },
    { title: 'Comprobante', key: 'file', sortable: false },
];
const loadLockerHistory = async () => {
    loadingHistory.value = true;
    try {
        const member = props.account.members.find(
            m => m.is_primary_holder
        );
        if (!member) return;
        const response = await axios.get(
            route('members.lockers.history', {
                member: member.member_id
            })
        );
        lockerHistory.value = response.data.data;
    } catch (error) {
        console.error(error);
    } finally {
        loadingHistory.value = false;
    }
};

const filteredHistory = computed(() => {
    let data = Array.isArray(lockerHistory.value)
        ? [...lockerHistory.value]
        : [];

    const search = historySearch.value?.toLowerCase();

    if (search) {
        data = data.filter(item =>
            item.old_locker?.number?.toString().includes(search) ||
            item.new_locker?.number?.toString().includes(search) ||
            item.created_at?.toLowerCase().includes(search)
        );
    }

    if (dateFrom.value) {
        data = data.filter(item => item.created_at >= dateFrom.value);
    }

    if (dateTo.value) {
        data = data.filter(item => item.created_at <= dateTo.value + 'T23:59:59');
    }

    return data;
});

watch(editCurrentPage, () => {
    if (!editingMember.value) {
        return;
    }
    loadAvailableEditLockers();
});

let lockerSearchTimeout: any = null;
watch(editLockerSearch, () => {
    if (!editingMember.value) {
        return;
    }
    clearTimeout(lockerSearchTimeout);
    lockerSearchTimeout = setTimeout(() => {
        editCurrentPage.value = 1;
        loadAvailableEditLockers();
    }, 400);
});
const letterRules = [
    requiredFileRule,
    fileTypeRule(["pdf", "jpg", "jpeg", "png"]),
    fileMaxSizeRule(2),
];

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
    badges: Array<{ label: string; color: string }>;
    target_monthly_fee: number | null;
    monthly_fee_total: number | null;
    monthly_fee_share: number | null;
    effective_monthly_fee: number | null;
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

interface MemberPaymentFormData {
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

const chargesLoading = ref(false);
const accountCharges = ref<ChargeItem[]>([]);
const billingClubPaymentMethods = ref<ClubPaymentMethodItem[]>([]);
const showMemberPaymentModal = ref(false);
const selectedMemberCharge = ref<ChargeItem | null>(null);
const memberPaymentAmount = ref('0.00');
const memberPaymentFormRef = ref();
const chargesTableOptions = ref({ page: 1, itemsPerPage: 10 });

const chargesHeaders = [
    { title: 'Parque',       key: 'club_name',     sortable: false },
    { title: 'Concepto',     key: 'concept_name',  sortable: false },
    { title: 'Descripción',  key: 'description',   sortable: false },
    { title: 'Vencimiento',  key: 'due_date',       sortable: false },
    { title: 'Estado',       key: 'status',         sortable: false },
    { title: 'Saldo',        key: 'balance',        sortable: false, align: 'end' as const },
    { title: 'Acciones',     key: 'actions',        sortable: false, align: 'center' as const },
];

const memberPaymentForm = useForm<MemberPaymentFormData>({
    membership_account_id: null,
    club_id: null,
    payment_method_id: null,
    paid_at: nowAsLocalInput(),
    reference: '',
    bank_name: '',
    check_number: '',
    notes: '',
    applications: [],
});

// Summary computeds
const chargesTotal = computed(() => accountCharges.value.reduce((s, c) => s + c.balance, 0));
const chargesOverdue = computed(() => {
    const today = new Date();
    const norm = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    return accountCharges.value.filter(c => c.due_date && new Date(`${c.due_date}T00:00:00`) < norm).reduce((s, c) => s + c.balance, 0);
});
const chargesMonthly = computed(() => accountCharges.value.filter(c => c.concept_code === 'MONTHLY_FEE').reduce((s, c) => s + c.balance, 0));
const chargesInscription = computed(() => accountCharges.value.filter(c => c.concept_code === 'INSCRIPTION').reduce((s, c) => s + c.balance, 0));

// Payment modal computeds
const memberPaymentMethods = computed(() => {
    if (selectedMemberCharge.value?.club_id == null) return [];
    return billingClubPaymentMethods.value.find(c => c.id === selectedMemberCharge.value!.club_id)?.payment_methods ?? [];
});

const memberSelectedPaymentMethod = computed(() =>
    memberPaymentMethods.value.find(m => m.id === memberPaymentForm.payment_method_id) ?? null,
);

const memberPaymentAmountRules = computed(() => [
    (v: string | number) => Number(v || 0) > 0 || 'Captura un importe mayor a cero',
    (v: string | number) => Number(v || 0) <= (selectedMemberCharge.value?.balance ?? 0) || 'El importe no puede exceder el saldo pendiente',
    (v: string | number) => {
        if (selectedMemberCharge.value?.allows_partial_payments) return true;
        return Number(Number(v || 0).toFixed(2)) === Number((selectedMemberCharge.value?.balance ?? 0).toFixed(2)) || 'Este cargo debe liquidarse completo';
    },
]);

const memberPaymentMethodRules = [selectRequired];
const memberPaidAtRules = [required];
const memberNotesRules = [optionalLength(0, 1000)];
const memberReferenceRules = [(v: string) => (!memberSelectedPaymentMethod.value?.requires_reference ? true : required(v)), optionalLength(0, 255)];
const memberBankNameRules = [(v: string) => (!memberSelectedPaymentMethod.value?.requires_bank_name ? true : required(v)), optionalLength(0, 255)];
const memberCheckNumberRules = [(v: string) => (!memberSelectedPaymentMethod.value?.requires_check_number ? true : required(v)), optionalLength(0, 255)];

// Helpers
const chargeStatusLabel = (status: string) =>
    ({ pending: 'Pendiente', partial: 'Parcial', paid: 'Pagado', cancelled: 'Cancelado' }[status] ?? status);

const chargeStatusColor = (status: string) =>
    ({ pending: 'warning', partial: 'info', paid: 'success', cancelled: 'default' }[status] ?? 'default');

const chargeConceptColor = (code: string | null) =>
    ({ MONTHLY_FEE: 'primary', INSCRIPTION: 'secondary', BUSINESS_AD: 'orange' }[code ?? ''] ?? 'default');

const resolveDueState = (value: string | null) => {
    if (!value) return { label: 'Sin fecha', color: 'default' };
    const dueDate = new Date(`${value}T00:00:00`);
    const today = new Date();
    const norm = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    if (dueDate < norm) return { label: 'Vencido', color: 'error' };
    if (dueDate.getTime() === norm.getTime()) return { label: 'Vence hoy', color: 'warning' };
    return { label: 'Por vencer', color: 'info' };
};

const formatCurrency = (value: number | null | undefined) =>
    currencyFormatter.format(Number(value ?? 0));

// Functions
const loadAccountCharges = async () => {
    chargesLoading.value = true;
    try {
        const res = await axios.get(route('members.billing.charges', props.membership.id));
        accountCharges.value = res.data.charges ?? [];
        billingClubPaymentMethods.value = res.data.club_payment_methods ?? [];
    } catch {
        customToastSwal({ title: 'No se pudieron cargar los cargos pendientes.', icon: 'error' });
    } finally {
        chargesLoading.value = false;
    }
};

const openMemberPaymentModal = (charge: ChargeItem) => {
    selectedMemberCharge.value = charge;
    memberPaymentAmount.value = charge.balance.toFixed(2);
    memberPaymentForm.reset();
    memberPaymentForm.clearErrors();
    memberPaymentFormRef.value?.resetValidation?.();
    memberPaymentForm.membership_account_id = props.account.id;
    memberPaymentForm.club_id = charge.club_id;
    memberPaymentForm.paid_at = nowAsLocalInput();
    showMemberPaymentModal.value = true;
};

const closeMemberPaymentModal = () => {
    showMemberPaymentModal.value = false;
    selectedMemberCharge.value = null;
    memberPaymentForm.reset();
    memberPaymentForm.clearErrors();
    memberPaymentFormRef.value?.resetValidation?.();
};

const submitMemberPayment = async () => {
    if (!selectedMemberCharge.value) return;

    const validationResult = await memberPaymentFormRef.value?.validate();
    if (validationResult && !validationResult.valid) {
        customToastSwal({ title: 'Revisa los campos marcados antes de guardar el cobro.', icon: 'warning' });
        return;
    }

    const amount = Number(Number(memberPaymentAmount.value).toFixed(2));
    if (amount <= 0) {
        customToastSwal({ title: 'Captura un importe mayor a cero.', icon: 'warning' });
        return;
    }

    memberPaymentForm.applications = [{ charge_id: selectedMemberCharge.value.id, amount }];

    memberPaymentForm.post(route('billing.payments.store'), {
        preserveScroll: true,
        onSuccess: (pageResponse) => {
            customToastSwal({
                title: (pageResponse.props as any).flash?.success || 'Cobro registrado correctamente.',
                icon: 'success',
            });
            closeMemberPaymentModal();
            accountCharges.value = [];
            loadAccountCharges();
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${memberPaymentForm.errors.messageError || 'No se pudo registrar el cobro.'}`,
                text: `${memberPaymentForm.errors.exception || ''}`,
                icon: 'error',
            });
        },
    });
};

console.log(can)
</script>

<template>
    <Head title="Gestionar Cuenta" />

    <AppLayout>
        <template #header>Gestionar Cuenta</template>
        <template #options>
            <div class="flex-wrap d-flex ga-2">
                <BaseButton
                    :icon-only="false"
                    action="cancel"
                    text="Volver"
                    @click="router.visit(route('members.index'))"
                />

                <BaseButton
                    v-if="props.canAddFamilyMembers"
                    :icon-only="false"
                    action="add"
                    text="Agregar familiar"
                    @click="
                        router.visit(
                            route('members.family-members.create', props.membership.id),
                        )
                    "
                />
            </div>
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12" md="11" class="mx-auto">
                    <v-container class="py-6">

                        <!-- Resumen siempre visible -->
                        <v-row>
                            <v-col cols="12" md="4">
                                <v-card class="pa-4 h-100" variant="tonal">
                                    <div class="text-caption text-medium-emphasis">Cuenta</div>
                                    <div class="text-h6 font-weight-bold">{{ props.account.membership_number || "-" }}</div>
                                    <div class="mt-2 text-body-2">{{ props.account.account_club_code || "-" }} · {{ props.account.account_club_name || "Sin club" }}</div>
                                    <div class="mt-2 text-body-2">Cuenta {{ accountTypeLabel }}</div>
                                    <div class="text-body-2">Estatus {{ statusLabel(props.account.status) }}</div>

                                    <!-- Número interno -->
                                    <v-divider class="my-2" />
                                    <div class="d-flex align-center justify-space-between">
                                        <div>
                                            <div class="text-caption text-medium-emphasis">No. interno</div>
                                            <div class="text-body-2 font-weight-medium">
                                                {{ props.account.internal_account_number || "Sin asignar" }}
                                            </div>
                                        </div>
                                        <v-btn
                                            icon="mdi-pencil"
                                            size="x-small"
                                            variant="text"
                                            @click="openInternalNumberDialog"
                                        />
                                    </div>
                                </v-card>
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-card class="pa-4 h-100" variant="tonal">
                                    <div class="text-caption text-medium-emphasis">Titular actual</div>
                                    <div class="text-h6 font-weight-bold">{{ props.account.primary_holder?.full_name || "-" }}</div>
                                    <div class="mt-2 text-body-2">{{ props.account.primary_holder?.email || "Sin correo" }}</div>
                                    <div class="text-body-2">{{ props.account.primary_holder?.phone || "Sin teléfono" }}</div>
                                </v-card>
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-card class="pa-4 h-100" variant="tonal">
                                    <div class="text-caption text-medium-emphasis">Cuota actual</div>
                                    <div class="text-h6 font-weight-bold">{{ currencyFormatter.format(props.account.current_monthly_fee) }}</div>
                                    <div class="flex-wrap mt-2 d-flex align-center ga-1">
                                        <span class="text-body-2">Total actual a cobrar</span>
                                        <v-chip v-if="props.account.spans_multiple_clubs" size="x-small" color="info" variant="tonal">
                                            Ambos parques
                                        </v-chip>
                                    </div>
                                </v-card>
                            </v-col>
                        </v-row>

                        <!-- Tabs -->
                        <v-tabs v-model="activeTab" class="mt-6" color="primary">
                            <v-tab v-if="can.includes('accounts.view')" value="cuenta" prepend-icon="mdi-card-account-details">Cuenta</v-tab>
                            <!-- <v-tab v-if="can.includes('billing.view')" value="cargos-pendientes" prepend-icon="mdi-cash-multiple">Cargos pendientes</v-tab> -->
                            <v-tab v-if="can.includes('members.view')" value="integrantes" prepend-icon="mdi-account-group">Integrantes</v-tab>
                            <v-tab v-if="can.includes('documents.view')" value="documentos" prepend-icon="mdi-file-document-multiple">Documentos</v-tab>
                            <v-tab v-if="can.includes('absences.view')" value="ausencias" prepend-icon="mdi-calendar-remove">Ausencias</v-tab>
                            <v-tab v-if="can.includes('clinical-history.view')" value="historia-clinica" prepend-icon="mdi-clipboard-pulse">Historia Clínica</v-tab>
                            <v-tab v-if="can.includes('history.view')" value="historial" prepend-icon="mdi-history">Historial</v-tab>
                            <v-tab v-if="can.includes('lockers-history.view')" value="historial-casilleros" prepend-icon="mdi-locker">Historial casilleros</v-tab>
                            <v-tab
                                v-if="props.accountTree && (props.accountTree.origin || props.accountTree.derived.length)"
                                value="arbol"
                                prepend-icon="mdi-family-tree"
                            >
                                Árbol
                            </v-tab>
                        </v-tabs>

                        <v-divider />

                        <v-window v-model="activeTab" class="mt-4">

                            <!-- ══ TAB: CUENTA ══ -->
                            <v-window-item value="cuenta" v-if="can.includes('accounts.view')">
                                <!-- Acciones -->
                                <v-card class="mb-4 pa-4">
                                    <div class="flex-wrap mb-2 d-flex align-center justify-space-between ga-2">
                                        <div>
                                            <div class="text-subtitle-1 font-weight-bold">Acciones de la cuenta</div>
                                            <div class="text-body-2 text-medium-emphasis">Gestiona la membresía y sus integrantes.</div>
                                        </div>
                                        <div class="flex-wrap d-flex ga-2">
                                            <v-btn v-if="can.includes('members.lockers.create')" color="primary" variant="tonal"
                                                @click="router.visit(route('members.lockers.create', props.account.id))">
                                                Asignar casillero
                                            </v-btn>
                                            <v-btn v-if="can.includes('acts.index')" color="primary" variant="tonal"
                                                @click="router.visit(route('acts.index', props.account.id))">
                                                Registrar multa
                                            </v-btn>
                                            <v-btn v-if="props.canChangePrimaryHolder" color="primary" variant="tonal"
                                                @click="router.visit(route('members.change-holder.create', props.membership.id))">
                                                Cambiar titular
                                            </v-btn>
                                            <v-btn v-if="props.canSeparateMembers" color="primary" variant="tonal"
                                                @click="router.visit(route('members.separation.create', props.membership.id))">
                                                Separar integrante
                                            </v-btn>
                                            <v-btn v-if="props.canAddFamilyMembers" color="primary"
                                                @click="router.visit(route('members.family-members.create', props.membership.id))">
                                                Agregar familiar
                                            </v-btn>
                                        </div>
                                    </div>
                                </v-card>

                                <!-- Membresías activas -->
                                <v-card class="pa-4">
                                    <div class="mb-4 text-subtitle-1 font-weight-bold">Membresías activas</div>
                                    <div class="d-flex flex-column ga-3">
                                        <div
                                            v-for="activeMembership in props.account.active_memberships"
                                            :key="activeMembership.id"
                                            class="px-4 py-3 border rounded-lg"
                                        >
                                            <div class="flex-wrap d-flex align-center justify-space-between ga-2">
                                                <div>
                                                    <div class="font-weight-medium">{{ activeMembership.membership_type_name }}</div>
                                                    <div class="text-caption text-medium-emphasis">{{ activeMembership.club_code }} · {{ activeMembership.club_name }}</div>
                                                </div>
                                                <div class="flex-wrap d-flex ga-2">
                                                    <v-chip size="small"
                                                        :color="activeMembership.is_billable ? 'success' : 'default'"
                                                        :variant="activeMembership.is_billable ? 'flat' : 'tonal'">
                                                        {{ activeMembership.is_billable ? "Se cobra" : "Incluida" }}
                                                    </v-chip>
                                                    <v-chip v-if="activeMembership.billing_split_mode === 'equal_split'" size="small" color="info" variant="tonal">50/50</v-chip>
                                                    <v-chip size="small" :color="statusColor(activeMembership.status)" variant="tonal">
                                                        {{ statusLabel(activeMembership.status) }}
                                                    </v-chip>
                                                </div>
                                            </div>
                                            <div v-if="activeMembership.is_billable" class="mt-2 text-body-2">
                                                Cuota a cobrar: {{ currencyFormatter.format(activeMembership.monthly_fee_share) }}
                                            </div>
                                            <div class="text-caption text-medium-emphasis">
                                                Vigencia: {{ formatDate(activeMembership.start_date) }} a {{ formatDate(activeMembership.end_date) }}
                                            </div>
                                        </div>
                                    </div>
                                </v-card>
                            </v-window-item>

                            <!-- ══ TAB: INTEGRANTES ══ -->
                            <v-window-item value="integrantes" v-if="can.includes('members.view')">
                                <v-card class="pa-4">
                                    <div class="flex-wrap mb-4 d-flex align-center justify-space-between ga-2">
                                        <div>
                                            <div class="text-subtitle-1 font-weight-bold">Integrantes de la cuenta</div>
                                            <div class="text-body-2 text-medium-emphasis">Información general de cada integrante.</div>
                                        </div>
                                        <v-chip color="primary" variant="tonal">{{ props.account.members.length }} integrante(s)</v-chip>
                                    </div>
                                    <v-row>
                                        <v-col v-for="member in props.account.members" :key="member.member_id" cols="12" md="6">
                                            <v-card variant="outlined" class="pa-4 h-100">
                                                <div class="flex-wrap mb-3 d-flex align-center justify-space-between ga-2">
                                                    <div>
                                                        <div class="font-weight-medium">{{ member.full_name }}</div>
                                                        <div class="text-caption text-medium-emphasis">{{ member.relationship_name || "Sin parentesco" }}</div>
                                                    </div>
                                                    <v-chip v-if="member.is_primary_holder" color="primary" size="small" variant="flat">Titular</v-chip>
                                                </div>
                                                <v-row>
                                                    <v-col cols="12" md="8">
                                                        <div class="text-body-2"><strong>Edad:</strong> {{ member.age ?? "-" }}</div>
                                                        <div class="text-body-2"><strong>Nacimiento:</strong> {{ formatDate(member.birthdate) }}</div>
                                                        <div class="text-body-2"><strong>Correo:</strong> {{ member.email || "-" }}</div>
                                                        <div class="text-body-2"><strong>Teléfono:</strong> {{ member.phone || "-" }}</div>
                                                        <div class="text-body-2"><strong>Nacionalidad:</strong> {{ member.nationality || "-" }}</div>
                                                        <div class="text-body-2"><strong>Estado civil:</strong> {{ member.marital_status || "-" }}</div>
                                                        <div class="text-body-2"><strong>Ocupación:</strong> {{ member.occupation || member.school_name || "-" }}</div>
                                                        <div class="text-body-2"><strong>Domicilio:</strong> {{ addressSummary(member) || "-" }}</div>
                                                    </v-col>
                                                    <v-col cols="12" md="4" class="mt-3">
                                                        <div v-if="member.locker?.length" class="locker-grid-mini">
                                                        <v-card
                                                                v-for="locker in member.locker"
                                                                :key="locker.assignment_id"
                                                                class="text-center locker-mini"
                                                                variant="outlined"
                                                                color="primary"
                                                            >
                                                                <!-- EDIT -->
                                                                <v-tooltip text="Editar casillero" location="top"  v-if="can.includes('members.lockers.change')">
                                                                    <template #activator="{ props }">
                                                                        <v-btn
                                                                            v-bind="props"
                                                                            icon
                                                                            size="x-small"
                                                                            variant="text"
                                                                            class="btn-edit"
                                                                            @click.stop="editLocker(member, locker)"
                                                                        >
                                                                            <v-icon size="16">mdi-pencil</v-icon>
                                                                        </v-btn>
                                                                    </template>
                                                                </v-tooltip>

                                                                <!-- DELETE -->
                                                                <v-tooltip text="Eliminar casillero" location="top"  v-if="can.includes('members.lockers.remove')">
                                                                    <template #activator="{ props }">
                                                                        <v-btn
                                                                            v-bind="props"
                                                                            icon
                                                                            size="x-small"
                                                                            variant="text"
                                                                            class="btn-delete"
                                                                            @click.stop="removeLocker(locker.assignment_id)"
                                                                        >
                                                                            <v-icon size="16">mdi-close</v-icon>
                                                                        </v-btn>
                                                                    </template>
                                                                </v-tooltip>

                                                                <!-- CONTENIDO -->
                                                                <div class="locker-content">
                                                                    <v-icon size="20">mdi-locker</v-icon>
                                                                    <div class="text-subtitle-2 font-weight-bold">
                                                                        {{ locker.number }}
                                                                    </div>
                                                                </div>
                                                            </v-card>
                                                        </div>
                                                        <div v-else class="text-center text-caption text-medium-emphasis">
                                                            Sin casilleros
                                                        </div>
                                                    </v-col>
                                                </v-row>
                                                <div class="justify-end mt-3 d-flex">
                                                    <v-btn size="small" variant="tonal" color="primary" prepend-icon="mdi-pencil"
                                                        @click="router.visit(route('members.member.edit', { membership: props.membership.id, member: member.member_id }))">
                                                        Editar
                                                    </v-btn>
                                                </div>
                                            </v-card>
                                        </v-col>
                                    </v-row>
                                </v-card>
                            </v-window-item>

                            <!-- ══ TAB: DOCUMENTOS ══ -->
                            <v-window-item value="documentos" v-if="can.includes('documents.view')">
                                <v-card class="pa-4">
                                    <div class="mb-4 text-subtitle-1 font-weight-bold">Documentación</div>

                                    <div v-if="!membersWithDocs.length" class="text-body-2 text-medium-emphasis">
                                        No hay documentos requeridos configurados para este tipo de membresía.
                                    </div>

                                    <template v-else>
                                        <!-- Tabs de integrantes (solo cuando hay más de uno) -->
                                        <v-tabs
                                            v-if="membersWithDocs.length > 1"
                                            v-model="docsSelectedMemberId"
                                            color="primary"
                                            density="compact"
                                            class="mb-4"
                                        >
                                            <v-tab
                                                v-for="m in membersWithDocs"
                                                :key="m.member_id"
                                                :value="m.member_id"
                                            >
                                                {{ m.full_name }}
                                                <v-icon v-if="m.is_primary_holder" size="14" class="ml-1">mdi-star</v-icon>
                                            </v-tab>
                                        </v-tabs>

                                        <v-row dense v-if="currentDocsMember">
                                            <v-col v-for="doc in currentDocsMember.documents" :key="doc.document_type_id" cols="12" sm="6" md="4">
                                                <v-card variant="outlined" class="pa-3 h-100 d-flex flex-column"
                                                    :color="!doc.already_uploaded && doc.is_required ? 'error' : undefined">
                                                    <div class="flex-wrap mb-2 d-flex align-center justify-space-between ga-1">
                                                        <span class="text-body-2 font-weight-medium">
                                                            {{ doc.name }}<span v-if="doc.is_required" class="text-error">*</span>
                                                        </span>
                                                        <v-chip size="x-small" :color="doc.already_uploaded ? 'success' : 'warning'"
                                                            :prepend-icon="doc.already_uploaded ? 'mdi-check-circle' : 'mdi-alert-circle'" variant="tonal">
                                                            {{ doc.already_uploaded ? 'Completo' : 'Pendiente' }}
                                                        </v-chip>
                                                    </div>
                                                    <v-chip v-if="doc.is_club_specific" size="x-small" color="info" variant="tonal"
                                                        prepend-icon="mdi-map-marker-outline" class="mb-2 align-self-start">
                                                        Propio de este parque
                                                    </v-chip>
                                                    <div class="mb-2 text-caption text-medium-emphasis">
                                                        {{ doc.allowed_extensions.join(', ').toUpperCase() }}
                                                        <template v-if="doc.allow_multiple"> · {{ doc.uploaded_docs.length }}/{{ doc.number_files }} archivo(s)</template>
                                                    </div>
                                                    <div v-if="doc.uploaded_docs.length" class="mb-3">
                                                        <div v-for="(uploaded, idx) in doc.uploaded_docs" :key="uploaded.id"
                                                            class="py-1 d-flex align-center justify-space-between">
                                                            <span class="text-caption text-medium-emphasis">
                                                                {{ doc.allow_multiple ? `Archivo ${idx + 1}` : 'Documento' }}
                                                                <template v-if="uploaded.uploaded_at"> · {{ uploaded.uploaded_at }}</template>
                                                            </span>
                                                            <div class="d-flex align-center ga-1">
                                                                <v-btn v-if="uploaded.url" size="x-small" variant="text" color="primary" prepend-icon="mdi-eye" :href="uploaded.url" target="_blank">
                                                                    Ver
                                                                </v-btn>

                                                                <v-btn v-else size="x-small" variant="text" color="primary" prepend-icon="mdi-eye" @click="viewDocument(uploaded.id as number)">
                                                                    Ver
                                                                </v-btn>

                                                                <!-- Solo documentos reales (no el comprobante de casillero,
                                                                     que no vive en members.documents) se pueden reemplazar. -->
                                                                <v-btn
                                                                    v-if="can.includes('members.documents.store') && typeof uploaded.id === 'number'"
                                                                    size="x-small" variant="text" color="default" prepend-icon="mdi-file-replace-outline"
                                                                    @click="openDocumentModal(currentDocsMember, doc, uploaded.id as number)"
                                                                >
                                                                    Reemplazar
                                                                </v-btn>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <v-spacer />
                                                    <v-btn v-if="can.includes('members.documents.store') && !doc.already_uploaded" size="small"
                                                        color="primary" variant="tonal" prepend-icon="mdi-upload"
                                                        @click="openDocumentModal(currentDocsMember, doc)">
                                                        Subir
                                                    </v-btn>
                                                </v-card>
                                            </v-col>
                                        </v-row>
                                    </template>
                                </v-card>
                            </v-window-item>

                            <!-- ══ TAB: AUSENCIAS ══ -->
                            <v-window-item value="ausencias" v-if="can.includes('absences.view')">
                                <v-card class="pa-4">
                                    <div class="flex-wrap mb-4 d-flex align-center justify-space-between ga-2">
                                        <div>
                                            <div class="text-subtitle-1 font-weight-bold">Permiso por ausencia</div>
                                            <div class="text-body-2 text-medium-emphasis">Durante su vigencia se cobra el porcentaje configurado sobre las membresías cobrables.</div>
                                        </div>
                                        <v-btn color="primary" variant="tonal" @click="openAbsencePermitDialog">Registrar permiso</v-btn>
                                    </div>

                                    <v-row class="mb-4">
                                        <v-col cols="12" md="4">
                                            <v-card variant="tonal" class="pa-4 h-100">
                                                <div class="text-caption text-medium-emphasis">Estado actual</div>
                                                <div class="text-h6 font-weight-bold">
                                                    {{ props.account.current_absence_permit ? statusLabel(props.account.current_absence_permit.status) : "Sin permiso activo" }}
                                                </div>
                                                <div v-if="props.account.current_absence_permit" class="mt-2 text-body-2">
                                                    Vigencia: {{ formatDate(props.account.current_absence_permit.start_date) }} a {{ formatDate(props.account.current_absence_permit.end_date) }}
                                                </div>
                                            </v-card>
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-card variant="tonal" class="pa-4 h-100">
                                                <div class="text-caption text-medium-emphasis">Porcentaje durante permiso</div>
                                                <div class="text-h6 font-weight-bold">
                                                    {{ props.account.current_absence_permit ? `${props.account.current_absence_permit.charge_percentage}%` : "25%" }}
                                                </div>
                                                <div class="mt-2 text-body-2">Aplicado sobre membresías cobrables del titular.</div>
                                            </v-card>
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-card variant="tonal" class="pa-4 h-100">
                                                <div class="text-caption text-medium-emphasis">Cuota estimada con permiso</div>
                                                <div class="text-h6 font-weight-bold">
                                                    {{ props.account.absence_permit_preview_fee !== null ? currencyFormatter.format(props.account.absence_permit_preview_fee) : "-" }}
                                                </div>
                                                <div class="mt-2 text-body-2">Estimado mensual mientras el permiso esté activo.</div>
                                            </v-card>
                                        </v-col>
                                    </v-row>

                                    <div class="mb-3 text-subtitle-2 font-weight-bold">Historial de permisos</div>
                                    <div v-if="!props.account.absence_permits.length" class="text-body-2 text-medium-emphasis">
                                        No hay permisos por ausencia registrados.
                                    </div>
                                    <div v-else class="d-flex flex-column ga-3">
                                        <div v-for="absencePermit in props.account.absence_permits" :key="absencePermit.id" class="px-4 py-3 border rounded-lg">
                                            <div class="flex-wrap d-flex align-center justify-space-between ga-2">
                                                <div>
                                                    <div class="font-weight-medium">{{ formatDate(absencePermit.start_date) }} a {{ formatDate(absencePermit.end_date) }}</div>
                                                    <div class="text-caption text-medium-emphasis">{{ absencePermit.charge_percentage }}% sobre cuota cobrable</div>
                                                </div>
                                                <div class="flex-wrap d-flex ga-2">
                                                    <v-chip size="small" :color="statusColor(absencePermit.status)" variant="tonal">{{ statusLabel(absencePermit.status) }}</v-chip>
                                                    <v-btn v-if="['approved', 'active'].includes(absencePermit.status)" color="error" size="small" variant="text"
                                                        @click="cancelAbsencePermit(absencePermit.id)">
                                                        Cancelar
                                                    </v-btn>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-body-2">
                                                {{ absencePermit.blocks_facility_access ? "Bloquea instalaciones" : "No bloquea instalaciones" }} ·
                                                {{ absencePermit.blocks_reservations ? "Bloquea reservaciones" : "No bloquea reservaciones" }}
                                            </div>
                                            <div v-if="absencePermit.notes" class="mt-2 text-body-2 text-medium-emphasis">{{ absencePermit.notes }}</div>
                                        </div>
                                    </div>
                                </v-card>
                            </v-window-item>

                            <!-- ══ TAB: HISTORIAL ══ -->
                            <v-window-item value="historial" v-if="can.includes('history.view')">
                                <v-card class="pa-4">
                                    <div class="mb-4 text-subtitle-1 font-weight-bold">Historial de membresía</div>
                                    <v-data-table-server
                                        :items="historyItems"
                                        :items-length="historyTotal"
                                        :loading="historyLoading"
                                        v-model:page="historyPage"
                                        v-model:items-per-page="historyPerPage"
                                        :items-per-page-options="[5, 10, 25]"
                                        density="compact"
                                        no-data-text="Sin eventos registrados."
                                        @update:options="fetchHistory"
                                        :headers="[
                                            { title: 'Fecha',          key: 'effective_date',           sortable: false },
                                            { title: 'Evento',         key: 'reason',                   sortable: false },
                                            { title: 'Tipo anterior',  key: 'old_membership_type_name', sortable: false },
                                            { title: 'Tipo nuevo',     key: 'new_membership_type_name', sortable: false },
                                            { title: 'Cuota anterior', key: 'previous_monthly_fee',     sortable: false, align: 'end' },
                                            { title: 'Cuota nueva',    key: 'new_monthly_fee',          sortable: false, align: 'end' },
                                            { title: 'Realizado por',  key: 'changed_by_name',          sortable: false },
                                        ]"
                                    >
                                        <template #item.effective_date="{ item }">
                                            <span class="text-no-wrap">{{ formatDate(item.effective_date) }}</span>
                                        </template>
                                        <template #item.reason="{ item }">{{ item.reason ?? '-' }}</template>
                                        <template #item.old_membership_type_name="{ item }">
                                            <span class="text-medium-emphasis">{{ item.old_membership_type_name ?? '-' }}</span>
                                        </template>
                                        <template #item.previous_monthly_fee="{ item }">
                                            <span class="text-medium-emphasis">{{ item.previous_monthly_fee !== null ? currencyFormatter.format(item.previous_monthly_fee) : '-' }}</span>
                                        </template>
                                        <template #item.new_monthly_fee="{ item }">
                                            {{ item.new_monthly_fee !== null ? currencyFormatter.format(item.new_monthly_fee) : '-' }}
                                        </template>
                                        <template #item.changed_by_name="{ item }">
                                            <span class="text-medium-emphasis">{{ item.changed_by_name ?? 'Sistema' }}</span>
                                        </template>
                                    </v-data-table-server>
                                </v-card>
                            </v-window-item>

                            <!-- ══ TAB: HISTORIAL CASILLEROS ══ -->
                            <v-window-item value="historial-casilleros" v-if="can.includes('lockers-history.view')">
                                <v-card class="pa-4">
                                    <div class="mb-4 text-subtitle-1 font-weight-bold">Historial de casilleros</div>
                                    <v-data-table
                                        :headers="headers"
                                        :items="filteredHistory"
                                        v-model:items-per-page="options.itemsPerPage"
                                        v-model:page="options.page"
                                        :items-per-page-options="[5, 10, 15]"
                                        class="custom-table"
                                    >

                                        <!-- Fecha -->
                                        <template #item.created_at="{ item }">
                                            <div class="text-caption text-medium-emphasis">
                                                {{ new Date(item.created_at).toLocaleString() }}
                                            </div>
                                        </template>
                                        <template #item.member_name="{ item }">
                                            <div class="font-weight-medium">
                                                {{ item.member_name }}
                                            </div>
                                        </template>
                                        <!-- Cambio -->
                                        <template #item.change="{ item }">
                                            <div class="d-flex align-center ga-2">
                                                <template v-if="item.old_locker">
                                                    <v-chip size="small" color="gray" variant="tonal">
                                                        {{ item.old_locker.number }}
                                                    </v-chip>

                                                    <v-icon size="16">mdi-arrow-right</v-icon>

                                                    <v-chip size="small" color="green" variant="tonal">
                                                        {{ item.new_locker?.number ?? 'N/A' }}
                                                    </v-chip>
                                                </template>
                                                <template v-else>
                                                    <v-chip size="small" color="primary" variant="tonal">
                                                        Asignación inicial
                                                    </v-chip>
                                                    <v-icon size="16">mdi-arrow-right</v-icon>
                                                    <v-chip size="small" color="green" variant="tonal">
                                                        {{ item.new_locker?.number ?? 'N/A' }}
                                                    </v-chip>
                                                </template>
                                            </div>
                                        </template>

                                        <!-- Usuario -->
                                        <template #item.user="{ item }">
                                            <v-chip size="small" variant="outlined">
                                                {{ item.user ?? 'Sistema' }}
                                            </v-chip>
                                        </template>

                                        <!-- Archivo -->
                                        <template #item.file="{ item }">
                                            <div class="d-flex ga-2">
                                                <!-- Ver -->
                                                <v-btn
                                                    v-if="item.file_url"
                                                    size="small"
                                                    variant="tonal"
                                                    color="primary"
                                                    :href="item.file_url"
                                                    target="_blank"
                                                >
                                                    <v-icon start size="16">mdi-eye</v-icon>
                                                    Ver
                                                </v-btn>
                                                <!-- Descargar -->
                                                <v-btn
                                                    v-if="item.file_url"
                                                    size="small"
                                                    variant="outlined"
                                                    color="primary"
                                                    :href="item.file_url"
                                                    download
                                                >
                                                    <v-icon start size="16">mdi-download</v-icon>
                                                    Descargar
                                                </v-btn>
                                                <span v-if="!item.file_url" class="text-medium-emphasis">—</span>
                                            </div>
                                        </template>

                                    </v-data-table>
                                </v-card>
                            </v-window-item>

                            <!-- ══ TAB: HISTORIA CLÍNICA ══ -->
                            <v-window-item value="historia-clinica" v-if="can.includes('clinical-history.view')">
                                <v-card class="pa-4">
                                    <div class="mb-4 d-flex align-center justify-space-between">
                                        <div>
                                            <div class="text-subtitle-1 font-weight-bold">Historia Clínica</div>
                                            <div class="text-body-2 text-medium-emphasis">Datos médicos del integrante.</div>
                                        </div>
                                    </div>

                                    <v-progress-linear v-if="clinicalHistoryLoading" indeterminate color="primary" class="mb-4" />

                                    <!-- Selector de integrante (solo cuando hay más de uno) -->
                                    <v-tabs
                                        v-if="clinicalMembers.length > 1"
                                        v-model="clinicalSelectedMemberId"
                                        color="primary"
                                        density="compact"
                                        class="mb-4"
                                    >
                                        <v-tab
                                            v-for="m in clinicalMembers"
                                            :key="m.member_id"
                                            :value="m.member_id"
                                        >
                                            {{ m.member_name }}
                                            <v-icon v-if="m.is_primary_holder" size="14" class="ml-1">mdi-star</v-icon>
                                        </v-tab>
                                    </v-tabs>

                                    <template v-if="currentClinicalMember">
                                        <v-form ref="clinicalFormRef">
                                            <!-- ── Tipo de sangre ── -->
                                            <div class="mt-2 mb-2 text-caption font-weight-bold text-uppercase text-medium-emphasis">Tipo de Sangre</div>
                                            <v-row dense>
                                                <v-col cols="12" sm="4">
                                                    <v-select
                                                        v-model="clinicalForm.blood_type"
                                                        label="Grupo sanguíneo"
                                                        :items="['A', 'B', 'AB', 'O']"
                                                        density="compact"
                                                        variant="outlined"
                                                        clearable
                                                    />
                                                </v-col>
                                                <v-col cols="12" sm="4">
                                                    <v-select
                                                        v-model="clinicalForm.blood_rh"
                                                        label="Factor RH"
                                                        :items="[{ title: 'Positivo (+)', value: 'positive' }, { title: 'Negativo (−)', value: 'negative' }]"
                                                        density="compact"
                                                        variant="outlined"
                                                        clearable
                                                    />
                                                </v-col>
                                            </v-row>

                                            <!-- ── Padecimientos ── -->
                                            <v-divider class="my-4" />
                                            <div class="mb-3 text-caption font-weight-bold text-uppercase text-medium-emphasis">Padecimientos</div>
                                            <v-row dense>
                                                <v-col cols="12" sm="6" md="4">
                                                    <div class="d-flex align-center ga-4">
                                                        <span class="text-body-2 flex-grow-1">Diabetes</span>
                                                        <v-btn-toggle v-model="clinicalForm.has_diabetes" density="compact" variant="outlined" divided mandatory color="#0a2540" selected-class="clinical-btn-active">
                                                            <v-btn :value="true" size="small">Sí</v-btn>
                                                            <v-btn :value="false" size="small">No</v-btn>
                                                        </v-btn-toggle>
                                                    </div>
                                                    <v-select
                                                        v-if="clinicalForm.has_diabetes"
                                                        v-model="clinicalForm.diabetes_type"
                                                        label="Tipo"
                                                        :items="['I', 'II']"
                                                        density="compact"
                                                        variant="outlined"
                                                        clearable
                                                        class="mt-2"
                                                    />
                                                </v-col>
                                                <v-col cols="12" sm="6" md="4">
                                                    <div class="d-flex align-center ga-4">
                                                        <span class="text-body-2 flex-grow-1">Cardiopatía</span>
                                                        <v-btn-toggle v-model="clinicalForm.has_heart_condition" density="compact" variant="outlined" divided mandatory color="#0a2540" selected-class="clinical-btn-active">
                                                            <v-btn :value="true" size="small">Sí</v-btn>
                                                            <v-btn :value="false" size="small">No</v-btn>
                                                        </v-btn-toggle>
                                                    </div>
                                                </v-col>
                                                <v-col cols="12" sm="6" md="4">
                                                    <div class="d-flex align-center ga-4">
                                                        <span class="text-body-2 flex-grow-1">Epilepsia</span>
                                                        <v-btn-toggle v-model="clinicalForm.has_epilepsy" density="compact" variant="outlined" divided mandatory color="#0a2540" selected-class="clinical-btn-active">
                                                            <v-btn :value="true" size="small">Sí</v-btn>
                                                            <v-btn :value="false" size="small">No</v-btn>
                                                        </v-btn-toggle>
                                                    </div>
                                                </v-col>
                                                <v-col cols="12" sm="6" md="4">
                                                    <div class="d-flex align-center ga-4">
                                                        <span class="text-body-2 flex-grow-1">Asma</span>
                                                        <v-btn-toggle v-model="clinicalForm.has_asthma" density="compact" variant="outlined" divided mandatory color="#0a2540" selected-class="clinical-btn-active">
                                                            <v-btn :value="true" size="small">Sí</v-btn>
                                                            <v-btn :value="false" size="small">No</v-btn>
                                                        </v-btn-toggle>
                                                    </div>
                                                </v-col>
                                                <v-col cols="12" sm="6" md="4">
                                                    <div class="d-flex align-center ga-4">
                                                        <span class="text-body-2 flex-grow-1">Alergia</span>
                                                        <v-btn-toggle v-model="clinicalForm.has_allergy" density="compact" variant="outlined" divided mandatory color="#0a2540" selected-class="clinical-btn-active">
                                                            <v-btn :value="true" size="small">Sí</v-btn>
                                                            <v-btn :value="false" size="small">No</v-btn>
                                                        </v-btn-toggle>
                                                    </div>
                                                </v-col>
                                            </v-row>

                                            <!-- ── Medicamentos ── -->
                                            <v-divider class="my-4" />
                                            <div class="mb-3 text-caption font-weight-bold text-uppercase text-medium-emphasis">Medicamentos</div>
                                            <v-row dense>
                                                <v-col cols="12" sm="6" md="4">
                                                    <div class="d-flex align-center ga-4">
                                                        <span class="text-body-2 flex-grow-1">¿Toma medicamentos?</span>
                                                        <v-btn-toggle v-model="clinicalForm.takes_medication" density="compact" variant="outlined" divided mandatory color="#0a2540" selected-class="clinical-btn-active">
                                                            <v-btn :value="true" size="small">Sí</v-btn>
                                                            <v-btn :value="false" size="small">No</v-btn>
                                                        </v-btn-toggle>
                                                    </div>
                                                </v-col>
                                                <v-col v-if="clinicalForm.takes_medication" cols="12">
                                                    <v-textarea
                                                        v-model="clinicalForm.medication_details"
                                                        label="Especifique los medicamentos"
                                                        density="compact"
                                                        variant="outlined"
                                                        rows="2"
                                                        counter="1000"
                                                    />
                                                </v-col>
                                            </v-row>

                                            <!-- ── Alérgenos ── -->
                                            <v-divider class="my-4" />
                                            <div class="mb-3 text-caption font-weight-bold text-uppercase text-medium-emphasis">Alérgenos</div>
                                            <v-row dense>
                                                <v-col cols="12" sm="6" md="5">
                                                    <div class="d-flex align-center ga-4">
                                                        <span class="text-body-2 flex-grow-1">Polen, polvo, caspa animal, alimentos, etc.</span>
                                                        <v-btn-toggle v-model="clinicalForm.has_allergens" density="compact" variant="outlined" divided mandatory color="#0a2540" selected-class="clinical-btn-active">
                                                            <v-btn :value="true" size="small">Sí</v-btn>
                                                            <v-btn :value="false" size="small">No</v-btn>
                                                        </v-btn-toggle>
                                                    </div>
                                                </v-col>
                                                <v-col v-if="clinicalForm.has_allergens" cols="12">
                                                    <v-textarea
                                                        v-model="clinicalForm.allergen_details"
                                                        label="Especifique los alérgenos"
                                                        density="compact"
                                                        variant="outlined"
                                                        rows="2"
                                                        counter="1000"
                                                    />
                                                </v-col>
                                            </v-row>

                                            <!-- ── Presión arterial ── -->
                                            <v-divider class="my-4" />
                                            <div class="mb-3 text-caption font-weight-bold text-uppercase text-medium-emphasis">Presión Arterial</div>
                                            <v-row dense>
                                                <v-col cols="12" sm="6" md="4">
                                                    <div class="d-flex align-center ga-4">
                                                        <span class="text-body-2 flex-grow-1">Normal</span>
                                                        <v-btn-toggle v-model="clinicalForm.normal_blood_pressure" density="compact" variant="outlined" divided color="#0a2540" selected-class="clinical-btn-active">
                                                            <v-btn :value="true" size="small">Sí</v-btn>
                                                            <v-btn :value="false" size="small">No</v-btn>
                                                        </v-btn-toggle>
                                                    </div>
                                                </v-col>
                                                <v-col cols="12" sm="6" md="4">
                                                    <div class="d-flex align-center ga-4">
                                                        <span class="text-body-2 flex-grow-1">Hipertensión</span>
                                                        <v-btn-toggle v-model="clinicalForm.has_hypertension" density="compact" variant="outlined" divided mandatory color="#0a2540" selected-class="clinical-btn-active">
                                                            <v-btn :value="true" size="small">Sí</v-btn>
                                                            <v-btn :value="false" size="small">No</v-btn>
                                                        </v-btn-toggle>
                                                    </div>
                                                </v-col>
                                            </v-row>

                                            <!-- ── Condiciones especiales ── -->
                                            <v-divider class="my-4" />
                                            <div class="mb-3 text-caption font-weight-bold text-uppercase text-medium-emphasis">Condiciones Especiales</div>
                                            <v-row dense>
                                                <v-col cols="12">
                                                    <v-textarea
                                                        v-model="clinicalForm.special_conditions"
                                                        label="Describa las condiciones especiales (opcional)"
                                                        density="compact"
                                                        variant="outlined"
                                                        rows="2"
                                                        counter="2000"
                                                    />
                                                </v-col>
                                            </v-row>

                                            <!-- ── Contacto de emergencia ── -->
                                            <v-divider class="my-4" />
                                            <div class="mb-3 text-caption font-weight-bold text-uppercase text-medium-emphasis">En Caso de Emergencia</div>
                                            <v-row dense>
                                                <v-col cols="12" sm="6">
                                                    <v-text-field
                                                        v-model="clinicalForm.emergency_contact_name"
                                                        label="Nombre"
                                                        density="compact"
                                                        variant="outlined"
                                                        :rules="[clinicalNameRule]"
                                                    />
                                                </v-col>
                                                <v-col cols="12" sm="3">
                                                    <v-text-field
                                                        v-model="clinicalForm.emergency_contact_phone"
                                                        label="Teléfono"
                                                        density="compact"
                                                        variant="outlined"
                                                        maxlength="10"
                                                        :rules="[clinicalPhoneRule]"
                                                        @keydown="blockNonDigit"
                                                        @paste="e => handleDigitPaste(e, 'emergency_contact_phone', 10)"
                                                    />
                                                </v-col>
                                                <v-col cols="12" sm="3">
                                                    <v-text-field
                                                        v-model="clinicalForm.emergency_contact_mobile"
                                                        label="Celular"
                                                        density="compact"
                                                        variant="outlined"
                                                        maxlength="10"
                                                        :rules="[clinicalPhoneRule]"
                                                        @keydown="blockNonDigit"
                                                        @paste="e => handleDigitPaste(e, 'emergency_contact_mobile', 10)"
                                                    />
                                                </v-col>
                                                <v-col cols="12" sm="6">
                                                    <v-text-field
                                                        v-model="clinicalForm.emergency_notify_name"
                                                        label="En caso necesario, informar a"
                                                        density="compact"
                                                        variant="outlined"
                                                        :rules="[clinicalNameRule]"
                                                    />
                                                </v-col>
                                            </v-row>

                                            <!-- ── Médico tratante ── -->
                                            <v-divider class="my-4" />
                                            <div class="mb-3 text-caption font-weight-bold text-uppercase text-medium-emphasis">Médico Tratante</div>
                                            <v-row dense>
                                                <v-col cols="12" sm="6">
                                                    <v-text-field
                                                        v-model="clinicalForm.treating_physician"
                                                        label="Nombre del médico"
                                                        density="compact"
                                                        variant="outlined"
                                                        :rules="[clinicalNameRule]"
                                                    />
                                                </v-col>
                                                <v-col cols="12" sm="3">
                                                    <v-text-field
                                                        v-model="clinicalForm.treating_physician_phone"
                                                        label="Teléfono"
                                                        density="compact"
                                                        variant="outlined"
                                                        maxlength="10"
                                                        :rules="[clinicalPhoneRule]"
                                                        @keydown="blockNonDigit"
                                                        @paste="e => handleDigitPaste(e, 'treating_physician_phone', 10)"
                                                    />
                                                </v-col>
                                            </v-row>

                                            <!-- ── Seguridad social y seguro médico ── -->
                                            <v-divider class="my-4" />
                                            <div class="mb-3 text-caption font-weight-bold text-uppercase text-medium-emphasis">Seguridad Social y Seguro Médico</div>
                                            <v-row dense>
                                                <v-col cols="12" sm="6">
                                                    <v-text-field
                                                        v-model="clinicalForm.social_security_number"
                                                        label="Número de Seguridad Social (NSS)"
                                                        density="compact"
                                                        variant="outlined"
                                                        maxlength="11"
                                                        :rules="[clinicalNssRule]"
                                                        @keydown="blockNonDigit"
                                                        @paste="e => handleDigitPaste(e, 'social_security_number', 11)"
                                                    />
                                                </v-col>
                                                <v-col cols="12" sm="6">
                                                    <v-text-field
                                                        v-model="clinicalForm.medical_insurance"
                                                        label="Seguro de Gastos Médicos"
                                                        density="compact"
                                                        variant="outlined"
                                                    />
                                                </v-col>
                                                <v-col cols="12" sm="4">
                                                    <v-text-field
                                                        v-model="clinicalForm.insurance_company"
                                                        label="Compañía"
                                                        density="compact"
                                                        variant="outlined"
                                                    />
                                                </v-col>
                                                <v-col cols="12" sm="4">
                                                    <v-text-field
                                                        v-model="clinicalForm.insurance_policy_number"
                                                        label="No. de Póliza"
                                                        density="compact"
                                                        variant="outlined"
                                                    />
                                                </v-col>
                                                <v-col cols="12" sm="4">
                                                    <v-text-field
                                                        v-model="clinicalForm.insurance_mobile"
                                                        label="Celular del seguro"
                                                        density="compact"
                                                        variant="outlined"
                                                        maxlength="10"
                                                        :rules="[clinicalPhoneRule]"
                                                        @keydown="blockNonDigit"
                                                        @paste="e => handleDigitPaste(e, 'insurance_mobile', 10)"
                                                    />
                                                </v-col>
                                            </v-row>

                                            <!-- ── Guardar ── -->
                                            <v-divider class="my-4" />
                                            <div class="justify-end d-flex">
                                                <v-btn
                                                    color="primary"
                                                    :loading="clinicalSaving"
                                                    @click="saveClinicalHistory"
                                                >
                                                    Guardar historia clínica
                                                </v-btn>
                                            </div>
                                        </v-form>
                                    </template>

                                    <v-alert
                                        v-else-if="!clinicalHistoryLoading"
                                        type="info"
                                        variant="tonal"
                                        density="compact"
                                    >
                                        No hay integrantes disponibles para mostrar.
                                    </v-alert>
                                </v-card>
                            </v-window-item>

                            <!-- ══ TAB: CARGOS PENDIENTES ══ -->
                            <v-window-item value="cargos-pendientes" v-if="can.includes('billing.view')">
                                <div class="d-flex flex-column ga-4">

                                    <!-- Encabezado -->
                                    <div class="flex-wrap d-flex align-center justify-space-between ga-2">
                                        <div>
                                            <div class="text-subtitle-1 font-weight-bold">Cargos pendientes</div>
                                            <div class="text-body-2 text-medium-emphasis">Saldo pendiente y cobros asociados a esta cuenta.</div>
                                        </div>
                                        <v-btn variant="tonal" color="primary" prepend-icon="mdi-refresh" :loading="chargesLoading" @click="accountCharges = []; loadAccountCharges()">
                                            Actualizar
                                        </v-btn>
                                    </div>

                                    <v-progress-linear v-if="chargesLoading" indeterminate color="primary" />

                                    <!-- Tarjetas resumen -->
                                    <v-row dense>
                                        <v-col cols="12" sm="6" md="3">
                                            <v-card rounded="lg" border elevation="0">
                                                <v-card-text class="pa-4">
                                                    <div class="mb-1 text-caption text-medium-emphasis">Total pendiente</div>
                                                    <div class="text-h6 font-weight-bold">{{ formatCurrency(chargesTotal) }}</div>
                                                </v-card-text>
                                            </v-card>
                                        </v-col>
                                        <v-col cols="12" sm="6" md="3">
                                            <v-card rounded="lg" border elevation="0" color="error" variant="tonal">
                                                <v-card-text class="pa-4">
                                                    <div class="mb-1 text-caption text-medium-emphasis">Vencido</div>
                                                    <div class="text-h6 font-weight-bold">{{ formatCurrency(chargesOverdue) }}</div>
                                                </v-card-text>
                                            </v-card>
                                        </v-col>
                                        <v-col cols="12" sm="6" md="3">
                                            <v-card rounded="lg" border elevation="0" color="primary" variant="tonal">
                                                <v-card-text class="pa-4">
                                                    <div class="mb-1 text-caption text-medium-emphasis">Mensualidades</div>
                                                    <div class="text-h6 font-weight-bold">{{ formatCurrency(chargesMonthly) }}</div>
                                                </v-card-text>
                                            </v-card>
                                        </v-col>
                                        <v-col cols="12" sm="6" md="3">
                                            <v-card rounded="lg" border elevation="0" color="secondary" variant="tonal">
                                                <v-card-text class="pa-4">
                                                    <div class="mb-1 text-caption text-medium-emphasis">Inscripciones</div>
                                                    <div class="text-h6 font-weight-bold">{{ formatCurrency(chargesInscription) }}</div>
                                                </v-card-text>
                                            </v-card>
                                        </v-col>
                                    </v-row>

                                    <!-- Tabla de cargos -->
                                    <v-card rounded="lg" border elevation="0">
                                        <v-data-table
                                            :headers="chargesHeaders"
                                            :items="accountCharges"
                                            :loading="chargesLoading"
                                            v-model:page="chargesTableOptions.page"
                                            v-model:items-per-page="chargesTableOptions.itemsPerPage"
                                            :items-per-page-options="[10, 25, 50]"
                                            no-data-text="Esta cuenta no tiene cargos pendientes."
                                            density="comfortable"
                                        >
                                            <!-- Parque -->
                                            <template #item.club_name="{ item }">
                                                <v-chip v-if="item.club_code" size="small" variant="tonal" color="primary">
                                                    {{ item.club_code }}
                                                </v-chip>
                                                <span v-else class="text-medium-emphasis">—</span>
                                            </template>

                                            <!-- Concepto -->
                                            <template #item.concept_name="{ item }">
                                                <div class="gap-1 py-1 d-flex flex-column">
                                                    <v-chip size="small" :color="chargeConceptColor(item.concept_code)" variant="tonal">
                                                        {{ item.concept_name ?? '—' }}
                                                    </v-chip>
                                                    <div class="flex-wrap d-flex ga-1">
                                                        <v-chip v-for="badge in item.badges" :key="badge.label" size="x-small" :color="badge.color" variant="tonal">
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
                                                <div v-if="item.concept_code === 'MONTHLY_FEE' && item.monthly_fee_total" class="text-caption text-medium-emphasis">
                                                    Total: {{ formatCurrency(item.monthly_fee_total) }}
                                                    <template v-if="item.monthly_fee_share"> · Parte: {{ formatCurrency(item.monthly_fee_share) }}</template>
                                                </div>
                                            </template>

                                            <!-- Vencimiento -->
                                            <template #item.due_date="{ item }">
                                                <div class="gap-1 py-1 d-flex flex-column">
                                                    <span class="text-body-2">{{ formatDate(item.due_date) }}</span>
                                                    <v-chip size="x-small" :color="resolveDueState(item.due_date).color" variant="tonal">
                                                        {{ resolveDueState(item.due_date).label }}
                                                    </v-chip>
                                                </div>
                                            </template>

                                            <!-- Estado -->
                                            <template #item.status="{ item }">
                                                <v-chip size="small" :color="chargeStatusColor(item.status)" variant="tonal">
                                                    {{ chargeStatusLabel(item.status) }}
                                                </v-chip>
                                            </template>

                                            <!-- Saldo -->
                                            <template #item.balance="{ item }">
                                                <div class="text-body-2 font-weight-medium text-end">{{ formatCurrency(item.balance) }}</div>
                                                <div v-if="item.balance < item.amount" class="text-caption text-medium-emphasis text-end">
                                                    de {{ formatCurrency(item.amount) }}
                                                </div>
                                                <div class="justify-end mt-1 d-flex">
                                                    <v-chip size="x-small" :color="item.allows_partial_payments ? 'info' : 'default'" variant="tonal">
                                                        {{ item.allows_partial_payments ? 'Parcial' : 'Pago total' }}
                                                    </v-chip>
                                                </div>
                                            </template>

                                            <!-- Acciones -->
                                            <template #item.actions="{ item }">
                                                <v-btn
                                                    v-if="can.includes('billing.store')"
                                                    size="small"
                                                    color="primary"
                                                    variant="tonal"
                                                    prepend-icon="mdi-cash-plus"
                                                    @click="openMemberPaymentModal(item)"
                                                >
                                                    Cobrar
                                                </v-btn>
                                            </template>
                                        </v-data-table>
                                    </v-card>
                                </div>
                            </v-window-item>

                            <!-- ══ TAB: ÁRBOL ══ -->
                            <v-window-item value="arbol">
                                <v-card class="pa-4">
                                    <div class="mb-4 text-subtitle-1 font-weight-bold">Cuentas relacionadas</div>
                                    <div v-if="props.accountTree?.origin" class="mb-4">
                                        <div class="mb-1 text-caption text-medium-emphasis text-uppercase">Cuenta de origen</div>
                                        <v-card variant="tonal" color="primary" class="cursor-pointer pa-3"
                                            @click="props.accountTree!.origin!.membership_id && router.visit(route('members.manage.show', props.accountTree!.origin!.membership_id))">
                                            <div class="gap-2 d-flex align-center">
                                                <v-icon size="small">mdi-account-arrow-up</v-icon>
                                                <div>
                                                    <span class="font-weight-medium">#{{ props.accountTree.origin.membership_number }}</span>
                                                    — {{ props.accountTree.origin.holder_name }}
                                                    <span v-if="props.accountTree.origin.membership_type_name" class="text-medium-emphasis">({{ props.accountTree.origin.membership_type_name }})</span>
                                                </div>
                                                <v-spacer />
                                                <v-chip size="x-small" :color="props.accountTree.origin.status === 'active' ? 'success' : 'default'">
                                                    {{ statusLabel(props.accountTree.origin.status) }}
                                                </v-chip>
                                            </div>
                                        </v-card>
                                    </div>
                                    <div v-if="props.accountTree?.derived.length">
                                        <div class="mb-2 text-caption text-medium-emphasis text-uppercase">Cuentas derivadas ({{ props.accountTree.derived.length }})</div>
                                        <AccountTreeNode v-for="node in props.accountTree.derived" :key="node.id" :node="node" class="mb-2" />
                                    </div>
                                </v-card>
                            </v-window-item>

                        </v-window>

                    </v-container>
                </v-col>
            </v-row>
        </div>
    </AppLayout>

    <!-- ── Dialog: Número de cuenta interno ── -->
    <v-dialog v-model="showInternalNumberDialog" max-width="420" persistent>
        <v-card rounded="lg">
            <v-card-title class="pb-2 text-subtitle-1 font-weight-bold pa-4">
                <v-icon size="20" class="mr-2">mdi-identifier</v-icon>
                Número de cuenta interno
            </v-card-title>

            <v-card-text class="pt-2 pa-4">
                <v-text-field
                    v-model="internalNumberForm.internal_account_number"
                    label="No. cuenta interno"
                    placeholder="Ej. 1234, A-001, etc."
                    hint="Opcional. Si se captura, no puede repetirse entre cuentas."
                    persistent-hint
                    clearable
                    prepend-inner-icon="mdi-pound"
                    :error-messages="internalNumberForm.errors.internal_account_number"
                    autofocus
                    @keydown.enter.prevent="saveInternalNumber"
                />
            </v-card-text>

            <v-card-actions class="pt-0 pa-3">
                <v-spacer />
                <v-btn
                    variant="text"
                    @click="showInternalNumberDialog = false"
                >
                    Cancelar
                </v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    :loading="internalNumberForm.processing"
                    @click="saveInternalNumber"
                >
                    Guardar
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <!-- ── Dialog: Permiso por ausencia ── -->
    <v-dialog v-model="showAbsencePermitDialog" max-width="520" persistent>
        <v-card rounded="lg">
            <v-card-title class="pb-2 d-flex align-center justify-space-between pa-4">
                <span class="text-h6 font-weight-bold">Registrar permiso por ausencia</span>
                <v-btn icon="mdi-close" variant="text" density="compact" @click="showAbsencePermitDialog = false" />
            </v-card-title>

            <v-divider />

            <v-card-text class="pa-4">
                <v-form ref="absencePermitFormRef">
                    <v-row dense>
                        <v-col cols="12" sm="6">
                            <MonthPicker
                                v-model="absencePermitForm.start_month"
                                label="Mes de inicio"
                                :min="currentMonth"
                                :error-messages="absencePermitForm.errors.start_month"
                            />
                        </v-col>
                        <v-col cols="12" sm="6">
                            <MonthPicker
                                v-model="absencePermitForm.end_month"
                                label="Mes de término"
                                :min="minEndMonth"
                                :error-messages="absencePermitForm.errors.end_month"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-text-field
                                v-model.number="absencePermitForm.charge_percentage"
                                label="Porcentaje a cobrar (%)"
                                type="number"
                                density="compact"
                                variant="outlined"
                                suffix="%"
                                :min="0"
                                :max="100"
                                :error-messages="absencePermitForm.errors.charge_percentage"
                                :disabled="true"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="absencePermitForm.notes"
                                label="Notas (opcional)"
                                density="compact"
                                variant="outlined"
                                rows="2"
                                :error-messages="absencePermitForm.errors.notes"
                            />
                        </v-col>

                        <v-col cols="12">
                            <div class="mb-1 font-weight-medium text-body-2">
                                Documento de solicitud
                                <span class="text-error">*</span>
                            </div>
                            <CustomFileUploadField
                                v-model="permitFiles"
                                label="Seleccionar documento"
                                hint="PDF, JPG o PNG · máx. 2 MB"
                                accept=".pdf,.jpg,.jpeg,.png"
                                :rules="permitDocRules"
                            />
                            <div
                                v-if="absencePermitForm.errors.absence_permit_document"
                                class="mt-1 text-error text-caption"
                            >
                                {{ absencePermitForm.errors.absence_permit_document }}
                            </div>
                        </v-col>
                    </v-row>
                </v-form>
            </v-card-text>

            <v-divider />

            <v-card-actions class="gap-2 pa-4">
                <v-spacer />
                <v-btn variant="text" @click="showAbsencePermitDialog = false">Cancelar</v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    :loading="absencePermitForm.processing"
                    prepend-icon="mdi-check"
                    @click="submitAbsencePermit"
                >
                    Guardar
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <!-- Document upload dialog -->
    <v-dialog v-model="showDocumentModal" max-width="480" persistent>
        <v-card rounded="xl">
            <div class="px-6 py-4 border-b d-flex align-center justify-space-between">
                <div class="d-flex align-center ga-3">
                    <v-avatar color="primary" variant="tonal" size="42">
                        <v-icon>mdi-file-upload</v-icon>
                    </v-avatar>
                    <div>
                        <div class="text-h6 font-weight-bold">
                            {{ documentModalReplaceId !== null ? 'Reemplazar documento' : 'Subir documento' }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ documentModalMember?.full_name }}
                        </div>
                    </div>
                </div>
                <v-btn icon="mdi-close" variant="text" density="compact" @click="closeDocumentModal" />
            </div>

            <v-card-text class="pa-6">
                <v-form ref="documentFormRef">
                    <div class="mb-1 text-body-2 font-weight-medium">{{ documentModalDoc?.name }}</div>
                    <div class="mb-4 text-caption text-medium-emphasis">
                        Formatos aceptados: {{ documentModalDoc?.allowed_extensions?.join(', ') ?? 'pdf, jpg, jpeg, png' }}
                    </div>

                    <CustomFileUploadField
                        v-model="documentFiles"
                        label="Documento"
                        :accept="(documentModalDoc?.allowed_extensions ?? ['pdf','jpg','jpeg','png']).map(e => `.${e}`).join(',')"
                        :multiple="documentModalReplaceId === null && (documentModalDoc?.allow_multiple ?? false)"
                        :hint="documentModalReplaceId !== null
                            ? `${(documentModalDoc?.allowed_extensions ?? []).join(', ').toUpperCase()} · 1 archivo`
                            : documentModalDoc?.allow_multiple
                                ? `${(documentModalDoc.allowed_extensions ?? []).join(', ').toUpperCase()} · se requieren ${documentModalDoc.number_files ?? 1} archivo(s)`
                                : `${(documentModalDoc?.allowed_extensions ?? []).join(', ').toUpperCase()} · 1 archivo`"
                        :rules="documentFileRules"
                    />
                </v-form>
            </v-card-text>

            <v-divider />

            <v-card-actions class="gap-2 pa-4">
                <v-spacer />
                <v-btn variant="text" @click="closeDocumentModal">Cancelar</v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    :loading="documentForm.processing"
                    prepend-icon="mdi-check"
                    @click="submitDocument"
                >
                    Guardar
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <!-- ── Dialog: Registrar cobro ── -->
    <v-dialog v-model="showMemberPaymentModal" max-width="560" persistent>
        <v-card rounded="lg">
            <v-card-title class="pb-2 d-flex align-center justify-space-between pa-4">
                <span class="text-h6 font-weight-bold">Registrar cobro</span>
                <v-btn icon="mdi-close" variant="text" density="compact" @click="closeMemberPaymentModal" />
            </v-card-title>

            <v-divider />

            <v-card-text class="pa-4">
                <!-- Detalle del cargo seleccionado -->
                <v-sheet v-if="selectedMemberCharge" rounded="lg" color="surface-variant" class="mb-4 pa-3">
                    <div class="mb-1 d-flex justify-space-between align-start">
                        <div>
                            <div class="text-body-2 font-weight-medium">{{ props.account.primary_holder?.full_name }}</div>
                            <div class="text-caption">{{ props.account.membership_number }}</div>
                            <div v-if="props.account.internal_account_number" class="text-caption text-primary font-weight-medium">
                                <v-icon size="10" class="mr-1">mdi-pound</v-icon>{{ props.account.internal_account_number }}
                            </div>
                        </div>
                        <v-chip size="small" :color="chargeConceptColor(selectedMemberCharge.concept_code)" variant="tonal">
                            {{ selectedMemberCharge.concept_name }}
                        </v-chip>
                    </div>
                    <div v-if="selectedMemberCharge.description" class="text-caption">{{ selectedMemberCharge.description }}</div>
                    <div class="mt-2 d-flex justify-space-between align-center">
                        <div class="text-caption">
                            Vencimiento:
                            <v-chip size="x-small" :color="resolveDueState(selectedMemberCharge.due_date).color" variant="tonal" class="ml-1">
                                {{ formatDate(selectedMemberCharge.due_date) }}
                            </v-chip>
                        </div>
                        <div class="text-body-2 font-weight-bold">
                            Saldo: {{ formatCurrency(selectedMemberCharge.balance) }}
                        </div>
                    </div>
                </v-sheet>

                <!-- Formulario -->
                <v-form ref="memberPaymentFormRef">
                    <v-row dense>
                        <!-- Importe -->
                        <v-col cols="12">
                            <v-text-field
                                v-model="memberPaymentAmount"
                                label="Importe a cobrar"
                                type="number"
                                density="compact"
                                variant="outlined"
                                prefix="$"
                                :rules="memberPaymentAmountRules"
                                :readonly="!selectedMemberCharge?.allows_partial_payments"
                                :hint="selectedMemberCharge?.allows_partial_payments ? 'Puedes capturar un importe parcial' : 'Este concepto debe liquidarse por el monto completo'"
                                persistent-hint
                            />
                        </v-col>

                        <!-- Método de pago -->
                        <v-col cols="12">
                            <v-select
                                v-model="memberPaymentForm.payment_method_id"
                                :items="memberPaymentMethods.map(m => ({ title: m.internal_key ? `${m.name} (${m.internal_key})` : m.name, value: m.id }))"
                                label="Método de pago"
                                density="compact"
                                variant="outlined"
                                :rules="memberPaymentMethodRules"
                                :error-messages="memberPaymentForm.errors.payment_method_id"
                            />
                            <v-alert
                                v-if="memberSelectedPaymentMethod && !memberSelectedPaymentMethod.affects_cash_cut"
                                type="info"
                                variant="tonal"
                                density="compact"
                                class="mt-2"
                                icon="mdi-cash-register"
                            >
                                Este método de pago <strong>no impacta</strong> en el corte de caja.
                            </v-alert>
                        </v-col>

                        <!-- Fecha -->
                        <v-col cols="12">
                            <v-text-field
                                v-model="memberPaymentForm.paid_at"
                                label="Fecha de pago"
                                type="datetime-local"
                                density="compact"
                                variant="outlined"
                                :rules="memberPaidAtRules"
                                :error-messages="memberPaymentForm.errors.paid_at"
                            />
                        </v-col>

                        <!-- Referencia (solo si el método lo requiere) -->
                        <v-col v-if="memberSelectedPaymentMethod?.requires_reference" cols="12">
                            <v-text-field
                                v-model="memberPaymentForm.reference"
                                label="Referencia"
                                density="compact"
                                variant="outlined"
                                :rules="memberReferenceRules"
                                :error-messages="memberPaymentForm.errors.reference"
                            />
                        </v-col>

                        <!-- Banco (solo si el método lo requiere) -->
                        <v-col v-if="memberSelectedPaymentMethod?.requires_bank_name" cols="12">
                            <v-text-field
                                v-model="memberPaymentForm.bank_name"
                                label="Banco"
                                density="compact"
                                variant="outlined"
                                :rules="memberBankNameRules"
                                :error-messages="memberPaymentForm.errors.bank_name"
                            />
                        </v-col>

                        <!-- No. cheque (solo si el método lo requiere) -->
                        <v-col v-if="memberSelectedPaymentMethod?.requires_check_number" cols="12">
                            <v-text-field
                                v-model="memberPaymentForm.check_number"
                                label="No. de cheque"
                                density="compact"
                                variant="outlined"
                                :rules="memberCheckNumberRules"
                                :error-messages="memberPaymentForm.errors.check_number"
                            />
                        </v-col>

                        <!-- Notas -->
                        <v-col cols="12">
                            <v-textarea
                                v-model="memberPaymentForm.notes"
                                label="Notas (opcional)"
                                density="compact"
                                variant="outlined"
                                rows="2"
                                :rules="memberNotesRules"
                                :error-messages="memberPaymentForm.errors.notes"
                            />
                        </v-col>
                    </v-row>
                </v-form>
            </v-card-text>

            <v-divider />

            <v-card-actions class="gap-2 pa-4">
                <v-spacer />
                <v-btn variant="text" @click="closeMemberPaymentModal">Cancelar</v-btn>
                <v-btn
                    color="primary"
                    variant="flat"
                    :loading="memberPaymentForm.processing"
                    prepend-icon="mdi-check"
                    @click="submitMemberPayment"
                >
                    Registrar cobro
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <!-- Dialog: Edit locker-->
    <v-dialog
        v-model="showEditLockerModal"
        max-width="850"
    >
        <v-card rounded="xl">

            <!-- HEADER -->
            <div class="px-6 py-4 border-b d-flex align-center justify-space-between">

                <div class="d-flex align-center ga-3">

                    <v-avatar
                        color="primary"
                        variant="tonal"
                        size="42"
                    >
                        <v-icon>
                            mdi-locker
                        </v-icon>
                    </v-avatar>

                    <div>

                        <div class="text-h6 font-weight-bold">
                            Cambiar casillero
                        </div>

                        <div class="text-caption text-medium-emphasis">
                            Selecciona un nuevo casillero disponible
                        </div>

                    </div>

                </div>

                <v-btn
                    icon
                    variant="text"
                    @click="showEditLockerModal = false"
                >
                    <v-icon>
                        mdi-close
                    </v-icon>
                </v-btn>

            </div>

            <!-- BODY -->
            <v-card-text class="pa-6">

                <!-- INFO -->
                <v-row class="mb-6">

                    <v-col cols="12" md="6">

                        <v-card
                            variant="tonal"
                            color="primary"
                            class="pa-4 h-100"
                        >
                            <div class="mb-1 text-caption text-medium-emphasis">
                                Integrante
                            </div>

                            <div class="text-subtitle-1 font-weight-bold">
                                {{ editingMember?.full_name }}
                            </div>
                        </v-card>

                    </v-col>

                    <v-col cols="12" md="6">

                        <v-card
                            variant="tonal"
                            color="grey"
                            class="pa-4 h-100"
                        >
                            <div class="mb-1 text-caption text-medium-emphasis">
                                Casillero actual
                            </div>

                            <div class="text-h5 font-weight-bold">
                                {{ editingMember?.currentLocker?.number }}
                            </div>
                        </v-card>
                    </v-col>
                </v-row>

                <!-- TITLE -->
                <div class="mb-4 d-flex align-center justify-space-between">

                    <div class="text-subtitle-1 font-weight-bold">
                        Casilleros disponibles
                    </div>

                </div>
                <v-row class="mb-4">
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="editLockerSearch"
                            label="Buscar casillero"
                            prepend-inner-icon="mdi-magnify"
                            variant="outlined"
                            density="comfortable"
                            hide-details
                        />
                    </v-col>
                    <v-col
                        cols="12"
                        md="6"
                        class="justify-end d-flex align-center"
                    >
                        <v-chip
                            variant="outlined"
                            size="small"
                        >
                            {{ editTotal }} disponibles
                        </v-chip>
                    </v-col>
                    <!--<v-col cols="12" md="12">
                        <v-file-input
                            v-model="editLockerFile"
                            label="Adjuntar comprobante"
                            prepend-icon="mdi-paperclip"
                            variant="outlined"
                            density="comfortable"
                            accept="image/*,.pdf"
                            show-size
                            clearable
                        />
                    </v-col>-->
                    <v-col cols="12" md="12">
                        <div class="mb-1 font-weight-medium">
                            Adjuntar comprobante de cambio de casillero
                            <span class="text-error">*</span>
                        </div>
                        <CustomFileUploadField
                            v-model="editLockerFile"
                            label="Seleccionar comprobante"
                            hint="PDF, JPG o PNG · máx. 2 MB"
                            accept=".pdf,.jpg,.jpeg,.png"
                            :rules="letterRules"
                        />
                        <!--<div v-if="form.errors.cancellation_letter" class="mt-1 text-error text-caption">
                            {{ form.errors.cancellation_letter }}
                        </div>-->
                    </v-col>
                </v-row>

                <!-- GRID -->
                <div class="edit-locker-grid">

                    <v-card
                        v-for="locker in availableEditLockers"
                        :key="locker.id"
                        class="text-center locker-option"
                        :class="{
                            'locker-selected': editSelectedLocker === locker.id
                        }"
                        :elevation="editSelectedLocker === locker.id ? 8 : 1"
                        @click="editSelectedLocker = locker.id"
                    >

                        <v-icon
                            size="28"
                            class="mb-2"
                            :color="editSelectedLocker === locker.id ? 'white' : 'primary'"
                        >
                            mdi-locker
                        </v-icon>

                        <div
                            class="text-subtitle-1 font-weight-bold"
                        >
                            {{ locker.number }}
                        </div>

                        <v-icon
                            v-if="editSelectedLocker === locker.id"
                            size="18"
                            class="mt-2"
                        >
                            mdi-check-circle
                        </v-icon>

                    </v-card>

                </div>
                <div class="mt-6 d-flex align-center justify-space-between">
                    <div class="text-caption text-medium-emphasis">
                        Mostrando
                        {{ ((editCurrentPage - 1) * editPerPage) + 1 }}
                        -
                        {{
                            Math.min(
                                editCurrentPage * editPerPage,
                                editTotal
                            )
                        }}
                        de {{ editTotal }}
                    </div>
                    <v-pagination
                        v-model="editCurrentPage"
                        :length="editTotalPages"
                        density="comfortable"
                        rounded="circle"
                        :total-visible="7"
                    />
                </div>
            </v-card-text>

            <!-- FOOTER -->
            <v-card-actions class="px-6 py-4 border-t">

                <v-spacer />

                <v-btn
                    variant="text"
                    @click="showEditLockerModal = false"
                >
                    Cancelar
                </v-btn>

                <v-btn
                    color="primary"
                    :disabled="!editSelectedLocker"
                    @click="updateLocker"
                >
                    Guardar cambios
                </v-btn>

            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
<style>
.locker-option {
    padding: 20px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

.locker-option:hover {
    transform: translateY(-2px);
}

.locker-selected {
    background: rgb(var(--v-theme-primary));
    color: white;
    border-color: rgb(var(--v-theme-primary));
}
.edit-locker-grid {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 12px;
}

.locker-grid-mini {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    justify-content: end;
    width: fit-content;
    margin-left: auto;
    direction: rtl;
}

.locker-mini {
    position: relative;
    padding: 20px 25px 8px;
    border: 1px solid rgb(var(--v-theme-primary));
    border-radius: 10px;
    overflow: hidden;
    direction: ltr;
}

/* botón editar */
.btn-edit {
    position: absolute;
    top: 2px;
    left: 0px;
    min-width: auto;
    padding: 0px;
}

/* botón eliminar */
.btn-delete {
    position: absolute;
    top: 2px;
    right: 0px;
    min-width: auto;
    padding: 0px;
}

/* contenido */
.locker-content {
    display: flex;
    margin-top: 10px;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}
/* TABLA HISTORIAL CASILLEROS */
.custom-table {
    border-radius: 16px;
    overflow: hidden;
}

.custom-table .v-data-table__thead {
    background-color: #f5f7fa;
}

.custom-table .v-data-table__tr:hover {
    background-color: rgba(0, 0, 0, 0.03);
    transition: 0.2s;
}
/*Responsive adjustments for the locker grid*/
@media (max-width: 1400px) {

    .edit-locker-grid {
        grid-template-columns: repeat(8, 1fr);
    }
}

@media (max-width: 1100px) {

    .edit-locker-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}

@media (max-width: 768px) {

    .edit-locker-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 500px) {

    .edit-locker-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
.swal2-container {
    z-index: 999999 !important;
}
.v-btn-group--density-compact.v-btn-group {
  height: 36px;
  border: #0a2540;
}

/* ── Historia Clínica: toggle activo sólido ── */
.clinical-btn-active {
    background-color: #0a2540 !important;
    color: #ffffff !important;
    opacity: 1 !important;
}
.clinical-btn-active .v-btn__content {
    color: #ffffff !important;
}
</style>

