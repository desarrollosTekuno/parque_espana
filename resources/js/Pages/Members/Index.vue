<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";

interface MemberItem {
    id: number;
    membership_id: number | null;
    membership_number: string;
    account_club_name: string | null;
    account_club_code: string | null;
    holder_name: string;
    email: string | null;
    phone: string | null;
    monthly_fee: number;
    status: string;
    can_change_membership: boolean;
    can_change_primary_holder: boolean;
    can_separate_member: boolean;
    active_memberships: ActiveMembershipItem[];
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
    start_date: string | null;
    end_date: string | null;
    status: string;
}

interface PaginatedMembers {
    data: MemberItem[];
    total: number;
}

interface CancelledAccountItem {
    id: number;
    membership_id: number | null;
    membership_number: string;
    holder_name: string;
    email: string | null;
    membership_type_name: string | null;
    cancelled_at: string | null;
    members_count: number;
}

interface PaginatedCancelledAccounts {
    data: CancelledAccountItem[];
    total: number;
}

interface Props {
    members?: PaginatedMembers;
    pendingMembersCount?: number;
    cancelledAccounts?: PaginatedCancelledAccounts;
}

const props = withDefaults(defineProps<Props>(), {
    members: () => ({
        data: [],
        total: 0,
    }),
    pendingMembersCount: 0,
    cancelledAccounts: () => ({
        data: [],
        total: 0,
    }),
});

const headers = [
    { title: "No. cuenta", key: "membership_number" },
    { title: "Titular", key: "holder_name" },
    { title: "Membresías activas", key: "active_memberships", sortable: false },
    { title: "Correo", key: "email", sortable: false },
    { title: "Teléfono", key: "phone", sortable: false },
    { title: "Cuota actual", key: "monthly_fee" },
    { title: "Estatus", key: "status" },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref<MemberItem[]>(props.members.data ?? []);
const total = ref(props.members.total ?? 0);
const pendingMembersCount = ref(props.pendingMembersCount ?? 0);
const loading = ref(false);
const search = ref("");
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});

const cancelledItems = ref<CancelledAccountItem[]>(props.cancelledAccounts?.data ?? []);
const cancelledTotal = ref(props.cancelledAccounts?.total ?? 0);
const cancelledLoading = ref(false);
const cancelledSearch = ref("");
const cancelledOptions = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});
const can = usePage().props.auth.permissions;
const canReactivate = can.includes("members.reactivate");

const prefix = "members";
const cancelledPrefix = "cancelled";
const activeTab = ref<"active" | "cancelled">("active");

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const statusLabel = (status: string) => {
    const map: Record<string, string> = {
        active: "Activa",
        pending: "Pendiente",
        suspended: "Suspendida",
        cancelled: "Cancelada",
    };

    return map[status] ?? status;
};

const statusColor = (status: string) => {
    const map: Record<string, string> = {
        active: "success",
        pending: "warning",
        suspended: "orange",
        cancelled: "error",
    };

    return map[status] ?? "default";
};

const fetchItems = async () => {
    loading.value = true;

    const params = {
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
    };

    router.get(route("members.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            const data = (page.props as any)[prefix]?.data ?? [];
            const totalCount = (page.props as any)[prefix]?.total ?? 0;
            const pendingCount = (page.props as any).pendingMembersCount ?? 0;

            items.value = data;
            total.value = totalCount;
            pendingMembersCount.value = pendingCount;
            loading.value = false;
        },
        onError: () => {
            loading.value = false;
        },
    });
};

watch([options, search], debounce(fetchItems, 400), { deep: true });

const emptyMessage = computed(() =>
    search.value
        ? "No se encontraron membresías activas con ese criterio"
        : "No hay membresías activas para mostrar",
);

const fetchCancelledItems = async () => {
    cancelledLoading.value = true;

    const params = {
        [`${cancelledPrefix}_page`]: cancelledOptions.value.page,
        [`${cancelledPrefix}_per_page`]: cancelledOptions.value.itemsPerPage,
        [`${cancelledPrefix}_search`]: cancelledSearch.value,
        [`${cancelledPrefix}_sort`]: cancelledOptions.value.sortBy?.[0]?.key ?? "id",
        [`${cancelledPrefix}_order`]: cancelledOptions.value.sortBy?.[0]?.order ?? "desc",
    };

    router.get(route("members.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            const data = (page.props as any).cancelledAccounts?.data ?? [];
            const totalCount = (page.props as any).cancelledAccounts?.total ?? 0;
            cancelledItems.value = data;
            cancelledTotal.value = totalCount;
            cancelledLoading.value = false;
        },
        onError: () => {
            cancelledLoading.value = false;
        },
    });
};

watch([cancelledOptions, cancelledSearch], debounce(fetchCancelledItems, 400), { deep: true });

