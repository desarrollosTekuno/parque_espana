<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import CustomFileUploadField from "@/Components/CustomFileUploadField.vue";
import {
    required,
    selectRequired,
    validatePhone,
    email,
    fileTypeRule,
    requiredFileRule,
    fileExactCountRule,
    fileMaxSizeRule,
    minLength,
    maxLength,
    postalCode,
} from "@/constants/validationRules";
import { customToastSwal } from "@/utils/swal";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";

// ─── Interfaces ───────────────────────────────────────────────────────────────

interface CountryCatalog {
    id: number;
    code: string;
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
    code: string;
    name: string;
}

interface RelationshipRef {
    id: number;
    name: string;
}

interface DocumentType {
    id: number;
    name: string;
    allowed_extensions: string;
    min_age: number | null;
    max_age: number | null;
    max_file_size_kb: number | null;
    pivot: {
        is_required: boolean;
        allow_multiple: boolean;
        number_files: number;
    };
    relationships: RelationshipRef[];
}

interface TargetMembershipType {
    id: number;
    name: string;
    code: string;
    document_types: DocumentType[];
}

interface Transition {
    id: number;
    transition_type: "family_to_solidaria" | "solidaria_to_individual";
    monthly_fee: number;
    has_multiple_clubs: boolean;
    from_membership_type: string | null;
    club_code: string | null;
    club_name: string | null;
    target_membership_type: TargetMembershipType;
}

interface ExistingDocument {
    id: number;
    document_type_id: number;
    uploaded_at: string | null;
}

interface PrefillMember {
    id: number;
    first_name: string;
    last_name: string;
    second_last_name: string | null;
    birthdate: string | null;
    birth_place: string | null;
    birth_country_id: number | null;
    birth_state_id: number | null;
    birth_city_id: number | null;
    state: string | null;
    city: string | null;
    nationality_id: number | null;
    marital_status_id: number | null;
    phone: string | null;
    email: string | null;
    occupation: string | null;
    school_name: string | null;
    is_primary_holder: boolean;
    relationship_id: number | null;
    relationship_name: string | null;
    address: {
        street: string | null;
        neighborhood: string | null;
        postal_code: string | null;
        country_id: number | null;
        state_id: number | null;
        city_id: number | null;
        years_in_city: number | null;
    } | null;
    employment: {
        company_name: string | null;
        company_address: string | null;
        company_phone: string | null;
    } | null;
    existing_documents: ExistingDocument[];
}

interface DocumentFormItem {
    document_type_id: number;
    name: string;
    allowed_extensions: string[];
    is_required: boolean;
    allow_multiple: boolean;
    number_files: number;
    max_file_size_kb: number | null;
    min_age: number | null;
    max_age: number | null;
    files: File[];
    document_id: number | null;
    already_uploaded: boolean;
    uploaded_at: string | null;
    update_mode: boolean;
}

