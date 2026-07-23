<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { maxLength } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { ref } from "vue";

const can = usePage().props.auth.permissions;
const page = usePage<any>();

interface ClubConektaItem {
    club_id: number;
    club_name: string;
    club_code: string;
    conekta_public_key: string | null;
    has_conekta_secret_key: boolean;
}

interface Props {
    clubs?: ClubConektaItem[];
    conektaPaymentMethodExists?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    clubs: () => [],
    conektaPaymentMethodExists: false,
});

interface ConektaCredentialForm {
    club_id: number | null;
    conekta_public_key: string;
    conekta_secret_key: string;
}

const showModal = ref(false);
const formRef = ref();
const selectedClubName = ref("");
const hasSecretKey = ref(false);

const headers = [
    { title: "Parque", key: "club_name", sortable: false },
    { title: "Llave pública", key: "conekta_public_key", sortable: false },
    { title: "Llave secreta", key: "has_conekta_secret_key", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
];

const form = useForm<ConektaCredentialForm>({
    club_id: null,
    conekta_public_key: "",
    conekta_secret_key: "",
});

const openEdit = (item: ClubConektaItem) => {
    form.clearErrors();
    form.club_id = item.club_id;
    form.conekta_public_key = item.conekta_public_key ?? "";
    form.conekta_secret_key = "";
    selectedClubName.value = item.club_name;
    hasSecretKey.value = item.has_conekta_secret_key;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    form.clearErrors();
};

const save = () => {
    formRef.value?.validate().then(({ valid: isValid }: { valid: boolean }) => {
        if (!isValid) return;

        form.put(route("conekta-credentials.update"), {
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({
                    title: page.props.flash.success || "",
                    icon: "success",
                });
                closeModal();
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
</script>

<template>
    <Head title="Credenciales Conekta" />

    <AppLayout>
        <template #header>
            <h2 class="text-h5">Credenciales Conekta por parque</h2>
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-alert
                v-if="!props.conektaPaymentMethodExists"
                type="warning"
                variant="tonal"
                class="ma-4"
            >
                Todavía no existe un método de pago con proveedor "Conekta". Créalo primero desde
                <strong>Métodos de pago</strong> antes de configurar las credenciales aquí.
            </v-alert>

            <v-row>
                <v-col cols="12">
                    <v-data-table
                        :headers="headers"
                        :items="props.clubs"
                        no-data-text="No tienes parques asignados"
                        class="elevation-1"
                    >
                        <template #item.conekta_public_key="{ item }">
                            <span v-if="item.conekta_public_key" class="text-body-2">{{ item.conekta_public_key }}</span>
                            <v-chip v-else size="small" color="grey" variant="tonal">Sin configurar</v-chip>
                        </template>

                        <template #item.has_conekta_secret_key="{ item }">
                            <v-chip
                                size="small"
                                :color="item.has_conekta_secret_key ? 'success' : 'grey'"
                                variant="tonal"
                            >
                                {{ item.has_conekta_secret_key ? "Configurada" : "Sin configurar" }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('conekta-credentials.update')"
                                :icon-only="false"
                                action="edit"
                                text="Configurar"
                                :disabled="!props.conektaPaymentMethodExists"
                                @click="openEdit(item)"
                            />
                        </template>
                    </v-data-table>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="480" persistent>
            <v-form ref="formRef" @submit.prevent="save">
                <v-card prepend-icon="mdi-credit-card-lock-outline" :title="`Credenciales Conekta — ${selectedClubName}`">
                    <v-card-text>
                        <p class="text-body-2 text-medium-emphasis mb-4">
                            Estas credenciales son exclusivas de la cuenta comercial de Conekta de
                            <strong>{{ selectedClubName }}</strong>.
                        </p>
                        <v-text-field
                            v-model="form.conekta_public_key"
                            label="Llave pública"
                            clearable
                            class="mb-2"
                            :rules="[maxLength(255)]"
                            :error-messages="form.errors.conekta_public_key"
                        />
                        <v-text-field
                            v-model="form.conekta_secret_key"
                            label="Llave secreta"
                            type="password"
                            clearable
                            :rules="[maxLength(1000)]"
                            :error-messages="form.errors.conekta_secret_key"
                            :hint="
                                hasSecretKey
                                    ? 'Ya hay una llave secreta configurada. Déjalo vacío para conservarla'
                                    : 'Sin llave secreta configurada'
                            "
                            persistent-hint
                        />
                    </v-card-text>

                    <v-divider />

                    <v-card-actions>
                        <v-spacer />
                        <v-btn text="Cancelar" variant="plain" @click="closeModal" />
                        <v-btn color="primary" text="Guardar" variant="tonal" type="submit" :loading="form.processing" />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>
