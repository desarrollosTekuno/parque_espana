<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";

interface DocumentTypeOption {
    id: number;
    name: string;
}

interface DocumentTypeItem {
    id: number;
    name: string;
    is_required: boolean;
    allow_multiple: boolean;
    number_files: number;
}

interface MembershipTypeItem {
    id: number;
    code: string;
    name: string;
    description: string | null;
    requires_origin_family: boolean;
    show_in_listing: boolean;
    is_spanish_descent: boolean;
    allows_multiple_members: boolean;
    validity_months: number | null;
    document_types: DocumentTypeItem[];
}

interface Props {
    membershipTypes?: any;
    allDocumentTypes?: DocumentTypeOption[];
    filters?: Record<string, string | number | null>;
}

const props = withDefaults(defineProps<Props>(), {
    membershipTypes: null,
    allDocumentTypes: () => [],
    filters: () => ({}),
});

const page = usePage<any>();
const can = page.props.auth.permissions;
const showModal = ref(false);
const showDocsPanel = ref(false);
const formSendRef = ref();
const loading = ref(false);
const items = ref(props.membershipTypes?.data ?? []);
const total = ref(props.membershipTypes?.total ?? 0);
const search = ref(String(props.filters?.search ?? ""));
const prefix = "membershipTypes";

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "name", order: "asc" }],
});

const headers = [
    { title: "Tipo de membresía", key: "name" },
    { title: "Vigencia", key: "validity_months", sortable: false },
    { title: "Características", key: "features", sortable: false },
    { title: "Documentos requeridos", key: "document_types", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
];

interface DocumentTypeForm {
    document_type_id: number | null;
    is_required: boolean;
    allow_multiple: boolean;
    number_files: number;
}

interface MembershipTypeForm {
    id: number | null;
    code: string;
    name: string;
    description: string | null;
    requires_origin_family: boolean;
    show_in_listing: boolean;
    is_spanish_descent: boolean;
    allows_multiple_members: boolean;
    validity_months: number | null;
    document_types: DocumentTypeForm[];
}

const form = useForm<MembershipTypeForm>({
    id: null,
    code: "",
    name: "",
    description: null,
    requires_origin_family: false,
    show_in_listing: true,
    is_spanish_descent: false,
    allows_multiple_members: false,
    validity_months: null,
    document_types: [],
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.id = null;
    form.code = "";
    form.name = "";
    form.description = null;
    form.requires_origin_family = false;
    form.show_in_listing = true;
    form.is_spanish_descent = false;
    form.allows_multiple_members = false;
    form.validity_months = null;
    form.document_types = [];
    showDocsPanel.value = false;
};

const openCreate = () => {
    resetForm();
    showModal.value = true;
};

const openEdit = (item: MembershipTypeItem) => {
    resetForm();
    form.id = item.id;
    form.code = item.code;
    form.name = item.name;
    form.description = item.description;
    form.requires_origin_family = item.requires_origin_family;
    form.show_in_listing = item.show_in_listing;
    form.is_spanish_descent = item.is_spanish_descent;
    form.allows_multiple_members = item.allows_multiple_members;
    form.validity_months = item.validity_months;
    form.document_types = item.document_types.map((d) => ({
        document_type_id: d.id,
        is_required: d.is_required,
        allow_multiple: d.allow_multiple,
        number_files: d.number_files,
    }));
    showDocsPanel.value = item.document_types.length > 0;
    showModal.value = true;
};

const close = () => {
    resetForm();
    showModal.value = false;
};

// Documentos requeridos
const availableDocTypes = computed(() => {
    const usedIds = form.document_types.map((d) => d.document_type_id);
    return props.allDocumentTypes.filter((d) => !usedIds.includes(d.id));
});

const addDocumentType = () => {
    form.document_types.push({
        document_type_id: null,
        is_required: true,
        allow_multiple: false,
        number_files: 1,
    });
};

const removeDocumentType = (index: number) => {
    form.document_types.splice(index, 1);
};

const docTypeName = (id: number | null) => {
    return props.allDocumentTypes.find((d) => d.id === id)?.name ?? "—";
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
            form.put(route("membership-types.update", form.id), callbacks);
            return;
        }

        form.post(route("membership-types.store"), callbacks);
    });
};