interface MemberForm {
    first_name: string;
    last_name: string;
    second_last_name: string | null;
    birthdate: string | null;
    age: number | null;
    birth_place: string | null;
    birth_country_id: number | null;
    birth_state_id: number | null;
    birth_city_id: number | null;
    nationality_id: number | null;
    marital_status_id: number | null;
    phone: string | null;
    email: string | null;
    occupation: string | null;
    school_name: string | null;
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

interface FormData {
    member: MemberForm;
    documents: DocumentFormItem[];
}

interface SiblingTransition {
    id: number;
    club_code: string | null;
    club_name: string | null;
    target_membership_type: string | null;
}

interface Props {
    transition: Transition;
    sibling_transition: SiblingTransition | null;
    prefillMember: PrefillMember;
    countries: CountryCatalog[];
    nationalities: CountryCatalog[];
    maritalStatuses: MaritalStatus[];
}

// ─── Props / page ─────────────────────────────────────────────────────────────

const props = defineProps<Props>();
const page = usePage<any>();

// ─── Stepper ──────────────────────────────────────────────────────────────────

const step = ref(1);
// const steps = ["Datos del integrante", "Documentos", "Confirmación"];
const steps = ["Datos del integrante", "Confirmación"];
const memberStepRef = ref();
const documentsStepRef = ref();

// ─── Location catalogs ────────────────────────────────────────────────────────

const statesByCountry = ref<Record<number, StateCatalog[]>>({});
const citiesByState = ref<Record<number, CityCatalog[]>>({});

const getCountryDisplayName = (country: CountryCatalog | null | undefined) =>
    country?.translations?.["es-MX"]?.trim() ||
    country?.translations?.es?.trim() ||
    country?.name ||
    "";

const countryOptions = computed(() =>
    props.countries.map((c) => ({ id: c.id, title: getCountryDisplayName(c) })),
);

const nationalityOptions = computed(() =>
    props.nationalities.map((c) => ({
        id: c.id,
        title: c.demonym ? c.demonym : getCountryDisplayName(c),
    })),
);

const maritalStatusOptions = computed(() =>
    props.maritalStatuses.map((m) => ({ id: m.id, title: m.name })),
);

const getCountryName = (id: number | null) =>
    id ? getCountryDisplayName(props.countries.find((c) => c.id === id)) : "";

const getStateOptions = (countryId: number | null) =>
    countryId ? (statesByCountry.value[countryId] ?? []) : [];

const getStateName = (countryId: number | null, stateId: number | null) =>
    getStateOptions(countryId).find((s) => s.id === stateId)?.name ?? "";

const getCityOptions = (stateId: number | null) =>
    stateId ? (citiesByState.value[stateId] ?? []) : [];

const getCityName = (stateId: number | null, cityId: number | null) =>
    getCityOptions(stateId).find((c) => c.id === cityId)?.name ?? "";

const fetchStates = async (countryId: number | null) => {
    if (!countryId || statesByCountry.value[countryId]) return;
    const res = await fetch(
        route("members.location-catalogs.states", { country_id: countryId }),
        { headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } },
    );
    if (res.ok) statesByCountry.value[countryId] = await res.json();
};

const fetchCities = async (stateId: number | null) => {
    if (!stateId || citiesByState.value[stateId]) return;
    const res = await fetch(
        route("members.location-catalogs.cities", { state_id: stateId }),
        { headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } },
    );
    if (res.ok) citiesByState.value[stateId] = await res.json();
};

const onBirthCountryChange = async (countryId: number | null) => {
    form.member.birth_country_id = countryId;
    form.member.birth_place = getCountryName(countryId);
    form.member.birth_state_id = null;
    form.member.birth_city_id = null;
    if (countryId) await fetchStates(countryId);
};

const onBirthStateChange = async (stateId: number | null) => {
    form.member.birth_state_id = stateId;
    form.member.birth_city_id = null;
    if (stateId) await fetchCities(stateId);
};

const onBirthCityChange = (cityId: number | null) => {
    form.member.birth_city_id = cityId;
};

const onAddressCountryChange = async (countryId: number | null) => {
    form.member.address.country_id = countryId;
    form.member.address.state_id = null;
    form.member.address.city_id = null;
    if (countryId) await fetchStates(countryId);
};

const onAddressStateChange = async (stateId: number | null) => {
    form.member.address.state_id = stateId;
    form.member.address.city_id = null;
    if (stateId) await fetchCities(stateId);
};

const onAddressCityChange = (cityId: number | null) => {
    form.member.address.city_id = cityId;
};

// ─── Age calculation ──────────────────────────────────────────────────────────

const parseDateInput = (value: string | null): Date | null => {
    if (!value) return null;
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (match) {
        const [, year, month, day] = match;
        const d = new Date(Number(year), Number(month) - 1, Number(day));
        if (
            d.getFullYear() === Number(year) &&
            d.getMonth() === Number(month) - 1 &&
            d.getDate() === Number(day)
        ) return d;
        return null;
    }
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? null : d;
};

const calculateAge = (birthdate: string | null): number | null => {
    if (!birthdate) return null;
    const today = new Date();
    const birth = parseDateInput(birthdate);
    if (!birth) return null;
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
};

const birthdateRule = (value: string | null) => {
    if (!value) return "La fecha de nacimiento es requerida";
    const age = calculateAge(value);
    form.member.age = age;
    if (age === null) return "La fecha de nacimiento no es válida";
    if (age < 0) return "La fecha de nacimiento no es válida";
    return true;
};

