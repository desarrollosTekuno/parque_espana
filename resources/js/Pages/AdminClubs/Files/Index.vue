<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";
import { required, maxLength, selectRequired, fileMaxSizeRule, requiredFileRule, fileMimeTypeRule } from "@/constants/validationRules";
import CustomFileUploadField from "@/Components/CustomFileUploadField.vue";
import { formatBytes, getFileMeta } from "@/utils/fileUtils";
import axios from "axios"; 

declare function route(name: string, params?: any): string;
const showFormatModal = ref(false);
const formFileSendRef = ref(); // Referencia al formulario dentro del modal de creación/edición de formato
const fileRef = ref(); // Referencia al formulario de carga del archivo del club

interface ClubFileItem {
    id: number;
    file_original_name: string;
    file_mime_type: string;
    file_size_formatted: string;
    file_url: string | null;
    is_active: boolean;
    display_order: number;
}

interface FileFormatItem {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_required: boolean;
    is_active: boolean;
    allowed_mime_types: string[];
    max_size_bytes: number;
    club_file: ClubFileItem | null;
    module: string;
}

interface CurrentClub {
    id: number;
    name: string;
    code: string;
}

interface Props {
    files?: any;
    currentClub?: CurrentClub | null;
    filters?: Record<string, string | number | null>;
    modules?: string[];
    commonMimeTypes?: { title: string; value: string }[];
}

const props = withDefaults(defineProps<Props>(), {
    files: null,
    currentClub: null,
    filters: () => ({}),
    modules: () => [],
    commonMimeTypes: () => [],
});

const page = usePage<any>();
const can = (page.props as any).auth?.permissions as string[] ?? [];

// Genera el texto de ayuda del campo de carga a partir de los MIME types permitidos del formato.
const uploadHint = computed(() => {
    const mimes = uploadTargetFormat.value?.allowed_mime_types;
    if (!mimes?.length) return undefined;
    return mimes.map(mime => props.commonMimeTypes.find(m => m.value === mime)?.title ?? mime).join(", ");
});

const types = (mimes: string[]) => {
    return mimes.map(mime => props.commonMimeTypes.find(m => m.value === mime)?.title ?? mime).join(", ");
}

interface FormatForm {
    id: number | null;
    code: string;
    name: string;
    description: string | null;
    is_required: boolean;
    is_active: boolean;
    allowed_mime_types: string[];
    max_size_mb: number | null;
    module: string;
}

const formatForm = useForm<FormatForm>({
    id: null,
    code: "",
    name: "",
    description: null,
    is_required: true,
    is_active: true,
    allowed_mime_types: [],
    max_size_mb: null,
    module: "",
});

const resetFormatForm = () => {
    formatForm.reset();
    formatForm.clearErrors();
    formatForm.id = null;
    formatForm.code = "";
    formatForm.name = "";
    formatForm.description = "";
    formatForm.is_required = true;
    formatForm.is_active = true;
    formatForm.allowed_mime_types = [];
    formatForm.max_size_mb = null;
    formatForm.module = "";
};

const openCreateFormat = () => {
    resetFormatForm();
    showFormatModal.value = true;
};

const openEditFormat = (item: FileFormatItem) => {
    resetFormatForm();
    formatForm.id = item.id;
    formatForm.code = item.code;
    formatForm.name = item.name;
    formatForm.description = item.description;
    formatForm.is_required = item.is_required;
    formatForm.is_active = item.is_active;
    formatForm.allowed_mime_types = item.allowed_mime_types ?? [];
    formatForm.max_size_mb = item.max_size_bytes ? +(item.max_size_bytes / 1_048_576).toFixed(1) : null;
    formatForm.module = item.module;
    showFormatModal.value = true;
};

const closeFormatModal = () => {
    resetFormatForm();
    showFormatModal.value = false;
};

