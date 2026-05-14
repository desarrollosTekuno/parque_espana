<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";
import { required, minLength,maxLength } from '../../../constants/validationRules';
interface PaymentMethodItem {
    id: number;
    code: string;
    name: string;
    description: string | null;
    requires_reference: boolean;
    requires_bank_name: boolean;
    requires_check_number: boolean;
    affects_cash_cut: boolean;
    is_active: boolean;
    club_enabled: boolean;
    club_display_order: number;
}

interface CurrentClub {
    id: number;
    name: string;
    code: string;
}

interface Props {
    paymentMethods?: any;
    currentClub?: CurrentClub | null;
    filters?: Record<string, string | number | null>;
}

const props = withDefaults(defineProps<Props>(), {
    paymentMethods: null,
    currentClub: null,
    filters: () => ({}),
});

const page = usePage<any>();
const can = page.props.auth.permissions;
const showModal = ref(false);
const formSendRef = ref();
const loading = ref(false);
const items = ref(props.paymentMethods?.data ?? []);
const total = ref(props.paymentMethods?.total ?? 0);
const search = ref(String(props.filters?.search ?? ""));
const activeFilter = ref(props.filters?.is_active ?? null);
const prefix = "paymentMethods";

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "name", order: "asc" }],
});

const headers = computed(() => [
    // { title: "ID", key: "id" },
    { title: "Método de pago", key: "name" },
    { title: "Requisitos", key: "requirements", sortable: false },
    { title: "Corte de caja", key: "affects_cash_cut", sortable: false },
    { title: "Activo", key: "is_active", sortable: false },
    {
        title: props.currentClub?.code
            ? `Habilitado en ${props.currentClub.code}`
            : "Habilitado en club",
        key: "club_enabled",
        sortable: false,
    },
    { title: "Acciones", key: "actions", sortable: false },
]);

const yesNoOptions = [
    { title: "Todos", value: null },
    { title: "Sí", value: "true" },
    { title: "No", value: "false" },
];

interface PaymentMethodForm {
    id: number | null;
    code: string;
    name: string;
    description: string | null;
    requires_reference: boolean;
    requires_bank_name: boolean;
    requires_check_number: boolean;
    affects_cash_cut: boolean;
    is_active: boolean;
}

const form = useForm<PaymentMethodForm>({
    id: null,
    code: "",
    name: "",
    description: null,
    requires_reference: false,
    requires_bank_name: false,
    requires_check_number: false,
    affects_cash_cut: true,
    is_active: true,
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.id = null;
    form.code = "";
    form.name = "";
    form.description = null;
    form.requires_reference = false;
    form.requires_bank_name = false;
    form.requires_check_number = false;
    form.affects_cash_cut = true;
    form.is_active = true;
};

const openCreate = () => {
    resetForm();
    showModal.value = true;
};

const openEdit = (item: PaymentMethodItem) => {
    resetForm();
    form.id = item.id;
    form.code = item.code;
    form.name = item.name;
    form.description = item.description;
    form.requires_reference = item.requires_reference;
    form.requires_bank_name = item.requires_bank_name;
    form.requires_check_number = item.requires_check_number;
    form.affects_cash_cut = item.affects_cash_cut;
    form.is_active = item.is_active;
    showModal.value = true;
};

const close = () => {
    resetForm();
    showModal.value = false;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }: { valid: boolean }) => {
        if (!isValid) return;

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
            form.put(route("payment-methods.update", form.id), callbacks);
            return;
        }

        form.post(route("payment-methods.store"), callbacks);
    });
};

