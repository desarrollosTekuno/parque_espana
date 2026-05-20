<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import FormQuillEditor from "@/Components/Form/FormQuillEditor.vue";
import { fileMaxCountRule, fileMaxSizeRule, fileTypeRule, required } from "@/constants/validationRules";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";
import axios from "axios";

/* ====================== Props ====================== */
interface NotificationItem {
    id: number;
    title: string;
    body: string;
    scope: "I" | "G";
    created_at: string | null;
    sent_date: string | null;
    scheduled_date: string | null;
    scheduled_time: string | null;
    recipients_count: number;
    status: { id: number; name: string; code: string } | null;
    creator: { id: number; name: string } | null;
    email_logs: Array<{
        id: number;
        to_email: string;
        status: string;
        sent_at: string | null;
        error_message: string | null;
        email_config: { id: number; profile_name: string; from_address: string; host: string } | null;
    }>;
}

interface Props {
    club_id: number | null;
    email_notifications: {
        data: NotificationItem[];
        total: number;
    };
}

const props = defineProps<Props>();


/* ====================== Variables ====================== */
const showModal = ref(false);
const showRecipientsModal = ref(false);
const showPreviewModal = ref(false);
const showHistoryModal = ref(false);
const selectedNotification = ref<NotificationItem | null>(null);
const historySearch = ref("");
const members = ref<Array<{ id: number; name: string; email: string }>>([]);
const recipients = ref<Array<{ id: number; name: string; email: string }>>([]);
const recipientsCount = ref(0);
const selectedRecipientsCount = ref(0);
const allRecipientsSelected = ref(false);
const formSendRef = ref();
const prefix = "email_notifications";
const items = ref<NotificationItem[]>(props.email_notifications?.data ?? []);
const total = ref(props.email_notifications?.total ?? 0);
const loading = ref(false);
const search = ref("");
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});

const headers = [
    { title: "Titulo", key: "title" },
    { title: "Alcance", key: "scope", sortable: false },
    { title: "Destinatarios", key: "recipients_count", sortable: false },
    { title: "SMTP", key: "smtp", sortable: false },
    { title: "Estado", key: "status", sortable: false },
    { title: "Fecha", key: "created_at" },
    { title: "Creado por", key: "creator", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
];

const historyHeaders = [
    { title: "Destinatario", key: "to_email" },
    { title: "SMTP", key: "smtp", sortable: false },
    { title: "Remitente", key: "from_address", sortable: false },
    { title: "Estado", key: "status" },
    { title: "Enviado en", key: "sent_at" },
    { title: "Error", key: "error_message" },
];

/* ====================== useForm ====================== */
const form = useForm({
    scope: "G" as "I" | "G",
    club_id: props.club_id,
    individual_email: "",
    title: "",
    body: "",
    attachments: [] as File[],
    send_type: "now",
    scheduled_date: "",
    scheduled_time: "",
    selected_recipient_ids: [] as number[],
});

/* ====================== Computed ====================== */

/* ====================== Funciones ====================== */
const create = () => {
    showModal.value = true;
    form.scope = "G";
    form.club_id = props.club_id;
    form.title = "Prueba de notificacion";
    form.body = "<p>Correo de prueba temporal.</p>";
    form.send_type = "now";
    form.scheduled_date = "";
    form.scheduled_time = "";
    getMembers();
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

    router.get(route("email-notifications.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (inertiaPage) => {
            items.value = inertiaPage.props.email_notifications?.data ?? [];
            total.value = inertiaPage.props.email_notifications?.total ?? 0;
            loading.value = false;
        },
        onError: () => {
            loading.value = false;
        },
    });
};

const closeModal = () => {
    form.reset();
    form.clearErrors();
    showModal.value = false;
}

const changeRecipientsModal = () => {
    showRecipientsModal.value = !showRecipientsModal.value;
};

const changePreviewModal = () => {
    showPreviewModal.value = !showPreviewModal.value;
};

const openHistoryModal = (item: NotificationItem) => {
    selectedNotification.value = item;
    historySearch.value = "";
    showHistoryModal.value = true;
};

const closeHistoryModal = () => {
    selectedNotification.value = null;
    historySearch.value = "";
    showHistoryModal.value = false;
};