const onBirthdateChange = () => {
    form.member.age = calculateAge(form.member.birthdate);
};

// ─── Documents ────────────────────────────────────────────────────────────────

const TITULAR_RELATIONSHIP_NAME = "Titular";

/** Default max file size when the document type has no limit configured (2 MB in KB). */
const DEFAULT_MAX_FILE_SIZE_KB = 2048;

/**
 * Pure helper — filters document types by relationship + age range.
 * Does NOT reference `form` so it can be called before `form` is declared.
 */
const getDocumentTypesForAge = (age: number | null) =>
    props.transition.target_membership_type.document_types.filter((dt) => {
        if (!dt.relationships.some((r) => r.name === TITULAR_RELATIONSHIP_NAME)) return false;
        if (age !== null) {
            if (dt.min_age !== null && age < dt.min_age) return false;
            if (dt.max_age !== null && age > dt.max_age) return false;
        }
        return true;
    });

const buildDocuments = (age: number | null): DocumentFormItem[] =>
    getDocumentTypesForAge(age).map((dt) => {
        const existing =
            props.prefillMember.existing_documents?.find(
                (e) => e.document_type_id === dt.id,
            ) ?? null;
        return {
            document_type_id:  dt.id,
            name:              dt.name,
            allowed_extensions: dt.allowed_extensions
                ? dt.allowed_extensions.split(",").map((e) => e.trim().toLowerCase())
                : [],
            is_required:       dt.pivot.is_required,
            allow_multiple:    dt.pivot.allow_multiple,
            number_files:      dt.pivot.number_files,
            max_file_size_kb:  dt.max_file_size_kb,
            min_age:           dt.min_age,
            max_age:           dt.max_age,
            files:             [],
            document_id:       existing?.id ?? null,
            already_uploaded:  existing !== null,
            uploaded_at:       existing?.uploaded_at ?? null,
            update_mode:       false,
        };
    });

// ─── Form ─────────────────────────────────────────────────────────────────────

const p = props.prefillMember;

const form = useForm<FormData>({
    member: {
        first_name:        p.first_name ?? "",
        last_name:         p.last_name ?? "",
        second_last_name:  p.second_last_name ?? null,
        birthdate:         p.birthdate ?? null,
        age:               calculateAge(p.birthdate ?? null),
        birth_place:       p.birth_place ?? null,
        birth_country_id:  p.birth_country_id ?? null,
        birth_state_id:    p.birth_state_id ?? null,
        birth_city_id:     p.birth_city_id ?? null,
        nationality_id:    p.nationality_id ?? null,
        marital_status_id: p.marital_status_id ?? null,
        phone:             p.phone ?? null,
        email:             p.email ?? null,
        occupation:        p.occupation ?? null,
        school_name:       p.school_name ?? null,
        address: {
            street:        p.address?.street ?? null,
            neighborhood:  p.address?.neighborhood ?? null,
            postal_code:   p.address?.postal_code ?? null,
            country_id:    p.address?.country_id ?? null,
            state_id:      p.address?.state_id ?? null,
            city_id:       p.address?.city_id ?? null,
            years_in_city: p.address?.years_in_city ?? null,
        },
        employment: {
            company_name:    p.employment?.company_name ?? null,
            company_address: p.employment?.company_address ?? null,
            company_phone:   p.employment?.company_phone ?? null,
        },
    },
    documents: buildDocuments(calculateAge(p.birthdate ?? null)),
});

/**
 * Reactive computed — safe here because `form` is already declared above.
 * Used if we ever need to rebuild documents reactively in the template.
 */
const titularDocumentTypes = computed(() => getDocumentTypesForAge(form.member.age));

