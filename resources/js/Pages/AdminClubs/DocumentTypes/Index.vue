<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";
import { required, allowedExtensions } from "@/constants/validationRules";

// ─── Interfaces ───────────────────────────────────────────────────────────────

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
    min_age: number | null;
    max_age: number | null;
    max_file_size_kb: number | null;
    relationship_ids: number[];
    relationships: RelationshipOption[];
}

interface Props {
    documentTypes?: any;
    allRelationships?: RelationshipOption[];
    filters?: Record<string, string | number | null>;
    messageError?: string;
}

// ─── Props / page ─────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<Props>(), {
    documentTypes: null,
    allRelationships: () => [],
    filters: () => ({}),
});

const page = usePage<any>();
const can = page.props.auth.permissions;

// ─── Table state ──────────────────────────────────────────────────────────────

const showModal = ref(false);
const formSendRef = ref();
const loading = ref(false);
const items = ref<DocumentTypeItem[]>(props.documentTypes?.data ?? []);
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
    { title: "Extensiones", key: "allowed_extensions", sortable: false },
    { title: "Rango de edad", key: "age_range", sortable: false },
    { title: "Tamaño máx.", key: "max_file_size_kb", sortable: false },
    { title: "Acciones", key: "actions", sortable: false, align: "end" as const },
];

// ─── Form ─────────────────────────────────────────────────────────────────────

interface DocumentTypeForm {
    id: number | null;
    code: string;
    name: string;
    description: string | null;
    allowed_extensions: string | null;
    min_age: number | null;
    max_age: number | null;
    max_file_size_kb: number | null;
    relationship_ids: number[];
}