const generatePreview = () => {
    formSendRef.value?.validate().then(({ valid }: { valid: boolean }) => {
        if (valid) {
            showPreviewModal.value = true;
        }
    });
};

const onScopeChange = (value: "I" | "G") => {
    form.scope = value;
    form.club_id = props.club_id;
    form.individual_email = "";
    form.selected_recipient_ids = [];
    getMembers();
};

const getMembers = async () => {
    if (!form.club_id) {
        recipients.value = [];
        form.selected_recipient_ids = [];
        return;
    }

    try {
        const response = await axios.get(route("email-notifications.members", { club_id: form.club_id }));

        recipients.value = response.data.recipients ?? [];

        if (form.scope === "G") {
            form.selected_recipient_ids = recipients.value.map((recipient) => recipient.id);
        }
    } catch (e) {
        console.error(e);
        recipients.value = [];
        form.selected_recipient_ids = [];
    }
};

const toggleAllRecipients = () => {
    form.selected_recipient_ids = recipients.value.map((recipient) => recipient.id);
};

const toggleRecipient = (id: number) => {
    if (form.selected_recipient_ids.includes(id)) {
        form.selected_recipient_ids = form.selected_recipient_ids.filter((recipientId) => recipientId !== id);
        return;
    }

    form.selected_recipient_ids.push(id);
};

const getSendTypeLabel = () => {
    if (form.send_type === "scheduled") {
        return "Programado";
    }

    return "Enviar ahora";
};

const getDestinationLabel = () => {
    if (form.scope === "I") {
        return form.individual_email || "Sin correo individual";
    }

    return `${selectedRecipientsCount.value} destinatarios`;
};

const formatFileSize = (size: number) => {
    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const getNotificationDate = (item: NotificationItem) => {
    if (item.sent_date) {
        return item.sent_date;
    }

    if (item.scheduled_date) {
        return `${item.scheduled_date} ${item.scheduled_time || ""}`;
    }

    return item.created_at || "-";
};

const getNotificationScope = (item: NotificationItem) => {
    if (item.scope === "I") {
        return "Individual";
    }

    return "General";
};

const getStatusColor = (code: string | undefined) => {
    if (code === "sent" || code === "Enviada") {
        return "success";
    }

    if (code === "scheduled") {
        return "warning";
    }

    if (code === "pending" || code === "Pendiente") {
        return "info";
    }

    if (code === "failed") {
        return "error";
    }

    return "default";
};

const getLogStatusText = (status: string) => {
    if (status === "sent") {
        return "Enviado";
    }

    if (status === "failed") {
        return "Fallido";
    }

    return status;
};

const getNotificationSmtp = (item: NotificationItem) => {
    if (!item.email_logs || item.email_logs.length === 0) {
        return null;
    }

    return item.email_logs[0].email_config;
};

const isImageFile = (file: File) => {
    return file.type.startsWith("image/");
};

const getFilePreviewUrl = (file: File) => {
    return URL.createObjectURL(file);
};

const save = () => {
    form.post(route("email-notifications.store"), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showPreviewModal.value = false;
            closeModal();
            fetchItems();
            customToastSwal({
                title: "Correo registrado con exito.",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: "No se pudo registrar el correo.",
                icon: "error",
            });
        },
    });
};

const cancelNotification = async (item: NotificationItem) => {
    const confirmed = await customConfirmSwal({
        title: "¿Cancelar notificacion?",
        text: `Se cancelara "${item.title}" y no se enviara.`,
        confirmText: "Sí, cancelar",
        actionType: "delete",
    });

    if (!confirmed) return;

    router.patch(route("email-notifications.cancel", item.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            fetchItems();
            customToastSwal({
                title: "Notificacion cancelada.",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: "No se pudo cancelar.",
                icon: "error",
            });
        },
    });
};
/* ====================== Watchers ====================== */
watch(
    () => props.email_notifications,
    () => {
        items.value = props.email_notifications?.data ?? [];
        total.value = props.email_notifications?.total ?? 0;
    },
    { deep: true }
);

watch(
    () => props.club_id,
    () => {
        form.club_id = props.club_id;
    }
);

