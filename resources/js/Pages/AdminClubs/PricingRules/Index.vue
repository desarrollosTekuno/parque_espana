<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";

interface MembershipTypeOption {
    id: number;
    name: string;
    code: string;
    club_name: string | null;
}

interface PricingRuleItem {
    id: number;
    membership_type_id: number;
    membership_type_name: string | null;
    membership_type_code: string | null;
    membership_type_club_name: string | null;
    from_membership_type_id: number | null;
    from_membership_type_name: string | null;
    from_membership_type_code: string | null;
    from_membership_type_club_name: string | null;
    min_age: number | null;
    max_age: number | null;
    requires_origin_family: boolean;
    requires_multiple_clubs: boolean;
    monthly_fee: number | null;
    inscription_fee: number | null;
    priority: number;
    valid_from: string | null;
    valid_until: string | null;
    is_active: boolean;
}

interface Props {
    pricingRules?: any;
    targetMembershipTypes?: MembershipTypeOption[];
    originMembershipTypes?: MembershipTypeOption[];
    filters?: Record<string, string | number | null>;
}

const props = withDefaults(defineProps<Props>(), {
    pricingRules: null,
    targetMembershipTypes: () => [],
    originMembershipTypes: () => [],
    filters: () => ({}),
});

const page = usePage<any>();
const can = page.props.auth.permissions;
const showModal = ref(false);
const formSendRef = ref();
const loading = ref(false);
const items = ref(props.pricingRules?.data ?? []);
const total = ref(props.pricingRules?.total ?? 0);
const search = ref(String(props.filters?.search ?? ""));
const membershipTypeFilter = ref(props.filters?.membership_type_id ?? null);
const fromMembershipTypeFilter = ref(props.filters?.from_membership_type_id ?? null);
const multipleClubsFilter = ref(props.filters?.requires_multiple_clubs ?? null);
const activeFilter = ref(props.filters?.is_active ?? null);

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "priority", order: "asc" }],
});

const prefix = "pricingRules";

const headers = [
    { title: "ID", key: "id" },
    { title: "Destino", key: "membership_type_name" },
    { title: "Origen", key: "from_membership_type_name", sortable: false },
    { title: "Edad", key: "age_range", sortable: false },
    { title: "Condiciones", key: "conditions", sortable: false },
    { title: "Cuota vigente", key: "monthly_fee", sortable: false },
    { title: "Inscripción vigente", key: "inscription_fee", sortable: false },
    { title: "Prioridad", key: "priority" },
    { title: "Vigencia", key: "validity", sortable: false },
    { title: "Activo", key: "is_active", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
];

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const formatAmount = (value: number | null) => (value === null ? "Sin definir" : currencyFormatter.format(value));

const yesNoOptions = [
    { title: "Todos", value: null },
    { title: "Sí", value: "true" },
    { title: "No", value: "false" },
];

const fromMembershipTypeFilterOptions = computed(() => [
    { title: "Todos", value: null },
    { title: "Sin membresía origen", value: "none" },
    ...props.originMembershipTypes.map((membershipType) => ({
        title: `${membershipType.code} · ${membershipType.name}${membershipType.club_name ? ` (${membershipType.club_name})` : ""}`,
        value: membershipType.id,
    })),
]);

const targetMembershipTypeOptions = computed(() =>
    props.targetMembershipTypes.map((membershipType) => ({
        title: `${membershipType.code} · ${membershipType.name}`,
        value: membershipType.id,
    })),
);

const originMembershipTypeOptions = computed(() => [
    { title: "Sin membresía origen", value: null },
    ...props.originMembershipTypes.map((membershipType) => ({
        title: `${membershipType.code} · ${membershipType.name}${membershipType.club_name ? ` (${membershipType.club_name})` : ""}`,
        value: membershipType.id,
    })),
]);

interface PricingRuleForm {
    id: number | null;
    membership_type_id: number | null;
    from_membership_type_id: number | null;
    min_age: number | null;
    max_age: number | null;
    requires_origin_family: boolean;
    requires_multiple_clubs: boolean;
    priority: number;
    valid_from: string | null;
    valid_until: string | null;
    is_active: boolean;
}

const form = useForm<PricingRuleForm>({
    id: null,
    membership_type_id: null,
    from_membership_type_id: null,
    min_age: null,
    max_age: null,
    requires_origin_family: false,
    requires_multiple_clubs: false,
    priority: 10,
    valid_from: null,
    valid_until: null,
    is_active: true,
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.id = null;
    form.from_membership_type_id = null;
    form.min_age = null;
    form.max_age = null;
    form.requires_origin_family = false;
    form.requires_multiple_clubs = false;
    form.priority = 10;
    form.valid_from = null;
    form.valid_until = null;
    form.is_active = true;
};

const openCreate = () => {
    resetForm();
    showModal.value = true;
};

const openEdit = (item: PricingRuleItem) => {
    resetForm();
    form.id = item.id;
    form.membership_type_id = item.membership_type_id;
    form.from_membership_type_id = item.from_membership_type_id;
    form.min_age = item.min_age;
    form.max_age = item.max_age;
    form.requires_origin_family = item.requires_origin_family;
    form.requires_multiple_clubs = item.requires_multiple_clubs;
    form.priority = item.priority;
    form.valid_from = item.valid_from;
    form.valid_until = item.valid_until;
    form.is_active = item.is_active;
    showModal.value = true;
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
            form.put(route("pricing-rules.update", form.id), callbacks);
            return;
        }

        form.post(route("pricing-rules.store"), callbacks);
    });
};