const destroy = (item: MembershipTypeItem) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este tipo de membresía?",
        text: "Se eliminará junto con sus documentos requeridos asociados.",
    }).then((result) => {
        if (!result.isConfirmed) return;

        form.delete(route("membership-types.destroy", item.id), {
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
    };

    router.get(route("membership-types.index"), params, {
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
    [options, search],
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
    <Head title="Tipos de membresía" />

    <AppLayout>
        <template #header>Tipos de membresía</template>
        <template #options>
            <BaseButton
                v-if="can.includes('membership-types.store')"
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
                        no-data-text="No hay tipos de membresía para mostrar"
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

                        <template #item.validity_months="{ item }">
                            <span v-if="item.validity_months">
                                {{ item.validity_months }}
                                {{ item.validity_months === 1 ? "mes" : "meses" }}
                            </span>
                            <span v-else class="text-medium-emphasis text-caption">Sin límite</span>
                        </template>

                        <template #item.features="{ item }">
                            <div class="d-flex flex-wrap ga-1">
                                <v-chip
                                    v-if="item.allows_multiple_members"
                                    size="small"
                                    color="primary"
                                    variant="tonal"
                                >
                                    Familiar
                                </v-chip>
                                <v-chip
                                    v-if="item.is_spanish_descent"
                                    size="small"
                                    color="warning"
                                    variant="tonal"
                                >
                                    Descendencia española
                                </v-chip>
                                <v-chip
                                    v-if="item.requires_origin_family"
                                    size="small"
                                    color="secondary"
                                    variant="tonal"
                                >
                                    Requiere familia origen
                                </v-chip>
                                <v-chip
                                    v-if="!item.show_in_listing"
                                    size="small"
                                    color="default"
                                    variant="tonal"
                                >
                                    Oculto en listado
                                </v-chip>
                            </div>
                        </template>

                        <template #item.document_types="{ item }">
                            <div v-if="item.document_types.length === 0" class="text-caption text-medium-emphasis">
                                Sin documentos
                            </div>
                            <div v-else class="d-flex flex-wrap ga-1">
                                <v-chip
                                    v-for="doc in item.document_types"
                                    :key="doc.id"
                                    size="small"
                                    :color="doc.is_required ? 'error' : 'default'"
                                    variant="tonal"
                                >
                                    {{ doc.name }}
                                </v-chip>
                            </div>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('membership-types.update')"
                                action="edit"
                                @click="openEdit(item)"
                            />
                            <BaseButton
                                v-if="can.includes('membership-types.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <!-- Modal -->
        <v-dialog v-model="showModal" max-width="800" persistent>
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card
                    prepend-icon="mdi-card-account-details-outline"
                    :title="form.id ? 'Editar tipo de membresía' : 'Nuevo tipo de membresía'"
                >
                    <v-card-text>
                        <v-row>
                            <!-- Código y nombre -->
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="form.code"
                                    label="Código"
                                    :rules="[(v: unknown) => !!v || 'Campo requerido']"
                                    :error-messages="form.errors.code"
                                />
                            </v-col>
                            <v-col cols="12" md="8">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre"
                                    :rules="[(v: unknown) => !!v || 'Campo requerido']"
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

                            <!-- Vigencia -->
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="form.validity_months"
                                    label="Vigencia (meses)"
                                    type="number"
                                    min="1"
                                    hint="Dejar vacío para sin límite"
                                    persistent-hint
                                    :error-messages="form.errors.validity_months"
                                />
                            </v-col>

                            <!-- Switches -->
                            <v-col cols="12">
                                <v-row>
                                    <v-col cols="12" md="3">
                                        <v-switch
                                            v-model="form.show_in_listing"
                                            color="primary"
                                            label="Mostrar en listado"
                                            hide-details
                                        />
                                    </v-col>
                                    <v-col cols="12" md="3">
                                        <v-switch
                                            v-model="form.allows_multiple_members"
                                            color="primary"
                                            label="Permite múltiples miembros"
                                            hide-details
                                        />
                                    </v-col>
                                    <v-col cols="12" md="3">
                                        <v-switch
                                            v-model="form.is_spanish_descent"
                                            color="warning"
                                            label="Descendencia española"
                                            hide-details
                                        />
                                    </v-col>
                                    <v-col cols="12" md="3">
                                        <v-switch
                                            v-model="form.requires_origin_family"
                                            color="secondary"
                                            label="Requiere familia origen"
                                            hide-details
                                        />
                                    </v-col>
                                </v-row>
                            </v-col>

                            <!-- Documentos requeridos -->
                            <v-col cols="12">
                                <v-divider class="mb-4" />
                                <div class="d-flex align-center justify-space-between mb-3">
                                    <span class="text-subtitle-2">Documentos requeridos</span>
                                    <v-btn
                                        size="small"
                                        variant="tonal"
                                        color="primary"
                                        prepend-icon="mdi-plus"
                                        :disabled="availableDocTypes.length === 0"
                                        @click="addDocumentType"
                                    >
                                        Agregar documento
                                    </v-btn>
                                </div>

                                <div
                                    v-if="form.document_types.length === 0"
                                    class="text-caption text-medium-emphasis text-center py-2"
                                >
                                    Sin documentos requeridos configurados
                                </div>

                                <v-table v-else density="compact">
                                    <thead>
                                        <tr>
                                            <th>Documento</th>
                                            <th class="text-center">Obligatorio</th>
                                            <th class="text-center">Múltiples</th>
                                            <th class="text-center">Núm. archivos</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(doc, index) in form.document_types"
                                            :key="index"
                                        >
                                            <td style="min-width: 220px;">
                                                <v-select
                                                    v-model="doc.document_type_id"
                                                    :items="[
                                                        ...availableDocTypes,
                                                        ...(doc.document_type_id
                                                            ? [{ id: doc.document_type_id, name: docTypeName(doc.document_type_id) }]
                                                            : [])
                                                    ]"
                                                    item-title="name"
                                                    item-value="id"
                                                    density="compact"
                                                    variant="underlined"
                                                    hide-details
                                                    :rules="[(v: unknown) => !!v || 'Requerido']"
                                                />
                                            </td>
                                            <td class="text-center">
                                                <v-checkbox
                                                    v-model="doc.is_required"
                                                    color="error"
                                                    hide-details
                                                    density="compact"
                                                />
                                            </td>
                                            <td class="text-center">
                                                <v-checkbox
                                                    v-model="doc.allow_multiple"
                                                    color="primary"
                                                    hide-details
                                                    density="compact"
                                                    @update:model-value="(val) => { if (!val) doc.number_files = 1; }"
                                                />
                                            </td>
                                            <td class="text-center" style="width: 100px;">
                                                <v-text-field
                                                    v-model.number="doc.number_files"
                                                    type="number"
                                                    min="1"
                                                    max="99"
                                                    density="compact"
                                                    variant="underlined"
                                                    hide-details
                                                    :disabled="!doc.allow_multiple"
                                                />
                                            </td>
                                            <td>
                                                <v-btn
                                                    icon="mdi-delete-outline"
                                                    size="small"
                                                    variant="text"
                                                    color="error"
                                                    @click="removeDocumentType(index)"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </v-table>
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
