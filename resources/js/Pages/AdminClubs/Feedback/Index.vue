<!-- Crud de quejas y sugerencias -->
<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import {
    required,
    maxLength,
    fileTypeRule,
    fileMaxSizeRule,
    fileMaxCountRule,
} from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";

const can = usePage().props.auth.permissions;
const page = usePage<any>();

interface Props {
    tickets?: any;
    categories?: any[];
    ticketTypes?: any[];
    statuses?: any[];
    priorities?: any[];
}

const props = withDefaults(defineProps<Props>(), {
    tickets: null,
    categories: () => [],
    ticketTypes: () => [],
    statuses: () => [],
    priorities: () => [],
});

interface FeedbackForm {
    id: number | null;
    ticket_type_id: number | null;
    category_id: number | null;
    status_id: number | null;
    priority_id: number | null;
    title: string;
    description: string;
    is_anonymous: boolean;
    attachments: File[];
}

const showModal = ref(false);
const showDetailModal = ref(false);
const formSendRef = ref();
const selectedTicket = ref<any>(null);
const previewAttachment = ref<any>(null);
const visibleComments = computed(() => (selectedTicket.value?.comments ?? []).filter((comment: any) => !comment?.is_internal));

const form = useForm<FeedbackForm>({
    id: null,
    ticket_type_id: null,
    category_id: null,
    status_id: null,
    priority_id: null,
    title: "",
    description: "",
    is_anonymous: false,
    attachments: [],
});

const headers = [
    { title: "Folio", key: "ticket_number" },
    { title: "Título", key: "title" },
    { title: "Tipo", key: "type.name" },
    { title: "Categoría", key: "category.name" },
    { title: "Prioridad", key: "priority.name" },
    { title: "Estatus", key: "status.name" },
    { title: "Fecha", key: "ticket_date" },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref(props.tickets?.data ?? []);
const total = ref(props.tickets?.total ?? 0);
const loading = ref(false);
const search = ref("");

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});

const prefix = "tickets";

const statusChipColor = (status: any): string => {
    if (status?.color) {
        return status.color;
    }

    const code = String(status?.code ?? "").toUpperCase();

    if (code === "SUBMITTED") return "info";
    if (code === "IN_PROGRESS") return "warning";
    if (code === "RESOLVED") return "success";
    if (code === "CANCELLED") return "grey";
    if (code === "REJECTED") return "error";

    return "primary";
};

const priorityChipColor = (priority: any): string => {
    if (priority?.color) {
        return priority.color;
    }

    const code = String(priority?.code ?? priority?.name ?? "").toUpperCase();

    if (code.includes("ALTA") || code === "HIGH") return "error";
    if (code.includes("MEDIA") || code === "MEDIUM") return "warning";
    if (code.includes("BAJA") || code === "LOW") return "success";

    return "primary";
};

const statusToneClass = (status: any): string => {
    const code = String(status?.code ?? "").toUpperCase();

    if (code === "SUBMITTED") return "fb-badge fb-badge--status fb-badge--info";
    if (code === "IN_PROGRESS") return "fb-badge fb-badge--status fb-badge--warn";
    if (code === "RESOLVED") return "fb-badge fb-badge--status fb-badge--ok";
    if (code === "CANCELLED") return "fb-badge fb-badge--status fb-badge--muted";
    if (code === "REJECTED") return "fb-badge fb-badge--status fb-badge--danger";

    return "fb-badge fb-badge--status fb-badge--default";
};

const priorityToneClass = (priority: any): string => {
    const code = String(priority?.code ?? priority?.name ?? "").toUpperCase();

    if (code.includes("ALTA") || code === "HIGH") return "fb-badge fb-badge--priority fb-badge--danger";
    if (code.includes("MEDIA") || code === "MEDIUM") return "fb-badge fb-badge--priority fb-badge--warn";
    if (code.includes("BAJA") || code === "LOW") return "fb-badge fb-badge--priority fb-badge--ok";

    return "fb-badge fb-badge--priority fb-badge--default";
};

const fetchItems = async () => {
    loading.value = true;

    const params = {
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
    };

    router.get(route("feedback.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            const data = page.props[prefix]?.data ?? [];
            const totalCount = page.props[prefix]?.total ?? 0;

            items.value = data;
            total.value = totalCount;
            loading.value = false;
        },
        onError: () => {
            loading.value = false;
        },
    });
};

