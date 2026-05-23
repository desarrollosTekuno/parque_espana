<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";

declare function route(name: string, params?: any): string;

interface ParkFileItem {
    id: number;
    name: string;
    description: string | null;
    file_original_name: string;
    file_mime_type: string | null;
    file_size_formatted: string;
    file_url: string | null;
    is_active: boolean;
    created_at: string;
    club_enabled: boolean;
    club_display_order: number;
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
}

const props = withDefaults(defineProps<Props>(), {
    parkFiles: null,
    currentClub: null,
    filters: () => ({}),
});

const page = usePage<any>();
const can = (page.props as any).auth?.permissions as string[] ?? [];
const showModal = ref(false);
const loading = ref(false);
const items = ref<ParkFileItem[]>(props.parkFiles?.data ?? []);
const total = ref<number>(props.parkFiles?.total ?? 0);
const search = ref(String(props.filters?.search ?? ""));
const activeFilter = ref(props.filters?.is_active ?? null);
const prefix = "parkFiles";

const fileInputRef = ref<HTMLInputElement | null>(null);
const selectedFileName = ref<string | null>(null);
const selectedFile = ref<File | null>(null);

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});

const headers = computed(() => [
    { title: "Tipo", key: "file_mime_type", width: 60, sortable: false },
    { title: "Nombre", key: "name" },
    { title: "Archivo", key: "file_original_name", sortable: false },
    { title: "Tamaño", key: "file_size_formatted", width: 110, sortable: false },
    { title: "Fecha", key: "created_at", width: 120, sortable: false },
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

interface ParkFileForm {
    id: number | null;
    name: string;
    description: string | null;
    is_active: boolean;
}

const form = useForm<ParkFileForm>({
    id: null,
    name: "",
    description: null,
    is_active: true,
});

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.id = null;
    form.name = "";
    form.description = null;
    form.is_active = true;
    selectedFile.value = null;
    selectedFileName.value = null;
    if (fileInputRef.value) fileInputRef.value.value = "";
};

const openCreate = () => {
    resetForm();
    showModal.value = true;
};

const openEdit = (item: ParkFileItem) => {
    resetForm();
    form.id = item.id;
    form.name = item.name;
    form.description = item.description;
    form.is_active = item.is_active;
    selectedFileName.value = item.file_original_name;
    showModal.value = true;
};

const close = () => {
    resetForm();
    showModal.value = false;
};

const onFileChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;

    const maxBytes = 20 * 1024 * 1024;
    if (file.size > maxBytes) {
        customToastSwal({ title: "El archivo no debe superar los 20 MB", icon: "error" });
        (e.target as HTMLInputElement).value = "";
        return;
    }

    selectedFile.value = file;
    selectedFileName.value = file.name;
};

const triggerFileInput = () => fileInputRef.value?.click();

const save = async () => {
    if (!form.name.trim()) {
        customToastSwal({ title: "Debes ingresar un nombre", icon: "error" });
        return;
    }
    if (!form.id && !selectedFile.value) {
        customToastSwal({ title: "Debes adjuntar un archivo", icon: "error" });
        return;
    }

    const result = await customConfirmSwal({
        title: form.id ? "¿Actualizar archivo?" : "¿Cargar archivo?",
        text: "Confirma para continuar",
    });
    if (!result.isConfirmed) return;

    const data = new FormData();
    data.append("name", form.name);
    data.append("description", form.description ?? "");
    data.append("is_active", form.is_active ? "1" : "0");
    if (selectedFile.value) {
        data.append("file", selectedFile.value);
    }

    const routeName = form.id
        ? route("park-files.update", form.id)
        : route("park-files.store");

    router.post(routeName, data, {
        forceFormData: true,
        onSuccess: () => {
            customToastSwal({ title: (page.props as any).flash?.success || "Guardado correctamente", icon: "success" });
            close();
            fetchItems();
        },
        onError: (errors: any) => {
            customToastSwal({
                title: `Error: ${errors.messageError ?? "Error al guardar"}`,
                text: errors.exception ?? "",
                icon: "error",
            });
        },
    });
};