// Preload location catalogs for prefilled values
onMounted(async () => {
    const countryIds = [
        p.birth_country_id,
        p.address?.country_id,
    ].filter((id): id is number => id !== null);

    await Promise.all([...new Set(countryIds)].map(fetchStates));

    const stateIds = [
        p.birth_state_id,
        p.address?.state_id,
    ].filter((id): id is number => id !== null);

    await Promise.all([...new Set(stateIds)].map(fetchCities));
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const transitionLabel = computed(() =>
    props.transition.transition_type === "family_to_solidaria"
        ? "Sale de cuenta familiar → nueva cuenta solidaria"
        : "Actualización solidaria → individual",
);

const previewingDocId = ref<number | null>(null);

const previewDocument = async (documentId: number) => {
    previewingDocId.value = documentId;
    try {
        const res = await fetch(route("member-documents.url", documentId));
        if (!res.ok) throw new Error("Sin acceso");
        const { url } = await res.json();
        window.open(url, "_blank");
    } catch {
        // silencioso
    } finally {
        previewingDocId.value = null;
    }
};

const fullName = computed(() =>
    [form.member.first_name, form.member.last_name, form.member.second_last_name]
        .filter(Boolean)
        .join(" "),
);

// ─── Stepper navigation ───────────────────────────────────────────────────────

const handleNext = async (next: () => void) => {
    if (step.value === 1) {
        const { valid } = await memberStepRef.value?.validate();
        if (!valid) {
            customToastSwal({
                text: "Revisa los datos del formulario. Hay campos requeridos o con formato incorrecto.",
                icon: "warning",
            });
            return;
        }
    }

    if (step.value === 2) {
        const { valid } = await documentsStepRef.value?.validate();
        if (!valid) {
            customToastSwal({
                text: "Revisa los documentos requeridos. Hay campos con errores o documentos faltantes.",
                icon: "warning",
            });
            return;
        }
    }

    next();
};

const handlePrev = (prev: () => void) => {
    if (step.value === 2) documentsStepRef.value?.resetValidation();
    if (step.value === 1) memberStepRef.value?.resetValidation();
    prev();
};

// ─── Submit ───────────────────────────────────────────────────────────────────

const submit = () => {
    form.transform((data) => ({
        member: {
            first_name:        data.member.first_name,
            last_name:         data.member.last_name,
            second_last_name:  data.member.second_last_name,
            birthdate:         data.member.birthdate,
            birth_place:       data.member.birth_place,
            birth_country_id:  data.member.birth_country_id,
            birth_state_id:    data.member.birth_state_id,
            birth_city_id:     data.member.birth_city_id,
            nationality_id:    data.member.nationality_id,
            marital_status_id: data.member.marital_status_id,
            phone:             data.member.phone,
            email:             data.member.email,
            occupation:        data.member.occupation,
            school_name:       data.member.school_name,
            address:           data.member.address,
            employment:        data.member.employment,
        },
        documents: data.documents.map((doc) => ({
            document_type_id: doc.document_type_id,
            files: doc.files ?? [],
        })),
    }));

    form.post(route("members.age-transitions.promote", props.transition.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash?.success || "Transición promovida correctamente.",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${form.errors.messageError ?? "Ocurrió un error al promover la transición."}`,
                icon: "error",
            });
        },
    });
};
</script>

<template>
    <Head title="Promover Transición por Edad" />

    <AppLayout>
        <template #header>Promover Transición por Edad</template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-alert
                        v-if="props.sibling_transition"
                        type="info"
                        variant="tonal"
                        class="mb-0 ma-4"
                    >
                        Este integrante también tiene una transición pendiente en
                        <strong>{{ props.sibling_transition.club_code }} – {{ props.sibling_transition.club_name }}</strong>
                        (hacia {{ props.sibling_transition.target_membership_type ?? "N/D" }}).
                        Al confirmar este formulario, <strong>se promoverán las dos juntas</strong> y la cuota
                        se calculará como de ambos parques.
                    </v-alert>

                    <v-stepper v-model="step" :items="steps" show-actions>

                        <!-- ══════════════════════════════════════════════════
                             PASO 1 — Datos del integrante
                        ══════════════════════════════════════════════════ -->
                        <template #item.1>
                            <v-form ref="memberStepRef">
                                <v-container class="overflow-auto h-[500px]">

                                    <!-- Banner de transición -->
                                    <v-alert
                                        type="info"
                                        variant="tonal"
                                        class="mb-6"
                                    >
                                        Los datos se precargaron desde la cuenta
                                        actual para agilizar el proceso. Puedes
                                        ajustar la información antes de confirmar.
                                    </v-alert>

                                    <v-card class="pa-4">
                                        <div class="mb-4 d-flex justify-space-between align-center">
                                            <div class="d-flex align-center ga-2">
                                                <h4>Titular</h4>
                                                <v-chip size="small" color="info" variant="tonal">
                                                    Existente
                                                </v-chip>
                                            </div>
                                        </div>

                                        <!-- ── Datos personales ── -->
                                        <v-row>
                                            <v-col cols="12">
                                                <h5 class="mb-2 text-subtitle-1 font-weight-bold">
                                                    Datos personales
                                                </h5>
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.first_name"
                                                    label="Nombre(s)"
                                                    :rules="[required, minLength(2), maxLength(75)]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.last_name"
                                                    label="Apellido paterno"
                                                    :rules="[required, minLength(2), maxLength(50)]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.second_last_name"
                                                    label="Apellido materno"
                                                    :rules="[minLength(2), maxLength(50)]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.birthdate"
                                                    type="date"
                                                    label="Fecha de nacimiento"
                                                    :rules="[required, birthdateRule]"
                                                    @update:model-value="onBirthdateChange"
                                                />
                                            </v-col>

                                            <v-col v-if="form.member.age !== null" cols="12" md="4">
                                                <v-text-field
                                                    :model-value="form.member.age"
                                                    label="Edad"
                                                    readonly
                                                />
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
                                                    clearable
                                                    :disabled="!form.member.birth_country_id"
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
                                                    clearable
                                                    :disabled="!form.member.birth_state_id"
                                                    @update:model-value="onBirthCityChange"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-autocomplete
                                                    v-model="form.member.nationality_id"
                                                    :items="nationalityOptions"
                                                    item-title="title"
                                                    item-value="id"
                                                    label="Nacionalidad"
                                                    clearable
                                                    auto-select-first
                                                    :rules="[selectRequired]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-autocomplete
                                                    v-model="form.member.marital_status_id"
                                                    :items="maritalStatusOptions"
                                                    item-title="title"
                                                    item-value="id"
                                                    label="Estado civil"
                                                    clearable
                                                    auto-select-first
                                                    :rules="[selectRequired]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.phone"
                                                    label="Teléfono"
                                                    :rules="[required, validatePhone]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.email"
                                                    label="Correo"
                                                    :rules="[required, email, maxLength(255)]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.occupation"
                                                    label="Ocupación"
                                                    :rules="[required, minLength(2), maxLength(100)]"
                                                />
                                            </v-col>
                                        </v-row>

                                        <!-- ── Domicilio ── -->
                                        <v-row class="mt-2">
                                            <v-col cols="12">
                                                <h5 class="mb-2 text-subtitle-1 font-weight-bold">
                                                    Domicilio
                                                </h5>
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.address.street"
                                                    label="Calle"
                                                    :rules="[required, minLength(3), maxLength(150)]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.address.neighborhood"
                                                    label="Colonia"
                                                    :rules="[required, minLength(3), maxLength(150)]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.address.postal_code"
                                                    label="Código postal"
                                                    :rules="[postalCode]"
                                                />
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
                                                    clearable
                                                    :disabled="!form.member.address.country_id"
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
                                                    clearable
                                                    :disabled="!form.member.address.state_id"
                                                    @update:model-value="onAddressCityChange"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-number-input
                                                    v-model="form.member.address.years_in_city"
                                                    label="Años radicando en la ciudad"
                                                    :rules="[required]"
                                                />
                                            </v-col>
                                        </v-row>

                                        <!-- ── Información laboral ── -->
                                        <v-row class="mt-2">
                                            <v-col cols="12">
                                                <h5 class="mb-2 text-subtitle-1 font-weight-bold">
                                                    Información laboral
                                                </h5>
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.employment.company_name"
                                                    label="Empresa"
                                                    :rules="[minLength(2), maxLength(150)]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.employment.company_address"
                                                    label="Dirección de la empresa"
                                                    :rules="[minLength(5), maxLength(255)]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="form.member.employment.company_phone"
                                                    label="Teléfono de la empresa"
                                                    :rules="[validatePhone]"
                                                />
                                            </v-col>
                                        </v-row>
                                    </v-card>

                                </v-container>
                            </v-form>
                        </template>

                        <!-- ══════════════════════════════════════════════════ PASO 2 — Documentos ══════════════════════════════════════════════════ -->
                        <!-- <template #item.2>
                            <v-form ref="documentsStepRef">
                                <v-container class="h-[500px] overflow-auto">

                                    <div class="mb-4">
                                        <h3 class="mb-1 text-h6">Documentos</h3>
                                        <p class="mb-0 text-body-2 text-medium-emphasis">
                                            Carga los documentos requeridos para el
                                            nuevo tipo de membresía.
                                        </p>
                                    </div>

                                    <v-alert type="info" variant="tonal" class="mb-4">
                                        Estos documentos corresponden al nuevo tipo de
                                        membresía que quedará vigente en esta cuenta.
                                    </v-alert>

                                    <v-card class="pa-4">
                                        <h4 class="mb-4 text-subtitle-1 font-weight-bold">
                                            Titular
                                            <span
                                                v-if="form.member.first_name || form.member.last_name"
                                                class="font-weight-regular"
                                            >
                                                — {{ fullName }}
                                            </span>
                                        </h4>

                                        <div
                                            v-if="form.documents.length === 0"
                                            class="text-body-2 text-medium-emphasis"
                                        >
                                            No se requieren documentos para este tipo de membresía.
                                        </div>

                                        <v-row>
                                            <v-col
                                                v-for="doc in form.documents"
                                                :key="doc.document_type_id"
                                                cols="12"
                                                md="6"
                                            >
                                                <div class="flex-wrap mb-2 d-flex align-center justify-space-between ga-1">
                                                    <span class="font-weight-medium">
                                                        {{ doc.name }}
                                                        <span
                                                            v-if="doc.is_required && !doc.already_uploaded"
                                                            class="text-error"
                                                        >
                                                            *
                                                        </span>
                                                    </span>
                                                    <div class="d-flex align-center ga-1">
                                                        <v-chip
                                                            v-if="doc.min_age !== null || doc.max_age !== null"
                                                            size="x-small"
                                                            color="info"
                                                            variant="tonal"
                                                        >
                                                            <template v-if="doc.min_age !== null && doc.max_age !== null">
                                                                {{ doc.min_age }}–{{ doc.max_age }} años
                                                            </template>
                                                            <template v-else-if="doc.min_age !== null">
                                                                ≥ {{ doc.min_age }} años
                                                            </template>
                                                            <template v-else>
                                                                ≤ {{ doc.max_age }} años
                                                            </template>
                                                        </v-chip>
                                                        <v-chip
                                                            v-if="doc.already_uploaded && !doc.update_mode"
                                                            size="x-small"
                                                            color="success"
                                                            variant="tonal"
                                                            prepend-icon="mdi-check-circle"
                                                        >
                                                            Ya cargado{{ doc.uploaded_at ? ` · ${doc.uploaded_at}` : "" }}
                                                        </v-chip>
                                                    </div>
                                                </div>

                                                <div v-if="doc.already_uploaded && !doc.update_mode" class="d-flex align-center ga-2">
                                                    <v-btn
                                                        v-if="doc.document_id !== null"
                                                        size="small"
                                                        variant="tonal"
                                                        color="info"
                                                        :icon="previewingDocId === doc.document_id ? 'mdi-loading' : 'mdi-eye'"
                                                        :loading="previewingDocId === doc.document_id"
                                                        @click="previewDocument(doc.document_id!)"
                                                    />
                                                    <v-btn
                                                        size="small"
                                                        variant="tonal"
                                                        color="warning"
                                                        prepend-icon="mdi-refresh"
                                                        @click="doc.update_mode = true"
                                                    >
                                                        Actualizar
                                                    </v-btn>
                                                </div>

                                                <template v-else>
                                                    <v-btn
                                                        v-if="doc.already_uploaded && doc.update_mode"
                                                        size="x-small"
                                                        variant="text"
                                                        color="default"
                                                        class="mb-1"
                                                        prepend-icon="mdi-close"
                                                        @click="doc.update_mode = false; doc.files = []"
                                                    >
                                                        Cancelar actualización
                                                    </v-btn>

                                                    <CustomFileUploadField
                                                        v-model="doc.files"
                                                        :label="`${doc.allow_multiple ? doc.number_files + ' x ' : ''}${doc.name}`"
                                                        :hint="doc.allowed_extensions.join(', ')"
                                                        :accept="doc.allowed_extensions.map((ext) => `.${ext}`).join(',')"
                                                        :multiple="doc.allow_multiple"
                                                        :rules="[
                                                            ...(doc.is_required && !doc.already_uploaded
                                                                ? [requiredFileRule, fileExactCountRule(doc.number_files)]
                                                                : []),
                                                            fileTypeRule(doc.allowed_extensions),
                                                            fileMaxSizeRule((doc.max_file_size_kb ?? DEFAULT_MAX_FILE_SIZE_KB) / 1024),
                                                        ]"
                                                        clearable
                                                    />
                                                </template>
                                            </v-col>
                                        </v-row>
                                    </v-card>

                                </v-container>
                            </v-form>
                        </template> -->

                        <!-- ══════════════════════════════════════════════════ PASO 2 — Confirmación ══════════════════════════════════════════════════ -->
                        <template #item.2>
                            <v-container class="h-[500px] overflow-auto">
                                <h3 class="mb-4 text-title-large">Confirmación</h3>

                                <!-- Resumen de transición -->
                                <v-card class="mb-4 pa-4">
                                    <p>
                                        <strong>Tipo de transición:</strong>
                                        {{ transitionLabel }}
                                    </p>
                                    <p>
                                        <strong>Membresía de origen:</strong>
                                        {{ transition.from_membership_type || "—" }}
                                    </p>
                                    <p>
                                        <strong>Membresía destino:</strong>
                                        {{ transition.target_membership_type.name }}
                                    </p>
                                    <p>
                                        <strong>Club:</strong>
                                        {{ transition.club_code }} – {{ transition.club_name }}
                                    </p>
                                    <p>
                                        <strong>Cuota mensual:</strong>
                                        {{ currencyFormatter.format(transition.monthly_fee) }}
                                        <v-chip
                                            v-if="transition.has_multiple_clubs"
                                            size="x-small"
                                            color="info"
                                            class="ml-1"
                                        >
                                            Ambos parques
                                        </v-chip>
                                    </p>
                                </v-card>

                                <!-- Resumen de integrante -->
                                <v-card class="mb-4 pa-4">
                                    <h4 class="mb-2 font-weight-bold">Titular</h4>
                                    <p>{{ fullName }}</p>
                                    <p v-if="form.member.birthdate">
                                        <strong>Fecha de nacimiento:</strong>
                                        {{ form.member.birthdate }}
                                    </p>
                                    <p v-if="form.member.phone">
                                        <strong>Teléfono:</strong>
                                        {{ form.member.phone }}
                                    </p>
                                    <p v-if="form.member.email">
                                        <strong>Correo:</strong>
                                        {{ form.member.email }}
                                    </p>
                                    <p v-if="form.member.occupation">
                                        <strong>Ocupación:</strong>
                                        {{ form.member.occupation }}
                                    </p>
                                </v-card>

                                <v-alert type="info" variant="tonal">
                                    Al confirmar, se activará la nueva cuenta con el
                                    tipo de membresía destino y se generarán los cargos
                                    correspondientes. Esta acción no se puede deshacer.
                                </v-alert>
                            </v-container>
                        </template>

                        <!-- ══ Botones de navegación ══ -->
                        <template #actions="{ next, prev }">
                            <div class="d-flex w-100">
                                <v-btn
                                    variant="text"
                                    :disabled="step === 1"
                                    @click="handlePrev(prev)"
                                >
                                    Anterior
                                </v-btn>

                                <v-spacer />

                                <v-btn
                                    v-if="step < steps.length"
                                    color="primary"
                                    @click="handleNext(next)"
                                >
                                    Siguiente
                                </v-btn>

                                <v-btn
                                    v-else
                                    color="success"
                                    :loading="form.processing"
                                    @click="submit"
                                >
                                    Confirmar y promover
                                </v-btn>
                            </div>
                        </template>

                    </v-stepper>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>
