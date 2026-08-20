<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import CustomFileUploadField from "@/Components/CustomFileUploadField.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import {
    email,
    fileMaxSizeRule,
    fileTypeRule,
    minLength,
    postalCode,
    required,
    requiredFileRule,
    selectRequired,
    validatePhone,
} from "@/constants/validationRules";
import { customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";

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
    relationship_id?: number | null;
    relationship_name: string | null;
    email: string | null;
    phone: string | null;
    age: number | null;
    has_other_club_membership: boolean;
    other_club_name: string | null;
    target_membership_options: TargetMembershipOption[];
    prefill: MemberForm;
}

interface CountryCatalog {
    id: number;
    name: string;
    translations: Record<string, string> | null;
    demonym: string | null;
}

interface StateCatalog {
    id: number;
    country_id: number;
    name: string;
}

interface CityCatalog {
    id: number;
    state_id: number;
    name: string;
}

interface MaritalStatus {
    id: number;
    name: string;
}

interface MemberForm {
    first_name: string | null;
    last_name: string | null;
    second_last_name: string | null;
    birthdate: string | null;
    birth_place: string | null;
    birth_country_id: number | null;
    birth_state_id: number | null;
    birth_city_id: number | null;
    nationality_id: number | null;
    marital_status_id: number | null;
    phone: string | null;
    email: string | null;
    occupation: string | null;
    address: {
        street: string | null;
        neighborhood: string | null;
        postal_code: string | null;
        country_id: number | null;
        state_id: number | null;
        city_id: number | null;
        years_in_city: number | null;
    };
    employment: {
        company_name: string | null;
        company_address: string | null;
        company_phone: string | null;
    };
}

interface SeparationReason {
    id: number;
    code: string;
    name: string;
    relationship_id: number | null;
    relationship_name?: string | null;
    document_type_id: number | null;
    document_type_code?: string | null;
    document_type_name?: string | null;
    allowed_extensions?: string[] | string | null;
    max_file_size_kb?: number | null;
    requires_document: boolean;
}

interface Props {
    membership: SourceMembership;
    candidateMembers?: CandidateMember[];
    separationReasons?: SeparationReason[];
    countries: CountryCatalog[];
    nationalities: CountryCatalog[];
    maritalStatuses: MaritalStatus[];
}

const props = withDefaults(defineProps<Props>(), {
    candidateMembers: () => [],
    separationReasons: () => [],
});

const form = useForm({
    member_id: null as number | null,
    target_membership_type_id: null as number | null,
    separation_reason_id: null as number | null,
    reason: "",
    reason_document: null as File | null,
    member: null as MemberForm | null,
});

const formRef = ref<{ validate(): Promise<{ valid: boolean }> } | null>(null);
const reasonDocumentFiles = ref<File[] | null>(null);
const statesByCountry = ref<Record<number, StateCatalog[]>>({});
const citiesByState = ref<Record<number, CityCatalog[]>>({});

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
        relationship_name: candidate.relationship_name,
    })),
);

const selectedMember = computed(
    () =>
        props.candidateMembers.find(
            (candidate) => candidate.member_id === form.member_id,
        ) ?? null,
);

const getCountryDisplayName = (country: CountryCatalog | null | undefined) =>
    country?.translations?.["es-MX"]?.trim() || country?.translations?.es?.trim() || country?.name || "";

const countryOptions = computed(() =>
    props.countries.map((country) => ({ id: country.id, title: getCountryDisplayName(country) })),
);

const nationalityOptions = computed(() =>
    props.nationalities.map((country) => ({
        id: country.id,
        title: country.demonym || getCountryDisplayName(country),
    })),
);

const maritalStatusOptions = computed(() =>
    props.maritalStatuses.map((status) => ({ id: status.id, title: status.name })),
);

const getStateOptions = (countryId: number | null) =>
    countryId ? statesByCountry.value[countryId] ?? [] : [];

const getCityOptions = (stateId: number | null) =>
    stateId ? citiesByState.value[stateId] ?? [] : [];

const fetchStates = async (countryId: number | null) => {
    if (!countryId || statesByCountry.value[countryId]) return;
    const response = await fetch(route("members.location-catalogs.states", { country_id: countryId }));
    if (response.ok) statesByCountry.value[countryId] = await response.json();
};

