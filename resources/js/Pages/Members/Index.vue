<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";

interface MemberItem {
    id: number;
    membership_id: number | null;
    membership_number: string;
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
    is_billable: boolean;
    start_date: string | null;
    end_date: string | null;
    status: string;
}

interface PaginatedMembers {
    data: MemberItem[];
    total: number;
}

interface Props {
    members?: PaginatedMembers;
    pendingMembersCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    members: () => ({
        data: [],
        total: 0,
    }),
    pendingMembersCount: 0,
});

const headers = [
    { title: "Folio", key: "membership_number" },
    { title: "Titular", key: "holder_name" },
    { title: "Membresias activas", key: "active_memberships", sortable: false },
    { title: "Correo", key: "email", sortable: false },
    { title: "Telefono", key: "phone", sortable: false },
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
const prefix = "members";

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
        ? "No se encontraron socios titulares activos con ese criterio"
        : "No hay socios titulares activos para mostrar",
);
</script>

<template>
    <Head title="Socios" />

    <AppLayout>
        <template #header>Socios Titulares Activos</template>
        <template #options>
            <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="router.visit(route('members.create'))"
                action="add"
            />
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
                                    No hay socios titulares activos para mostrar.
                                    Actualmente existen
                                    {{ pendingMembersCount }}
                                    membresias pendientes.
                                </v-alert>

                                <v-text-field
                                    v-model="search"
                                    label="Buscar socios"
                                    class="mx-4 mt-2"
                                    clearable
                                />
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
                                                ? `Cuota a cobrar: ${currencyFormatter.format(membership.monthly_fee)}`
                                                : `Monto referencial: ${currencyFormatter.format(membership.monthly_fee)}`
                                        }}
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
                                    text="Cambiar membresia"
                                    tooltip="Cambiar el tipo de membresia en este parque"
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
                                    text="Agregar membresia"
                                    tooltip="Agregar membresia del otro parque"
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
                            </div>
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>
