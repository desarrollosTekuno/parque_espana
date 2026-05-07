<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, nextTick, ref, watch } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";

interface ClubOption {
    id: number;
    code: string;
    name: string;
}

interface MembershipTypeOption {
    id: number;
    club_id: number;
    name: string;
    code: string;
}

interface InterclubPackageRuleItem {
    id: number;
    source_club_id: number;
    source_club_name: string | null;
    source_club_code: string | null;
    target_club_id: number;
    target_club_name: string | null;
    target_club_code: string | null;
    source_membership_type_id: number | null;
    source_membership_type_name: string | null;
    source_membership_type_code: string | null;
    target_membership_type_id: number;
    target_membership_type_name: string | null;
    target_membership_type_code: string | null;
    package_code: string;
    min_years_in_source_club: number | null;
    requires_active_source_membership: boolean;
    monthly_fee: number;
    inscription_fee: number;
    priority: number;
    valid_from: string | null;
    valid_until: string | null;
    is_active: boolean;
}

interface Props {
    interclubPackageRules?: any;
    clubs?: ClubOption[];
    membershipTypes?: MembershipTypeOption[];
    filters?: Record<string, string | number | null>;
}

const props = withDefaults(defineProps<Props>(), {
    interclubPackageRules: null,
    clubs: () => [],
    membershipTypes: () => [],
    filters: () => ({}),
});

const page = usePage<any>();
const can = page.props.auth.permissions;
const showModal = ref(false);
const formSendRef = ref();
const isHydratingForm = ref(false);
const loading = ref(false);
const items = ref(props.interclubPackageRules?.data ?? []);
const total = ref(props.interclubPackageRules?.total ?? 0);
const search = ref(String(props.filters?.search ?? ""));
const sourceClubFilter = ref(props.filters?.source_club_id ?? null);
const sourceMembershipTypeFilter = ref(props.filters?.source_membership_type_id ?? null);
const activeFilter = ref(props.filters?.is_active ?? null);

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "priority", order: "asc" }],
});

const prefix = "interclubPackageRules";