watch([options, search], debounce(fetchItems, 400), { deep: true });

watch([recipients, () => form.selected_recipient_ids], () => {
        recipientsCount.value = recipients.value.length;
        selectedRecipientsCount.value = form.selected_recipient_ids.length;
        allRecipientsSelected.value =
            recipients.value.length > 0 &&
            form.selected_recipient_ids.length === recipients.value.length;
    },
    { deep: true, immediate: true }
);

/* ====================== Lifecycle ====================== */
</script>

<template>
    <Head title="Notificaciones por correo" />

    <AppLayout>
        <template #header>
            <h2 class="text-h5">Notificaciones por correo</h2>
        </template>

        <template #options>
            <BaseButton
                variant="elevated"
                :icon-only="false"
                action="add"
                @click="create"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-data-table-server
                v-model:options="options"
                fixed-header
                hover
                height="80vh"
                :headers="headers"
                :items="items"
                :items-length="total"
                :loading="loading"
                class="elevation-1"
                :items-per-page-options="[10, 25, 50, 100]"
                items-per-page-text=" Mostrar"
                no-data-text="No hay notificaciones por correo guardadas"
            >
                <template #top>
                    <v-text-field
                        v-model="search"
                        label="Buscar notificaciones"
                        class="mx-4 mt-2"
                        clearable
                    />
                </template>

                <template #item.scope="{ item }">
                    {{ getNotificationScope(item) }}
                </template>

                <template #item.status="{ item }">
                    <v-chip
                        size="small"
                        variant="tonal"
                        :color="getStatusColor(item.status?.code)"
                    >
                        {{ item.status?.name || "Sin estado" }}
                    </v-chip>
                </template>

                <template #item.smtp="{ item }">
                    <div v-if="getNotificationSmtp(item)">
                        <div class="text-body-2 font-weight-medium">
                            {{ getNotificationSmtp(item)?.profile_name }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            {{ getNotificationSmtp(item)?.from_address }}
                        </div>
                    </div>
                    <span v-else class="text-caption text-medium-emphasis">
                        Pendiente de envio
                    </span>
                </template>

                <template #item.created_at="{ item }">
                    {{ getNotificationDate(item) }}
                </template>

                <template #item.creator="{ item }">
                    {{ item.creator?.name || "-" }}
                </template>

                <template #item.actions="{ item }">
                    <BaseButton
                        action="view"
                        @click="openHistoryModal(item)"
                    />
                    <BaseButton
                        v-if="item.status?.code === 'scheduled' || item.status?.code === 'pending'"
                        action="cancel"
                        @click="cancelNotification(item)"
                    />
                </template>
            </v-data-table-server>
        </div>

        <!-- ===================================== MODALES ===================================== -->
        <v-dialog v-model="showModal" max-width="900">
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card title="Nueva notificacion">
                    <v-card-text>
                        <v-row>
                            <v-col cols="12">
                                    <div class="mb-2 text-subtitle-2">Selecciona a quienes se les enviara el correo</div>
                                    <v-btn-toggle v-model="form.scope" class="w-100" color="primary" mandatory @update:model-value="onScopeChange">
                                        <v-btn value="G" class="flex-grow-1" prepend-icon="mdi mdi-account-group-outline">Por parque</v-btn>
                                        <v-btn value="I" class="flex-grow-1" prepend-icon="mdi-account">Individual</v-btn>
                                    </v-btn-toggle>
                                    <div class="mt-1 text-caption text-medium-emphasis">
                                        {{ form.scope === 'I' ? 'Se enviara a la persona seleccionada.' : 'Se enviara al grupo general.' }}
                                    </div>
                                    <div class="px-1 mt-2 d-flex justify-space-between align-center">
                                        <div class="text-caption text-medium-emphasis" v-if="form.scope == 'G'">
                                            {{ `Seleccionados: ${selectedRecipientsCount} de ${recipientsCount}` }}
                                        </div>
                                        <a
                                            v-if="form.scope != 'I' && selectedRecipientsCount > 0"
                                            href="#"
                                            class="text-primary text-decoration-underline text-caption"
                                            :style="recipientsCount == 0 ? 'pointer-events:none;opacity:0.5;' : ''"
                                            @click.prevent="changeRecipientsModal"
                                        >
                                            Ver correos
                                        </a>
                                    </div>
                            </v-col>

                            <v-col v-if="form.scope == 'G'" cols="12">

                            </v-col>

                            <v-col v-if="form.scope == 'I'" cols="12">
                                <v-autocomplete
                                    v-model="form.individual_email"
                                    label="Seleccionar persona"
                                    :items="recipients"
                                    item-title="name"
                                    item-value="email"
                                    :rules="[required]"
                                    required
                                    clearable
                                    no-data-text="No se encontraron personas"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.title"
                                    label="Titulo"
                                    :rules="[required]"
                                    required
                                />
                            </v-col>

                            <v-col cols="12">
                                <FormQuillEditor
                                    v-model="form.body"
                                    label="Descripcion"
                                    placeholder="Escribe el contenido del correo..."
                                    :required="true"
                                    toolbar="essential"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-file-input
                                    v-model="form.attachments"
                                    class="mt-3"
                                    name="attachments[]"
                                    label="Adjuntos"
                                    multiple
                                    chips
                                    show-size
                                    counter
                                    clearable
                                    prepend-icon=""
                                    append-inner-icon="mdi-paperclip"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                    hint="Puedes adjuntar varios archivos (PDF, Office o imagen). Maximo 5 archivos de 10MB c/u."
                                    persistent-hint
                                    :rules="[
                                        fileMaxCountRule(5),
                                        fileTypeRule(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png']),
                                        fileMaxSizeRule(10),
                                    ]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.send_type"
                                    label="Tipo de envio"
                                    :items="[
                                        { title: 'Enviar ahora', value: 'now' },
                                        { title: 'Programar envio', value: 'scheduled' },
                                    ]"
                                    item-title="title"
                                    item-value="value"
                                    :rules="[required]"
                                    required
                                />
                            </v-col>

                            <v-col v-if="form.send_type === 'scheduled'" cols="12" md="3">
                                <v-text-field
                                    v-model="form.scheduled_date"
                                    label="Fecha"
                                    type="date"
                                    :rules="[required]"
                                    required
                                />
                            </v-col>

                            <v-col v-if="form.send_type === 'scheduled'" cols="12" md="3">
                                <v-text-field
                                    v-model="form.scheduled_time"
                                    label="Hora"
                                    type="time"
                                    :rules="[required]"
                                    required
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
                            @click="closeModal"
                        />
                        <BaseButton
                            :icon-only="false"
                            text="Generar notificacion"
                            variant="flat"
                            action="save"
                            @click="generatePreview"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>

        <v-dialog v-model="showRecipientsModal" max-width="900">
            <v-card rounded="lg" elevation="6">
                <v-card-title class="px-5 pt-4 pb-3 d-flex align-center justify-space-between">
                    <div class="d-flex align-center ga-2">
                        <v-avatar size="34" color="primary" variant="tonal">
                            <v-icon size="20">mdi-email-multiple-outline</v-icon>
                        </v-avatar>
                        <div>
                            <div class="text-subtitle-1 font-weight-bold">Seleccion de correos</div>
                            <div class="text-caption text-medium-emphasis">Elige quienes recibiran la notificacion</div>
                        </div>
                    </div>

                    <v-chip color="primary" variant="flat" size="small">
                        {{ selectedRecipientsCount }} / {{ recipientsCount }} seleccionados
                    </v-chip>
                </v-card-title>

                <v-divider />

                <v-card-text class="px-5 py-4">
                    <div class="mb-4 d-flex align-center justify-space-between">
                        <div class="text-caption text-medium-emphasis">
                            Revisa y confirma los destinatarios del envio general.
                        </div>

                        <v-btn
                            size="small"
                            variant="tonal"
                            color="primary"
                            prepend-icon="mdi-check-all"
                            :disabled="allRecipientsSelected"
                            @click="toggleAllRecipients"
                        >
                            Seleccionar todos
                        </v-btn>
                    </div>

                    <v-table fixed-header height="420" class="border rounded-lg bg-grey-lighten-5">
                        <thead>
                            <tr>
                                <th style="width: 70px;" class="text-center">
                                    <v-checkbox-btn
                                        :model-value="allRecipientsSelected"
                                        :indeterminate="selectedRecipientsCount > 0 && !allRecipientsSelected"
                                        @click.stop="toggleAllRecipients"
                                    />
                                </th>
                                <th>Nombre</th>
                                <th>Correo</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="recipient in recipients"
                                :key="recipient.id"
                                class="cursor-pointer"
                                style="transition: background-color .2s ease;"
                                @click="toggleRecipient(recipient.id)"
                            >
                                <td class="text-center">
                                    <v-checkbox-btn
                                        :model-value="form.selected_recipient_ids.includes(recipient.id)"
                                        @click.stop="toggleRecipient(recipient.id)"
                                    />
                                </td>
                                <td>{{ recipient.name }}</td>
                                <td>{{ recipient.email }}</td>
                            </tr>

                            <tr v-if="recipients.length === 0">
                                <td colspan="3" class="py-6 text-center text-medium-emphasis">
                                    No se encontraron correos.
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card-text>

                <v-divider />

                <v-card-actions class="px-5 py-3">
                    <v-spacer />
                    <BaseButton
                        :icon-only="false"
                        text="Cerrar"
                        variant="tonal"
                        action="cancel"
                        @click="changeRecipientsModal"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showPreviewModal" max-width="800">
            <v-card class="!rounded-[28px] overflow-hidden d-flex flex-column">
                <div class="border-b border-gray-200 pa-8 ">
                    <div class="flex-wrap d-flex justify-space-between align-start ga-6">
                        <div>
                            <div class="mb-1 tracking-widest text-primary text-caption font-weight-bold text-uppercase">
                                Previsualización
                            </div>

                            <div class="text-h5 font-weight-bold">
                                Notificación por correo
                            </div>

                            <div class="mt-1 text-body-2 text-medium-emphasis">
                                Revisa el contenido antes de confirmar el envío.
                            </div>
                        </div>

                        <v-chip
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-email-check-outline"
                            class="font-weight-medium"
                        >
                            {{ form.scope === "I" ? "Envio individual" : "Envio general" }}
                        </v-chip>
                    </div>
                </div>

                <v-card-text class="pa-8 overflow-auto h-[50vh]">
                    <section class="border border-gray-200 rounded-xl ">
                        <div class="px-4 py-3 border-b border-gray-200 bg-slate-50 d-flex align-center justify-space-between ga-3">
                            <div class="text-caption text-medium-emphasis">Vista previa del correo</div>
                        </div>

                        <div class="bg-white border-b border-gray-200">
                            <div class="px-5 py-4 border-b border-gray-200 bg-slate-50/60">
                                <div class="mb-1 text-caption text-medium-emphasis">Titulo</div>
                                <div class="text-body-1 font-weight-bold">{{ form.title || "Sin titulo" }}</div>
                            </div>

                            <div class="flex-wrap px-5 py-3 d-flex ga-4 text-body-2">
                                <div><span class="text-medium-emphasis">De:</span> Sistema</div>
                                <div>
                                    <span class="text-medium-emphasis">Destino:</span>
                                    <template>
                                        {{ getDestinationLabel() }}
                                    </template>
                                    <a
                                        v-if="form.scope === 'G'"
                                        href="#"
                                        class="ml-2 text-caption text-primary text-decoration-underline"
                                        @click.prevent="changeRecipientsModal"
                                    >
                                        Ver mas
                                    </a>
                                </div>
                            </div>

                            <div class="flex-wrap px-5 pb-4 d-flex ga-2">
                                <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-bullseye-arrow">
                                    {{ form.scope === "I" ? "Individual" : "General" }}
                                </v-chip>
                                <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-send-clock-outline">
                                    {{ getSendTypeLabel() }}
                                </v-chip>
                                <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-paperclip">
                                    {{ form.attachments.length }} adjunto(s)
                                </v-chip>
                                <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-account-group-outline">
                                    {{ selectedRecipientsCount }} seleccionado(s)
                                </v-chip>
                                <v-chip v-if="form.send_type === 'scheduled'" size="small" variant="tonal" color="primary" prepend-icon="mdi-calendar-clock">
                                    {{ form.scheduled_date || "Sin fecha" }} {{ form.scheduled_time || "" }}
                                </v-chip>
                            </div>
                        </div>

                        <div class="bg-white pa-5">
                            <div class="mb-2 text-caption text-medium-emphasis font-weight-bold text-uppercase">
                                Título
                            </div>
                            <div class="mb-5 text-h6 font-weight-bold">
                                {{ form.title || "Sin título" }}
                            </div>

                            <div class="mb-2 text-caption text-medium-emphasis font-weight-bold text-uppercase">
                                Mensaje
                            </div>

                            <div class="max-h-[280px] overflow-y-auto border-gray-200 bg-white pa-4 text-body-2 leading-7">
                                <div v-html="form.body">
                                </div>
                            </div>

                            <div v-if="form.attachments.length > 0" class="grid grid-cols-1 gap-3 my-5 md:grid-cols-2">
                                <div v-for="(file, index) in form.attachments" :key="`${file.name}-${index}`" class="p-3 border border-gray-200 rounded-xl bg-gray-50">
                                    <div class="mb-2 text-body-2 font-weight-medium text-truncate">
                                        {{ file.name }}
                                    </div>
                                    <div class="mb-2 text-caption text-medium-emphasis">
                                        {{ file.type || "Sin tipo" }} - {{ formatFileSize(file.size) }}
                                    </div>

                                    <v-img
                                        v-if="isImageFile(file)"
                                        :src="getFilePreviewUrl(file)"
                                        :alt="file.name"
                                        height="120"
                                        cover
                                        class="overflow-hidden border rounded-lg"
                                    />

                                    <div v-else class="py-6 text-center bg-white border rounded-lg text-medium-emphasis">
                                        <v-icon size="42" color="primary" class="mb-2">
                                            mdi-file-document-outline
                                        </v-icon>
                                        <div class="text-caption">
                                            Documento adjunto
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-caption text-medium-emphasis">
                                No se agregaron adjuntos.
                            </div>
                        </div>

                    </section>
                </v-card-text>

                <v-card-actions class="pt-0 pa-8">
                    <v-spacer />
                    <BaseButton
                        :icon-only="false"
                        variant="tonal"
                        action="cancel"
                        @click="changePreviewModal"
                    />
                    <BaseButton
                        :icon-only="false"
                        :text="form.send_type === 'scheduled' ? 'Guardar para enviar despues' : 'Enviar'"
                        variant="flat"
                        action="save"
                        @click="save"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showHistoryModal" max-width="1000">
            <v-card>
                <v-card-title class="d-flex align-center justify-space-between">
                    <div>
                        <div class="text-h6">Historial de envio</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ selectedNotification?.title || "Notificacion" }}
                        </div>
                    </div>
                    <v-chip size="small" variant="tonal" color="primary">
                        {{ selectedNotification?.email_logs?.length || 0 }} registro(s)
                    </v-chip>
                </v-card-title>

                <v-card-text>
                    <v-data-table
                        :headers="historyHeaders"
                        :items="selectedNotification?.email_logs || []"
                        :search="historySearch"
                        :items-per-page="10"
                        :items-per-page-options="[10, 25, 50, 100]"
                        items-per-page-text=" Mostrar"
                        no-data-text="Todavia no hay historial de envio para esta notificacion"
                        class="border rounded-lg"
                    >
                        <template #top>
                            <v-text-field
                                v-model="historySearch"
                                label="Buscar en historial"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.smtp="{ item }">
                            {{ item.email_config?.profile_name || "-" }}
                        </template>

                        <template #item.from_address="{ item }">
                            {{ item.email_config?.from_address || "-" }}
                        </template>

                        <template #item.status="{ item }">
                            <v-chip size="small" variant="tonal" :color="getStatusColor(item.status)">
                                {{ getLogStatusText(item.status) }}
                            </v-chip>
                        </template>

                        <template #item.error_message="{ item }">
                            <span class="text-caption text-error">
                                {{ item.error_message || "-" }}
                            </span>
                        </template>
                    </v-data-table>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        :icon-only="false"
                        text="Cerrar"
                        variant="tonal"
                        action="cancel"
                        @click="closeHistoryModal"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
