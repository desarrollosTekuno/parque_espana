<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { debounce } from "lodash";
import { customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";
import { selectRequired } from "@/constants/validationRules";

declare function route(name: string, params?: any): string;

interface DocumentItem {
    id: number;
    code: string;
    name: string;
    description: string | null;
    module: string;
    requires_input: boolean;
}

interface CurrentClub {
    id: number;
    name: string;
    code: string;
}

interface Props {
    documents?: any;
    currentClub?: CurrentClub | null;
    filters?: Record<string, string | number | null>;
}

const props = withDefaults(defineProps<Props>(), {
    documents: null,
    currentClub: null,
    filters: () => ({}),
});

const page = usePage<any>();
const can = (page.props as any).auth?.permissions as string[] ?? [];

// Modal para inputs extra
const showInputModal = ref(false);
const selectedDocument = ref<DocumentItem | null>(null);
const inputFormRef = ref();

const inputForm = ref({
    genero: null as string | null,
});

const generoOptions = [
    { title: "Dama", value: "DAMA" },
    { title: "Caballero", value: "CABALLERO" },
    { title: "Niño", value: "NIÑO" },
];

const resetInputForm = () => {
    inputForm.value = { genero: null };
};

const handleDownload = (item: DocumentItem) => {
    console.log('Clic recibido', item);
    if (item.requires_input) {
        selectedDocument.value = item;
        resetInputForm();
        showInputModal.value = true;
        return;
    }
    triggerDownload(item);
};

const closeInputModal = () => {
    showInputModal.value = false;
    selectedDocument.value = null;
    resetInputForm();
};

const submitInputAndDownload = async () => {
    const { valid } = await inputFormRef.value?.validate();
    if (!valid) return;

    triggerDownload(selectedDocument.value!, buildExtraParams(selectedDocument.value!));
    closeInputModal();
};

const buildExtraParams = (item: DocumentItem): Record<string, string> => {
    switch (item.code) {
        case "SOL_LOCKER":
            return { gender: inputForm.value.genero ?? "" };
        default:
            return {};
    }
};

const triggerDownload = (item: DocumentItem, extraParams: Record<string, string> = {}) => {
    try {
        const params = new URLSearchParams({
            club_id: String((page.props as any).auth.currentClub),
            ...extraParams,
        });
        const url = `${route("file-generator.download", item.id)}?${params.toString()}`;
        console.log('URL de descarga:', url);
        window.location.href = url;
    } catch (error: any) {
        customToastSwal({
            title: "Error al descargar",
            text: error?.message ?? "",
            icon: "error",
        });
    }
};

// Datatable server side
const headers = computed(() => [
    { title: "Código", key: "code", sortable: false },
    { title: "Nombre", key: "name", sortable: false },
    { title: "Módulo", key: "module", sortable: false },
    { title: "Acciones", key: "actions", sortable: false, align: "end" as const },
]);

const loading = ref(false);
const items = ref<DocumentItem[]>(props.documents?.data ?? []);
const total = ref<number>(props.documents?.total ?? 0);
const search = ref(String(props.filters?.search ?? ""));
const prefix = "documents";

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "name", order: "asc" }],
});

const fetchItems = () => {
    loading.value = true;

    const params = {
        club_id: (page.props as any).auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
    };

    router.get(route("file-generator.index"), params, {
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

watch([options, search], debounce(fetchItems, 400), { deep: true });
watch(() => (page.props as any).auth.currentClub, fetchItems);
</script>

<template>
    <Head title="Generar Documentos" />

    <AppLayout>
        <template #header>Generar Documentos</template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-alert
                        v-if="currentClub"
                        type="info"
                        variant="tonal"
                        class="mx-4 mt-4"
                    >
                        Documentos disponibles para
                        <strong>{{ currentClub.name }} ({{ currentClub.code }})</strong>.
                        Selecciona el documento que deseas generar y descargar.
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
                        no-data-text="No hay documentos disponibles para descargar"
                    >
                        <template #top>
                            <v-row class="px-4 pt-4">
                                <v-col cols="12">
                                    <v-text-field
                                        v-model="search"
                                        label="Buscar por código o nombre"
                                        clearable
                                        prepend-inner-icon="mdi-magnify"
                                    />
                                </v-col>
                            </v-row>
                        </template>

                        <template #item.code="{ item }">
                            <code class="text-caption bg-grey-lighten-4 px-2 py-1 rounded">
                                {{ item.code }}
                            </code>
                        </template>

                        <template #item.name="{ item }">
                            <div class="font-weight-medium">{{ item.name }}</div>
                            <div v-if="item.description" class="text-caption text-medium-emphasis">
                                {{ item.description }}
                            </div>
                        </template>

                        <template #item.module="{ item }">
                            <v-chip size="small" variant="tonal" color="primary">
                                {{ item.module }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('file-generator.download')"
                                icon="mdi-download"
                                size="small"
                                variant="text"
                                color="primary"
                                title="Generar y descargar"
                                @click="handleDownload(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <!-- Modal para inputs adicionales -->
        <v-dialog v-model="showInputModal" max-width="440" persistent>
            <v-form @submit.prevent="submitInputAndDownload" ref="inputFormRef">
                <v-card
                    prepend-icon="mdi-form-select"
                    :title="`Generar: ${selectedDocument?.name ?? ''}`"
                >
                    <v-card-text>
                        <template v-if="selectedDocument?.code === 'SOL_LOCKER'">
                            <p class="text-body-2 mb-3">
                                Selecciona el género del casillero para generar el documento.
                            </p>
                            <v-select
                                v-model="inputForm.genero"
                                :items="generoOptions"
                                item-title="title"
                                item-value="value"
                                label="Género"
                                prepend-inner-icon="mdi-account"
                                :rules="[selectRequired]"
                            />
                        </template>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        <BaseButton
                            :icon-only="false"
                            variant="tonal"
                            action="cancel"
                            @click="closeInputModal"
                        />
                        <BaseButton
                            :icon-only="false"
                            text="Descargar"
                            variant="flat"
                            action="download"
                            @click="submitInputAndDownload"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>