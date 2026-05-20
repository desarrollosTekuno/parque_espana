<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import FormQuillEditor from "@/Components/Form/FormQuillEditor.vue";
import { required } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";
import axios from "axios";
import { computed, ref, watch } from "vue";

interface Props {
    club_id: number | null;
}

const props = defineProps<Props>();

const showModal = ref(false);
const showRecipientsModal = ref(false);
const formSendRef = ref();
const recipientsCount = ref(0);
const recipientsItems = ref<Array<{ id: number; name: string; email: string }>>([]);
const selectedRecipientIds = ref<number[]>([]);
const recipientsSearch = ref("");
const recipientsPage = ref(1);
const recipientsPerPage = 20;

const form = useForm({
    scope: "by_club" as "by_club" | "individual",
    club_id: null as number | null,
    individual_email: "",
    title: "",
    body: "",
    attachments: [] as File[],
    send_type: "now" as "now" | "scheduled",
    scheduled_date: "",
    scheduled_time: "",
    extra_emails: [] as string[],
    selected_recipient_ids: [] as number[],
});

const getMembers = async () => {
    try {
        const response = await axios.get(route("email-notifications.getMembers", form));
        recipientsCount.value = response.data.count ?? 0;
        recipientsItems.value = response.data.items ?? [];
        selectedRecipientIds.value = recipientsItems.value.map((item) => item.id);
    } catch (e) {
        console.error(e);
    }
};

const filteredRecipients = computed(() => {
    if (!recipientsSearch.value.trim()) {
        return recipientsItems.value;
    }

    const term = recipientsSearch.value.trim().toLowerCase();
    return recipientsItems.value.filter((item) => {
        return item.name.toLowerCase().includes(term) || item.email.toLowerCase().includes(term);
    });
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

const toggleRecipient = (id: number, checked: boolean | null) => {
    if (checked) {
        if (!selectedRecipientIds.value.includes(id)) {
            selectedRecipientIds.value.push(id);
        }
        return;
    }

    selectedRecipientIds.value = selectedRecipientIds.value.filter((itemId) => itemId !== id);
};

watch(recipientsSearch, () => {
    recipientsPage.value = 1;
});

watch(filteredRecipients, () => {
    if (recipientsPage.value > recipientsPagesCount.value) {
        recipientsPage.value = recipientsPagesCount.value;
    }
});

const openModal = () => {
    form.reset();
    form.clearErrors();
    form.scope = "by_club";
    form.club_id = props.club_id;
    form.send_type = "now";
    showModal.value = true;
    getMembers();
};

const onScopeChange = () => {
    if (form.scope === "by_club") {
        form.club_id = props.club_id;
        getMembers();
    }
};

const closeModal = () => {
    showModal.value = false;
};

const openRecipientsModal = () => {
    showRecipientsModal.value = true;
};

const closeRecipientsModal = () => {
    showRecipientsModal.value = false;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid }: { valid: boolean }) => {
        if (!valid) {
            return;
        }

        form.selected_recipient_ids = [...selectedRecipientIds.value];

        form.post(route("email-notifications.store"), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
            },
        });
    });
};
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
                @click="openModal"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg pa-6">
            <p class="text-body-1">Entraste correctamente al modulo de notificaciones por correo.</p>
            Estas usando el club {{ club_id }}
        </div>

        <v-dialog v-model="showModal" max-width="900" persistent>
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card title="Nueva notificacion">
                    <v-card-text>
                        <v-row>
                            <v-col cols="12">
                                    <div class="mb-2 text-subtitle-2">Selecciona a quienes se les enviara el correo</div>
                                    <v-btn-toggle v-model="form.scope" class="w-100" color="primary" mandatory divided @update:model-value="onScopeChange">
                                        <v-btn value="by_club" class="flex-grow-1" prepend-icon="mdi-map-marker">Por parque</v-btn>
                                        <v-btn value="individual" class="flex-grow-1" prepend-icon="mdi-account">Individual</v-btn>
                                    </v-btn-toggle>
                                    <div class="mt-1 text-caption text-medium-emphasis">
                                        {{ form.scope === 'individual' ? 'Se enviara a la persona seleccionada' : 'Selecciona un parque especifico.' }}
                                    </div>

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
                            </v-col>

                            <v-col v-if="form.scope === 'individual'" cols="12">
                                <v-text-field
                                    v-model="form.individual_email"
                                    label="Correo destino"
                                    :rules="[required]"
                                    required
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
                                    label="Adjuntos"
                                    name="attachments[]"
                                    multiple
                                    chips
                                    show-size
                                    counter
                                    clearable
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
                        <v-btn text="Cerrar" variant="plain" @click="closeModal" />
                        <v-btn text="Guardar" color="primary" variant="tonal" type="submit" :loading="form.processing" />
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
    </AppLayout>
</template>