const saveFormat = async () => {
    formFileSendRef.value?.validate().then(({ valid: isValid }) => {
        if (!isValid) {
            return;
        } else {
            if (formatForm.id) {
                formatForm.put(route("files.update", formatForm.id), {
                    onSuccess: () => {
                        customToastSwal({
                            title: page.props.flash.success || "",
                            icon: "success",
                        });
                        closeFormatModal();
                        fetchItems();
                    },
                    onError: () => {
                        customToastSwal({
                            title: `Error: ${formatForm.errors.messageError}`,
                            text: `${formatForm.errors.exception}`,
                            icon: "error",
                        });
                    },
                });

            } else {
                formatForm.post(route("files.store"), {
                    onSuccess: () => {
                        customToastSwal({
                            title: page.props.flash.success || "",
                            icon: "success",
                        });
                        closeFormatModal();
                        fetchItems();
                    },
                    onError: () => {
                        customToastSwal({
                            title: `Error: ${formatForm.errors.messageError}`,
                            text: `${formatForm.errors.exception}`,
                            icon: "error",
                        });
                    },
                });
            }
        }
    });
};

const destroyFormat = (item: FileFormatItem) => {
    customConfirmSwal({
        title: `¿Eliminar el formato "${item.name}"?`,
        text: "Esto eliminará también los archivos de todos los clubes.",
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route("files.destroy", item.id), {
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({
                    title: (page.props as any).flash?.success || "Eliminado correctamente",
                    icon: "success"
                });
                fetchItems();
            },
            onError: (errors: any) => {
                customToastSwal({
                    title: `Error: ${errors.messageError ?? "Error al eliminar"}`,
                    text: errors.exception ?? "",
                    icon: "error",
                });
            },
        });
    });
};

// Modal: Subir archivo del club
const showUploadModal = ref(false);
const uploadTargetFormat = ref<FileFormatItem | null>(null);
const selectedFiles = ref<File[]>([]);
const detectedVariables = ref<string[]>([]);
const loadingVariables = ref(false);

const openUploadModal = (item: FileFormatItem) => {
    uploadTargetFormat.value = item;
    selectedFiles.value = [];
    detectedVariables.value = [];
    showUploadModal.value = true;
};

const closeUploadModal = () => {
    showUploadModal.value = false;
    uploadTargetFormat.value = null;
    selectedFiles.value = [];
    detectedVariables.value = [];
};

watch(selectedFiles, async (files) => {
    detectedVariables.value = [];
    if (!files?.length) return;

    const file = files[0];

    const formData = new FormData();
    formData.append('documento', file);

    try {
        loadingVariables.value = true;
        const response = await axios.post(route('files.variables'), formData);
        detectedVariables.value = response.data.variables ?? [];
    } catch {
        detectedVariables.value = [];
    } finally {
        loadingVariables.value = false;
    }
});

const uploadClubFile = async () => {
    const { valid } = await fileRef.value?.validate();
    if (!valid) return;

    const data = new FormData();
    data.append("file", selectedFiles.value[0]);
    data.append("club_id", String((page.props as any).auth.currentClub));

    router.post(route("files.club-file.upload", uploadTargetFormat.value!.id), data, {
        forceFormData: true,
        onSuccess: () => {
            customToastSwal({
                title: (page.props as any).flash?.success || "Archivo cargado correctamente",
                icon: "success"
            });
            closeUploadModal();
            fetchItems();
        },
        onError: (errors: any) => {
            customToastSwal({
                title: `Error: ${errors.messageError ?? "Error al cargar"}`,
                text: errors.exception ?? "",
                icon: "error",
            });
        },
    });
};

const destroyClubFile = (item: FileFormatItem) => {
    customConfirmSwal({
        title: `¿Eliminar el archivo de "${item.name}"?`,
        text: "Se eliminará el archivo subido por este club.",
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route("files.club-file.destroy", item.id), {
            data: { club_id: (page.props as any).auth.currentClub },
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({ title: (page.props as any).flash?.success || "Eliminado correctamente", icon: "success" });
                fetchItems();
            },
            onError: (errors: any) => {
                customToastSwal({
                    title: `Error: ${errors.messageError ?? "Error al eliminar"}`,
                    text: errors.exception ?? "",
                    icon: "error",
                });
            },
        });
    });
};

