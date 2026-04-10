<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const page = usePage<any>();

interface SourceMembership {
    id: number;
    membership_number: string | null;
    holder_name: string;
    membership_type_name: string | null;
    membership_type_code: string | null;
    club_name: string | null;
    club_code: string | null;
    status: string;
    start_date: string | null;
}

interface AccountMemberOption {
    member_id: number;
    full_name: string;
    relationship_name: string | null;
    email: string | null;
    phone: string | null;
    is_primary_holder: boolean;
}

interface Props {
    membership: SourceMembership;
    currentPrimaryHolder: AccountMemberOption | null;
    candidateMembers?: AccountMemberOption[];
}

const props = withDefaults(defineProps<Props>(), {
    candidateMembers: () => [],
});

const form = useForm({
    new_primary_member_id: null as number | null,
    reason: "",
});

const candidateOptions = computed(() =>
    props.candidateMembers.map((candidate) => ({
        value: candidate.member_id,
        title: candidate.relationship_name
            ? `${candidate.full_name} (${candidate.relationship_name})`
            : candidate.full_name,
    })),
);

const selectedCandidate = computed(() =>
    props.candidateMembers.find(
        (candidate) => candidate.member_id === form.new_primary_member_id,
    ) ?? null,
);

const submit = () => {
    form.patch(route("members.change-holder.update", props.membership.id), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${form.errors.messageError || "No se pudo cambiar el titular"}`,
                text: `${form.errors.exception || ""}`,
                icon: "error",
            });
        },
    });
};
</script>

<template>
    <Head title="Cambiar Titular" />

    <AppLayout>
        <template #header>Cambiar Titular</template>
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
                        <v-card class="pa-4 mb-4" variant="tonal">
                            <div class="text-subtitle-1 font-weight-bold mb-2">
                                Cuenta actual
                            </div>
                            <p>
                                <strong>Folio:</strong>
                                {{ props.membership.membership_number || "-" }}
                            </p>
                            <p>
                                <strong>Membresía:</strong>
                                {{ props.membership.membership_type_name }}
                            </p>
                            <p>
                                <strong>Club:</strong>
                                {{ props.membership.club_name }}
                            </p>
                        </v-card>

                        <v-card class="pa-4 mb-4">
                            <div class="text-subtitle-1 font-weight-bold mb-2">
                                Titular actual
                            </div>
                            <p>{{ props.currentPrimaryHolder?.full_name || "-" }}</p>
                            <p v-if="props.currentPrimaryHolder?.email">
                                <strong>Correo:</strong>
                                {{ props.currentPrimaryHolder.email }}
                            </p>
                            <p v-if="props.currentPrimaryHolder?.phone">
                                <strong>Teléfono:</strong>
                                {{ props.currentPrimaryHolder.phone }}
                            </p>
                        </v-card>

                        <v-form @submit.prevent="submit">
                            <v-card class="pa-4">
                                <div class="text-subtitle-1 font-weight-bold mb-2">
                                    Nuevo titular
                                </div>
                                <p class="text-body-2 text-medium-emphasis mb-4">
                                    Selecciona al integrante que asumirá la titularidad
                                    de esta misma cuenta.
                                </p>

                                <v-autocomplete
                                    v-model="form.new_primary_member_id"
                                    :items="candidateOptions"
                                    item-title="title"
                                    item-value="value"
                                    label="Integrante"
                                    :error-messages="form.errors.new_primary_member_id"
                                    clearable
                                />

                                <v-textarea
                                    v-model="form.reason"
                                    label="Motivo (opcional)"
                                    rows="3"
                                    :error-messages="form.errors.reason"
                                />

                                <v-alert
                                    v-if="selectedCandidate"
                                    type="info"
                                    variant="tonal"
                                    class="mb-4"
                                >
                                    Se promoverá a
                                    <strong>{{ selectedCandidate.full_name }}</strong>
                                    como nuevo titular. El folio y la membresía
                                    actual se conservarán.
                                </v-alert>

                                <div class="d-flex justify-end ga-2">
                                    <v-btn
                                        variant="text"
                                        @click="router.visit(route('members.index'))"
                                    >
                                        Cancelar
                                    </v-btn>
                                    <v-btn
                                        color="primary"
                                        :disabled="!form.new_primary_member_id"
                                        :loading="form.processing"
                                        @click="submit"
                                    >
                                        Confirmar cambio
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