const headers = [
    { title: "ID", key: "id" },
    { title: "Origen", key: "source", sortable: false },
    { title: "Destino", key: "target", sortable: false },
    { title: "Paquete", key: "package_code", sortable: false },
    { title: "Condiciones", key: "conditions", sortable: false },
    { title: "Cuota", key: "monthly_fee" },
    { title: "Inscripción", key: "inscription_fee", sortable: false },
    { title: "Prioridad", key: "priority" },
    { title: "Vigencia", key: "validity", sortable: false },
    { title: "Activa", key: "is_active", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
];

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const yesNoOptions = [
    { title: "Todos", value: null },
    { title: "Sí", value: "true" },
    { title: "No", value: "false" },
];

const clubFilterOptions = computed(() => [
    { title: "Todos", value: null },
    ...props.clubs.map((club) => ({
        title: `${club.code} · ${club.name}`,
        value: club.id,
    })),
]);

const sourceMembershipTypeFilterOptions = computed(() => [
    { title: "Todos", value: null },
    { title: "Sin membresía origen", value: "none" },
    ...props.membershipTypes.map((membershipType) => ({
        title: `${membershipType.code} · ${membershipType.name}`,
        value: membershipType.id,
    })),
]);

const sourceClubOptions = computed(() =>
    props.clubs.map((club) => ({
        title: `${club.code} · ${club.name}`,
        value: club.id,
    })),
);

const targetClubOptions = computed(() =>
    props.clubs
        .filter((club) => club.id === page.props.auth.currentClub)
        .map((club) => ({
            title: `${club.code} · ${club.name}`,
            value: club.id,
        })),
);

const sourceMembershipTypeOptions = computed(() => {
    if (!form.source_club_id) {
        return [{ title: "Sin membresía origen", value: null }];
    }

    return [
        { title: "Sin membresía origen", value: null },
        ...props.membershipTypes
            .filter((membershipType) => membershipType.club_id === Number(form.source_club_id))
            .map((membershipType) => ({
                title: `${membershipType.code} · ${membershipType.name}`,
                value: membershipType.id,
            })),
    ];
});

const targetMembershipTypeOptions = computed(() =>
    props.membershipTypes
        .filter((membershipType) => membershipType.club_id === Number(form.target_club_id))
        .map((membershipType) => ({
            title: `${membershipType.code} · ${membershipType.name}`,
            value: membershipType.id,
        })),
);

interface InterclubPackageRuleForm {
    id: number | null;
    source_club_id: number | null;
    target_club_id: number | null;
    source_membership_type_id: number | null;
    target_membership_type_id: number | null;
    package_code: string;
    min_years_in_source_club: number | null;
    requires_active_source_membership: boolean;
    monthly_fee: string | number;
    inscription_fee: string | number;
    priority: number;
    valid_from: string | null;
    valid_until: string | null;
    is_active: boolean;
}

const form = useForm<InterclubPackageRuleForm>({
    id: null,
    source_club_id: null,
    target_club_id: page.props.auth.currentClub ?? null,
    source_membership_type_id: null,
    target_membership_type_id: null,
    package_code: "",
    min_years_in_source_club: null,
    requires_active_source_membership: true,
    monthly_fee: "",
    inscription_fee: 0,
    priority: 10,
    valid_from: null,
    valid_until: null,
    is_active: true,
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.id = null;
    form.source_club_id = null;
    form.target_club_id = page.props.auth.currentClub ?? null;
    form.source_membership_type_id = null;
    form.target_membership_type_id = null;
    form.package_code = "";
    form.min_years_in_source_club = null;
    form.requires_active_source_membership = true;
    form.monthly_fee = "";
    form.inscription_fee = 0;
    form.priority = 10;
    form.valid_from = null;
    form.valid_until = null;
    form.is_active = true;
};

watch(
    () => form.source_club_id,
    () => {
        if (isHydratingForm.value) {
            return;
        }

        form.source_membership_type_id = null;
    },
);

watch(
    () => form.target_club_id,
    () => {
        if (isHydratingForm.value) {
            return;
        }

        form.target_membership_type_id = null;
    },
);

const openCreate = () => {
    resetForm();
    showModal.value = true;
};

const openEdit = async (item: InterclubPackageRuleItem) => {
    resetForm();
    showModal.value = true;
    isHydratingForm.value = true;
    form.id = item.id;
    form.source_club_id = item.source_club_id;
    form.target_club_id = item.target_club_id;
    form.source_membership_type_id = item.source_membership_type_id;
    form.target_membership_type_id = item.target_membership_type_id;
    form.package_code = item.package_code;
    form.min_years_in_source_club = item.min_years_in_source_club;
    form.requires_active_source_membership = item.requires_active_source_membership;
    form.monthly_fee = item.monthly_fee;
    form.inscription_fee = item.inscription_fee;
    form.priority = item.priority;
    form.valid_from = item.valid_from;
    form.valid_until = item.valid_until;
    form.is_active = item.is_active;
    await nextTick();
    isHydratingForm.value = false;
};

const close = () => {
    resetForm();
    showModal.value = false;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }: { valid: boolean }) => {
        if (!isValid) {
            return;
        }

        const callbacks = {
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({
                    title: page.props.flash.success || "",
                    icon: "success",
                });
                close();
                fetchItems();
            },
            onError: () => {
                customToastSwal({
                    title: `Error: ${form.errors.messageError}`,
                    text: `${form.errors.exception ?? ""}`,
                    icon: "error",
                });
            },
        };

        if (form.id) {
            form.put(route("interclub-package-rules.update", form.id), callbacks);
            return;
        }

        form.post(route("interclub-package-rules.store"), callbacks);
    });
};

const destroy = (item: InterclubPackageRuleItem) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este paquete interclub?",
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        form.delete(route("interclub-package-rules.destroy", item.id), {
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({
                    title: page.props.flash.success || "",
                    icon: "success",
                });
                fetchItems();
            },
            onError: () => {
                customToastSwal({
                    title: `Error: ${form.errors.messageError}`,
                    text: `${form.errors.exception ?? ""}`,
                    icon: "error",
                });
            },
        });
    });
};

