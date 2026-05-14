<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";

interface RelationshipOption {
    id: number;
    name: string;
}

interface DocumentTypeItem {
    id: number;
    code: string;
    name: string;
    description: string | null;
    allowed_extensions: string | null;
    relationship_ids: number[];
    relationships: RelationshipOption[];
}

interface Props {
    documentTypes?: any;
    allRelationships?: RelationshipOption[];
    filters?: Record<string, string | number | null>;
}

const props = withDefaults(defineProps<Props>(), {
    documentTypes: null,
    allRelationships: () => [],
    filters: () => ({}),
});

const page = usePage<any>();
const can = page.props.auth.permissions;
const showModal = ref(false);
const formSendRef = ref();
const loading = ref(false);
const items = ref(props.documentTypes?.data ?? []);
const total = ref(props.documentTypes?.total ?? 0);
const search = ref(String(props.filters?.search ?? ""));
const prefix = "documentTypes";

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "name", order: "asc" }],
});

const headers = [
    { title: "Tipo de documento", key: "name" },
    { title: "Parentescos", key: "relationships", sortable: false },
    { title: "Extensiones permitidas", key: "allowed_extensions", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
];

interface DocumentTypeForm {
    id: number | null;
    code: string;
    name: string;
    description: string | null;
    allowed_extensions: string | null;
    relationship_ids: number[];
}

const form = useForm<DocumentTypeForm>({
    id: null,
    code: "",
    name: "",
    description: null,
    allowed_extensions: null,
    relationship_ids: [],
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.id = null;
    form.code = "";
    form.name = "";
    form.description = null;
    form.allowed_extensions = null;
    form.relationship_ids = [];
};

const openCreate = () => {
    resetForm();
    showModal.value = true;
};

const openEdit = (item: DocumentTypeItem) => {
    resetForm();
    form.id = item.id;
    form.code = item.code;
    form.name = item.name;
    form.description = item.description;
    form.allowed_extensions = item.allowed_extensions;
    form.relationship_ids = [...item.relationship_ids];
    showModal.value = true;
};

const close = () => {
    resetForm();
    showModal.value = false;
};

const extensionsList = (raw: string | null) => {
    if (!raw) return [];
    return raw.split(",").map((e) => e.trim()).filter(Boolean);
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
            form.put(route("document-types.update", form.id), callbacks);
            return;
        }

        form.post(route("document-types.store"), callbacks);
    });
};

const destroy = (item: DocumentTypeItem) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este tipo de documento?",
    }).then((result) => {
        if (!result.isConfirmed) return;

        form.delete(route("document-types.destroy", item.id), {
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
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "name",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "asc",
    };

    router.get(route("document-types.index"), params, {
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
</script>

<template>
    <Head title="Tipos de documento" />

    <AppLayout>
        <template #header>Tipos de documento</template>
        <template #options>
            <BaseButton
                v-if="can.includes('document-types.store')"
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
                        no-data-text="No hay tipos de documento para mostrar"
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

                        <template #item.relationships="{ item }">
                            <div v-if="item.relationships.length === 0" class="text-caption text-medium-emphasis">
                                Todos
                            </div>
                            <div v-else class="d-flex flex-wrap ga-1">
                                <v-chip
                                    v-for="rel in item.relationships"
                                    :key="rel.id"
                                    size="small"
                                    color="secondary"
                                    variant="tonal"
                                >
                                    {{ rel.name }}
                                </v-chip>
                            </div>
                        </template>

                        <template #item.allowed_extensions="{ item }">
                            <div v-if="extensionsList(item.allowed_extensions).length" class="d-flex flex-wrap ga-1">
                                <v-chip
                                    v-for="ext in extensionsList(item.allowed_extensions)"
                                    :key="ext"
                                    size="small"
                                    color="primary"
                                    variant="tonal"
                                >
                                    .{{ ext }}
                                </v-chip>
                            </div>
                            <span v-else class="text-caption text-medium-emphasis">Todas</span>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('document-types.update')"
                                action="edit"
                                @click="openEdit(item)"
                            />
                            <BaseButton
                                v-if="can.includes('document-types.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="640" persistent>
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card
                    prepend-icon="mdi-file-document-outline"
                    :title="form.id ? 'Editar tipo de documento' : 'Nuevo tipo de documento'"
                >
                    <v-card-text>
                        <v-row>
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

                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.allowed_extensions"
                                    label="Extensiones permitidas"
                                    hint="Separadas por coma, sin punto. Ej: pdf, jpg, png"
                                    persistent-hint
                                    :error-messages="form.errors.allowed_extensions"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-divider class="mb-3" />
                                <v-select
                                    v-model="form.relationship_ids"
                                    :items="allRelationships"
                                    item-title="name"
                                    item-value="id"
                                    label="Parentescos que lo requieren"
                                    multiple
                                    chips
                                    closable-chips
                                    hint="Sin selección = aplica a todos los parentescos"
                                    persistent-hint
                                    :error-messages="form.errors.relationship_ids"
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
