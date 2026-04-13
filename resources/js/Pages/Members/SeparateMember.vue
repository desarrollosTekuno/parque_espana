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

interface TargetMembershipOption {
    id: number;
    code: string | null;
    name: string;
    monthly_fee: number;
    inscription_fee: number;
}

interface CandidateMember {
    member_id: number;
    full_name: string;
    relationship_name: string | null;
    email: string | null;
    phone: string | null;
    age: number | null;
    target_membership_options: TargetMembershipOption[];
}

interface Props {
    membership: SourceMembership;
    candidateMembers?: CandidateMember[];
}

const props = withDefaults(defineProps<Props>(), {
    candidateMembers: () => [],
});

const form = useForm({
    member_id: null as number | null,
    target_membership_type_id: null as number | null,
    reason: "",
});

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const memberOptions = computed(() =>
    props.candidateMembers.map((candidate) => ({
        value: candidate.member_id,
        title: candidate.relationship_name
            ? `${candidate.full_name} (${candidate.relationship_name})`
            : candidate.full_name,
    })),
);

const selectedMember = computed(
    () =>
        props.candidateMembers.find(
            (candidate) => candidate.member_id === form.member_id,
        ) ?? null,
);

const targetMembershipOptions = computed(() =>
    (selectedMember.value?.target_membership_options ?? []).map((option) => ({
        value: option.id,
        title: option.code ? `${option.code} - ${option.name}` : option.name,
    })),
);

const selectedTargetMembership = computed(
    () =>
        selectedMember.value?.target_membership_options.find(
            (option) => option.id === form.target_membership_type_id,
        ) ?? null,
);

const submit = () => {
    form.post(route("members.separation.store", props.membership.id), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${form.errors.messageError || "No se pudo separar al integrante"}`,
                text: `${form.errors.exception || ""}`,
                icon: "error",
            });
        },
    });
};
</script>

<template>
    <Head title="Separar Integrante" />

    <AppLayout>
        <template #header>Separar Integrante</template>
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
                                Cuenta familiar origen
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

                        <v-form @submit.prevent="submit">
                            <v-card class="pa-4">
                                <div class="text-subtitle-1 font-weight-bold mb-2">
                                    Nueva cuenta individual
                                </div>
                                <p class="text-body-2 text-medium-emphasis mb-4">
                                    Selecciona al integrante que saldrá de la cuenta
                                    familiar y el tipo de membresía destino. Se creará
                                    un nuevo folio para esa persona.
                                </p>

                                <v-autocomplete
                                    v-model="form.member_id"
                                    :items="memberOptions"
                                    item-title="title"
                                    item-value="value"
                                    label="Integrante"
                                    :error-messages="form.errors.member_id"
                                    clearable
                                    @update:modelValue="form.target_membership_type_id = null"
                                />

                                <v-card
                                    v-if="selectedMember"
                                    variant="outlined"
                                    class="pa-4 mb-4"
                                >
                                    <div class="font-weight-medium mb-2">
                                        {{ selectedMember.full_name }}
                                    </div>
                                    <p v-if="selectedMember.relationship_name">
                                        <strong>Parentesco:</strong>
                                        {{ selectedMember.relationship_name }}
                                    </p>
                                    <p v-if="selectedMember.age !== null">
                                        <strong>Edad:</strong>
                                        {{ selectedMember.age }}
                                    </p>
                                    <p v-if="selectedMember.email">
                                        <strong>Correo:</strong>
                                        {{ selectedMember.email }}
                                    </p>
                                    <p v-if="selectedMember.phone">
                                        <strong>Teléfono:</strong>
                                        {{ selectedMember.phone }}
                                    </p>
                                </v-card>

                                <v-autocomplete
                                    v-model="form.target_membership_type_id"
                                    :items="targetMembershipOptions"
                                    item-title="title"
                                    item-value="value"
                                    label="Membresía destino"
                                    :error-messages="form.errors.target_membership_type_id"
                                    :disabled="!selectedMember"
                                    clearable
                                />

                                <v-card
                                    v-if="selectedTargetMembership"
                                    variant="tonal"
                                    class="pa-4 mb-4"
                                >
                                    <div class="font-weight-medium mb-2">
                                        {{ selectedTargetMembership.name }}
                                    </div>
                                    <p>
                                        <strong>Mensualidad:</strong>
                                        {{
                                            currencyFormatter.format(
                                                selectedTargetMembership.monthly_fee,
                                            )
                                        }}
                                    </p>
                                    <p>
                                        <strong>Inscripción:</strong>
                                        {{
                                            currencyFormatter.format(
                                                selectedTargetMembership.inscription_fee,
                                            )
                                        }}
                                    </p>
                                </v-card>

                                <v-textarea
                                    v-model="form.reason"
                                    label="Motivo (opcional)"
                                    rows="3"
                                    :error-messages="form.errors.reason"
                                />

                                <v-alert
                                    v-if="selectedMember && selectedTargetMembership"
                                    type="info"
                                    variant="tonal"
                                    class="mb-4"
                                >
                                    Se creará una nueva cuenta para
                                    <strong>{{ selectedMember.full_name }}</strong>
                                    con la membresía
                                    <strong>{{ selectedTargetMembership.name }}</strong>.
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
                                        :disabled="!form.member_id || !form.target_membership_type_id"
                                        :loading="form.processing"
                                        @click="submit"
                                    >
                                        Confirmar separación
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