const create = () => {
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }) => {
        if (!isValid) {
            return;
        }

        const attachments = Array.isArray(form.attachments)
            ? form.attachments.filter(Boolean)
            : [];

        form
            .transform((data) => ({
                ...data,
                attachments,
            }))
            .post(route("feedback.store"), {
                forceFormData: true,
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
                        title: `Error: ${form.errors.messageError ?? ""}`,
                        text: `${form.errors.exception ?? ""}`,
                        icon: "error",
                    });
                },
            });
    });
};

const cancelTicket = (data: any) => {
    if (data?.status?.code !== "SUBMITTED") {
        customToastSwal({
            title: "Solo puedes cancelar tickets en estatus ENVIADO",
            icon: "warning",
        });
        return;
    }

    customConfirmSwal({
        title: "Deseas cancelar este ticket?",
        text: "",
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(
                route("feedback.cancel", data.id),
                {},
                {
                    onSuccess: () => {
                        customToastSwal({
                            title: page.props.flash.success || "",
                            icon: "success",
                        });

                        fetchItems();
                    },
                    onError: () => {
                        customToastSwal({
                            title: `Error: ${page.props.errors?.messageError ?? ""}`,
                            icon: "error",
                        });
                    },
                },
            );
        }
    });
};

const openDetail = (item: any) => {
    selectedTicket.value = item;
    previewAttachment.value = null;
    showDetailModal.value = true;
};

const closeDetail = () => {
    selectedTicket.value = null;
    previewAttachment.value = null;
    showDetailModal.value = false;
};

const getAttachmentUrl = (file: any): string => {
    return file.file_url || `/storage/${file.storage_path || file.file_path}`;
};

const canPreviewAttachment = (file: any): boolean => {
    const type = String(file?.file_type ?? "").toLowerCase();
    return type.startsWith("image/") || type === "application/pdf";
};

const openAttachmentPreview = (file: any) => {
    if (!canPreviewAttachment(file)) {
        window.open(getAttachmentUrl(file), "_blank");
        return;
    }

    previewAttachment.value = file;
};

const openAttachmentInNewTab = (file: any) => {
    window.open(getAttachmentUrl(file), "_blank");
};

const close = () => {
    form.reset();
    form.clearErrors();
    showModal.value = false;
};

