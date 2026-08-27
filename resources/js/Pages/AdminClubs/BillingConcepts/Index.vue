<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";

interface BillingConceptItem {
    id: number;
    code: string;
    internal_key: string | null;
    name: string;
    description: string | null;
    default_amount: number | null;
    club_amount: number | null;
    allows_manual_amount: boolean;
    is_recurring: boolean;
    allows_partial_payments: boolean;
    is_mobile_payable: boolean;
    splits_between_parks: boolean;
    applies_iva: boolean;
    club_applies_iva: boolean | null;
    is_active: boolean;
    requires_account: boolean;
}

interface CurrentClub {
    id: number;
    name: string;
    code: string;
}

interface Props {
    billingConcepts?: any;
    currentClub?: CurrentClub | null;
    filters?: Record<string, string | number | null>;
}

const props = withDefaults(defineProps<Props>(), {
    billingConcepts: null,
    currentClub: null,
    filters: () => ({}),
});

const page = usePage<any>();
const can = page.props.auth.permissions;
const showModal = ref(false);
const formSendRef = ref();
const loading = ref(false);
const items = ref(props.billingConcepts?.data ?? []);
const total = ref(props.billingConcepts?.total ?? 0);
const search = ref(String(props.filters?.search ?? ""));
const activeFilter = ref(props.filters?.is_active ?? null);
const prefix = "billingConcepts";

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "name", order: "asc" }],
});

const headers = computed(() => [
    // { title: "ID", key: "id" },
    { title: "Concepto", key: "name" },
    { title: "Clave interna", key: "internal_key", sortable: false },
    { title: "Monto base", key: "default_amount" },
    {
        title: props.currentClub?.code
            ? `Monto ${props.currentClub.code}`
            : "Monto del parque",
        key: "club_amount",
    },
    { title: "Configuración", key: "settings", sortable: false },
    { title: "Activo", key: "is_active", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
]);

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

interface BillingConceptForm {
    id: number | null;
    code: string;
    internal_key: string | null;
    name: string;
    description: string | null;
    default_amount: string | number | null;
    club_amount: string | number | null;
    allows_manual_amount: boolean;
    is_recurring: boolean;
    allows_partial_payments: boolean;
    is_mobile_payable: boolean;
    splits_between_parks: boolean;
    applies_iva: boolean;
    club_applies_iva: boolean | null;
    is_active: boolean;
    requires_account: boolean;
}

const form = useForm<BillingConceptForm>({
    id: null,
    code: "",
    internal_key: null,
    name: "",
    description: null,
    default_amount: null,
    club_amount: null,
    allows_manual_amount: true,
    is_recurring: false,
    allows_partial_payments: false,
    is_mobile_payable: true,
    splits_between_parks: false,
    applies_iva: false,
    club_applies_iva: null,
    is_active: true,
    requires_account: true,
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.id = null;
    form.code = "";
    form.internal_key = null;
    form.name = "";
    form.description = null;
    form.default_amount = null;
    form.club_amount = null;
    form.allows_manual_amount = true;
    form.is_recurring = false;
    form.allows_partial_payments = false;
    form.is_mobile_payable = true;
    form.splits_between_parks = false;
    form.applies_iva = false;
    form.club_applies_iva = null;
    form.is_active = true;
    form.requires_account = true;
};

const openCreate = () => {
    resetForm();
    showModal.value = true;
};

const openEdit = (item: BillingConceptItem) => {
    resetForm();
    form.id = item.id;
    form.code = item.code;
    form.internal_key = item.internal_key;
    form.name = item.name;
    form.description = item.description;
    form.default_amount = item.default_amount;
    form.club_amount = item.club_amount;
    form.allows_manual_amount = item.allows_manual_amount;
    form.is_recurring = item.is_recurring;
    form.allows_partial_payments = item.allows_partial_payments;
    form.is_mobile_payable = item.is_mobile_payable;
    form.splits_between_parks = item.splits_between_parks;
    form.applies_iva = item.applies_iva;
    form.club_applies_iva = item.club_applies_iva;
    form.is_active = item.is_active;
    form.requires_account = item.requires_account;
    showModal.value = true;
};

const close = () => {
    resetForm();
    showModal.value = false;
};

const formatAmount = (value: number | null) => {
    return value === null ? "Sin definir" : currencyFormatter.format(value);
};

// El override del parque en sesión manda si existe; si no, se usa el
// default del concepto (ver ChargeConcept::resolveAppliesIvaForClub).
const resolveConceptAppliesIva = (item: BillingConceptItem) =>
    item.club_applies_iva ?? item.applies_iva;

const clubIvaOverrideOptions = [
    // { title: "Usar el default del concepto", value: null },
    { title: "Sí", value: true },
    { title: "No", value: false },
];

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
            form.put(route("billing-concepts.update", form.id), callbacks);
            return;
        }

        form.post(route("billing-concepts.store"), callbacks);
    });
};

