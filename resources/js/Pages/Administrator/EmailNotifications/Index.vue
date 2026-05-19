<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import FormQuillEditor from "@/Components/Form/FormQuillEditor.vue";
import { fileMaxCountRule, fileMaxSizeRule, fileTypeRule, required } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, onBeforeUnmount, ref, watch } from "vue";

interface NotificationItem {
    id: number;
    body: string;
    sent_at: string | null;
    created_at: string | null;
    recipients_count: number;
    club: { id: number; name: string } | null;
    status: { id: number; name: string; code: string } | null;
    creator: { id: number; name: string } | null;
}

interface Props {
    email_notifications: {
        data: NotificationItem[];
        total: number;
    };
    clubs: { id: number; name: string }[];
    email_configs: { id: number; entity_id: number; profile_name: string; from_address: string; is_active: boolean }[];
}

const can = usePage().props.auth.permissions;
const page = usePage<any>();
const props = defineProps<Props>();
const prefix = "email_notifications";
const showModal = ref(false);
const showPreviewModal = ref(false);
const showRecipientsModal = ref(false);
const formSendRef = ref();
const recipientsCount = ref(0);
const recipientsItems = ref<Array<{ id: number; name: string; email: string }>>([]);
const selectedRecipientIds = ref<number[]>([]);
const extraEmails = ref<string[]>([]);
const recipientsSearch = ref("");
const recipientsPage = ref(1);
const recipientsPerPage = 20;
const attachmentPreviewItems = ref<Array<{ name: string; sizeLabel: string; type: string; isImage: boolean; previewUrl: string | null }>>([]);

const TEMP_DEFAULT_EMAIL_FORM = {
    title: "Prueba de notificacion",
    body: "<p>Correo de prueba temporal.</p>",
    individual_email: "qa@parquesesp.local",
    extra_emails: ["pruebas1@parquesesp.local", "pruebas2@parquesesp.local"],
};

const form = useForm({
    title: "",
    body: "",
    scope: "by_club" as "by_club" | "individual",
    club_id: null as number | null,
    individual_email: "",
    send_type: "now" as "now" | "scheduled",
    scheduled_date: "",
    scheduled_time: "",
    attachments: [] as File[],
    smtp_config_id: null as number | null,
    selected_recipient_ids: [] as number[],
    extra_emails: [] as string[],
});

const assignedClubs = computed(() => page.props.auth?.clubs ?? []);
const currentClubId = computed<number | null>(() => {
    const value = page.props.auth?.currentClub;
    return value ? Number(value) : null;
});
const hasMultipleAssignedClubs = computed(() => assignedClubs.value.length > 1);

const headers = [
    { title: "Fecha", key: "sent_at" },
    { title: "Titulo", key: "title" },
    { title: "Parque", key: "club" },
    { title: "Destinatarios", key: "recipients_count" },
    { title: "Estado", key: "status" },
    { title: "Enviado por", key: "creator" },
    { title: "Contenido", key: "body_preview", sortable: false },
];

const items = ref<NotificationItem[]>(props.email_notifications?.data ?? []);
const total = ref<number>(props.email_notifications?.total ?? 0);
const loading = ref(false);
const search = ref("");
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});

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
        onSuccess: (responsePage: any) => {
            items.value = responsePage.props.email_notifications?.data ?? [];
            total.value = responsePage.props.email_notifications?.total ?? 0;
            loading.value = false;
        },
        onError: () => {
            loading.value = false;
        },
    });
};

watch([options, search], debounce(fetchItems, 400), { deep: true });

const formatDate = (value: string | null) => {
    if (!value) {
        return "-";
    }
    return new Date(value).toLocaleString();
};

const getBodyPreview = (value: string) => {
    if (!value) {
        return "-";
    }
    return value.length > 80 ? `${value.slice(0, 80)}...` : value;
};