watch([options, search], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Quejas y sugerencias" />

    <AppLayout>
        <template #header> Quejas y sugerencias </template>

        <template #options>
            <BaseButton
                v-if="can.includes('feedback.store')"
                variant="elevated"
                :icon-only="false"
                @click="create"
                action="add"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-data-table-server
                        fixed-header
                        hover
                        height="500px"
                        :headers="headers"
                        :items="items"
                        :items-length="total"
                        :loading="loading"
                        v-model:options="options"
                        class="elevation-1"
                        :items-per-page-options="[10, 25, 50, 100]"
                        items-per-page-text="Mostrar"
                        no-data-text="No hay registros para mostrar"
                    >
                        <template #top>
                            <v-text-field
                                v-model="search"
                                label="Buscar quejas o sugerencias"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.status.name="{ item }">
                            <v-chip
                                v-if="item.status"
                                :class="statusToneClass(item.status)"
                                :color="statusChipColor(item.status)"
                                size="small"
                                variant="tonal"
                                prepend-icon="mdi-circle-medium"
                            >
                                {{ item.status.name }}
                            </v-chip>
                            <span v-else>-</span>
                        </template>

                        <template #item.priority.name="{ item }">
                            <v-chip
                                v-if="item.priority"
                                :class="priorityToneClass(item.priority)"
                                :color="priorityChipColor(item.priority)"
                                size="small"
                                variant="tonal"
                                prepend-icon="mdi-flag-variant"
                            >
                                {{ item.priority.name }}
                            </v-chip>
                            <span v-else>-</span>
                        </template>

                        <template #item.ticket_date="{ item }">
                            {{ item.ticket_date ?? "-" }}
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                action="view"
                                tooltip="Ver detalle"
                                @click="openDetail(item)"
                            />

                            <BaseButton
                                v-if="can.includes('feedback.update') && item.status?.code === 'SUBMITTED'"
                                action="cancel"
                                tooltip="Cancelar ticket"
                                @click="cancelTicket(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="800" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card prepend-icon="mdi-message-alert-outline" title="Crear queja o sugerencia">
                    <v-card-text class="h-full overflow-y-auto">
                        <v-row>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.title"
                                    label="Título"
                                    :rules="[required, maxLength(85)]"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.description"
                                    label="Descripción"
                                    rows="4"
                                    :rules="[required, maxLength(350)]"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="form.ticket_type_id"
                                    :items="props.ticketTypes"
                                    item-title="name"
                                    item-value="id"
                                    label="Tipo"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="form.category_id"
                                    :items="props.categories"
                                    item-title="name"
                                    item-value="id"
                                    label="Categoría"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="form.priority_id"
                                    :items="props.priorities"
                                    item-title="name"
                                    item-value="id"
                                    label="Prioridad"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="8">
                                <v-file-input
                                    v-model="form.attachments"
                                    name="attachments[]"
                                    label="Adjuntos (puedes subir varios)"
                                    multiple
                                    chips
                                    show-size
                                    counter
                                    clearable
                                    hint="Puedes seleccionar varios archivos a la vez (Ctrl + clic en Windows o Cmd + clic en Mac). También puedes arrastrar y soltar varios archivos aquí."
                                    persistent-hint
                                    prepend-icon="mdi-paperclip"
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.svg"
                                    :rules="[
                                        fileMaxCountRule(5),
                                        fileTypeRule(['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'svg']),
                                        fileMaxSizeRule(2),
                                    ]"
                                />
                            </v-col>

                            <v-col cols="4">
                                <v-switch
                                    v-model="form.is_anonymous"
                                    label="Registro anónimo"
                                    color="primary"
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
                            text="Enviar"
                            variant="flat"
                            :icon-only="false"
                            type="submit"
                            action="save"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>

        <v-dialog v-model="showDetailModal" max-width="800" scrollable>
            <v-card v-if="selectedTicket" rounded="xl" elevation="12" class="overflow-hidden">
                <div class="px-6 py-5 bg-white">
                    <div class="d-flex align-start justify-space-between ga-4">
                        <div>
                            <div class="flex-wrap d-flex align-center ga-3">
                                <h2 class="mb-0 text-h5 font-weight-bold text-grey-darken-4">
                                    {{ selectedTicket.title }}
                                </h2>

                                <v-chip
                                    v-if="selectedTicket.status"
                                    :color="statusChipColor(selectedTicket.status)"
                                    size="small"
                                    variant="flat"
                                    class="font-weight-medium"
                                >
                                    {{ selectedTicket.status.name }}
                                </v-chip>

                                <v-chip
                                    v-if="selectedTicket.priority"
                                    color="primary"
                                    size="small"
                                    variant="tonal"
                                    class="font-weight-medium"
                                >
                                    {{ selectedTicket.priority.name }}
                                </v-chip>
                            </div>

                            <div class="flex-wrap mt-3 text-body-2 text-grey-darken-1 d-flex align-center ga-4">
                                <span class="d-flex align-center ga-1">
                                    <v-icon size="17">mdi-pound</v-icon>
                                    {{ selectedTicket.ticket_number }}
                                </span>

                                <span class="d-flex align-center ga-1">
                                    <v-icon size="17">mdi-calendar-outline</v-icon>
                                    {{ selectedTicket.ticket_date ?? 'Sin fecha' }}
                                </span>

                                <span class="d-flex align-center ga-1">
                                    <v-icon size="17">mdi-shape-outline</v-icon>
                                    {{ selectedTicket.category?.name ?? 'Sin categoría' }}
                                </span>

                                <span class="d-flex align-center ga-1">
                                    <v-icon size="17">mdi-forum-outline</v-icon>
                                    {{ selectedTicket.type?.name ?? 'Sin tipo' }}
                                </span>
                            </div>
                        </div>

                        <BaseButton action="close" tooltip="Cerrar" @click="closeDetail" />
                    </div>
                </div>

                <v-divider />

                <v-card-text class="pa-0">
                    <v-row no-gutters>
                        <v-col cols="12" md="12" class="pa-6 bg-grey-lighten-5">
                            <v-card rounded="xl" elevation="0" class="mb-5 border">
                                <v-card-title class="px-5 py-4 d-flex align-center ga-3">
                                    <v-avatar color="primary" variant="tonal" size="38">
                                        <v-icon size="22">mdi-text-box-outline</v-icon>
                                    </v-avatar>

                                    <div>
                                        <div class="text-subtitle-1 font-weight-bold">
                                            Descripción del ticket
                                        </div>
                                        <div class="text-caption text-grey-darken-1">
                                            Detalle proporcionado por el usuario
                                        </div>
                                    </div>
                                </v-card-title>

                                <v-divider />

                                <v-card-text class="px-5 py-5 whitespace-pre-line text-body-1 text-grey-darken-3">
                                    {{ selectedTicket.description }}
                                </v-card-text>
                            </v-card>

                            <v-card rounded="xl" elevation="0" class="mb-5 border">
                                <v-card-title class="px-5 py-4 d-flex align-center justify-space-between">
                                    <div class="d-flex align-center ga-3">
                                        <v-avatar color="primary" variant="tonal" size="38">
                                            <v-icon size="22">mdi-paperclip</v-icon>
                                        </v-avatar>

                                        <div>
                                            <div class="text-subtitle-1 font-weight-bold">
                                                Evidencia adjunta
                                            </div>
                                            <div class="text-caption text-grey-darken-1">
                                                Haz clic en un archivo para visualizarlo
                                            </div>
                                        </div>
                                    </div>

                                    <v-chip size="small" color="primary" variant="tonal">
                                        {{ selectedTicket.attachments?.length ?? 0 }}
                                    </v-chip>
                                </v-card-title>

                                <v-divider />

                                <v-list density="comfortable" class="pa-3">
                                    <v-list-item
                                        v-for="file in selectedTicket.attachments ?? []"
                                        :key="file.id"
                                        :href="getAttachmentUrl(file)"
                                        target="_blank"
                                        rel="noopener"
                                        rounded="lg"
                                        class="mb-2 transition-all bg-white border border-gray-200 cursor-pointer hover:bg-primary/5 hover:border-primary"
                                    >
                                        <template #prepend>
                                            <v-avatar color="primary" variant="tonal" size="44">
                                                <v-icon size="23">mdi-file-eye-outline</v-icon>
                                            </v-avatar>
                                        </template>

                                        <template #title>
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-primary">
                                                    {{ file.file_name }}
                                                </span>

                                                <v-chip size="x-small" color="primary" variant="tonal">
                                                    Ver archivo
                                                </v-chip>
                                            </div>
                                        </template>

                                        <template #subtitle>
                                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                                <span>{{ file.file_type ?? 'archivo' }}</span>

                                                <span v-if="file.uploaded_by?.name">
                                                    · {{ file.uploaded_by.name }}
                                                </span>
                                            </div>
                                        </template>

                                        <template #append>
                                            <v-btn
                                                icon="mdi-open-in-new"
                                                variant="text"
                                                color="primary"
                                                size="small"
                                            />
                                        </template>
                                    </v-list-item>

                                    <v-list-item
                                        v-if="!(selectedTicket.attachments ?? []).length"
                                        title="Sin archivos adjuntos"
                                        subtitle="Este ticket no tiene evidencia cargada."
                                        rounded="lg"
                                        class="bg-white border border-gray-200"
                                    >
                                        <template #prepend>
                                            <v-avatar color="grey-lighten-3" size="40">
                                                <v-icon>mdi-file-hidden</v-icon>
                                            </v-avatar>
                                        </template>
                                    </v-list-item>
                                </v-list>
                            </v-card>

                            <v-card rounded="xl" elevation="0" class="border">
                                <v-card-title class="px-5 py-4 d-flex align-center justify-space-between">
                                    <div class="d-flex align-center ga-3">
                                        <v-avatar color="primary" variant="tonal" size="38">
                                            <v-icon size="22">mdi-message-processing-outline</v-icon>
                                        </v-avatar>

                                        <div>
                                            <div class="text-subtitle-1 font-weight-bold">
                                                Seguimiento / comentarios
                                            </div>
                                            <div class="text-caption text-grey-darken-1">
                                                Comunicación y seguimiento del caso
                                            </div>
                                        </div>
                                    </div>

                                    <v-chip size="small" color="primary" variant="tonal">
                                        {{ visibleComments.length }}
                                    </v-chip>
                                </v-card-title>

                                <v-divider />

                                <div class="pa-5">
                                    <div
                                        v-for="comment in visibleComments"
                                        :key="comment.id"
                                        class="mb-4 d-flex ga-3"
                                    >
                                        <v-avatar color="primary" size="38">
                                            <span class="text-white font-weight-bold">
                                                {{ (comment.user?.name ?? 'S').charAt(0) }}
                                            </span>
                                        </v-avatar>

                                        <div class="flex-grow-1">
                                            <div class="mb-1 d-flex justify-space-between align-center">
                                                <span class="font-weight-medium">
                                                    {{ comment.user?.name ?? 'Sistema' }}
                                                </span>

                                                <span class="text-caption text-grey">
                                                    {{ comment.created_at ?? '' }}
                                                </span>
                                            </div>

                                            <div class="rounded-lg pa-3 bg-grey-lighten-5 text-body-2">
                                                {{ comment.comment }}
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="!visibleComments.length" class="py-8 text-center text-grey">
                                        <v-icon size="38" class="mb-2">mdi-message-outline</v-icon>
                                        <div class="font-weight-medium">Sin comentarios registrados</div>
                                        <div class="text-caption">Aún no existe seguimiento para este ticket.</div>
                                    </div>
                                </div>
                            </v-card>
                        </v-col>

                        <v-col v-if="false" cols="12" md="4" class="bg-white pa-6 border-s">
                            <v-card rounded="xl" elevation="0" class="mb-5 border">
                                <v-card-title class="px-5 py-4 text-subtitle-1 font-weight-bold">
                                    Información general
                                </v-card-title>

                                <v-divider />

                                <v-list density="comfortable" class="py-2">
                                    <v-list-item>
                                        <template #prepend>
                                            <v-avatar color="grey-lighten-4" size="36">
                                                <v-icon>mdi-account-outline</v-icon>
                                            </v-avatar>
                                        </template>

                                        <v-list-item-title>Reportado por</v-list-item-title>
                                        <v-list-item-subtitle>
                                            {{
                                                selectedTicket.is_anonymous
                                                    ? 'Anónimo'
                                                    : (selectedTicket.reported_by?.name ?? 'Sin usuario')
                                            }}
                                        </v-list-item-subtitle>
                                    </v-list-item>

                                    <v-list-item>
                                        <template #prepend>
                                            <v-avatar color="grey-lighten-4" size="36">
                                                <v-icon>mdi-account-tie-outline</v-icon>
                                            </v-avatar>
                                        </template>

                                        <v-list-item-title>Asignado a</v-list-item-title>
                                        <v-list-item-subtitle>
                                            {{ selectedTicket.assigned_to?.name ?? 'Sin asignar' }}
                                        </v-list-item-subtitle>
                                    </v-list-item>

                                    <v-list-item>
                                        <template #prepend>
                                            <v-avatar color="grey-lighten-4" size="36">
                                                <v-icon>mdi-calendar-clock</v-icon>
                                            </v-avatar>
                                        </template>

                                        <v-list-item-title>Fecha de envío</v-list-item-title>
                                        <v-list-item-subtitle>
                                            {{ selectedTicket.submitted_at ?? '-' }}
                                        </v-list-item-subtitle>
                                    </v-list-item>

                                    <v-list-item>
                                        <template #prepend>
                                            <v-avatar color="grey-lighten-4" size="36">
                                                <v-icon>mdi-check-decagram-outline</v-icon>
                                            </v-avatar>
                                        </template>

                                        <v-list-item-title>Resolución</v-list-item-title>
                                        <v-list-item-subtitle>
                                            {{ selectedTicket.resolution_notes ?? 'Sin resolución' }}
                                        </v-list-item-subtitle>
                                    </v-list-item>
                                </v-list>
                            </v-card>

                            <v-card rounded="xl" elevation="0" class="border">
                                <v-card-title class="px-5 py-4 d-flex align-center justify-space-between">
                                    <span class="text-subtitle-1 font-weight-bold">
                                        Historial del caso
                                    </span>

                                    <v-chip size="small" variant="tonal">
                                        {{ selectedTicket.status_history?.length ?? 0 }}
                                    </v-chip>
                                </v-card-title>

                                <v-divider />

                                <v-timeline
                                    v-if="(selectedTicket.status_history ?? []).length"
                                    density="compact"
                                    side="end"
                                    truncate-line="both"
                                    class="px-4 py-3"
                                >
                                    <v-timeline-item
                                        v-for="history in selectedTicket.status_history ?? []"
                                        :key="history.id"
                                        dot-color="primary"
                                        size="small"
                                    >
                                        <div class="text-body-2 font-weight-medium">
                                            {{ history.old_status?.name ?? 'Sin estatus' }}
                                            →
                                            {{ history.new_status?.name ?? 'Sin estatus' }}
                                        </div>

                                        <div class="text-caption text-grey-darken-1">
                                            {{ history.changed_by?.name ?? 'Sistema' }}
                                        </div>

                                        <div class="mt-1 text-caption text-grey">
                                            {{ history.change_reason ?? 'Sin motivo' }}
                                        </div>

                                        <div class="mt-1 text-caption text-grey">
                                            {{ history.created_at ?? '' }}
                                        </div>
                                    </v-timeline-item>
                                </v-timeline>

                                <div v-else class="py-8 text-center text-grey">
                                    <v-icon size="38" class="mb-2">mdi-timeline-outline</v-icon>
                                    <div class="font-weight-medium">Sin historial registrado</div>
                                    <div class="text-caption">No hay cambios de estatus todavía.</div>
                                </div>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