const formatDateTime = (value: string | null) => {
    if (!value) return "-";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(date);
};
</script>

<template>
    <Head title="Usuarios" />

    <AppLayout>
        <template #header>Membresías</template>
        <template #options>
            <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="router.visit(route('members.create'))"
                action="add"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-tabs v-model="activeTab" color="primary" class="border-b">
                <v-tab value="active">Membresías activas</v-tab>
                <v-tab v-if="canReactivate" value="cancelled">
                    Dadas de baja
                    <v-badge
                        v-if="cancelledTotal > 0"
                        :content="cancelledTotal"
                        color="warning"
                        inline
                        class="ml-2"
                    />
                </v-tab>
            </v-tabs>

            <v-tabs-window v-model="activeTab">
                <!-- Tab: Membresías activas -->
                <v-tabs-window-item value="active">
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
                        items-per-page-text="Mostrar"
                        :no-data-text="emptyMessage"
                    >
                        <template #top>
                            <div>
                                <v-alert
                                    v-if="!loading && total === 0 && pendingMembersCount > 0"
                                    type="info"
                                    variant="tonal"
                                    class="mx-4 mt-4 mb-2"
                                >
                                    No hay membresías activas para mostrar.
                                    Actualmente existen
                                    {{ pendingMembersCount }}
                                    membresías pendientes.
                                </v-alert>

                                <v-text-field
                                    v-model="search"
                                    label="Buscar usuarios"
                                    class="mx-4 mt-2"
                                    clearable
                                />
                            </div>
                        </template>

                        <template #item.membership_number="{ item }">
                            <div class="font-weight-medium">
                                {{ item.membership_number }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ item.account_club_code || "-" }} ·
                                {{ item.account_club_name || "Sin club" }}
                            </div>
                        </template>

                        <template #item.active_memberships="{ item }">
                            <div class="py-2 d-flex flex-column ga-2">
                                <div
                                    v-for="membership in item.active_memberships"
                                    :key="membership.id"
                                    class="border rounded-lg px-3 py-2"
                                >
                                    <div
                                        class="d-flex flex-wrap align-center justify-space-between ga-2"
                                    >
                                        <div>
                                            <div class="font-weight-medium">
                                                {{ membership.membership_type_name }}
                                            </div>
                                            <div class="text-caption text-medium-emphasis">
                                                {{ membership.club_code }} · {{ membership.club_name }}
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap ga-2">
                                            <v-chip
                                                size="small"
                                                :color="membership.is_billable ? 'success' : 'default'"
                                                :variant="membership.is_billable ? 'flat' : 'tonal'"
                                            >
                                                {{
                                                    membership.is_billable
                                                        ? "Se cobra"
                                                        : "Incluida"
                                                }}
                                            </v-chip>
                                            <v-chip
                                                v-if="membership.billing_split_mode === 'equal_split'"
                                                size="small"
                                                color="info"
                                                variant="tonal"
                                            >
                                                50/50
                                            </v-chip>
                                            <v-chip
                                                size="small"
                                                :color="statusColor(membership.status)"
                                                variant="tonal"
                                            >
                                                {{ statusLabel(membership.status) }}
                                            </v-chip>
                                        </div>
                                    </div>

                                    <div class="text-caption text-medium-emphasis mt-2">
                                        {{
                                            membership.is_billable
                                                ? `Cuota a cobrar: ${currencyFormatter.format(membership.monthly_fee_share)}`
                                                : `Monto referencial: ${currencyFormatter.format(membership.monthly_fee_share)}`
                                        }}
                                    </div>
                                    <div
                                        v-if="membership.monthly_fee_total !== membership.monthly_fee_share"
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Cuota total del esquema:
                                        {{ currencyFormatter.format(membership.monthly_fee_total) }}
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template #item.monthly_fee="{ item }">
                            <div class="font-weight-bold">
                                {{ currencyFormatter.format(item.monthly_fee) }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                Total actual a cobrar
                            </div>
                        </template>

                        <template #item.email="{ item }">
                            {{ item.email || "-" }}
                        </template>

                        <template #item.phone="{ item }">
                            {{ item.phone || "-" }}
                        </template>

                        <template #item.status="{ item }">
                            <v-chip
                                :color="statusColor(item.status)"
                                size="small"
                                variant="tonal"
                            >
                                {{ statusLabel(item.status) }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <div class="d-flex flex-wrap justify-end">
                                <BaseButton
                                    :icon-only="false"
                                    action="edit"
                                    text="Gestionar cuenta"
                                    tooltip="Ver detalle de la cuenta y sus integrantes"
                                    @click="
                                        router.visit(
                                            route(
                                                'members.manage.show',
                                                item.membership_id,
                                            ),
                                        )
                                    "
                                    :disabled="!item.membership_id"
                                />

                                <BaseButton
                                    v-if="item.can_separate_member"
                                    :icon-only="false"
                                    action="add"
                                    text="Separar integrante"
                                    tooltip="Crear una nueva cuenta para un integrante de la familia"
                                    @click="
                                        router.visit(
                                            route(
                                                'members.separation.create',
                                                item.membership_id,
                                            ),
                                        )
                                    "
                                    :disabled="!item.membership_id"
                                />

                                <BaseButton
                                    v-if="item.can_change_primary_holder"
                                    :icon-only="false"
                                    action="edit"
                                    text="Cambiar titular"
                                    tooltip="Cambiar el titular de la cuenta familiar"
                                    @click="
                                        router.visit(
                                            route(
                                                'members.change-holder.create',
                                                item.membership_id,
                                            ),
                                        )
                                    "
                                    :disabled="!item.membership_id"
                                />

                                <BaseButton
                                    v-if="item.can_change_membership"
                                    :icon-only="false"
                                    action="edit"
                                    text="Cambiar membresía"
                                    tooltip="Cambiar el tipo de membresía en este parque"
                                    @click="
                                        router.visit(
                                            route(
                                                'members.transition.create',
                                                item.membership_id,
                                            ),
                                        )
                                    "
                                    :disabled="!item.membership_id"
                                />

                                <BaseButton
                                    :icon-only="false"
                                    action="add"
                                    text="Agregar membresía"
                                    tooltip="Agregar membresía del otro parque"
                                    @click="
                                        router.visit(
                                            route(
                                                'members.additional-membership.create',
                                                item.membership_id,
                                            ),
                                        )
                                    "
                                    :disabled="!item.membership_id"
                                />

                                <BaseButton
                                    :icon-only="false"
                                    action="delete"
                                    text="Dar de baja"
                                    tooltip="Procesar baja de la cuenta a solicitud del titular"
                                    @click="
                                        router.visit(
                                            route(
                                                'members.cancel.create',
                                                item.membership_id,
                                            ),
                                        )
                                    "
                                    :disabled="!item.membership_id"
                                />
                            </div>
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
                </v-tabs-window-item>

                <!-- Tab: Cuentas dadas de baja voluntaria -->
                <v-tabs-window-item v-if="canReactivate" value="cancelled">
                    <v-row>
                        <v-col cols="12">
                            <v-data-table-server
                                fixed-header
                                hover
                                height="500px"
                                :headers="[
                                    { title: 'No. cuenta', key: 'membership_number' },
                                    { title: 'Titular', key: 'holder_name' },
                                    { title: 'Membresía', key: 'membership_type_name', sortable: false },
                                    { title: 'Correo', key: 'email', sortable: false },
                                    { title: 'Fecha de baja', key: 'cancelled_at' },
                                    { title: 'Integrantes', key: 'members_count', sortable: false },
                                    { title: 'Acciones', key: 'actions', sortable: false },
                                ]"
                                :items="cancelledItems"
                                :items-length="cancelledTotal"
                                :loading="cancelledLoading"
                                v-model:options="cancelledOptions"
                                class="elevation-1"
                                :items-per-page-options="[10, 25, 50, 100]"
                                items-per-page-text="Mostrar"
                                no-data-text="No hay cuentas dadas de baja voluntaria"
                            >
                                <template #top>
                                    <v-text-field
                                        v-model="cancelledSearch"
                                        label="Buscar cuentas dadas de baja"
                                        class="mx-4 mt-2"
                                        clearable
                                    />
                                </template>

                                <template #item.membership_number="{ item }">
                                    <div class="font-weight-medium">{{ item.membership_number }}</div>
                                </template>

                                <template #item.cancelled_at="{ item }">
                                    <div class="text-caption">{{ formatDateTime(item.cancelled_at) }}</div>
                                </template>

                                <template #item.email="{ item }">
                                    {{ item.email || "-" }}
                                </template>

                                <template #item.membership_type_name="{ item }">
                                    {{ item.membership_type_name || "-" }}
                                </template>

                                <template #item.members_count="{ item }">
                                    <v-chip size="x-small" variant="tonal">
                                        {{ item.members_count }}
                                    </v-chip>
                                </template>

                                <template #item.actions="{ item }">
                                    <div class="d-flex flex-wrap justify-end">
                                        <BaseButton
                                            :icon-only="false"
                                            action="edit"
                                            text="Reactivar cuenta"
                                            tooltip="Ver historial y reactivar esta cuenta"
                                            @click="
                                                router.visit(
                                                    route(
                                                        'members.reactivate.create',
                                                        item.membership_id,
                                                    ),
                                                )
                                            "
                                            :disabled="!item.membership_id"
                                        />
                                    </div>
                                </template>
                            </v-data-table-server>
                        </v-col>
                    </v-row>
                </v-tabs-window-item>
            </v-tabs-window>
        </div>
    </AppLayout>
</template>