const fetchCities = async (stateId: number | null) => {
    if (!stateId || citiesByState.value[stateId]) return;
    const response = await fetch(route("members.location-catalogs.cities", { state_id: stateId }));
    if (response.ok) citiesByState.value[stateId] = await response.json();
};

const loadLocationCatalogs = async () => {
    if (!form.member) return;
    await Promise.all([
        fetchStates(form.member.birth_country_id),
        fetchStates(form.member.address.country_id),
    ]);
    await Promise.all([
        fetchCities(form.member.birth_state_id),
        fetchCities(form.member.address.state_id),
    ]);
};

const onBirthCountryChange = async (countryId: number | null) => {
    if (!form.member) return;
    form.member.birth_state_id = null;
    form.member.birth_city_id = null;
    await fetchStates(countryId);
};

const onBirthStateChange = async (stateId: number | null) => {
    if (!form.member) return;
    form.member.birth_city_id = null;
    await fetchCities(stateId);
};

const onAddressCountryChange = async (countryId: number | null) => {
    if (!form.member) return;
    form.member.address.state_id = null;
    form.member.address.city_id = null;
    await fetchStates(countryId);
};

const onAddressStateChange = async (stateId: number | null) => {
    if (!form.member) return;
    form.member.address.city_id = null;
    await fetchCities(stateId);
};

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