const downloadClubFile = (item: FileFormatItem) => {
    if (item.club_file?.file_url) window.open(item.club_file.file_url, "_blank");
};

//* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = computed(() => [
    { title: "Código", key: "code", sortable: false },
    { title: "Nombre", key: "name", sortable: false },
    { title: "Tipo", key: "allowed_mime_types", sortable: false },
    { title: "Req.", key: "is_required", sortable: false },
    { title: "Activo", key: "is_active", sortable: false },
    {
        title: props.currentClub?.code
            ? `Archivo — ${props.currentClub.code}`
            : "Archivo del Club",
        key: "club_file",
        sortable: false,
    },
    { title: "Acciones Documento", key: "actions", sortable: false },
    { title: "Acciones Formato", key: "actions2", sortable: false },
]);

const loading = ref(false);
const items = ref<FileFormatItem[]>(props.files?.data ?? []);
const total = ref<number>(props.files?.total ?? 0);
const search = ref(String(props.filters?.search ?? ""));
const isActive = ref(String(props.filters?.is_active ?? ""));
const prefix = "files";

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "asc" }],
});

const fetchItems = () => {
    loading.value = true;

    const params = {
        club_id: (page.props as any).auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_is_active`]: isActive.value,
    };

    router.get(route("files.index"), params, {
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

watch([options, search, isActive], debounce(fetchItems, 400), { deep: true });
watch(() => (page.props as any).auth.currentClub, fetchItems);
</script>

<template>
    <Head title="Archivos del Parque" />

    <AppLayout>
        <template #header>Archivos del Parque</template>
        <template #options>
            <BaseButton
                v-if="can.includes('files.store')"
                variant="elevated"
                :icon-only="false"
                action="add"
                @click="openCreateFormat"
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
                        Mostrando archivos para
                        <strong>{{ currentClub.name }} ({{ currentClub.code }})</strong>.
                        Cada fila muestra el archivo que este club tiene cargado para cada formato requerido.
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
                        :items-per-page-options="[10, 25, 50]"
                        items-per-page-text="Mostrar"
                        no-data-text="No hay formatos de archivos configurados"
                    >
                        <template #top>
                            <v-row class="px-4 pt-4">
                                <v-col cols="12" md="8">
                                    <v-text-field
                                        v-model="search"
                                        label="Buscar por código, nombre o descripción"
                                        clearable
                                        prepend-inner-icon="mdi-magnify"
                                    />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-select
                                        v-model="isActive"
                                        label="Estado"
                                        prepend-inner-icon="mdi-filter"
                                        :items="[
                                            { title: 'Todos', value: '' },
                                            { title: 'Activos', value: '1' },
                                            { title: 'Inactivos', value: '0' },
                                        ]"
                                        item-title="title"
                                        item-value="value"
                                    />
                                </v-col>
                            </v-row>
                        </template>

                        <!-- CÓDIGO -->
                        <template #item.code="{ item }">
                            <code class="text-caption bg-grey-lighten-4 px-2 py-1 rounded">{{ item.code }}</code>
                        </template>

                        <!-- NOMBRE / DESCRIPCIÓN -->
                        <template #item.name="{ item }">
                            <div class="font-weight-medium">{{ item.name }}</div>
                            <div v-if="item.description" class="text-caption text-medium-emphasis">
                                {{ item.description }}
                            </div>
                        </template>

                        <!-- TIPO DE ARCHIVO -->
                        <template #item.allowed_mime_types="{ item }">
                            <div class="font-weight-medium">{{ types(item.allowed_mime_types) }}</div>
                        </template>

                        <!-- REQUERIDO -->
                        <template #item.is_required="{ item }">
                            <v-icon
                                :icon="item.is_required ? 'mdi-check-circle' : 'mdi-minus-circle-outline'"
                                :color="item.is_required ? 'error' : 'grey'"
                                size="20"
                                :title="item.is_required ? 'Requerido' : 'Opcional'"
                            />
                        </template>

                        <!-- ACTIVO -->
                        <template #item.is_active="{ item }">
                            <v-chip
                                size="small"
                                :color="item.is_active ? 'success' : 'default'"
                                variant="tonal"
                            >
                                {{ item.is_active ? "Activo" : "Inactivo" }}
                            </v-chip>
                        </template>

                        <!-- ARCHIVO DEL CLUB -->
                        <template #item.club_file="{ item }">
                            <div v-if="item.club_file" class="d-flex align-center gap-2 py-1">
                                <v-icon
                                    v-bind="getFileMeta(item.club_file.file_mime_type)"
                                    size="22"
                                />
                                <div>
                                    <div class="text-caption font-weight-medium" style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                                        {{ item.club_file.file_original_name }}
                                    </div>
                                    <div class="text-caption text-medium-emphasis">
                                        {{ item.club_file.file_size_formatted }}
                                    </div>
                                </div>
                            </div>
                            <v-chip v-else size="small" variant="tonal" color="warning">
                                Sin archivo
                            </v-chip>
                        </template>

                        <!-- ACCIONES -->
                        <template #item.actions="{ item }">
                            <!-- Acciones del archivo del club -->
                            <BaseButton
                                v-if="item.club_file?.file_url"
                                icon="mdi-download"
                                size="small"
                                variant="text"
                                color="primary"
                                title="Descargar archivo"
                                @click="downloadClubFile(item)"
                            />
                            <BaseButton
                                v-if="can.includes('files.club-file.upload')"
                                :icon="item.club_file ? 'mdi-file-upload' : 'mdi-upload'"
                                size="small"
                                variant="text"
                                :color="item.club_file ? 'orange' : 'success'"
                                :title="item.club_file ? 'Reemplazar archivo' : 'Subir archivo'"
                                @click="openUploadModal(item)"
                            />
                            <BaseButton
                                v-if="item.club_file && can.includes('files.club-file.destroy')"
                                icon="mdi-file-remove-outline"
                                size="small"
                                variant="text"
                                color="error"
                                title="Eliminar archivo del club"
                                @click="destroyClubFile(item)"
                            />
                        </template>

                        <template #item.actions2="{ item }">
                            <!-- Acciones del formato -->
                            <BaseButton
                                v-if="can.includes('files.update')"
                                action="edit"
                                @click="openEditFormat(item)"
                            />
                            <BaseButton
                                v-if="can.includes('files.destroy')"
                                action="delete"
                                @click="destroyFormat(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <!-- Modal para crear / editar formato -->
        <v-dialog v-model="showFormatModal" max-width="600" persistent>
            <v-form @submit.prevent="saveFormat" ref="formFileSendRef">
                <v-card
                prepend-icon="mdi-file-cog-outline"
                :title="formatForm.id ? 'Editar formato de archivo' : 'Nuevo formato de archivo' "
                >
                    <v-card-text class="overflow-y-auto h-full">
                        <v-row>
                            <v-col cols="12" md="12">
                                <v-text-field
                                    v-model="formatForm.code"
                                    label="Código"
                                    :error-messages="formatForm.errors.code"
                                    hint="Identificador único, sin espacios"
                                    persistent-hint
                                    :rules="[required, maxLength(50)]"
                                />
                            </v-col>

                            <v-col cols="12" md="12">
                                <v-select
                                    v-model="formatForm.module"
                                    :items="modules"
                                    label="Módulo"
                                    clearable
                                    :error-messages="formatForm.errors.module"
                                    :rules="[selectRequired]"
                                />
                            </v-col>

                            <v-col cols="12" md="12">
                                <v-text-field
                                    v-model="formatForm.name"
                                    label="Nombre"
                                    :error-messages="formatForm.errors.name"
                                    :rules="[required, maxLength(200)]"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-textarea
                                    v-model="formatForm.description"
                                    label="Descripción"
                                    rows="3"
                                    auto-grow
                                    :error-messages="formatForm.errors.description"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-switch
                                    v-model="formatForm.is_required"
                                    color="error"
                                    label="Requerido"
                                    hide-details
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-switch
                                    v-model="formatForm.is_active"
                                    color="success"
                                    label="Activo"
                                    hide-details
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-select
                                    v-model="formatForm.allowed_mime_types"
                                    :items="commonMimeTypes"
                                    item-title="title"
                                    item-value="value"
                                    label="Tipos de archivo permitidos"
                                    multiple
                                    chips
                                    closable-chips
                                    clearable
                                    :error-messages="formatForm.errors.allowed_mime_types"
                                    :rules="[selectRequired]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model.number="formatForm.max_size_mb"
                                    label="Tamaño máximo (MB)"
                                    type="number"
                                    min="0.1"
                                    max="100"
                                    step="0.5"
                                    :error-messages="formatForm.errors.max_size_mb"
                                    hint="Deja vacío para usar el límite por defecto (2 MB)"
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
                            @click="closeFormatModal"
                        />
                        <BaseButton
                            :icon-only="false"
                            :text="formatForm.id ? 'Actualizar' : 'Crear'"
                            variant="flat"
                            action="save"
                            :loading="formatForm.processing"
                            @click="saveFormat"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>

        <!-- Modal para la carga del archivo para el club -->
        <v-dialog v-model="showUploadModal" max-width="480" persistent>
            <v-form @submit.prevent="uploadClubFile" ref="fileRef">
                <v-card prepend-icon="mdi-upload" :title="uploadTargetFormat?.club_file ? 'Reemplazar archivo' : 'Subir archivo'">
                    <v-card-text>
                        <v-alert
                            v-if="uploadTargetFormat"
                            type="info"
                            variant="tonal"
                            class="mb-4"
                            density="compact"
                        >
                            <strong>{{ uploadTargetFormat?.name }}</strong>
                            <div v-if="uploadTargetFormat?.allowed_mime_types?.length" class="text-caption mt-1">
                                Tipos permitidos: {{ uploadTargetFormat.allowed_mime_types.join(", ") }}
                            </div>
                            <div class="text-caption">
                                Tamaño máximo: {{ formatBytes(uploadTargetFormat.max_size_bytes) }}
                            </div>
                        </v-alert>

                        <CustomFileUploadField
                            v-model="selectedFiles"
                            :label="uploadTargetFormat?.name"
                            :hint="uploadHint"
                            :accept="uploadTargetFormat?.allowed_mime_types?.join(',')"
                            :multiple="false"
                            :rules="[
                                requiredFileRule,
                                fileMimeTypeRule(uploadTargetFormat?.allowed_mime_types),
                                fileMaxSizeRule(uploadTargetFormat?.max_size_bytes / 1_048_576)
                            ]"
                        />

                        <div v-if="loadingVariables" class="d-flex align-center gap-2 mt-3">
                            <v-progress-circular indeterminate size="18" width="2" color="primary" />
                            <span class="text-caption text-medium-emphasis">Leyendo variables del documento...</span>
                        </div>

                        <div v-else-if="detectedVariables.length" class="mt-3">
                            <div class="text-caption text-medium-emphasis mb-1">Variables detectadas en el documento:</div>
                            <div class="d-flex flex-wrap gap-1">
                                <v-chip
                                    v-for="variable in detectedVariables"
                                    :key="variable"
                                    size="small"
                                    color="primary"
                                    variant="tonal"
                                    :text="variable"
                                />
                            </div>
                        </div>

                        <v-alert
                            v-else-if="selectedFiles.length && !loadingVariables"
                            type="warning"
                            variant="tonal"
                            density="compact"
                            class="mt-3"
                            text="No se encontraron variables en el documento."
                        />
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        <BaseButton :icon-only="false" variant="tonal" action="cancel" @click="closeUploadModal()" />
                        <BaseButton
                            :icon-only="false"
                            :text="uploadTargetFormat?.club_file ? 'Reemplazar' : 'Subir'"
                            variant="flat"
                            action="save"
                            @click="uploadClubFile()"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>

        </v-dialog>
    </AppLayout>
</template>

