<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { ref } from "vue";

const page = usePage<any>();

interface MemberItem {
    member_id: number;
    full_name: string;
    relationship_name: string | null;
    is_primary_holder: boolean;
    email: string | null;
    has_mobile_access: boolean;
}

interface MembershipInfo {
    id: number;
    membership_number: string | null;
    holder_name: string;
    membership_type_name: string | null;
    club_name: string | null;
    status: string;
}

interface Props {
    membership: MembershipInfo;
    members: MemberItem[];
}

const props = defineProps<Props>();

const form = useForm({
    cancellation_letter: null as File | null,
});

const fileInput = ref<HTMLInputElement | null>(null);
const fileName = ref<string>("");
const confirmed = ref(false);

const onFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    form.cancellation_letter = file;
    fileName.value = file?.name ?? "";
};

const submit = () => {
    form.post(route("members.cancel.store", props.membership.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash?.success || "Cuenta dada de baja correctamente.",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${form.errors.messageError || "No se pudo procesar la baja"}`,
                text: `${form.errors.exception || ""}`,
                icon: "error",
            });
        },
    });
};
</script>

<template>
    <Head title="Dar de Baja Cuenta" />

    <AppLayout>
        <template #header>Dar de Baja Cuenta</template>
        <template #options>
            <BaseButton
                :icon-only="false"
                action="cancel"
                text="Volver"
                @click="router.visit(route('members.index'))"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12" md="10" class="mx-auto">
                    <v-container class="py-6">

                        <!-- Información de la cuenta -->
                        <v-card class="pa-4 mb-4" variant="tonal">
                            <div class="text-subtitle-1 font-weight-bold mb-2">
                                Información de la cuenta
                            </div>
                            <p>
                                <strong>No. cuenta:</strong>
                                {{ props.membership.membership_number || "-" }}
                            </p>
                            <p>
                                <strong>Titular:</strong>
                                {{ props.membership.holder_name }}
                            </p>
                            <p>
                                <strong>Membresía:</strong>
                                {{ props.membership.membership_type_name || "-" }}
                            </p>
                            <p>
                                <strong>Club:</strong>
                                {{ props.membership.club_name || "-" }}
                            </p>
                        </v-card>

                        <!-- Lista de integrantes -->
                        <v-card class="pa-4 mb-4" variant="outlined">
                            <div class="text-subtitle-1 font-weight-bold mb-3">
                                Integrantes que serán dados de baja
                            </div>
                            <v-list density="compact">
                                <v-list-item
                                    v-for="member in props.members"
                                    :key="member.member_id"
                                    :title="member.full_name"
                                    :subtitle="member.is_primary_holder ? 'Titular' : (member.relationship_name || 'Integrante')"
                                >
                                    <template #append>
                                        <div class="d-flex ga-1">
                                            <v-chip
                                                v-if="member.is_primary_holder"
                                                size="x-small"
                                                color="primary"
                                                variant="flat"
                                            >
                                                Titular
                                            </v-chip>
                                            <v-chip
                                                v-if="member.has_mobile_access"
                                                size="x-small"
                                                color="warning"
                                                variant="tonal"
                                            >
                                                Acceso app
                                            </v-chip>
                                        </div>
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-card>

                        <!-- Advertencia -->
                        <v-alert
                            type="error"
                            variant="tonal"
                            class="mb-4"
                        >
                            <strong>Esta acción es irreversible.</strong> Al confirmar la baja:
                            <ul class="mt-1 ml-4">
                                <li>Todos los integrantes quedarán inactivos.</li>
                                <li>Los casilleros asignados serán liberados.</li>
                                <li>El acceso a la app móvil será revocado para todos los integrantes.</li>
                                <li>El historial de pagos, reservaciones y multas se conserva.</li>
                            </ul>
                        </v-alert>

                        <v-form @submit.prevent="submit">
                            <v-card class="pa-4">
                                <div class="text-subtitle-1 font-weight-bold mb-3">
                                    Documentación requerida
                                </div>

                                <!-- Carga de carta de baja -->
                                <v-card
                                    variant="outlined"
                                    class="pa-4 mb-4"
                                    :color="form.errors.cancellation_letter ? 'error' : undefined"
                                >
                                    <div class="font-weight-medium mb-1">
                                        Carta de baja firmada por el titular
                                        <span class="text-error">*</span>
                                    </div>
                                    <div class="text-caption text-medium-emphasis mb-3">
                                        Formatos aceptados: PDF, JPG, PNG. Tamaño máximo: 5 MB.
                                    </div>

                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        style="display: none"
                                        @change="onFileChange"
                                    />

                                    <div class="d-flex align-center ga-3">
                                        <v-btn
                                            variant="outlined"
                                            prepend-icon="mdi-upload"
                                            @click="fileInput?.click()"
                                        >
                                            Seleccionar archivo
                                        </v-btn>
                                        <span
                                            v-if="fileName"
                                            class="text-body-2 text-success"
                                        >
                                            {{ fileName }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-body-2 text-medium-emphasis"
                                        >
                                            Ningún archivo seleccionado
                                        </span>
                                    </div>

                                    <div
                                        v-if="form.errors.cancellation_letter"
                                        class="text-error text-caption mt-2"
                                    >
                                        {{ form.errors.cancellation_letter }}
                                    </div>
                                </v-card>

                                <!-- Confirmación -->
                                <v-checkbox
                                    v-model="confirmed"
                                    color="error"
                                    class="mb-2"
                                >
                                    <template #label>
                                        <span class="text-body-2">
                                            Confirmo que el titular ha solicitado la baja de forma presencial
                                            y que la carta de baja está firmada.
                                        </span>
                                    </template>
                                </v-checkbox>

                                <div class="d-flex justify-end ga-2 mt-2">
                                    <v-btn
                                        variant="text"
                                        @click="router.visit(route('members.index'))"
                                    >
                                        Cancelar
                                    </v-btn>
                                    <v-btn
                                        color="error"
                                        :disabled="!form.cancellation_letter || !confirmed"
                                        :loading="form.processing"
                                        @click="submit"
                                    >
                                        Confirmar baja de cuenta
                                    </v-btn>
                                </div>
                            </v-card>
                        </v-form>

                    </v-container>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>
