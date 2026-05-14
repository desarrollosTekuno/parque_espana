<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, router, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";

interface Props {
    tickets?: any;
    statuses?: any[];
    priorities?: any[];
    filters?: {
        search?: string;
        status_id?: number | null;
        priority_id?: number | null;
    };
}

const props = withDefaults(defineProps<Props>(), {
    tickets: null,
    statuses: () => [],
    priorities: () => [],
    filters: () => ({}),
});
const page = usePage<any>();

const headers = [
    { title: "Fecha", key: "ticket_date" },
    { title: "Folio", key: "ticket_number" },
    { title: "Titulo", key: "title" },
    { title: "Categoria", key: "category.name" },
    { title: "Prioridad", key: "priority.name" },
    { title: "Estatus", key: "status.name" },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref(props.tickets?.data ?? []);
const total = ref(props.tickets?.total ?? 0);
const loading = ref(false);

const search = ref(props.filters?.search ?? "");
const statusFilter = ref(props.filters?.status_id ?? null);
const priorityFilter = ref(props.filters?.priority_id ?? null);

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});

const showDetailModal = ref(false);
const selectedTicket = ref<any | null>(null);
const showRejectDialog = ref(false);
const rejectReason = ref("");
const commentText = ref("");
const commentIsInternal = ref(false);
const sendingComment = ref(false);
const showStatusDialog = ref(false);
const targetStatusCode = ref<"IN_PROGRESS" | "RESOLVED" | null>(null);
const transitionComment = ref("");
const transitionResolutionNotes = ref("");
const sendingStatus = ref(false);

const statusChipColor = (status: any): string => {
    if (status?.color) return status.color;
    const code = String(status?.code ?? "").toUpperCase();
    if (code === "SUBMITTED") return "info";
    if (code === "IN_PROGRESS") return "warning";
    if (code === "RESOLVED") return "success";
    if (code === "CANCELLED") return "grey";
    if (code === "REJECTED") return "error";
    return "primary";
};

const priorityChipColor = (priority: any): string => {
    const code = String(priority?.code ?? priority?.name ?? "").toUpperCase();

    if (code.includes("URGENTE") || code.includes("ALTA") || code === "URGENT" || code === "HIGH") return "error";
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

    if (code.includes("URGENTE") || code.includes("ALTA") || code === "URGENT" || code === "HIGH") return "fb-badge fb-badge--priority fb-badge--danger";
    if (code.includes("MEDIA") || code === "MEDIUM") return "fb-badge fb-badge--priority fb-badge--warn";
    if (code.includes("BAJA") || code === "LOW") return "fb-badge fb-badge--priority fb-badge--ok";

    return "fb-badge fb-badge--priority fb-badge--default";
};

const fetchItems = async () => {
    loading.value = true;

    router.get(
        route("feedback-management.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value,
            status_id: statusFilter.value,
            priority_id: priorityFilter.value,
        },
        {
            preserveState: true,
            replace: true,
            onSuccess: (payload) => {
                items.value = payload.props.tickets?.data ?? [];
                total.value = payload.props.tickets?.total ?? 0;
                loading.value = false;
            },
            onError: () => {
                loading.value = false;
            },
        },
    );
};

const openDetail = (item: any) => {
    selectedTicket.value = item;
    commentText.value = "";
    commentIsInternal.value = false;
    transitionComment.value = "";
    transitionResolutionNotes.value = item?.resolution_notes ?? "";
    targetStatusCode.value = null;
    showDetailModal.value = true;
};

const closeDetail = () => {
    showDetailModal.value = false;
    selectedTicket.value = null;
    commentText.value = "";
    commentIsInternal.value = false;
    transitionComment.value = "";
    transitionResolutionNotes.value = "";
    targetStatusCode.value = null;
};

const getStatusIdByCode = (code: string): number | null => {
    const found = props.statuses.find((status: any) => String(status.code ?? "").toUpperCase() === code);
    return found?.id ?? null;
};

const currentStatusCode = () => String(selectedTicket.value?.status?.code ?? "").toUpperCase();

const canMoveToProcess = () => currentStatusCode() === "SUBMITTED";
const canResolve = () => currentStatusCode() === "IN_PROGRESS";

const openStatusDialog = (code: "IN_PROGRESS" | "RESOLVED") => {
    targetStatusCode.value = code;
    transitionComment.value = "";
    showStatusDialog.value = true;
};

const closeStatusDialog = () => {
    showStatusDialog.value = false;
    targetStatusCode.value = null;
    transitionComment.value = "";
};