const openSendModal = () => {
    form.reset();
    form.clearErrors();
    form.scope = "by_club";
    form.title = TEMP_DEFAULT_EMAIL_FORM.title;
    form.body = TEMP_DEFAULT_EMAIL_FORM.body;
    form.club_id = hasMultipleAssignedClubs.value ? null : currentClubId.value;
    form.individual_email = TEMP_DEFAULT_EMAIL_FORM.individual_email;
    form.send_type = "now";
    form.scheduled_date = "";
    form.scheduled_time = "";
    form.attachments = [];
    form.smtp_config_id = null;
    form.selected_recipient_ids = [];
    form.extra_emails = [...TEMP_DEFAULT_EMAIL_FORM.extra_emails];
    extraEmails.value = [...TEMP_DEFAULT_EMAIL_FORM.extra_emails];

    showModal.value = true;
    loadRecipientsPreview();
    setDefaultSmtpIfSingle();
};

const onScopeChange = () => {
    if (form.scope === "individual") {
        form.club_id = null;
    }

    if (form.scope === "by_club" && !form.club_id && !hasMultipleAssignedClubs.value) {
        form.club_id = currentClubId.value;
    }

    form.smtp_config_id = null;

    loadRecipientsPreview();
    setDefaultSmtpIfSingle();
};

const onClubChange = () => {
    form.smtp_config_id = null;

    if (form.scope !== "by_club") {
        return;
    }

    loadRecipientsPreview();
    setDefaultSmtpIfSingle();
};

const loadRecipientsPreview = async () => {
    if (form.scope === "individual") {
        recipientsCount.value = 0;
        recipientsItems.value = [];
        selectedRecipientIds.value = [];
        return;
    }

    const params = new URLSearchParams();
    params.set("scope", form.scope);
    if (form.club_id) {
        params.set("club_id", String(form.club_id));
    }

    try {
        const response = await fetch(`${route("email-notifications.recipients-preview")}?${params.toString()}`, {
            headers: {
                Accept: "application/json",
            },
            credentials: "same-origin",
        });

        const data = await response.json();
        recipientsCount.value = data.count ?? 0;
        recipientsItems.value = data.items ?? [];
        selectedRecipientIds.value = recipientsItems.value.map((item) => item.id);
    } catch (error) {
        recipientsCount.value = 0;
        recipientsItems.value = [];
        selectedRecipientIds.value = [];
    }
};

const openRecipientsModal = () => {
    showRecipientsModal.value = true;
};

const closeRecipientsModal = () => {
    showRecipientsModal.value = false;
};

const filteredRecipients = computed(() => {
    if (!recipientsSearch.value.trim()) {
        return recipientsItems.value;
    }

    const term = recipientsSearch.value.trim().toLowerCase();
    return recipientsItems.value.filter((item) =>
        item.name.toLowerCase().includes(term) || item.email.toLowerCase().includes(term),
    );
});

const recipientsPagesCount = computed(() => {
    const total = filteredRecipients.value.length;
    return Math.max(1, Math.ceil(total / recipientsPerPage));
});

const pagedRecipients = computed(() => {
    const start = (recipientsPage.value - 1) * recipientsPerPage;
    const end = start + recipientsPerPage;
    return filteredRecipients.value.slice(start, end);
});

const selectedRecipientsCount = computed(() => selectedRecipientIds.value.length);

const smtpOptions = computed(() => {
    const configs = props.email_configs ?? [];

    if (form.scope === "individual") {
        return configs;
    }

    if (form.scope === "by_club") {
        if (!form.club_id) {
            return [];
        }

        return configs.filter((config) => config.entity_id === form.club_id);
    }

    return configs;
});

const smtpRequiredRule = (value: number | null) => {
    if (smtpOptions.value.length === 0) {
        return "No hay servidores SMTP disponibles";
    }

    return !!value || "Selecciona un servidor SMTP";
};

const setDefaultSmtpIfSingle = () => {
    if (form.smtp_config_id || smtpOptions.value.length !== 1) {
        return;
    }

    form.smtp_config_id = smtpOptions.value[0].id;
};

const toggleRecipient = (id: number, checked: boolean | null) => {
    if (checked) {
        if (!selectedRecipientIds.value.includes(id)) {
            selectedRecipientIds.value.push(id);
        }
        return;
    }

    selectedRecipientIds.value = selectedRecipientIds.value.filter((recipientId) => recipientId !== id);
};