const normalizeText = (value: string | null | undefined) =>
    (value ?? "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();

const memberSeparationReasons = computed(() => {
    if (!selectedMember.value) return [];

    const relationshipId = selectedMember.value.relationship_id ?? null;
    const relationshipName = normalizeText(selectedMember.value.relationship_name);

    return props.separationReasons.filter((reason) => {
        if (reason.relationship_id && relationshipId) {
            return reason.relationship_id === relationshipId;
        }

        if (reason.relationship_name) {
            return normalizeText(reason.relationship_name) === relationshipName;
        }

        return false;
    });
});

const reasonOptions = computed(() =>
    memberSeparationReasons.value.map((reason) => ({
        value: reason.id,
        title: reason.name,
    })),
);

const selectedSeparationReason = computed(
    () =>
        memberSeparationReasons.value.find(
            (reason) => reason.id === form.separation_reason_id,
        ) ?? null,
);

const selectedReasonExtensions = computed(() => {
    const extensions = selectedSeparationReason.value?.allowed_extensions;

    if (Array.isArray(extensions)) {
        return extensions.length ? extensions : ["pdf", "jpg", "jpeg", "png"];
    }

    if (typeof extensions === "string" && extensions.trim() !== "") {
        return extensions.split(",").map((extension) => extension.trim().toLowerCase());
    }

    return ["pdf", "jpg", "jpeg", "png"];
});

const selectedReasonMaxSizeMb = computed(() => {
    const maxFileSizeKb = selectedSeparationReason.value?.max_file_size_kb;

    if (!maxFileSizeKb) {
        return 5;
    }

    return maxFileSizeKb / 1024;
});

const reasonDocumentRules = computed(() => [
    requiredFileRule,
    fileTypeRule(selectedReasonExtensions.value),
    fileMaxSizeRule(selectedReasonMaxSizeMb.value),
]);

const reasonDocumentAccept = computed(() =>
    selectedReasonExtensions.value.map((extension) => `.${extension}`).join(","),
);

const showReasonSelect = computed(() => memberSeparationReasons.value.length > 0);

const showReasonDocument = computed(() =>
    Boolean(selectedSeparationReason.value?.requires_document),
);

const canSubmit = computed(() => {
    if (!form.member_id || !form.member || !form.target_membership_type_id) return false;
    if (showReasonSelect.value && !form.separation_reason_id) return false;
    if (showReasonDocument.value && !reasonDocumentFiles.value?.length) return false;
    return true;
});

const clearReasonFields = () => {
    form.separation_reason_id = null;
    form.reason = "";
    form.reason_document = null;
    reasonDocumentFiles.value = null;
};

const onMemberChange = async () => {
    form.target_membership_type_id = null;
    form.member = selectedMember.value
        ? JSON.parse(JSON.stringify(selectedMember.value.prefill))
        : null;
    clearReasonFields();
    await loadLocationCatalogs();
};

const onReasonChange = () => {
    form.reason = selectedSeparationReason.value?.name ?? "";
    form.reason_document = null;
    reasonDocumentFiles.value = null;
};

const submit = async () => {
    const result = await formRef.value?.validate();
    if (result && !result.valid) return;

    form.reason_document = reasonDocumentFiles.value?.[0] ?? null;

    form.post(route("members.separation.store", props.membership.id), {
        forceFormData: true,
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

onMounted(async () => {
    await loadLocationCatalogs();
});
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
                <v-col cols="12" md="12" class="mx-auto">
                    <v-container class="py-6">
                        <v-card class="mb-4 pa-4" variant="tonal">
                            <div class="mb-2 text-subtitle-1 font-weight-bold">
                                Cuenta familiar origen
                            </div>
                            <p>
                                <strong>No. cuenta:</strong>
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

                        <v-form ref="formRef" @submit.prevent="submit">
                            <v-card class="pa-4">
                                <div class="mb-2 text-subtitle-1 font-weight-bold">
                                    Nueva cuenta individual
                                </div>
                                <p class="mb-4 text-body-2 text-medium-emphasis">
                                    Selecciona al integrante que saldrá de la cuenta
                                    familiar y el tipo de membresía destino. Se creará
                                    un nuevo número de cuenta para esa persona.
                                </p>

                                <v-autocomplete
                                    v-model="form.member_id"
                                    :items="memberOptions"
                                    item-title="title"
                                    item-value="value"
                                    label="Integrante"
                                    :error-messages="form.errors.member_id"
                                    clearable
                                    @update:modelValue="onMemberChange"
                                />

                                <v-card
                                    v-if="selectedMember"
                                    variant="outlined"
                                    class="mb-4 pa-4"
                                >
                                    <div class="mb-2 font-weight-medium">
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

                                <v-card v-if="form.member" variant="outlined" class="mb-4 pa-4">
                                    <div class="mb-4 text-subtitle-1 font-weight-bold">
                                        Datos del nuevo titular
                                    </div>
                                    <p class="mb-4 text-body-2 text-medium-emphasis">
                                        Confirma o actualiza los datos antes de crear la nueva cuenta.
                                    </p>

                                    <v-row>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.first_name" label="Nombre(s)" :rules="[required, minLength(2)]" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.last_name" label="Apellido paterno" :rules="[required, minLength(2)]" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.second_last_name" label="Apellido materno" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.birthdate" type="date" label="Fecha de nacimiento" :rules="[required]" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-autocomplete
                                                v-model="form.member.birth_country_id"
                                                :items="countryOptions"
                                                item-title="title"
                                                item-value="id"
                                                label="País de nacimiento"
                                                clearable
                                                :rules="[selectRequired]"
                                                @update:model-value="onBirthCountryChange"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-autocomplete
                                                v-model="form.member.birth_state_id"
                                                :items="getStateOptions(form.member.birth_country_id)"
                                                item-title="name"
                                                item-value="id"
                                                label="Estado de nacimiento"
                                                :disabled="!form.member.birth_country_id"
                                                clearable
                                                :rules="[selectRequired]"
                                                @update:model-value="onBirthStateChange"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-autocomplete
                                                v-model="form.member.birth_city_id"
                                                :items="getCityOptions(form.member.birth_state_id)"
                                                item-title="name"
                                                item-value="id"
                                                label="Ciudad de nacimiento"
                                                :disabled="!form.member.birth_state_id"
                                                clearable
                                            />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-autocomplete v-model="form.member.nationality_id" :items="nationalityOptions" item-title="title" item-value="id" label="Nacionalidad" clearable :rules="[selectRequired]" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-autocomplete v-model="form.member.marital_status_id" :items="maritalStatusOptions" item-title="title" item-value="id" label="Estado civil" clearable :rules="[selectRequired]" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.phone" label="Teléfono" :rules="[required, validatePhone]" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.email" label="Correo" :rules="[required, email]" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.occupation" label="Ocupación" :rules="[required, minLength(2)]" />
                                        </v-col>
                                    </v-row>

                                    <div class="mt-4 mb-2 text-subtitle-2 font-weight-bold">Domicilio</div>
                                    <v-row>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.address.street" label="Calle" :rules="[required, minLength(3)]" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.address.neighborhood" label="Colonia" :rules="[required, minLength(3)]" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.address.postal_code" label="Código postal" :rules="[postalCode]" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-autocomplete
                                                v-model="form.member.address.country_id"
                                                :items="countryOptions"
                                                item-title="title"
                                                item-value="id"
                                                label="País"
                                                clearable
                                                :rules="[selectRequired]"
                                                @update:model-value="onAddressCountryChange"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-autocomplete
                                                v-model="form.member.address.state_id"
                                                :items="getStateOptions(form.member.address.country_id)"
                                                item-title="name"
                                                item-value="id"
                                                label="Estado del domicilio"
                                                :disabled="!form.member.address.country_id"
                                                clearable
                                                :rules="[selectRequired]"
                                                @update:model-value="onAddressStateChange"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-autocomplete
                                                v-model="form.member.address.city_id"
                                                :items="getCityOptions(form.member.address.state_id)"
                                                item-title="name"
                                                item-value="id"
                                                label="Ciudad del domicilio"
                                                :disabled="!form.member.address.state_id"
                                                clearable
                                            />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-number-input v-model="form.member.address.years_in_city" label="Años radicando en la ciudad" :rules="[required]" />
                                        </v-col>
                                    </v-row>

                                    <div class="mt-4 mb-2 text-subtitle-2 font-weight-bold">Información laboral</div>
                                    <v-row>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.employment.company_name" label="Empresa" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.employment.company_address" label="Dirección de la empresa" />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field v-model="form.member.employment.company_phone" label="Teléfono de la empresa" :rules="[validatePhone]" />
                                        </v-col>
                                    </v-row>
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

                                <v-select
                                    v-if="showReasonSelect"
                                    v-model="form.separation_reason_id"
                                    :items="reasonOptions"
                                    item-title="title"
                                    item-value="value"
                                    label="Motivo de separación"
                                    :error-messages="form.errors.separation_reason_id || form.errors.reason"
                                    clearable
                                    @update:modelValue="onReasonChange"
                                />

                                <div v-if="showReasonDocument" class="mb-4">
                                    <div class="mb-1 font-weight-medium">
                                        {{ selectedSeparationReason?.document_type_name || "Documento requerido" }}
                                        <span class="text-error">*</span>
                                    </div>
                                    <CustomFileUploadField
                                        v-model="reasonDocumentFiles"
                                        :label="selectedSeparationReason?.document_type_name || 'Seleccionar documento'"
                                        :hint="`${selectedReasonExtensions.join(', ').toUpperCase()} · máx. ${selectedReasonMaxSizeMb} MB`"
                                        :accept="reasonDocumentAccept"
                                        :rules="reasonDocumentRules"
                                    />
                                    <div
                                        v-if="form.errors.reason_document"
                                        class="mt-1 text-error text-caption"
                                    >
                                        {{ form.errors.reason_document }}
                                    </div>
                                </div>

                                <v-card
                                    v-if="selectedTargetMembership"
                                    variant="tonal"
                                    class="mb-4 pa-4"
                                >
                                    <div class="mb-2 font-weight-medium">
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

                                    <v-alert
                                        v-if="selectedMember?.has_other_club_membership"
                                        type="info"
                                        variant="tonal"
                                        density="compact"
                                        class="mt-3"
                                        icon="mdi-information-outline"
                                    >
                                        <span v-if="selectedMember?.other_club_name">
                                            {{ selectedMember.full_name }} ya es titular en
                                            <strong>{{ selectedMember.other_club_name }}</strong>,
                                            por lo que la tarifa mostrada considera el paquete interclub.
                                        </span>
                                        <span v-else>
                                            {{ selectedMember.full_name }} ya es titular en otro parque,
                                            por lo que la tarifa mostrada considera el paquete interclub.
                                        </span>
                                    </v-alert>
                                </v-card>

                                <v-textarea
                                    v-if="!showReasonSelect"
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

                                <div class="justify-end d-flex ga-2">
                                    <v-btn
                                        variant="text"
                                        @click="router.visit(route('members.index'))"
                                    >
                                        Cancelar
                                    </v-btn>
                                    <v-btn
                                        color="primary"
                                        type="submit"
                                        :disabled="!canSubmit"
                                        :loading="form.processing"
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