const formatDate = (value: string | null) => {
    if (!value) {
        return "Sin vigencia";
    }

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

const validityLabel = (item: InterclubPackageRuleItem) => {
    if (!item.valid_from && !item.valid_until) {
        return "Siempre activo";
    }

    return `${formatDate(item.valid_from)} a ${formatDate(item.valid_until)}`;
};

const fetchItems = () => {
    loading.value = true;

    const params = {
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "priority",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "asc",
        [`${prefix}_source_club_id`]: sourceClubFilter.value,
        [`${prefix}_source_membership_type_id`]: sourceMembershipTypeFilter.value,
        [`${prefix}_is_active`]: activeFilter.value,
    };

    router.get(route("interclub-package-rules.index"), params, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
        onSuccess: (pageResponse) => {
            items.value = pageResponse.props[prefix]?.data ?? [];
            total.value = pageResponse.props[prefix]?.total ?? 0;
            loading.value = false;
        },
        onError: () => {
            loading.value = false;
        },
    });
};

watch(
    [options, search, sourceClubFilter, sourceMembershipTypeFilter, activeFilter],
    debounce(fetchItems, 400),
    { deep: true },
);

watch(
    () => page.props.auth.currentClub,
    () => {
        fetchItems();
        form.target_club_id = page.props.auth.currentClub ?? null;
    },
);
</script>

