<script setup lang="ts">
import AccountTreeNode from "@/Components/AccountTreeNode.vue";
import BaseButton from "@/Components/BaseButton.vue";
import CustomFileUploadField from "@/Components/CustomFileUploadField.vue";
import MonthPicker from "@/Components/MonthPicker.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { fileMaxSizeRule, fileTypeRule, requiredFileRule } from "@/constants/validationRules";
import { Head, router, useForm, usePage  } from "@inertiajs/vue3";
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
}

interface MembershipAccount {
    id: number;
    membership_number: string | null;
    account_club_name: string | null;
    account_club_code: string | null;
    account_type: string | null;
    status: string | null;
    current_monthly_fee: number;
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

onMounted(fetchHistory);

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
// Locker actions
const showEditLockerModal = ref(false);
const editingMember = ref(null);
const editSelectedLocker = ref(null);
const availableEditLockers = ref([]);
const editLockerSearch = ref('');
const editCurrentPage = ref(1);
const editPerPage = ref(30);
const editTotal = ref(0);
const editTotalPages = ref(1);

const editLocker = async (member: any) => {
    editingMember.value = member;
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
                    category: editingMember.value.locker.category,
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

    if (!result.isConfirmed) {
        return;
    }

    router.post(
        route('members.lockers.change'),
        {
            member_id: editingMember.value.member_id,
            old_locker_id: editingMember.value.locker.id,
            new_locker_id: editSelectedLocker.value,
        },
        {
            preserveScroll: true,

            onSuccess: () => {

                showEditLockerModal.value = false;

                customToastSwal({
                    title: 'Casillero actualizado',
                    icon: 'success'
                });

                router.reload({
                    only: ['account']
                });
            },

            onError: (errors) => {

                console.error(errors);

                customToastSwal({
                    title: 'No se pudo actualizar el casillero',
                    icon: 'error'
                });
            }
        }
    );
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
</script>

<template>
    <Head title="Gestionar Cuenta" />

    <AppLayout>
        <template #header>Gestionar Cuenta</template>
        <template #options>
            <div class="d-flex flex-wrap ga-2">
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
                        <v-row>
                            <v-col cols="12" md="4">
                                <v-card class="pa-4 h-100" variant="tonal">
                                    <div class="text-caption text-medium-emphasis">
                                        Cuenta
                                    </div>
                                    <div class="text-h6 font-weight-bold">
                                        {{ props.account.membership_number || "-" }}
                                    </div>
                                    <div class="text-body-2 mt-2">
                                        {{ props.account.account_club_code || "-" }} ·
                                        {{ props.account.account_club_name || "Sin club" }}
                                    </div>
                                    <div class="text-body-2 mt-2">
                                        Cuenta {{ accountTypeLabel }}
                                    </div>
                                    <div class="text-body-2">
                                        Estatus {{ statusLabel(props.account.status) }}
                                    </div>
                                </v-card>
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-card class="pa-4 h-100" variant="tonal">
                                    <div class="text-caption text-medium-emphasis">
                                        Titular actual
                                    </div>
                                    <div class="text-h6 font-weight-bold">
                                        {{ props.account.primary_holder?.full_name || "-" }}
                                    </div>
                                    <div class="text-body-2 mt-2">
                                        {{ props.account.primary_holder?.email || "Sin correo" }}
                                    </div>
                                    <div class="text-body-2">
                                        {{ props.account.primary_holder?.phone || "Sin teléfono" }}
                                    </div>
                                </v-card>
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-card class="pa-4 h-100" variant="tonal">
                                    <div class="text-caption text-medium-emphasis">
                                        Cuota actual
                                    </div>
                                    <div class="text-h6 font-weight-bold">
                                        {{
                                            currencyFormatter.format(
                                                props.account.current_monthly_fee,
                                            )
                                        }}
                                    </div>
                                    <div class="text-body-2 mt-2">
                                        Total actual a cobrar
                                    </div>
                                </v-card>
                            </v-col>
                        </v-row>

                        <v-card class="pa-4 mt-4">
                            <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-4">
                                <div>
                                    <div class="text-subtitle-1 font-weight-bold">
                                        Acciones de la cuenta
                                    </div>
                                    <div class="text-body-2 text-medium-emphasis">
                                        Desde aquí puedes gestionar la membresía y sus integrantes.
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap ga-2">
                                    <v-btn v-if="can.includes('members.lockers.create')"
                                        color="primary"
                                        variant="tonal"
                                        @click="
                                            router.visit(
                                                route(
                                                    'members.lockers.create',
                                                    props.account.id,
                                                ),
                                            )
                                        "
                                    >
                                        Asignar casillero
                                    </v-btn>
                                    <v-btn v-if="can.includes('acts.index')"
                                        color="primary"
                                        variant="tonal"
                                        @click="router.visit(route('acts.index', props.account.id))"
                                    >
                                        Registrar multa
                                    </v-btn>
                                    <v-btn
                                        v-if="props.canChangePrimaryHolder"
                                        color="primary"
                                        variant="tonal"
                                        @click="
                                            router.visit(
                                                route(
                                                    'members.change-holder.create',
                                                    props.membership.id,
                                                ),
                                            )
                                        "
                                    >
                                        Cambiar titular
                                    </v-btn>

                                    <v-btn
                                        v-if="props.canSeparateMembers"
                                        color="primary"
                                        variant="tonal"
                                        @click="
                                            router.visit(
                                                route(
                                                    'members.separation.create',
                                                    props.membership.id,
                                                ),
                                            )
                                        "
                                    >
                                        Separar integrante
                                    </v-btn>

                                    <v-btn
                                        v-if="props.canAddFamilyMembers"
                                        color="primary"
                                        @click="
                                            router.visit(
                                                route(
                                                    'members.family-members.create',
                                                    props.membership.id,
                                                ),
                                            )
                                        "
                                    >
                                        Agregar familiar
                                    </v-btn>
                                </div>
                            </div>
                        </v-card>

                        <v-card class="pa-4 mt-4">
                            <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-4">
                                <div>
                                    <div class="text-subtitle-1 font-weight-bold">
                                        Permiso por ausencia
                                    </div>
                                    <div class="text-body-2 text-medium-emphasis">
                                        Durante su vigencia se cobra el porcentaje configurado sobre las membresías cobrables y se bloquea el uso de instalaciones.
                                    </div>
                                </div>

                                <v-btn color="primary" variant="tonal" @click="openAbsencePermitDialog">
                                    Registrar permiso
                                </v-btn>
                            </div>

                            <v-row>
                                <v-col cols="12" md="4">
                                    <v-card variant="tonal" class="pa-4 h-100">
                                        <div class="text-caption text-medium-emphasis">
                                            Estado actual
                                        </div>
                                        <div class="text-h6 font-weight-bold">
                                            {{
                                                props.account.current_absence_permit
                                                    ? statusLabel(
                                                          props.account.current_absence_permit.status,
                                                      )
                                                    : "Sin permiso activo"
                                            }}
                                        </div>
                                        <div
                                            v-if="props.account.current_absence_permit"
                                            class="text-body-2 mt-2"
                                        >
                                            Vigencia:
                                            {{ formatDate(props.account.current_absence_permit.start_date) }}
                                            a
                                            {{ formatDate(props.account.current_absence_permit.end_date) }}
                                        </div>
                                    </v-card>
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-card variant="tonal" class="pa-4 h-100">
                                        <div class="text-caption text-medium-emphasis">
                                            Porcentaje durante permiso
                                        </div>
                                        <div class="text-h6 font-weight-bold">
                                            {{
                                                props.account.current_absence_permit
                                                    ? `${props.account.current_absence_permit.charge_percentage}%`
                                                    : "25%"
                                            }}
                                        </div>
                                        <div class="text-body-2 mt-2">
                                            Aplicado sobre membresías cobrables del titular.
                                        </div>
                                    </v-card>
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-card variant="tonal" class="pa-4 h-100">
                                        <div class="text-caption text-medium-emphasis">
                                            Cuota estimada con permiso
                                        </div>
                                        <div class="text-h6 font-weight-bold">
                                            {{
                                                props.account.absence_permit_preview_fee !== null
                                                    ? currencyFormatter.format(
                                                          props.account.absence_permit_preview_fee,
                                                      )
                                                    : "-"
                                            }}
                                        </div>
                                        <div class="text-body-2 mt-2">
                                            Estimado mensual mientras el permiso esté activo.
                                        </div>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <div class="mt-4">
                                <div class="text-subtitle-2 font-weight-bold mb-3">
                                    Historial
                                </div>

                                <div
                                    v-if="!props.account.absence_permits.length"
                                    class="text-body-2 text-medium-emphasis"
                                >
                                    No hay permisos por ausencia registrados.
                                </div>

                                <div v-else class="d-flex flex-column ga-3">
                                    <div
                                        v-for="absencePermit in props.account.absence_permits"
                                        :key="absencePermit.id"
                                        class="border rounded-lg px-4 py-3"
                                    >
                                        <div class="d-flex flex-wrap align-center justify-space-between ga-2">
                                            <div>
                                                <div class="font-weight-medium">
                                                    {{ formatDate(absencePermit.start_date) }}
                                                    a
                                                    {{ formatDate(absencePermit.end_date) }}
                                                </div>
                                                <div class="text-caption text-medium-emphasis">
                                                    {{ absencePermit.charge_percentage }}% sobre cuota cobrable
                                                </div>
                                            </div>

                                            <div class="d-flex flex-wrap ga-2">
                                                <v-chip
                                                    size="small"
                                                    :color="statusColor(absencePermit.status)"
                                                    variant="tonal"
                                                >
                                                    {{ statusLabel(absencePermit.status) }}
                                                </v-chip>

                                                <v-btn
                                                    v-if="
                                                        ['approved', 'active'].includes(
                                                            absencePermit.status,
                                                        )
                                                    "
                                                    color="error"
                                                    size="small"
                                                    variant="text"
                                                    @click="
                                                        cancelAbsencePermit(absencePermit.id)
                                                    "
                                                >
                                                    Cancelar
                                                </v-btn>
                                            </div>
                                        </div>

                                        <div class="text-body-2 mt-2">
                                            {{
                                                absencePermit.blocks_facility_access
                                                    ? "Bloquea instalaciones"
                                                    : "No bloquea instalaciones"
                                            }}
                                            ·
                                            {{
                                                absencePermit.blocks_reservations
                                                    ? "Bloquea reservaciones"
                                                    : "No bloquea reservaciones"
                                            }}
                                        </div>

                                        <div
                                            v-if="absencePermit.notes"
                                            class="text-body-2 text-medium-emphasis mt-2"
                                        >
                                            {{ absencePermit.notes }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </v-card>

                        <v-card class="pa-4 mt-4">
                            <div class="text-subtitle-1 font-weight-bold mb-4">
                                Membresías activas
                            </div>

                            <div class="d-flex flex-column ga-3">
                                <div
                                    v-for="activeMembership in props.account.active_memberships"
                                    :key="activeMembership.id"
                                    class="border rounded-lg px-4 py-3"
                                >
                                    <div class="d-flex flex-wrap align-center justify-space-between ga-2">
                                        <div>
                                            <div class="font-weight-medium">
                                                {{ activeMembership.membership_type_name }}
                                            </div>
                                            <div class="text-caption text-medium-emphasis">
                                                {{ activeMembership.club_code }} ·
                                                {{ activeMembership.club_name }}
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap ga-2">
                                            <v-chip
                                                size="small"
                                                :color="
                                                    activeMembership.is_billable
                                                        ? 'success'
                                                        : 'default'
                                                "
                                                :variant="
                                                    activeMembership.is_billable
                                                        ? 'flat'
                                                        : 'tonal'
                                                "
                                            >
                                                {{
                                                    activeMembership.is_billable
                                                        ? "Se cobra"
                                                        : "Incluida"
                                                }}
                                            </v-chip>
                                            <v-chip
                                                v-if="activeMembership.billing_split_mode === 'equal_split'"
                                                size="small"
                                                color="info"
                                                variant="tonal"
                                            >
                                                50/50
                                            </v-chip>

                                            <v-chip
                                                size="small"
                                                :color="statusColor(activeMembership.status)"
                                                variant="tonal"
                                            >
                                                {{ statusLabel(activeMembership.status) }}
                                            </v-chip>
                                        </div>
                                    </div>

                                    <div class="text-body-2 mt-2">
                                        {{
                                            currencyFormatter.format(
                                                activeMembership.monthly_fee_share,
                                            )
                                        }}
                                    </div>
                                    <div
                                        v-if="activeMembership.monthly_fee_total !== activeMembership.monthly_fee_share"
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Cuota total del esquema:
                                        {{
                                            currencyFormatter.format(
                                                activeMembership.monthly_fee_total,
                                            )
                                        }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis">
                                        Vigencia:
                                        {{ formatDate(activeMembership.start_date) }}
                                        a
                                        {{ formatDate(activeMembership.end_date) }}
                                    </div>
                                </div>
                            </div>
                        </v-card>

                        <v-card class="pa-4 mt-4">
                            <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-4">
                                <div>
                                    <div class="text-subtitle-1 font-weight-bold">
                                        Integrantes de la cuenta
                                    </div>
                                    <div class="text-body-2 text-medium-emphasis">
                                        Aquí puedes revisar la información general de cada integrante.
                                    </div>
                                </div>

                                <v-chip color="primary" variant="tonal">
                                    {{ props.account.members.length }} integrante(s)
                                </v-chip>
                            </div>

                            <v-row>
                                <v-col
                                    v-for="member in props.account.members"
                                    :key="member.member_id"
                                    cols="12"
                                    md="6"
                                >
                                    <v-card variant="outlined" class="pa-4 h-100">
                                        <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
                                            <div>
                                                <div class="font-weight-medium">
                                                    {{ member.full_name }}
                                                </div>
                                                <div class="text-caption text-medium-emphasis">
                                                    {{ member.relationship_name || "Sin parentesco" }}
                                                </div>
                                            </div>

                                            <v-chip
                                                v-if="member.is_primary_holder"
                                                color="primary"
                                                size="small"
                                                variant="flat"
                                            >
                                                Titular
                                            </v-chip>
                                        </div>
                                        <v-row>
                                            <v-col cols="12" md="9">
                                                <div class="text-body-2">
                                                    <strong>Edad:</strong>
                                                    {{ member.age ?? "-" }}
                                                </div>
                                                <div class="text-body-2">
                                                    <strong>Nacimiento:</strong>
                                                    {{ formatDate(member.birthdate) }}
                                                </div>
                                                <div class="text-body-2">
                                                    <strong>Correo:</strong>
                                                    {{ member.email || "-" }}
                                                </div>
                                                <div class="text-body-2">
                                                    <strong>Teléfono:</strong>
                                                    {{ member.phone || "-" }}
                                                </div>
                                                <div class="text-body-2">
                                                    <strong>Nacionalidad:</strong>
                                                    {{ member.nationality || "-" }}
                                                </div>
                                                <div class="text-body-2">
                                                    <strong>Estado civil:</strong>
                                                    {{ member.marital_status || "-" }}
                                                </div>
                                                <div class="text-body-2">
                                                    <strong>Ocupación:</strong>
                                                    {{ member.occupation || member.school_name || "-" }}
                                                </div>
                                                <div class="text-body-2">
                                                    <strong>Domicilio:</strong>
                                                    {{ addressSummary(member) || "-" }}
                                                </div>
                                            </v-col>
                                            <v-col cols="12" md="3" class="mt-3">
                                                <v-card v-if="member.locker"
                                                    class="pa-2 text-center"
                                                    color="primary"
                                                    variant="tonal"
                                                >
                                                     <v-btn
                                                        icon
                                                        size="x-small"
                                                        variant="text"
                                                        color="primary"
                                                        class="position-absolute top-0 left-0 ma-1"
                                                        @click.stop="editLocker(member)"
                                                    >
                                                        <v-icon size="18">
                                                            mdi-pencil
                                                        </v-icon>

                                                        <v-tooltip activator="parent" location="top">
                                                            Editar casillero
                                                        </v-tooltip>
                                                    </v-btn>
                                                    <v-btn
                                                        icon
                                                        size="x-small"
                                                        variant="text"
                                                        color="error"
                                                        class="position-absolute top-0 right-0 ma-1"
                                                        @click.stop="removeLocker(member.locker.assignment_id)"
                                                    >
                                                        <v-icon size="18">
                                                            mdi-close
                                                        </v-icon>

                                                        <v-tooltip activator="parent" location="top">
                                                            Dar de baja
                                                        </v-tooltip>
                                                    </v-btn>
                                                    <v-icon size="22" class="mt-5">
                                                        mdi-locker
                                                    </v-icon>

                                                    <div class="text-caption">
                                                        Casillero
                                                    </div>

                                                    <div class="text-h6 font-weight-bold">
                                                        {{ member.locker.number }}
                                                    </div>
                                                </v-card>
                                            </v-col>
                                        </v-row>
                                        

                                        <div class="d-flex justify-end mt-3">
                                            <v-btn
                                                size="small"
                                                variant="tonal"
                                                color="primary"
                                                prepend-icon="mdi-pencil"
                                                @click="router.visit(route('members.member.edit', { membership: props.membership.id, member: member.member_id }))"
                                            >
                                                Editar
                                            </v-btn>
                                        </div>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </v-card>
                        <!-- Árbol de cuentas relacionadas -->
                        <v-card
                            v-if="props.accountTree && (props.accountTree.origin || props.accountTree.derived.length)"
                            class="pa-4 mt-4"
                        >
                            <div class="text-subtitle-1 font-weight-bold mb-4">
                                Cuentas relacionadas
                            </div>

                            <!-- Cuenta de origen -->
                            <div v-if="props.accountTree.origin" class="mb-4">
                                <div class="text-caption text-medium-emphasis mb-1 text-uppercase">
                                    Cuenta de origen
                                </div>
                                <v-card
                                    variant="tonal"
                                    color="primary"
                                    class="pa-3 cursor-pointer"
                                    @click="props.accountTree!.origin!.membership_id && router.visit(route('members.manage.show', props.accountTree!.origin!.membership_id))"
                                >
                                    <div class="d-flex align-center gap-2">
                                        <v-icon size="small">mdi-account-arrow-up</v-icon>
                                        <div>
                                            <span class="font-weight-medium">
                                                #{{ props.accountTree.origin.membership_number }}
                                            </span>
                                            — {{ props.accountTree.origin.holder_name }}
                                            <span v-if="props.accountTree.origin.membership_type_name" class="text-medium-emphasis">
                                                ({{ props.accountTree.origin.membership_type_name }})
                                            </span>
                                        </div>
                                        <v-spacer />
                                        <v-chip size="x-small" :color="props.accountTree.origin.status === 'active' ? 'success' : 'default'">
                                            {{ statusLabel(props.accountTree.origin.status) }}
                                        </v-chip>
                                    </div>
                                </v-card>
                            </div>

                            <!-- Cuentas derivadas -->
                            <div v-if="props.accountTree.derived.length">
                                <div class="text-caption text-medium-emphasis mb-2 text-uppercase">
                                    Cuentas derivadas ({{ props.accountTree.derived.length }})
                                </div>
                                <AccountTreeNode
                                    v-for="node in props.accountTree.derived"
                                    :key="node.id"
                                    :node="node"
                                    class="mb-2"
                                />
                            </div>
                        </v-card>

                        <!-- Historial de membresía -->
                        <v-card class="pa-4 mt-4">
                            <div class="text-subtitle-1 font-weight-bold mb-4">
                                Historial de membresía
                            </div>

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
                                <template #item.reason="{ item }">
                                    {{ item.reason ?? '-' }}
                                </template>
                                <template #item.old_membership_type_name="{ item }">
                                    <span class="text-medium-emphasis">{{ item.old_membership_type_name ?? '-' }}</span>
                                </template>
                                <template #item.previous_monthly_fee="{ item }">
                                    <span class="text-medium-emphasis">
                                        {{ item.previous_monthly_fee !== null ? currencyFormatter.format(item.previous_monthly_fee) : '-' }}
                                    </span>
                                </template>
                                <template #item.new_monthly_fee="{ item }">
                                    {{ item.new_monthly_fee !== null ? currencyFormatter.format(item.new_monthly_fee) : '-' }}
                                </template>
                                <template #item.changed_by_name="{ item }">
                                    <span class="text-medium-emphasis">{{ item.changed_by_name ?? 'Sistema' }}</span>
                                </template>
                            </v-data-table-server>
                        </v-card>

                    </v-container>
                </v-col>
            </v-row>
        </div>
    </AppLayout>

    <!-- ── Dialog: Permiso por ausencia ── -->
    <v-dialog v-model="showAbsencePermitDialog" max-width="520" persistent>
        <v-card rounded="lg">
            <v-card-title class="d-flex align-center justify-space-between pa-4 pb-2">
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
                            <div class="font-weight-medium text-body-2 mb-1">
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
                                class="text-error text-caption mt-1"
                            >
                                {{ absencePermitForm.errors.absence_permit_document }}
                            </div>
                        </v-col>
                    </v-row>
                </v-form>
            </v-card-text>

            <v-divider />

            <v-card-actions class="pa-4 gap-2">
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

    <v-dialog
        v-model="showEditLockerModal"
        max-width="850"
    >
        <v-card rounded="xl">

            <!-- HEADER -->
            <div class="d-flex align-center justify-space-between px-6 py-4 border-b">

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
                            <div class="text-caption text-medium-emphasis mb-1">
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
                            <div class="text-caption text-medium-emphasis mb-1">
                                Casillero actual
                            </div>

                            <div class="text-h5 font-weight-bold">
                                {{ editingMember?.locker?.number }}
                            </div>
                        </v-card>

                    </v-col>

                </v-row>

                <!-- TITLE -->
                <div class="d-flex align-center justify-space-between mb-4">

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
                        class="d-flex justify-end align-center"
                    >
                        <v-chip
                            variant="outlined"
                            size="small"
                        >
                            {{ editTotal }} disponibles
                        </v-chip>
                    </v-col>
                </v-row>

                <!-- GRID -->
                <div class="edit-locker-grid">

                    <v-card
                        v-for="locker in availableEditLockers"
                        :key="locker.id"
                        class="locker-option text-center"
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
                <div class="d-flex align-center justify-space-between mt-6">
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
</style>
