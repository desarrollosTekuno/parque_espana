<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import CustomFileUploadField from "@/Components/CustomFileUploadField.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { fileMaxSizeRule, fileTypeRule, requiredFileRule } from "@/constants/validationRules";
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

interface CancellationReasonOption {
    id: number;
    name: string;
}

interface Props {
    membership: MembershipInfo;
    members: MemberItem[];
    cancellationReasons: CancellationReasonOption[];
}

const props = defineProps<Props>();

const form = useForm({
    cancellation_letter: null as File | null,
    cancellation_reason_id: null as number | null,
    waive_pending_charges: false,
});

const letterFiles = ref<File[] | null>(null);
const confirmed = ref(false);
const formRef = ref<{ validate(): Promise<{ valid: boolean }> } | null>(null);

const letterRules = [
    requiredFileRule,
    fileTypeRule(["pdf", "jpg", "jpeg", "png"]),
    fileMaxSizeRule(2),
];

const submit = async () => {
    const result = await formRef.value?.validate();
    if (!result?.valid) return;

    form.cancellation_letter = letterFiles.value?.[0] ?? null;
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

                        <v-form ref="formRef" @submit.prevent="submit">
                            <v-card class="pa-4">
                                <div class="text-subtitle-1 font-weight-bold mb-3">
                                    Documentación requerida
                                </div>

                                <!-- Motivo de la baja -->
                                <div class="mb-4">
                                    <v-select
                                        v-model="form.cancellation_reason_id"
                                        :items="props.cancellationReasons"
                                        item-title="name"
                                        item-value="id"
                                        label="Motivo de la baja"
                                        :rules="[(v: number | null) => !!v || 'Debes indicar el motivo de la baja']"
                                        :error-messages="form.errors.cancellation_reason_id"
                                        hide-details="auto"
                                    />
                                </div>

                                <!-- Carga de carta de baja -->
                                <div class="mb-4">
                                    <div class="font-weight-medium mb-1">
                                        Carta de baja firmada por el titular
                                        <span class="text-error">*</span>
                                    </div>
                                    <CustomFileUploadField
                                        v-model="letterFiles"
                                        label="Seleccionar carta de baja"
                                        hint="PDF, JPG o PNG · máx. 2 MB"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        :rules="letterRules"
                                    />
                                    <div
                                        v-if="form.errors.cancellation_letter"
                                        class="text-error text-caption mt-1"
                                    >
                                        {{ form.errors.cancellation_letter }}
                                    </div>
                                </div>

                                <!-- Condonación de adeudo -->
                                <v-checkbox
                                    v-model="form.waive_pending_charges"
                                    color="warning"
                                    class="mb-0"
                                    hide-details="auto"
                                    :error-messages="form.errors.waive_pending_charges"
                                >
                                    <template #label>
                                        <span class="text-body-2">
                                            Condonar el adeudo pendiente del socio (mensualidades u otros cargos sin pagar).
                                            Solo aplica si esta baja lo deja sin ninguna membresía activa en ningún parque.
                                        </span>
                                    </template>
                                </v-checkbox>
                                <v-alert
                                    v-if="form.waive_pending_charges"
                                    type="warning"
                                    variant="tonal"
                                    density="compact"
                                    class="mb-4"
                                >
                                    Se cancelará todo el adeudo pendiente del socio en todos sus parques — esta acción
                                    también es irreversible.
                                </v-alert>

                                <!-- Confirmación -->
                                <v-checkbox
                                    v-model="confirmed"
                                    color="error"
                                    class="mb-2"
                                    :rules="[(v: boolean) => v || 'Debes confirmar antes de continuar']"
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
                                        type="submit"
                                        :loading="form.processing"
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