const normalizeExtraEmails = () => {
    extraEmails.value = extraEmails.value
        .map((email) => email.trim().toLowerCase())
        .filter((email, index, list) => email.length > 0 && list.indexOf(email) === index);
};

const validExtraEmails = (value: string[]) => {
    if (!value || value.length === 0) {
        return true;
    }

    const pattern = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/;
    const hasInvalid = value.some((email) => !pattern.test((email || "").trim()));

    return !hasInvalid || "Hay correos extra con formato invalido";
};

const validIndividualEmail = (value: string) => {
    if (form.scope !== "individual") {
        return true;
    }

    const email = (value || "").trim();
    if (!email) {
        return "Ingresa un correo";
    }

    const pattern = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/;
    return pattern.test(email) || "Correo invalido";
};

const scheduledDateRule = (value: string) => {
    if (form.send_type !== "scheduled") {
        return true;
    }

    return !!(value || "").trim() || "Selecciona fecha y hora";
};

const scheduledTimeRule = (value: string) => {
    if (form.send_type !== "scheduled") {
        return true;
    }

    return !!(value || "").trim() || "Selecciona fecha y hora";
};

watch(recipientsSearch, () => {
    recipientsPage.value = 1;
});

watch(filteredRecipients, () => {
    if (recipientsPage.value > recipientsPagesCount.value) {
        recipientsPage.value = recipientsPagesCount.value;
    }
});

const closeModal = () => {
    form.reset();
    form.clearErrors();
    showPreviewModal.value = false;
    showModal.value = false;
};

watch(smtpOptions, () => {
    setDefaultSmtpIfSingle();
});

const closePreviewModal = () => {
    showPreviewModal.value = false;
};

const cancelPreview = () => {
    closeModal();
};

const openPreviewModal = () => {
    formSendRef.value?.validate().then(({ valid: isValid }: { valid: boolean }) => {
        if (!isValid) {
            return;
        }

        showPreviewModal.value = true;
    });
};

const previewScopeLabel = computed(() => {
    if (form.scope === "individual") {
        return "Individual";
    }

    if (form.scope === "by_club") {
        const selectedClub = assignedClubs.value.find((club: { id: number; name: string }) => club.id === form.club_id);
        return selectedClub ? `Por parque (${selectedClub.name})` : "Por parque";
    }

    return "Por parque";
});

const previewRecipientsLabel = computed(() => {
    if (form.scope === "individual") {
        return "1 seleccionado de 1";
    }

    return `${selectedRecipientsCount.value} seleccionados de ${recipientsCount.value}`;
});

const canOpenRecipientsPreview = computed(() => form.scope !== "individual" && selectedRecipientsCount.value > 0);

const previewSendTypeLabel = computed(() => {
    if (form.send_type === "now") {
        return "Envio inmediato";
    }

    const date = form.scheduled_date || "-";
    const time = form.scheduled_time || "-";
    return `Programado: ${date} ${time}`;
});