const updateStatus = () => {
    if (!selectedTicket.value || !targetStatusCode.value) return;

    const statusId = getStatusIdByCode(targetStatusCode.value);
    if (!statusId) return;

    sendingStatus.value = true;

    router.patch(
        route("feedback-management.update", selectedTicket.value.id),
        {
            action: "change_status",
            status_id: statusId,
            comment: transitionComment.value.trim() || null,
            resolution_notes: targetStatusCode.value === "RESOLVED" ? (transitionResolutionNotes.value.trim() || null) : null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (payload) => {
                const updatedItems = payload.props.tickets?.data ?? [];
                items.value = updatedItems;
                total.value = payload.props.tickets?.total ?? total.value;

                const refreshed = updatedItems.find((ticket: any) => ticket.id === selectedTicket.value?.id);
                if (refreshed) {
                    selectedTicket.value = refreshed;
                    transitionResolutionNotes.value = refreshed.resolution_notes ?? "";
                }

                closeStatusDialog();
                customToastSwal({
                    title: page.props.flash.success || "Estatus actualizado correctamente.",
                    icon: "success",
                });
                sendingStatus.value = false;
            },
            onError: () => {
                customToastSwal({
                    title: `Error: ${page.props.errors?.messageError ?? "No se pudo actualizar el estatus."}`,
                    text: `${page.props.errors?.exception ?? ""}`,
                    icon: "error",
                });
                sendingStatus.value = false;
            },
        },
    );
};

const storeComment = () => {
    if (!selectedTicket.value) return;
    if (!commentText.value.trim()) return;

    sendingComment.value = true;

    router.patch(
        route("feedback-management.update", selectedTicket.value.id),
        {
            action: "comment",
            comment: commentText.value.trim(),
            is_internal: commentIsInternal.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (payload) => {
                const updatedItems = payload.props.tickets?.data ?? [];
                items.value = updatedItems;
                total.value = payload.props.tickets?.total ?? total.value;

                const refreshed = updatedItems.find((ticket: any) => ticket.id === selectedTicket.value?.id);
                if (refreshed) {
                    selectedTicket.value = refreshed;
                }

                commentText.value = "";
                commentIsInternal.value = false;
                customToastSwal({
                    title: page.props.flash.success || "Comentario agregado correctamente.",
                    icon: "success",
                });
                sendingComment.value = false;
            },
            onError: () => {
                customToastSwal({
                    title: `Error: ${page.props.errors?.messageError ?? "No se pudo guardar el comentario."}`,
                    text: `${page.props.errors?.exception ?? ""}`,
                    icon: "error",
                });
                sendingComment.value = false;
            },
        },
    );
};

const openRejectDialog = () => {
    if (!selectedTicket.value) return;
    showRejectDialog.value = true;
};

const closeRejectDialog = () => {
    showRejectDialog.value = false;
    rejectReason.value = "";
};

const rejectTicket = () => {
    if (!selectedTicket.value) return;
    if (!rejectReason.value.trim()) return;

    router.patch(
        route("feedback-management.update", selectedTicket.value.id),
        {
            action: "reject",
            rejection_reason: rejectReason.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeRejectDialog();
                closeDetail();
                fetchItems();
                customToastSwal({
                    title: page.props.flash.success || "Ticket rechazado correctamente.",
                    icon: "success",
                });
            },
            onError: () => {
                customToastSwal({
                    title: `Error: ${page.props.errors?.messageError ?? "No se pudo rechazar el ticket."}`,
                    text: `${page.props.errors?.exception ?? ""}`,
                    icon: "error",
                });
            },
        },
    );
};