<template>
    <Head title="Paquetes interclub" />

    <AppLayout>
        <template #header>Paquetes interclub</template>
        <template #options>
            <BaseButton
                v-if="can.includes('interclub-package-rules.store')"
                variant="elevated"
                :icon-only="false"
                action="add"
                @click="openCreate"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-data-table-server
                        fixed-header
                        hover
                        height="560px"
                        :headers="headers"
                        :items="items"
                        :items-length="total"
                        :loading="loading"
                        v-model:options="options"
                        class="elevation-1"
                        :items-per-page-options="[10, 25, 50, 100]"
                        items-per-page-text="Mostrar"
                        no-data-text="No hay paquetes interclub para mostrar"
                    >
                        <template #top>
                            <v-row class="px-4 pt-4">
                                <v-col cols="12" md="3">
                                    <v-text-field
                                        v-model="search"
                                        label="Buscar"
                                        clearable
                                    />
                                </v-col>

                                <v-col cols="12" md="3">
                                    <v-select
                                        v-model="sourceClubFilter"
                                        :items="clubFilterOptions"
                                        label="Club origen"
                                        clearable
                                    />
                                </v-col>

                                <v-col cols="12" md="3">
                                    <v-select
                                        v-model="sourceMembershipTypeFilter"
                                        :items="sourceMembershipTypeFilterOptions"
                                        label="Membresía origen"
                                        clearable
                                    />
                                </v-col>

                                <v-col cols="12" md="3">
                                    <v-select
                                        v-model="activeFilter"
                                        :items="yesNoOptions"
                                        label="Activo"
                                        clearable
                                    />
                                </v-col>
                            </v-row>
                        </template>

                        <template #item.source="{ item }">
                            <div class="font-weight-medium">
                                {{ item.source_club_code }} · {{ item.source_club_name }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{
                                    item.source_membership_type_id
                                        ? `${item.source_membership_type_code} · ${item.source_membership_type_name}`
                                        : 'Sin membresía origen'
                                }}
                            </div>
                        </template>

                        <template #item.target="{ item }">
                            
                            <div class="font-weight-medium">
                                {{ item.target_club_code }} · {{ item.target_club_name }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ item.target_membership_type_code }} · {{ item.target_membership_type_name }}
                            </div>
                        </template>

                        <template #item.conditions="{ item }">
                            <div class="d-flex flex-wrap ga-1">
                                <v-chip
                                    size="small"
                                    :color="item.requires_active_source_membership ? 'primary' : 'default'"
                                    variant="tonal"
                                >
                                    {{
                                        item.requires_active_source_membership
                                            ? 'Origen activo'
                                            : 'Origen opcional'
                                    }}
                                </v-chip>
                                <v-chip
                                    size="small"
                                    color="info"
                                    variant="tonal"
                                >
                                    {{
                                        item.min_years_in_source_club !== null
                                            ? `${item.min_years_in_source_club}+ años`
                                            : 'Sin antigüedad mínima'
                                    }}
                                </v-chip>
                            </div>
                        </template>

                        <template #item.monthly_fee="{ item }">
                            {{ currencyFormatter.format(item.monthly_fee) }}
                        </template>

                        <template #item.inscription_fee="{ item }">
                            {{ currencyFormatter.format(item.inscription_fee) }}
                        </template>

                        <template #item.validity="{ item }">
                            {{ validityLabel(item) }}
                        </template>

                        <template #item.is_active="{ item }">
                            <v-chip
                                size="small"
                                :color="item.is_active ? 'success' : 'default'"
                                variant="tonal"
                            >
                                {{ item.is_active ? 'Activo' : 'Inactivo' }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('interclub-package-rules.update')"
                                action="edit"
                                @click="openEdit(item)"
                            />
                            <BaseButton
                                v-if="can.includes('interclub-package-rules.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="840" persistent>
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card
                    prepend-icon="mdi-swap-horizontal"
                    :title="`${form.id ? 'Editar paquete' : 'Nuevo paquete interclub'}`"
                >
                    <v-card-text>
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.source_club_id"
                                    :items="sourceClubOptions"
                                    label="Club origen"
                                    :rules="[(value: unknown) => !!value || 'Campo requerido']"
                                    :error-messages="form.errors.source_club_id"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.target_club_id"
                                    :items="targetClubOptions"
                                    label="Club destino"
                                    :rules="[(value: unknown) => !!value || 'Campo requerido']"
                                    :error-messages="form.errors.target_club_id"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.source_membership_type_id"
                                    :items="sourceMembershipTypeOptions"
                                    label="Membresía origen"
                                    clearable
                                    :error-messages="form.errors.source_membership_type_id"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.target_membership_type_id"
                                    :items="targetMembershipTypeOptions"
                                    label="Membresía destino"
                                    :rules="[(value: unknown) => !!value || 'Campo requerido']"
                                    :error-messages="form.errors.target_membership_type_id"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.package_code"
                                    label="Código del paquete"
                                    :rules="[(value: unknown) => !!value || 'Campo requerido']"
                                    :error-messages="form.errors.package_code"
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="form.min_years_in_source_club"
                                    label="Antigüedad mínima"
                                    type="number"
                                    min="0"
                                    :error-messages="form.errors.min_years_in_source_club"
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="form.priority"
                                    label="Prioridad"
                                    type="number"
                                    min="1"
                                    :rules="[(value: unknown) => !!value || 'Campo requerido']"
                                    :error-messages="form.errors.priority"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="form.monthly_fee"
                                    label="Cuota mensual"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :rules="[(value: unknown) => `${value}` !== '' || 'Campo requerido']"
                                    :error-messages="form.errors.monthly_fee"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="form.inscription_fee"
                                    label="Inscripción"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :rules="[(value: unknown) => `${value}` !== '' || 'Campo requerido']"
                                    :error-messages="form.errors.inscription_fee"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.requires_active_source_membership"
                                    color="primary"
                                    label="Requiere origen activo"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.valid_from"
                                    label="Válido desde"
                                    type="date"
                                    :error-messages="form.errors.valid_from"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.valid_until"
                                    label="Válido hasta"
                                    type="date"
                                    :error-messages="form.errors.valid_until"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.is_active"
                                    color="success"
                                    label="Regla activa"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        <BaseButton
                            :icon-only="false"
                            variant="tonal"
                            action="cancel"
                            @click="close"
                        />
                        <BaseButton
                            :icon-only="false"
                            :text="form.id ? 'Actualizar' : 'Guardar'"
                            variant="flat"
                            type="submit"
                            action="save"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>