const form = useForm<DocumentTypeForm>({
    id: null,
    code: "",
    name: "",
    description: null,
    allowed_extensions: null,
    min_age: null,
    max_age: null,
    max_file_size_kb: null,
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
    form.min_age = null;
    form.max_age = null;
    form.max_file_size_kb = null;
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
    form.min_age = item.min_age;
    form.max_age = item.max_age;
    form.max_file_size_kb = item.max_file_size_kb;
    form.relationship_ids = [...item.relationship_ids];
    showModal.value = true;
};

const close = () => {
    resetForm();
    showModal.value = false;
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

const extensionsList = (raw: string | null) =>
    raw
        ? raw.split(",").map((e) => e.trim()).filter(Boolean)
        : [];

/** Display a KB value as "X MB" or "X KB" */
const formatFileSize = (kb: number | null): string => {
    if (kb === null) return "2 MB (default)";
    if (kb >= 1024) return `${(kb / 1024).toFixed(kb % 1024 === 0 ? 0 : 1)} MB`;
    return `${kb} KB`;
};

/** Display age range chip label */
const ageRangeLabel = (item: DocumentTypeItem): string | null => {
    if (item.min_age === null && item.max_age === null) return null;
    if (item.min_age !== null && item.max_age !== null)
        return `${item.min_age}–${item.max_age} años`;
    if (item.min_age !== null) return `≥ ${item.min_age} años`;
    return `≤ ${item.max_age} años`;
};

// ─── CRUD ─────────────────────────────────────────────────────────────────────

/** Convierte un valor de input numérico a integer | null (evita NaN / cadena vacía). */
const toIntOrNull = (v: unknown): number | null => {
    if (v === null || v === undefined || v === "") return null;
    const n = Number(v);
    return Number.isFinite(n) ? Math.round(n) : null;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }: { valid: boolean }) => {
        if (!isValid) return;

        // Normalizar campos numéricos nullable antes de enviar
        form.min_age         = toIntOrNull(form.min_age);
        form.max_age         = toIntOrNull(form.max_age);
        form.max_file_size_kb = toIntOrNull(form.max_file_size_kb);

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

// ─── Fetch ────────────────────────────────────────────────────────────────────

const fetchItems = () => {
    loading.value = true;

    router.get(
        route("document-types.index"),
        {
            [`${prefix}_page`]:     options.value.page,
            [`${prefix}_per_page`]: options.value.itemsPerPage,
            [`${prefix}_search`]:   search.value,
            [`${prefix}_sort`]:     options.value.sortBy?.[0]?.key ?? "name",
            [`${prefix}_order`]:    options.value.sortBy?.[0]?.order ?? "asc",
        },
        {
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
        },
    );
};

watch([options, search], debounce(fetchItems, 400), { deep: true });
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

            <v-alert
                v-if="props.messageError"
                type="error"
                class="ma-4"
                closable
            >
                {{ props.messageError }}
            </v-alert>

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
                                        prepend-inner-icon="mdi-magnify"
                                        clearable
                                        variant="outlined"
                                        density="compact"
                                        hide-details
                                    />
                                </v-col>
                            </v-row>
                        </template>

                        <!-- Nombre + código -->
                        <template #item.name="{ item }">
                            <div class="font-weight-medium">
                                {{ item.code }} · {{ item.name }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ item.description || "Sin descripción" }}
                            </div>
                        </template>

                        <!-- Parentescos -->
                        <template #item.relationships="{ item }">
                            <div v-if="item.relationships.length === 0" class="text-caption text-medium-emphasis">
                                N/A
                            </div>
                            <div v-else class="d-flex flex-wrap ga-1 py-1">
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

                        <!-- Extensiones -->
                        <template #item.allowed_extensions="{ item }">
                            <div v-if="extensionsList(item.allowed_extensions).length" class="d-flex flex-wrap ga-1 py-1">
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

                        <!-- Rango de edad -->
                        <template #item.age_range="{ item }">
                            <v-chip
                                v-if="ageRangeLabel(item)"
                                size="small"
                                color="info"
                                variant="tonal"
                            >
                                {{ ageRangeLabel(item) }}
                            </v-chip>
                            <span v-else class="text-caption text-medium-emphasis">Sin restricción</span>
                        </template>

                        <!-- Tamaño máximo -->
                        <template #item.max_file_size_kb="{ item }">
                            <span :class="item.max_file_size_kb === null ? 'text-caption text-medium-emphasis' : ''">
                                {{ formatFileSize(item.max_file_size_kb) }}
                            </span>
                        </template>

                        <!-- Acciones -->
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

        <!-- ─── Dialog ────────────────────────────────────────────────────── -->
        <v-dialog v-model="showModal" max-width="680" persistent>
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card
                    prepend-icon="mdi-file-document-outline"
                    :title="form.id ? 'Editar tipo de documento' : 'Nuevo tipo de documento'"
                >
                    <v-card-text>
                        <v-row>
                            <!-- Código -->
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="form.code"
                                    label="Código"
                                    :rules="[required]"
                                    :error-messages="form.errors.code"
                                />
                            </v-col>

                            <!-- Nombre -->
                            <v-col cols="12" md="8">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre"
                                    :rules="[required]"
                                    :error-messages="form.errors.name"
                                />
                            </v-col>

                            <!-- Descripción -->
                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.description"
                                    label="Descripción"
                                    rows="2"
                                    auto-grow
                                    :error-messages="form.errors.description"
                                />
                            </v-col>

                            <!-- Extensiones -->
                            <v-col cols="12" md="8">
                                <v-text-field
                                    v-model="form.allowed_extensions"
                                    label="Extensiones permitidas"
                                    hint="Separadas por coma, sin punto. Ej: pdf, jpg, png"
                                    persistent-hint
                                    :error-messages="form.errors.allowed_extensions"
                                    :rules="[required, allowedExtensions]"
                                />
                            </v-col>

                            <!-- Tamaño máximo -->
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="form.max_file_size_kb"
                                    label="Tamaño máximo"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    :hint="form.max_file_size_kb ? formatFileSize(form.max_file_size_kb) : 'Default: 2 MB'"
                                    persistent-hint
                                    suffix="KB"
                                    :error-messages="form.errors.max_file_size_kb"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-divider class="mb-3" />
                            </v-col>

                            <!-- Rango de edad -->
                            <v-col cols="12">
                                <div class="text-subtitle-2 font-weight-bold mb-1">
                                    Restricción de edad
                                </div>
                                <p class="text-caption text-medium-emphasis mb-3">
                                    Dejar en blanco si aplica a cualquier edad.
                                    Ejemplo: INE → edad mínima 18.
                                </p>
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.min_age"
                                    label="Edad mínima"
                                    type="number"
                                    min="0"
                                    max="120"
                                    suffix="años"
                                    hint="Inclusive. Ej: 18 para mayores de edad."
                                    persistent-hint
                                    clearable
                                    :error-messages="form.errors.min_age"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.max_age"
                                    label="Edad máxima"
                                    type="number"
                                    min="0"
                                    max="120"
                                    suffix="años"
                                    hint="Inclusive. Ej: 17 para menores de edad."
                                    persistent-hint
                                    clearable
                                    :error-messages="form.errors.max_age"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-divider class="mb-3" />
                                <!-- Parentescos -->
                                <v-select
                                    v-model="form.relationship_ids"
                                    :items="allRelationships"
                                    item-title="name"
                                    item-value="id"
                                    label="Parentescos que lo requieren"
                                    multiple
                                    chips
                                    closable-chips
                                    hint="Sin selección = aplica a todos los parentescos."
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