const destroy = (item: PaymentMethodItem) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este método de pago?",
    }).then((result) => {
        if (!result.isConfirmed) return;

        form.delete(route("payment-methods.destroy", item.id), {
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

const toggleClub = (item: PaymentMethodItem) => {
    const action = item.club_enabled ? "deshabilitar" : "habilitar";
    customConfirmSwal({
        title: `¿Desea ${action} "${item.name}" para este club?`,
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.post(
            route("payment-methods.toggle-club", item.id),
            { club_id: page.props.auth.currentClub },
            {
                preserveScroll: true,
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "",
                        icon: "success",
                    });
                    fetchItems();
                },
                onError: (errors: any) => {
                    customToastSwal({
                        title: `Error: ${errors.messageError}`,
                        text: errors.exception ?? "",
                        icon: "error",
                    });
                },
            },
        );
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

    router.get(route("payment-methods.index"), params, {
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
    <Head title="Métodos de pago" />

    <AppLayout>
        <template #header>Métodos de pago</template>
        <template #options>
            <BaseButton
                v-if="can.includes('payment-methods.store')"
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
                        La columna "Habilitado en club" indica si el método está disponible para
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
                        no-data-text="No hay métodos de pago para mostrar"
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

                        <template #item.requirements="{ item }">
                            <div class="d-flex flex-wrap ga-1">
                                <v-chip
                                    v-if="item.requires_reference"
                                    size="small"
                                    color="primary"
                                    variant="tonal"
                                >
                                    Referencia
                                </v-chip>
                                <v-chip
                                    v-if="item.requires_bank_name"
                                    size="small"
                                    color="secondary"
                                    variant="tonal"
                                >
                                    Banco
                                </v-chip>
                                <v-chip
                                    v-if="item.requires_check_number"
                                    size="small"
                                    color="warning"
                                    variant="tonal"
                                >
                                    No. cheque
                                </v-chip>
                                <span
                                    v-if="!item.requires_reference && !item.requires_bank_name && !item.requires_check_number"
                                    class="text-medium-emphasis text-caption"
                                >
                                    Sin requisitos
                                </span>
                            </div>
                        </template>

                        <template #item.affects_cash_cut="{ item }">
                            <v-chip
                                size="small"
                                :color="item.affects_cash_cut ? 'success' : 'default'"
                                variant="tonal"
                            >
                                {{ item.affects_cash_cut ? "Sí" : "No" }}
                            </v-chip>
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

                        <template #item.club_enabled="{ item }">
                            <v-chip
                                size="small"
                                :color="item.club_enabled ? 'success' : 'default'"
                                variant="tonal"
                                :class="can.includes('payment-methods.update') ? 'cursor-pointer' : ''"
                                @click="can.includes('payment-methods.update') && toggleClub(item)"
                            >
                                {{ item.club_enabled ? "Habilitado" : "Deshabilitado" }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('payment-methods.update')"
                                action="edit"
                                @click="openEdit(item)"
                            />
                            <BaseButton
                                v-if="can.includes('payment-methods.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="720" persistent>
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card
                    prepend-icon="mdi-credit-card-outline"
                    :title="form.id ? 'Editar método de pago' : 'Nuevo método de pago'"
                >
                    <v-card-text>
                        <v-row>
                            <v-col cols="12" md="4">
                                <v-text-field
                                    :disabled="form.id"
                                    v-model="form.code"
                                    label="Código"
                                    :rules="[required, minLength(2), maxLength(20)]"
                                    :error-messages="form.errors.code"
                                />
                            </v-col>

                            <v-col cols="12" md="8">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre"
                                    :rules="[required, minLength(2), maxLength(50)]"
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
                                    :rules="[minLength(5), maxLength(255)]"
                                />
                            </v-col>

                            <v-col cols="12">
                                <div class="text-subtitle-2 mb-2">Requisitos al registrar un pago</div>
                                <v-row>
                                    <v-col cols="12" md="4">
                                        <v-switch
                                            v-model="form.requires_reference"
                                            color="primary"
                                            label="Requiere referencia"
                                            hide-details
                                        />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-switch
                                            v-model="form.requires_bank_name"
                                            color="secondary"
                                            label="Requiere banco"
                                            hide-details
                                        />
                                    </v-col>
                                    <v-col cols="12" md="4">
                                        <v-switch
                                            v-model="form.requires_check_number"
                                            color="warning"
                                            label="Requiere no. cheque"
                                            hide-details
                                        />
                                    </v-col>
                                </v-row>
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-switch
                                    v-model="form.affects_cash_cut"
                                    color="success"
                                    label="Afecta corte de caja"
                                    hide-details
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-switch
                                    v-model="form.is_active"
                                    color="success"
                                    label="Activo"
                                    hide-details
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
