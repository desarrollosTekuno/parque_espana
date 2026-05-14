<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { required } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";

interface NotificationItem {
    id: number;
    subject: string | null;
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
const props = defineProps<Props>();
const prefix = "email_notifications";
const showModal = ref(false);
const showRecipientsModal = ref(false);
const formSendRef = ref();
const recipientsCount = ref(0);
const recipientsItems = ref<Array<{ id: number; name: string; email: string }>>([]);
const selectedRecipientIds = ref<number[]>([]);
const extraEmails = ref<string[]>([]);
const recipientsSearch = ref("");
const recipientsPage = ref(1);
const recipientsPerPage = 20;

const form = useForm({
    scope: "all" as "all" | "by_club",
    club_id: null as number | null,
    smtp_config_id: null as number | null,
});

const headers = [
    { title: "Fecha", key: "sent_at" },
    { title: "Asunto", key: "subject" },
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
    form.scope = "all";
    form.club_id = null;
    form.smtp_config_id = null;
    extraEmails.value = [];
    showModal.value = true;
    loadRecipientsPreview();
};

const onScopeChange = () => {
    if (form.scope === "all") {
        form.club_id = null;
    }

    form.smtp_config_id = null;

    loadRecipientsPreview();
};

const onClubChange = () => {
    form.smtp_config_id = null;

    if (form.scope !== "by_club") {
        return;
    }

    loadRecipientsPreview();
};

const loadRecipientsPreview = async () => {
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
    showModal.value = false;
};

const saveStepOne = () => {
    formSendRef.value?.validate().then(({ valid: isValid }: { valid: boolean }) => {
        if (!isValid) {
            return;
        }

        closeModal();
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

        <v-dialog v-model="showModal" max-width="700" persistent>
            <v-form ref="formSendRef" @submit.prevent="saveStepOne">
                <v-card title="Enviar correo masivo">
                    <v-card-text>
                        <div class="p-1 mb-3 bg-red-500">
                            <div class="mb-2 text-subtitle-2">Selecciona a quienes se les enviara el correo</div>
                            <v-btn-toggle
                                v-model="form.scope"
                                color="primary"
                                mandatory
                                divided
                                @update:model-value="onScopeChange"
                            >
                                <v-btn value="all" prepend-icon="mdi-earth">Todos</v-btn>
                                <v-btn value="by_club" prepend-icon="mdi-map-marker">Por parque</v-btn>
                            </v-btn-toggle>
                            <div class="mt-1 text-caption text-medium-emphasis">
                                {{ form.scope === 'all' ? 'Se incluiran usuarios de todos los parques.' : 'Selecciona un parque especifico.' }}
                            </div>
                        </div>

                        <v-row>
                            <v-col v-if="form.scope === 'by_club'" cols="12" md="6">
                                <v-select
                                    v-model="form.club_id"
                                    :items="props.clubs"
                                    item-title="name"
                                    item-value="id"
                                    label="Selecciona el parque"
                                    :rules="[required]"
                                    required
                                    @update:model-value="onClubChange"
                                />
                            </v-col>
                            <v-col cols="12" :md="form.scope === 'by_club' ? 6 : 12">
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
                                Seleccionados: <strong>{{ selectedRecipientsCount }}</strong> de {{ recipientsCount }}
                            </div>
                            <a
                                v-if="selectedRecipientsCount > 0"
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
                            hint="Escribe correo y presiona Enter"
                            persistent-hint
                            :rules="[validExtraEmails]"
                            @update:model-value="normalizeExtraEmails"
                        />


                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        <v-btn variant="text" color="secondary" @click="closeModal">
                            Cancelar
                        </v-btn>
                        <v-btn type="submit" color="primary" variant="flat">
                            Continuar
                        </v-btn>
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
                    <v-btn variant="text" color="secondary" @click="closeRecipientsModal">
                        Cerrar
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