const formatFileSize = (bytes: number) => {
    if (!bytes || bytes <= 0) {
        return "0 B";
    }

    const units = ["B", "KB", "MB", "GB"];
    let value = bytes;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${value.toFixed(value >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
};

const clearAttachmentPreviews = () => {
    attachmentPreviewItems.value.forEach((item) => {
        if (item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
    });
    attachmentPreviewItems.value = [];
};

watch(
    () => form.attachments,
    (files) => {
        clearAttachmentPreviews();

        attachmentPreviewItems.value = (files ?? []).map((file) => {
            const isImage = (file.type || "").startsWith("image/");
            return {
                name: file.name,
                sizeLabel: formatFileSize(file.size),
                type: file.type || "application/octet-stream",
                isImage,
                previewUrl: isImage ? URL.createObjectURL(file) : null,
            };
        });
    },
    { deep: true },
);

onBeforeUnmount(() => {
    clearAttachmentPreviews();
});

const saveStepOne = () => {
    formSendRef.value?.validate().then(({ valid: isValid }: { valid: boolean }) => {
        if (!isValid) {
            return;
        }

        form.selected_recipient_ids = [...selectedRecipientIds.value];
        form.extra_emails = [...extraEmails.value];

        form.post(route("email-notifications.store"), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                fetchItems();
            },
        });
    });
};
</script>

<template>
    <Head title="Notificaciones por correo" />

    <AppLayout>
        <template #header>
            Historial de correos enviados
        </template>

        <template #options>
            <BaseButton
                v-if="can.includes('email-notifications.store')"
                variant="elevated"
                :icon-only="false"
                action="add"
                @click="openSendModal"
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
                        items-per-page-text=" Mostrar"
                        no-data-text="No hay correos enviados para mostrar"
                    >
                        <template #top>
                            <v-text-field
                                v-model="search"
                                label="Buscar correos"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.sent_at="{ item }">
                            {{ formatDate(item.sent_at || item.created_at) }}
                        </template>

                        <template #item.club="{ item }">
                            {{ item.club?.name || "Todos" }}
                        </template>

                        <template #item.status="{ item }">
                            {{ item.status?.name || "-" }}
                        </template>

                        <template #item.creator="{ item }">
                            {{ item.creator?.name || "-" }}
                        </template>

                        <template #item.body_preview="{ item }">
                            {{ getBodyPreview(item.body) }}
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="800">
            <v-form ref="formSendRef" @submit.prevent="openPreviewModal">
                <v-card title="Enviar correo masivo">
                    <v-card-text>
                        <div class="p-1 mb-3 ">
                            <div class="mb-2 text-subtitle-2">Selecciona a quienes se les enviara el correo</div>
                            <v-btn-toggle
                                v-model="form.scope"
                                color="primary"
                                mandatory
                                divided
                                @update:model-value="onScopeChange"
                            >
                                <v-btn value="individual" prepend-icon="mdi-account">Individual</v-btn>
                                <v-btn value="by_club" prepend-icon="mdi-map-marker">Por parque</v-btn>
                            </v-btn-toggle>
                            <div class="mt-1 text-caption text-medium-emphasis">
                                {{ form.scope === 'individual' ? 'Se enviara solo al correo indicado.' : 'Selecciona un parque especifico.' }}
                            </div>
                        </div>

                        <v-row>
                            <v-col v-if="form.scope === 'by_club'" cols="12" md="6">
                                <v-select
                                    v-model="form.club_id"
                                    :items="hasMultipleAssignedClubs ? assignedClubs : assignedClubs"
                                    item-title="name"
                                    item-value="id"
                                    :label="hasMultipleAssignedClubs ? 'Selecciona el parque' : 'Parque asignado'"
                                    :rules="[required]"
                                    :disabled="!hasMultipleAssignedClubs"
                                    required
                                    @update:model-value="onClubChange"
                                />
                            </v-col>
                            <v-col v-if="form.scope === 'individual'" cols="12" md="6">
                                <v-text-field
                                    v-model="form.individual_email"
                                    label="Correo destino"
                                    :rules="[validIndividualEmail]"
                                    required
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.smtp_config_id"
                                    :items="smtpOptions"
                                    item-title="profile_name"
                                    item-value="id"
                                    label="Servidor SMTP"
                                    :rules="[smtpRequiredRule]"
                                    :hint="form.scope === 'by_club' ? 'Servidores del parque seleccionado' : 'Servidores activos'"
                                    persistent-hint
                                    required
                                >
                                    <template #item="{ props: itemProps, item }">
                                        <v-list-item
                                            v-bind="itemProps"
                                            :title="item.raw.profile_name"
                                            :subtitle="item.raw.from_address"
                                        />
                                    </template>
                                </v-select>
                            </v-col>
                        </v-row>

                        <div class="px-1 mt-2 d-flex justify-space-between align-center">
                            <div class="text-caption text-medium-emphasis">
                                {{ form.scope === 'individual' ? 'Seleccionados: 1 de 1' : `Seleccionados: ${selectedRecipientsCount} de ${recipientsCount}` }}
                            </div>
                            <a
                                v-if="form.scope !== 'individual' && selectedRecipientsCount > 0"
                                href="#"
                                class="text-primary text-decoration-underline text-caption"
                                :style="recipientsCount === 0 ? 'pointer-events:none;opacity:0.5;' : ''"
                                @click.prevent="openRecipientsModal"
                            >
                                Ver correos
                            </a>
                        </div>

                        <v-combobox
                            v-model="extraEmails"
                            class="mt-3"
                            label="Correos extra"
                            chips
                            multiple
                            clearable
                            closable-chips
                            hint="Escribe correos separados por coma o presiona Enter (ej. correo@dom.com, otro@dom.com)"
                            persistent-hint
                            :rules="[validExtraEmails]"
                            @update:model-value="normalizeExtraEmails"
                        />

                        <v-divider class="my-4" />

                        <div class="mb-2 text-subtitle-2">Contenido del correo</div>

                        <v-row>
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
                        </v-row>

                        <v-divider class="my-4" />

                        <div class="mb-2 text-subtitle-2">Programacion de envio</div>

                        <v-row>
                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="form.send_type"
                                    :items="[
                                        { title: 'Enviar ahora', value: 'now' },
                                        { title: 'Programar envio', value: 'scheduled' },
                                    ]"
                                    item-title="title"
                                    item-value="value"
                                    label="Tipo de envio"
                                    required
                                />
                            </v-col>

                            <v-col cols="12" md="4" v-if="form.send_type === 'scheduled'">
                                <v-text-field
                                    v-model="form.scheduled_date"
                                    type="date"
                                    label="Fecha de envio"
                                    :rules="[scheduledDateRule]"
                                    required
                                />
                            </v-col>

                            <v-col cols="12" md="4" v-if="form.send_type === 'scheduled'">
                                <v-text-field
                                    v-model="form.scheduled_time"
                                    type="time"
                                    label="Hora de envio"
                                    :rules="[scheduledTimeRule]"
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
                            v-if="can.includes('email-notifications.store')"
                            :icon-only="false"
                            text="Generar notificacion"
                            variant="flat"
                            action="save"
                            @click="openPreviewModal"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>

        <v-dialog v-model="showRecipientsModal" max-width="700">
            <v-card title="Correos encontrados">
                <v-card-text>
                    <div class="mb-3 d-flex align-center justify-space-between">
                        <div>
                            Total encontrados: <strong>{{ recipientsCount }}</strong>
                        </div>
                        <div class="text-caption">
                            Mostrando {{ recipientsItems.length }} en esta vista
                        </div>
                    </div>

                    <v-text-field
                        v-model="recipientsSearch"
                        label="Buscar por nombre o correo"
                        density="compact"
                        variant="outlined"
                        clearable
                        class="mb-2"
                    />

                    <v-alert
                        v-if="filteredRecipients.length === 0"
                        type="info"
                        variant="tonal"
                        class="mb-2"
                    >
                        No hay correos para mostrar con ese filtro.
                    </v-alert>

                    <v-list
                        density="compact"
                        max-height="380"
                        class="border rounded"
                        style="overflow-y: auto;"
                    >
                        <v-list-item
                            v-for="recipient in pagedRecipients"
                            :key="recipient.id"
                            :title="recipient.name"
                            :subtitle="recipient.email"
                        >
                            <template #prepend>
                                <v-checkbox-btn
                                    :model-value="selectedRecipientIds.includes(recipient.id)"
                                    @update:model-value="(value) => toggleRecipient(recipient.id, value)"
                                />
                            </template>
                        </v-list-item>
                    </v-list>

                    <div class="justify-center mt-3 d-flex">
                        <v-pagination
                            v-model="recipientsPage"
                            :length="recipientsPagesCount"
                            :total-visible="6"
                        />
                    </div>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        :icon-only="false"
                        text="Cerrar"
                        variant="tonal"
                        action="cancel"
                        @click="closeRecipientsModal"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showPreviewModal" max-width="860">
            <v-card class="!rounded-[28px] overflow-hidden d-flex flex-column" style="max-height: 90vh;">
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
                            {{ previewSendTypeLabel }}
                        </v-chip>
                    </div>
                </div>

                <v-card-text class="pa-8" style="overflow-y: auto;">
                    <!-- Pre diseño de correo -->
                    <section class="overflow-hidden border border-gray-200 rounded-xl">
                        <div class="px-4 py-3 border-b border-gray-200 bg-slate-50 d-flex align-center justify-space-between ga-3">
                            <div class="text-caption text-medium-emphasis">Vista previa del correo</div>
                        </div>

                        <div class="bg-white border-b border-gray-200">
                            <div class="px-5 py-4 border-b border-gray-200 bg-slate-50/60">
                                <div class="mb-1 text-caption text-medium-emphasis">Titulo</div>
                                <div class="text-body-1 font-weight-bold">{{ form.title || "Sin titulo" }}</div>
                            </div>

                            <div class="px-5 py-3 d-flex flex-wrap ga-4 text-body-2">
                                <div><span class="text-medium-emphasis">De:</span> {{ smtpOptions.find((item) => item.id === form.smtp_config_id)?.from_address || "Servidor SMTP" }}</div>
                                <div>
                                    <span class="text-medium-emphasis">Destino:</span>
                                    <template v-if="form.scope === 'individual'">
                                        Individual
                                    </template>
                                    <template v-else-if="form.scope === 'by_club'">
                                        {{ previewScopeLabel }}
                                    </template>
                                    <a
                                        v-if="canOpenRecipientsPreview"
                                        href="#"
                                        class="ml-2 text-caption text-primary text-decoration-underline"
                                        @click.prevent="openRecipientsModal"
                                    >
                                        Ver mas
                                    </a>
                                </div>
                            </div>

                            <div class="px-5 pb-4 d-flex flex-wrap ga-2">
                                <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-bullseye-arrow">
                                    {{ previewScopeLabel }}
                                </v-chip>
                                <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-send-clock-outline">
                                    {{ previewSendTypeLabel }}
                                </v-chip>
                                <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-paperclip">
                                    {{ form.attachments?.length || 0 }} adjunto(s)
                                </v-chip>
                                <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-email-plus-outline">
                                    {{ extraEmails.length }} extra(s)
                                </v-chip>
                                <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-account-group-outline">
                                    {{ previewRecipientsLabel }}
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

                            <div
                                class="max-h-[280px] overflow-y-auto border border-gray-200 bg-white pa-4 text-body-2 leading-7"
                                v-html="form.body || '<p>Sin contenido</p>'"
                            />
                        </div>

                        <div v-if="attachmentPreviewItems.length > 0" class="pa-6">
                            <div class="mb-2 text-caption text-medium-emphasis font-weight-bold text-uppercase">
                                Archivos adjuntos
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div
                                    v-for="(file, index) in attachmentPreviewItems"
                                    :key="`${file.name}-${index}`"
                                    class="p-3 border border-gray-200 rounded-xl bg-gray-50"
                                >
                                    <div class="mb-2 text-body-2 font-weight-medium text-truncate">
                                        {{ file.name }}
                                    </div>
                                    <div class="mb-2 text-caption text-medium-emphasis">
                                        {{ file.type }} - {{ file.sizeLabel }}
                                    </div>

                                    <v-img
                                        v-if="file.isImage && file.previewUrl"
                                        :src="file.previewUrl"
                                        :alt="file.name"
                                        height="120"
                                        cover
                                        class="overflow-hidden border rounded-lg"
                                    />
                                    <div v-else class="text-caption text-medium-emphasis">
                                        Sin vista previa para este tipo de archivo.
                                    </div>
                                </div>
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
                        @click="closePreviewModal"
                    />
                    <BaseButton
                        v-if="can.includes('email-notifications.store')"
                        :icon-only="false"
                        text="Enviar"
                        variant="flat"
                        action="save"
                        @click="saveStepOne"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