watch([options, search, statusFilter, priorityFilter], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Gestion de casos" />

    <AppLayout>
        <template #header> Gestion de casos de quejas y sugerencias </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-data-table-server
                        fixed-header
                        hover
                        height="550px"
                        :headers="headers"
                        :items="items"
                        :items-length="total"
                        :loading="loading"
                        v-model:options="options"
                        :items-per-page-options="[10, 25, 50, 100]"
                        items-per-page-text="Mostrar"
                        no-data-text="No hay registros para mostrar"
                    >
                        <template #top>
                            <v-row class="mx-2 mt-2">
                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="search"
                                        label="Buscar por folio, titulo o descripcion"
                                        clearable
                                    />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-select
                                        v-model="statusFilter"
                                        :items="props.statuses"
                                        item-title="name"
                                        item-value="id"
                                        label="Estatus"
                                        clearable
                                    />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-select
                                        v-model="priorityFilter"
                                        :items="props.priorities"
                                        item-title="name"
                                        item-value="id"
                                        label="Prioridad"
                                        clearable
                                    />
                                </v-col>
                            </v-row>
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

                        <template #item.assigned_to.name="{ item }">
                            {{ item.assigned_to?.name ?? "Sin asignar" }}
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton action="view" tooltip="Ver detalle" @click="openDetail(item)" />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showDetailModal" max-width="1280" scrollable>
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
                                    :class="statusToneClass(selectedTicket.status)"
                                    :color="statusChipColor(selectedTicket.status)"
                                    size="small"
                                    variant="tonal"
                                    prepend-icon="mdi-circle-medium"
                                >
                                    {{ selectedTicket.status.name }}
                                </v-chip>

                                <v-chip
                                    v-if="selectedTicket.priority"
                                    :class="priorityToneClass(selectedTicket.priority)"
                                    :color="priorityChipColor(selectedTicket.priority)"
                                    size="small"
                                    variant="tonal"
                                    prepend-icon="mdi-flag-variant"
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
                        <v-col cols="12" md="6" class="pt-2 pa-4 bg-grey-lighten-5">
                            <v-card rounded="xl" elevation="0" class="mb-5 border">
                                <v-card-title class="px-5 py-4 d-flex align-center ga-3">
                                    <v-avatar color="primary" variant="tonal" size="38">
                                        <v-icon>mdi-account-outline</v-icon>
                                    </v-avatar>

                                    <div>
                                        <div class="text-subtitle-1 font-weight-bold">
                                            Reportado por
                                        </div>
                                        <div class="whitespace-pre-line text-body-1 text-grey-darken-3">
                                            {{ selectedTicket.is_anonymous ? 'Anónimo' : (selectedTicket.reported_by?.name ?? 'Sin usuario') }}
                                        </div>
                                    </div>
                                </v-card-title>

                                <v-divider />
                            </v-card>

                            <v-card rounded="xl" elevation="0" class="mb-5 border">
                                <v-card-title class="px-5 py-4 d-flex align-center ga-3">
                                    <v-avatar color="primary" variant="tonal" size="38">
                                        <v-icon size="22">mdi-text-box-outline</v-icon>
                                    </v-avatar>

                                    <div>
                                        <div class="text-subtitle-1 font-weight-bold">
                                            Descripción del ticket
                                        </div>
                                        <div class="whitespace-pre-line text-body-1 text-grey-darken-3">
                                            {{ selectedTicket.description }}
                                        </div>
                                    </div>
                                </v-card-title>

                                <v-divider />
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
                                        :href="file.file_url || `/storage/${file.storage_path || file.file_path}`"
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
                                    <span class="text-subtitle-1 font-weight-bold">
                                        Historial del caso
                                    </span>

                                    <v-chip size="small" variant="tonal">
                                        {{ selectedTicket.status_history?.length ?? 0 }}
                                    </v-chip>
                                </v-card-title>

                                <v-divider />

                                <v-timeline v-if="(selectedTicket.status_history ?? []).length" density="compact" side="end" truncate-line="both" class="px-4 py-3">
                                    <v-timeline-item v-for="history in selectedTicket.status_history ?? []" :key="history.id" dot-color="primary" size="small">
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

                        <!-- Comentarios -->
                        <v-col cols="12" md="6" class="px-4">
                            <v-card rounded="xl" elevation="0" class="mb-5 border">
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
                                        {{ selectedTicket.comments?.length ?? 0 }}
                                    </v-chip>
                                </v-card-title>

                                <v-divider />

                                <div class="pa-5">
                                    <v-card rounded="xl" elevation="0" class="mb-5 border bg-grey-lighten-5">
                                        <v-card-text>
                                            <div class="mb-3 d-flex align-center justify-space-between">
                                                <div>
                                                    <div class="text-subtitle-2 font-weight-bold">
                                                        Agregar seguimiento
                                                    </div>
                                                    <div class="text-caption text-grey-darken-1">
                                                        Responde al usuario o agrega una nota interna
                                                    </div>
                                                </div>

                                                <v-chip
                                                    size="small"
                                                    :color="commentIsInternal ? 'warning' : 'primary'"
                                                    variant="tonal"
                                                >
                                                    {{ commentIsInternal ? 'Interno' : 'Visible para usuario' }}
                                                </v-chip>
                                            </div>

                                            <v-textarea
                                                v-model="commentText"
                                                label="Comentario"
                                                rows="3"
                                                auto-grow
                                                clearable
                                                variant="outlined"
                                                density="comfortable"
                                            />

                                            <div class="mt-2 d-flex align-center justify-space-between ga-3">
                                                <v-switch
                                                    v-model="commentIsInternal"
                                                    color="warning"
                                                    hide-details
                                                    inset
                                                    label="Comentario interno"
                                                />

                                                <BaseButton
                                                    text="Enviar comentario"
                                                    action="save"
                                                    :icon-only="false"
                                                    variant="flat"
                                                    :disabled="!commentText.trim() || sendingComment"
                                                    :loading="sendingComment"
                                                    @click="storeComment"
                                                />
                                            </div>
                                        </v-card-text>
                                    </v-card>

                                    <div class="h-[50vh] overflow-auto px-3 py-4">
                                        <div v-for="comment in selectedTicket.comments ?? []" :key="comment.id" class="mb-4 d-flex ga-3">
                                            <v-avatar :color="comment.is_internal ? 'warning' : 'primary'" size="38">
                                                <span class="text-white font-weight-bold">
                                                    {{ (comment.user?.name ?? 'S').charAt(0) }}
                                                </span>
                                            </v-avatar>

                                            <div class="flex-grow-1">
                                                <div class="mb-1 d-flex justify-space-between align-center">
                                                    <div class="d-flex align-center ga-2">
                                                        <span class="font-weight-medium">
                                                            {{ comment.user?.name ?? 'Sistema' }}
                                                        </span>

                                                        <v-chip
                                                            v-if="comment.is_internal"
                                                            size="x-small"
                                                            color="warning"
                                                            variant="tonal"
                                                        >
                                                            Interno
                                                        </v-chip>

                                                        <v-chip
                                                            v-else
                                                            size="x-small"
                                                            color="primary"
                                                            variant="tonal"
                                                        >
                                                            Usuario
                                                        </v-chip>
                                                    </div>

                                                    <span class="text-caption text-grey">
                                                        {{ comment.created_at ?? '' }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="rounded-lg pa-3 text-body-2"
                                                    :class="comment.is_internal ? 'bg-warning-lighten-5 border border-warning' : 'bg-grey-lighten-5'"
                                                >
                                                    {{ comment.comment }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="!(selectedTicket.comments ?? []).length" class="py-8 text-center text-grey">
                                        <v-icon size="38" class="mb-2">mdi-message-outline</v-icon>
                                        <div class="font-weight-medium">Sin comentarios registrados</div>
                                        <div class="text-caption">Aún no existe seguimiento para este ticket.</div>
                                    </div>
                                </div>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-divider />

                <v-card-actions class="flex-wrap justify-end px-6 py-4 d-flex ga-2">
                    <BaseButton
                        text="Pasar a proceso"
                        action="save"
                        :icon-only="false"
                        variant="tonal"
                        v-if="canMoveToProcess()"
                        @click="openStatusDialog('IN_PROGRESS')"
                    />

                    <BaseButton
                        text="Solucionar"
                        action="save"
                        :icon-only="false"
                        variant="flat"
                        v-if="canResolve()"
                        @click="openStatusDialog('RESOLVED')"
                    />

                    <BaseButton
                        text="Rechazar"
                        action="delete"
                        :icon-only="false"
                        variant="outlined"
                        v-if="currentStatusCode() === 'SUBMITTED'"
                        @click="openRejectDialog"
                    />

                    <BaseButton
                        action="cancel"
                        :icon-only="false"
                        variant="text"
                        @click="closeDetail"
                    />
                </v-card-actions>

            </v-card>
        </v-dialog>

        <v-dialog v-model="showStatusDialog" max-width="640" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">
                    {{ targetStatusCode === 'IN_PROGRESS' ? 'Pasar ticket a proceso' : 'Marcar ticket como solucionado' }}
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-textarea
                        v-if="targetStatusCode === 'IN_PROGRESS'"
                        v-model="transitionComment"
                        label="Comentario de seguimiento"
                        rows="3"
                        auto-grow
                    />

                    <v-textarea
                        v-if="targetStatusCode === 'RESOLVED'"
                        v-model="transitionResolutionNotes"
                        label="Notas de resolución"
                        rows="3"
                        auto-grow
                        class="mt-3"
                    />
                </v-card-text>
                <v-card-actions class="justify-end px-4 pb-4 d-flex ga-2">
                    <BaseButton
                        action="cancel"
                        :icon-only="false"
                        variant="text"
                        @click="closeStatusDialog"
                    />
                    <BaseButton
                        text="Confirmar"
                        action="save"
                        :icon-only="false"
                        variant="flat"
                        :loading="sendingStatus"
                        @click="updateStatus"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showRejectDialog" max-width="640" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">Rechazar ticket</v-card-title>
                <v-divider />
                <v-card-text>
                    <p class="mb-3 text-body-2 text-grey-darken-1">
                        Indica el motivo del rechazo. Este texto quedará en el historial del ticket.
                    </p>
                    <v-textarea
                        v-model="rejectReason"
                        label="Motivo de rechazo"
                        rows="4"
                        auto-grow
                        :rules="[(v: string) => !!v?.trim() || 'El motivo es obligatorio']"
                    />

                </v-card-text>
                <v-card-actions class="justify-end px-4 pb-4 d-flex ga-2">
                    <BaseButton
                        action="cancel"
                        :icon-only="false"
                        variant="text"
                        @click="closeRejectDialog"
                    />
                    <BaseButton
                        text="Confirmar rechazo"
                        action="delete"
                        :icon-only="false"
                        variant="flat"
                        :disabled="!rejectReason.trim()"
                        @click="rejectTicket"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