const destroy = (item: PricingRuleItem) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar esta regla de precio?",
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        form.delete(route("pricing-rules.destroy", item.id), {
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

const ageRangeLabel = (item: PricingRuleItem) => {
    if (item.min_age === null && item.max_age === null) {
        return "Sin restricción";
    }

    if (item.min_age !== null && item.max_age !== null) {
        return `${item.min_age} a ${item.max_age} años`;
    }

    if (item.min_age !== null) {
        return `Desde ${item.min_age} años`;
    }

    return `Hasta ${item.max_age} años`;
};

const validityLabel = (item: PricingRuleItem) => {
    if (!item.valid_from && !item.valid_until) {
        return "Siempre activa";
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
        [`${prefix}_membership_type_id`]: membershipTypeFilter.value,
        [`${prefix}_from_membership_type_id`]: fromMembershipTypeFilter.value,
        [`${prefix}_requires_multiple_clubs`]: multipleClubsFilter.value,
        [`${prefix}_is_active`]: activeFilter.value,
    };

    router.get(route("pricing-rules.index"), params, {
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
    [options, search, membershipTypeFilter, fromMembershipTypeFilter, multipleClubsFilter, activeFilter],
    debounce(fetchItems, 400),
    { deep: true },
);

watch(
    () => page.props.auth.currentClub,
    () => {
        fetchItems();
    },
);
</script>

<template>
    <Head title="Reglas de precio" />

    <AppLayout>
        <template #header>Reglas de precio</template>
        <template #options>
            <BaseButton
                v-if="can.includes('pricing-rules.store')"
                variant="elevated"
                :icon-only="false"
                action="add"
                @click="openCreate"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-alert type="info" variant="tonal" class="mx-4 mt-4">
                        Esta pantalla define a quién le aplica cada regla (tipo de membresía,
                        edad, origen, multiclub). Las cuotas por año se capturan y consultan
                        desde el módulo <strong>Cuotas por año</strong>.
                    </v-alert>

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
                        no-data-text="No hay reglas de precio para mostrar"
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
                                        v-model="membershipTypeFilter"
                                        :items="[{ title: 'Todos', value: null }, ...targetMembershipTypeOptions]"
                                        label="Membresía destino"
                                        clearable
                                    />
                                </v-col>

                                <v-col cols="12" md="3">
                                    <v-select
                                        v-model="fromMembershipTypeFilter"
                                        :items="fromMembershipTypeFilterOptions"
                                        label="Membresía origen"
                                        clearable
                                    />
                                </v-col>

                                <v-col cols="12" md="3">
                                    <v-select
                                        v-model="multipleClubsFilter"
                                        :items="yesNoOptions"
                                        label="Multiclub"
                                        clearable
                                    />
                                </v-col>

                                <v-col cols="12" md="3">
                                    <v-select
                                        v-model="activeFilter"
                                        :items="yesNoOptions"
                                        label="Activa"
                                        clearable
                                    />
                                </v-col>
                            </v-row>
                        </template>

                        <template #item.membership_type_name="{ item }">
                            <div class="font-weight-medium">
                                {{ item.membership_type_code }} · {{ item.membership_type_name }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ item.membership_type_club_name || '-' }}
                            </div>
                        </template>

                        <template #item.from_membership_type_name="{ item }">
                            <div v-if="item.from_membership_type_id">
                                <div class="font-weight-medium">
                                    {{ item.from_membership_type_code }} · {{ item.from_membership_type_name }}
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ item.from_membership_type_club_name || '-' }}
                                </div>
                            </div>
                            <span v-else class="text-medium-emphasis">Sin origen</span>
                        </template>

                        <template #item.age_range="{ item }">
                            {{ ageRangeLabel(item) }}
                        </template>

                        <template #item.conditions="{ item }">
                            <div class="d-flex flex-wrap ga-1">
                                <v-chip
                                    size="small"
                                    :color="item.requires_origin_family ? 'primary' : 'default'"
                                    variant="tonal"
                                >
                                    {{
                                        item.requires_origin_family
                                            ? "Requiere origen familiar"
                                            : "Sin origen familiar"
                                    }}
                                </v-chip>
                                <v-chip
                                    size="small"
                                    :color="item.requires_multiple_clubs ? 'success' : 'default'"
                                    variant="tonal"
                                >
                                    {{
                                        item.requires_multiple_clubs
                                            ? "Multiclub"
                                            : "Club único"
                                    }}
                                </v-chip>
                            </div>
                        </template>

                        <template #item.monthly_fee="{ item }">
                            {{ formatAmount(item.monthly_fee) }}
                        </template>

                        <template #item.inscription_fee="{ item }">
                            {{ formatAmount(item.inscription_fee) }}
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
                                {{ item.is_active ? "Activa" : "Inactiva" }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('pricing-rules.update')"
                                action="edit"
                                @click="openEdit(item)"
                            />
                            <BaseButton
                                v-if="can.includes('pricing-rules.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="760" persistent>
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card
                    prepend-icon="mdi-currency-usd"
                    :title="`${form.id ? 'Editar regla' : 'Nueva regla de precio'}`"
                >
                    <v-card-text>
                        <v-alert v-if="!form.id" type="info" variant="tonal" density="compact" class="mb-4">
                            Después de guardar, captura la cuota de esta regla en el módulo
                            Cuotas por año.
                        </v-alert>

                        <v-row>
                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.membership_type_id"
                                    :items="targetMembershipTypeOptions"
                                    label="Membresía destino"
                                    :rules="[(value: unknown) => !!value || 'Campo requerido']"
                                    :error-messages="form.errors.membership_type_id"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.from_membership_type_id"
                                    :items="originMembershipTypeOptions"
                                    label="Membresía origen"
                                    clearable
                                    :error-messages="form.errors.from_membership_type_id"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.min_age"
                                    label="Edad mínima"
                                    type="number"
                                    min="0"
                                    :error-messages="form.errors.min_age"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.max_age"
                                    label="Edad máxima"
                                    type="number"
                                    min="0"
                                    :error-messages="form.errors.max_age"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.priority"
                                    label="Prioridad"
                                    type="number"
                                    min="1"
                                    :rules="[(value: unknown) => !!value || 'Campo requerido']"
                                    :error-messages="form.errors.priority"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.valid_from"
                                    label="Válida desde"
                                    type="date"
                                    :error-messages="form.errors.valid_from"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.valid_until"
                                    label="Válida hasta"
                                    type="date"
                                    :error-messages="form.errors.valid_until"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.requires_origin_family"
                                    color="primary"
                                    label="Requiere origen familiar"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.requires_multiple_clubs"
                                    color="primary"
                                    label="Requiere múltiples clubes"
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