const destroy = (item: BillingConceptItem) => {
    customConfirmSwal({
        title: "¿Esta segur@ que desea eliminar este concepto de cobro?",
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        form.delete(route("billing-concepts.destroy", item.id), {
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

const fetchItems = () => {
    loading.value = true;

    const params = {
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "name",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "asc",
        [`${prefix}_is_active`]: activeFilter.value,
    };

    router.get(route("billing-concepts.index"), params, {
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
    [options, search, activeFilter],
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
    <Head title="Conceptos de cobro" />

    <AppLayout>
        <template #header>Conceptos de cobro</template>
        <template #options>
            <BaseButton
                v-if="can.includes('billing-concepts.store')"
                variant="elevated"
                :icon-only="false"
                action="add"
                @click="openCreate"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-alert
                        v-if="currentClub"
                        type="info"
                        variant="tonal"
                        class="mx-4 mt-4"
                    >
                        Los montos por parque se editan sobre el club actual:
                        <strong>{{ currentClub.name }} ({{ currentClub.code }})</strong>.
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
                        no-data-text="No hay conceptos de cobro para mostrar"
                    >
                        <template #top>
                            <v-row class="px-4 pt-4">
                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="search"
                                        label="Buscar"
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

                        <template #item.name="{ item }">
                            <div class="font-weight-medium">
                                {{ item.code }} · {{ item.name }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ item.description || "Sin descripción" }}
                            </div>
                        </template>

                        <template #item.internal_key="{ item }">
                            <v-chip v-if="item.internal_key" size="small" variant="tonal">
                                {{ item.internal_key }}
                            </v-chip>
                            <span v-else class="text-medium-emphasis">—</span>
                        </template>

                        <template #item.default_amount="{ item }">
                            {{ formatAmount(item.default_amount) }}
                        </template>

                        <template #item.club_amount="{ item }">
                            {{ formatAmount(item.club_amount) }}
                        </template>

                        <template #item.settings="{ item }">
                            <div class="d-flex flex-wrap ga-1">
                                <v-chip
                                    size="small"
                                    :color="item.is_recurring ? 'primary' : 'default'"
                                    variant="tonal"
                                >
                                    {{ item.is_recurring ? "Recurrente" : "No recurrente" }}
                                </v-chip>
                                <v-chip
                                    size="small"
                                    :color="item.allows_partial_payments ? 'success' : 'default'"
                                    variant="tonal"
                                >
                                    {{
                                        item.allows_partial_payments
                                            ? "Permite parcialidades"
                                            : "Sin parcialidades"
                                    }}
                                </v-chip>
                                <v-chip
                                    size="small"
                                    :color="item.is_mobile_payable ? 'primary' : 'default'"
                                    variant="tonal"
                                >
                                    {{ item.is_mobile_payable ? "Pagable en app" : "No pagable en app" }}
                                </v-chip>
                                <v-chip
                                    v-if="item.splits_between_parks"
                                    size="small"
                                    color="info"
                                    variant="tonal"
                                >
                                    Split 50/50
                                </v-chip>
                                <v-chip
                                    size="small"
                                    :color="resolveConceptAppliesIva(item) ? 'primary' : 'default'"
                                    variant="tonal"
                                >
                                    {{ resolveConceptAppliesIva(item) ? "Facturable" : "No facturable" }}
                                    <span v-if="item.club_applies_iva !== null" class="ml-1">({{ currentClub?.code }})</span>
                                </v-chip>
                                <v-chip
                                    v-if="!item.requires_account"
                                    size="small"
                                    color="warning"
                                    variant="tonal"
                                >
                                    Sin cuenta
                                </v-chip>
                                <v-chip
                                    v-if="!item.allows_manual_amount"
                                    size="small"
                                    color="warning"
                                    variant="tonal"
                                >
                                    Importe fijo
                                </v-chip>
                            </div>
                        </template>

                        <template #item.is_active="{ item }">
                            <v-chip
                                size="small"
                                :color="item.is_active ? 'success' : 'default'"
                                variant="tonal"
                            >
                                {{ item.is_active ? "Activo" : "Inactivo" }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('billing-concepts.update')"
                                action="edit"
                                @click="openEdit(item)"
                            />
                            <BaseButton
                                v-if="can.includes('billing-concepts.destroy')"
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
                    prepend-icon="mdi-receipt-text-outline"
                    :title="`${form.id ? 'Editar concepto' : 'Nuevo concepto de cobro'}`"
                >
                    <v-card-text>
                        <v-row>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="form.code"
                                    label="Código"
                                    :rules="[(value: unknown) => !!value || 'Campo requerido']"
                                    :error-messages="form.errors.code"
                                    :disabled="form.id !== null"
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="form.internal_key"
                                    label="Clave interna"
                                    hint="Referencia al catálogo contable/legado, opcional"
                                    persistent-hint
                                    :error-messages="form.errors.internal_key"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre"
                                    :rules="[(value: unknown) => !!value || 'Campo requerido']"
                                    :error-messages="form.errors.name"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.description"
                                    label="Descripción"
                                    rows="2"
                                    auto-grow
                                    :error-messages="form.errors.description"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.default_amount"
                                    label="Monto base"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :error-messages="form.errors.default_amount"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.club_amount"
                                    :label="currentClub?.code ? `Monto ${currentClub.code}` : 'Monto del parque actual'"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    hint="Si se deja vacio, se usa el monto base."
                                    persistent-hint
                                    :error-messages="form.errors.club_amount"
                                />
                            </v-col>

                            <!-- <v-col cols="12" md="6">
                                <v-switch
                                    v-model="form.applies_iva"
                                    color="primary"
                                    label="Factura IVA (default)"
                                    hint="Si este concepto factura IVA cuando no hay un override específico para el parque."
                                    persistent-hint
                                />
                            </v-col> -->

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.club_applies_iva"
                                    :items="clubIvaOverrideOptions"
                                    :label="currentClub?.code ? `¿Este concepto es facturable?` : 'Si'"
                                   
                                    persistent-hint
                                    :error-messages="form.errors.club_applies_iva"
                                />
                            </v-col>
                            <v-col cols=12></v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.is_recurring"
                                    color="primary"
                                    label="Recurrente"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.allows_partial_payments"
                                    color="primary"
                                    label="Permite parcialidades"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.is_mobile_payable"
                                    color="primary"
                                    label="Pagable desde la app"
                                    hint="Si se apaga, la app no podrá cobrar cargos de este concepto."
                                    persistent-hint
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.splits_between_parks"
                                    color="info"
                                    label="Divide 50/50 entre parques"
                                    hint="Solo aplicaría a socios titulares en ambos parques."
                                    persistent-hint
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.is_active"
                                    color="success"
                                    label="Activo"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.requires_account"
                                    color="warning"
                                    :label="form.requires_account ? 'Requiere cuenta de socio' : 'Se puede vender sin cuenta'"
                                    hint="Apágalo para conceptos que se venden a cualquiera sin ligarlos a un socio (p. ej. un pase diario a un visitante)."
                                    persistent-hint
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-switch
                                    v-model="form.allows_manual_amount"
                                    color="warning"
                                    :label="form.allows_manual_amount ? 'Permite capturar importe a mano' : 'Importe fijo (no editable)'"
                                    hint="Apágalo para que en Cobranza el importe quede fijo al monto configurado (base o del parque) y no se pueda escribir a mano."
                                    persistent-hint
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