const destroy = (item: ParkFileItem) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este archivo?",
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.delete(route("park-files.destroy", item.id), {
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

const toggleClub = (item: ParkFileItem) => {
    const action = item.club_enabled ? "deshabilitar" : "habilitar";
    customConfirmSwal({
        title: `¿Desea ${action} "${item.name}" para este club?`,
    }).then((result) => {
        if (!result.isConfirmed) return;

        router.post(
            route("park-files.toggle-club", item.id),
            { club_id: page.props.auth.currentClub },
            {
                preserveScroll: true,
                onSuccess: () => {
                    customToastSwal({ title: (page.props as any).flash?.success || "", icon: "success" });
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

const download = (item: ParkFileItem) => {
    if (item.file_url) window.open(item.file_url, "_blank");
};

const getFileIcon = (mime: string | null) => {
    if (!mime) return "mdi-file-outline";
    if (mime.startsWith("image/")) return "mdi-file-image";
    if (mime === "application/pdf") return "mdi-file-pdf-box";
    if (mime.includes("word") || mime.includes("document")) return "mdi-file-word";
    if (mime.includes("excel") || mime.includes("spreadsheet")) return "mdi-file-excel";
    if (mime.includes("zip") || mime.includes("compressed")) return "mdi-zip-box";
    return "mdi-file-outline";
};

const getFileIconColor = (mime: string | null) => {
    if (!mime) return "grey";
    if (mime.startsWith("image/")) return "teal";
    if (mime === "application/pdf") return "red";
    if (mime.includes("word") || mime.includes("document")) return "blue";
    if (mime.includes("excel") || mime.includes("spreadsheet")) return "green";
    if (mime.includes("zip") || mime.includes("compressed")) return "orange";
    return "grey";
};

const fetchItems = () => {
    loading.value = true;

    const params = {
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_is_active`]: activeFilter.value,
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
    <Head title="Archivos del Parque" />

    <AppLayout>
        <template #header>Archivos del Parque</template>
        <template #options>
            <BaseButton
                v-if="can.includes('park-files.store')"
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
                        La columna "Habilitado en club" indica si el archivo está disponible para
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
                        no-data-text="No hay archivos para mostrar"
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

                        <!-- TIPO -->
                        <template #item.file_mime_type="{ item }">
                            <v-icon
                                :color="getFileIconColor(item.file_mime_type)"
                                :icon="getFileIcon(item.file_mime_type)"
                                size="28"
                            />
                        </template>

                        <!-- NOMBRE -->
                        <template #item.name="{ item }">
                            <div class="font-weight-medium">{{ item.name }}</div>
                            <div class="text-caption text-medium-emphasis">
                                {{ item.description || "Sin descripción" }}
                            </div>
                        </template>

                        <!-- ARCHIVO ORIGINAL -->
                        <template #item.file_original_name="{ item }">
                            <span class="text-caption text-medium-emphasis">{{ item.file_original_name }}</span>
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

                        <!-- HABILITADO EN CLUB -->
                        <template #item.club_enabled="{ item }">
                            <v-chip
                                size="small"
                                :color="item.club_enabled ? 'success' : 'default'"
                                variant="tonal"
                                :class="can.includes('park-files.update') ? 'cursor-pointer' : ''"
                                @click="can.includes('park-files.update') && toggleClub(item)"
                            >
                                {{ item.club_enabled ? "Habilitado" : "Deshabilitado" }}
                            </v-chip>
                        </template>

                        <!-- ACCIONES -->
                        <template #item.actions="{ item }">
                            <v-btn
                                icon="mdi-download"
                                size="small"
                                variant="text"
                                color="primary"
                                title="Descargar"
                                @click="download(item)"
                            />
                            <BaseButton
                                v-if="can.includes('park-files.update')"
                                action="edit"
                                @click="openEdit(item)"
                            />
                            <BaseButton
                                v-if="can.includes('park-files.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <!-- MODAL CREAR / EDITAR -->
        <v-dialog v-model="showModal" max-width="560" persistent>
            <v-card prepend-icon="mdi-file-document-outline" :title="form.id ? 'Editar archivo' : 'Nuevo archivo'">
                <v-card-text>
                    <v-row>
                        <v-col cols="12">
                            <v-text-field
                                v-model="form.name"
                                label="Nombre *"
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
                            <v-switch
                                v-model="form.is_active"
                                color="success"
                                label="Activo"
                                hide-details
                            />
                        </v-col>

                        <v-col cols="12">
                            <input
                                ref="fileInputRef"
                                type="file"
                                class="d-none"
                                @change="onFileChange"
                            />
                            <div
                                class="file-upload-zone"
                                :class="{ 'file-upload-zone--filled': selectedFileName }"
                                @click="triggerFileInput"
                            >
                                <v-icon
                                    :color="selectedFileName ? 'primary' : 'grey-lighten-1'"
                                    :icon="selectedFileName ? 'mdi-file-check' : 'mdi-upload'"
                                    size="32"
                                />
                                <div class="mt-2 text-center">
                                    <span v-if="selectedFileName" class="text-body-2 font-weight-medium text-primary">
                                        {{ selectedFileName }}
                                    </span>
                                    <template v-else>
                                        <span class="text-caption text-medium-emphasis d-block">
                                            {{ form.id ? "Reemplazar archivo (opcional)" : "Haz clic para seleccionar un archivo *" }}
                                        </span>
                                        <span class="text-caption text-disabled d-block">Máx. 20 MB</span>
                                    </template>
                                </div>
                            </div>
                            <div v-if="form.errors.file" class="text-caption text-error mt-1">
                                {{ form.errors.file }}
                            </div>
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
                        action="save"
                        :loading="form.processing"
                        @click="save"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>

<style scoped>
.file-upload-zone {
    border: 2px dashed rgba(0, 0, 0, 0.15);
    border-radius: 12px;
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    transition: border-color 0.2s, background-color 0.2s;
}

.file-upload-zone:hover {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgba(var(--v-theme-primary), 0.04);
}

.file-upload-zone--filled {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgba(var(--v-theme-primary), 0.04);
}
</style>
