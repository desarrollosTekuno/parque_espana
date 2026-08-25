<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
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
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

const page = usePage<any>();

// ─── Interfaces ───────────────────────────────────────────────────────────────

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

interface Relationship {
    id: number;
    name: string;
}

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
    country_id: number;
    state_id: number;
    name: string;
}

interface Nationality extends CountryCatalog {}

interface MaritalStatus {
    id: number;
    code: string;
    name: string;
}

interface AccountMemberItem {
    member_id: number;
    full_name: string;
    relationship_name: string | null;
    is_primary_holder: boolean;
}

interface MembershipAccount {
    membership_number: string | null;
    account_club_name?: string | null;
    account_club_code?: string | null;
    members: AccountMemberItem[];
}

interface AvailableGroupMember {
    member_id: number;
    full_name: string;
    birthdate: string | null;
    age: number | null;
    relationship_name: string | null;
    club_name: string | null;
    club_code: string | null;
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
    relationships: { id: number; name: string }[];
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

interface Props {
    membership: SourceMembership;
    account: MembershipAccount;
    relationships?: Relationship[];
    countries?: CountryCatalog[];
    nationalities?: Nationality[];
    maritalStatuses?: MaritalStatus[];
    availableGroupMembers?: AvailableGroupMember[];
    membershipDocumentTypes?: DocumentType[];
}

// ─── Props ────────────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<Props>(), {
    relationships: () => [],
    countries: () => [],
    nationalities: () => [],
    maritalStatuses: () => [],
    availableGroupMembers: () => [],
    membershipDocumentTypes: () => [],
});

// ─── Stepper (only for new mode) ──────────────────────────────────────────────

const step = ref(1);
// const steps = ["Datos del familiar", "Documentos", "Confirmación"];
const steps = ["Datos del familiar",  "Confirmación"];
const memberStepRef = ref();
const documentsStepRef = ref();

// ─── Mode ─────────────────────────────────────────────────────────────────────

const mode = ref<"new" | "existing">(
    props.availableGroupMembers.length > 0 ? "existing" : "new",
);

// Reset stepper when switching to new mode
watch(mode, (val) => {
    if (val === "new") step.value = 1;
});

// ─── Existing-member state ────────────────────────────────────────────────────

const existingFormRef = ref();
const selectedGroupMemberId = ref<number | null>(null);
const existingRelationshipId = ref<number | null>(null);

// ─── Form (new member) ───────────────────────────────────────────────────────

const form = useForm({
    first_name: "",
    last_name: "",
    second_last_name: "",
    birthdate: null as string | null,
    birth_country_id: null as number | null,
    birth_state_id: null as number | null,
    birth_city_id: null as number | null,
    birth_place: "",
    city: "",
    state: "",
    nationality_id: null as number | null,
    marital_status_id: null as number | null,
    phone: "",
    email: "",
    occupation: "",
    school_name: "",
    relationship_id: null as number | null,
    address: {
        country_id: null as number | null,
        state_id: null as number | null,
        city_id: null as number | null,
        street: "",
        neighborhood: "",
        postal_code: "",
        years_in_city: null as number | null,
    },
    employment: {
        company_name: "",
        company_address: "",
        company_phone: "",
    },
    documents: [] as DocumentFormItem[],
});

// ─── Location catalog helpers ─────────────────────────────────────────────────

const statesByCountry = ref<Record<number, StateCatalog[]>>({});
const citiesByState = ref<Record<number, CityCatalog[]>>({});

const normalizeText = (value: string | null | undefined) =>
    (value ?? "")
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .toLowerCase()
        .trim();

const getCountryDisplayName = (country: CountryCatalog | null | undefined) =>
    country?.translations?.["es-MX"]?.trim() ||
    country?.translations?.es?.trim() ||
    country?.name ||
    "";

const defaultCountry = computed(
    () =>
        props.countries.find(
            (c) =>
                c.code === "MX" ||
                normalizeText(getCountryDisplayName(c)) === "mexico" ||
                normalizeText(c.name) === "mexico",
        ) ?? null,
);

if (defaultCountry.value) {
    form.address.country_id = defaultCountry.value.id;
}

const countryOptions = computed(() =>
    props.countries.map((c) => ({
        value: c.id,
        title: getCountryDisplayName(c),
    })),
);

const nationalityOptions = computed(() =>
    props.nationalities.map((c) => ({
        value: c.id,
        title: c.demonym || c.name,
    })),
);

const maritalStatusOptions = computed(() =>
    props.maritalStatuses.map((m) => ({ value: m.id, title: m.name })),
);

const getCountryName = (countryId: number | null) =>
    getCountryDisplayName(
        props.countries.find((c) => c.id === countryId),
    );

const getStateOptions = (countryId: number | null) =>
    countryId ? statesByCountry.value[countryId] ?? [] : [];

const getStateName = (countryId: number | null, stateId: number | null) =>
    getStateOptions(countryId).find((s) => s.id === stateId)?.name ?? "";

const getCityOptions = (stateId: number | null) =>
    stateId ? citiesByState.value[stateId] ?? [] : [];

const getCityName = (stateId: number | null, cityId: number | null) =>
    getCityOptions(stateId).find((c) => c.id === cityId)?.name ?? "";

const fetchStates = async (countryId: number | null) => {
    if (!countryId) return [];
    if (statesByCountry.value[countryId]) return statesByCountry.value[countryId];
    const response = await fetch(
        route("members.location-catalogs.states", { country_id: countryId }),
        { method: "GET", headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } },
    );
    if (!response.ok) throw new Error("No se pudieron cargar los estados.");
    const payload = (await response.json()) as StateCatalog[];
    statesByCountry.value[countryId] = payload;
    return payload;
};

const fetchCities = async (stateId: number | null) => {
    if (!stateId) return [];
    if (citiesByState.value[stateId]) return citiesByState.value[stateId];
    const response = await fetch(
        route("members.location-catalogs.cities", { state_id: stateId }),
        { method: "GET", headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } },
    );
    if (!response.ok) throw new Error("No se pudieron cargar las ciudades.");
    const payload = (await response.json()) as CityCatalog[];
    citiesByState.value[stateId] = payload;
    return payload;
};

const onBirthCountryChange = async (countryId: number | null) => {
    form.birth_country_id = countryId;
    form.birth_place = getCountryName(countryId);
    form.birth_state_id = null;
    form.state = "";
    form.birth_city_id = null;
    form.city = "";
    if (countryId) await fetchStates(countryId);
};

const onBirthStateChange = async (stateId: number | null) => {
    form.birth_state_id = stateId;
    form.state = getStateName(form.birth_country_id, stateId);
    form.birth_city_id = null;
    form.city = "";
    if (stateId) await fetchCities(stateId);
};

const onBirthCityChange = (cityId: number | null) => {
    form.birth_city_id = cityId;
    form.city = getCityName(form.birth_state_id, cityId);
};

const onAddressCountryChange = async (countryId: number | null) => {
    form.address.country_id = countryId;
    form.address.state_id = null;
    form.address.city_id = null;
    if (countryId) await fetchStates(countryId);
};

const onAddressStateChange = async (stateId: number | null) => {
    form.address.state_id = stateId;
    form.address.city_id = null;
    if (stateId) await fetchCities(stateId);
};

const onAddressCityChange = (cityId: number | null) => {
    form.address.city_id = cityId;
};

if (form.address.country_id) {
    void fetchStates(form.address.country_id);
}

// ─── Relationship helpers ─────────────────────────────────────────────────────

const accountHasSpouse = computed(() =>
    props.account.members.some((m) =>
        ["conyuge", "esposo", "esposa"].includes(normalizeText(m.relationship_name)),
    ),
);

const relationshipOptions = computed(() =>
    props.relationships
        .filter((r) => {
            if (!accountHasSpouse.value) return true;
            return !["conyuge", "esposo", "esposa"].includes(normalizeText(r.name));
        })
        .map((r) => ({ value: r.id, title: r.name })),
);

const relationshipName = computed(
    () => props.relationships.find((r) => r.id === form.relationship_id)?.name ?? "",
);

const isChildRelationship = computed(() =>
    ["hijo(a)", "hijo", "hija"].includes(normalizeText(relationshipName.value)),
);

const isSpouseRelationship = computed(() =>
    ["conyuge", "esposo", "esposa"].includes(normalizeText(relationshipName.value)),
);

// ─── Age calculation ──────────────────────────────────────────────────────────

const parseDateInput = (value: string | null): Date | null => {
    if (!value) return null;
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) return null;
    const [, year, month, day] = match;
    const parsedDate = new Date(Number(year), Number(month) - 1, Number(day));
    if (
        parsedDate.getFullYear() !== Number(year) ||
        parsedDate.getMonth() !== Number(month) - 1 ||
        parsedDate.getDate() !== Number(day)
    ) return null;
    return parsedDate;
};

const calculateAge = (birthdate: string | null): number | null => {
    const birth = parseDateInput(birthdate);
    if (!birth) return null;
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) age--;
    return age;
};

const currentAge = computed(() => calculateAge(form.birthdate));

const birthdateRule = (value: string | null) => {
    if (!value) return "La fecha de nacimiento es requerida";
    const age = calculateAge(value);
    if (age === null || age < 0) return "La fecha de nacimiento no es válida";
    if (isChildRelationship.value && age >= 24) return "Los hijos no pueden ser mayores de 24 años";
    return true;
};

// ─── Documents ────────────────────────────────────────────────────────────────

/**
 * Document types that apply to the selected relationship AND the member's age.
 *
 * Filtering rules (both must pass):
 *  1. Relationship match — the document type must list the selected relationship
 *     (if the relationships array is empty it applies to all).
 *  2. Age range — if min_age / max_age is set on the pivot, the member's age
 *     must fall within that range. When the birthdate has not been entered yet
 *     (age === null) we include the document so the user sees it; it will be
 *     hidden/skipped once a valid birthdate is provided and the age is outside range.
 */
/** Default max file size when document type has no limit configured (5 MB in KB). */
const DEFAULT_MAX_FILE_SIZE_KB = 2048;

/**
 * Document types that apply to the selected relationship AND the member's age.
 *
 *  1. Relationship match — the doc type must list the selected relationship.
 *     Empty relationships array = applies to all.
 *  2. Age range — when age is known and falls outside [min_age, max_age] the
 *     document is excluded. Unknown age (birthdate not entered yet) → include.
 */
const applicableDocumentTypes = computed(() => {
    if (!form.relationship_id) return [];
    const age = currentAge.value;

    return props.membershipDocumentTypes.filter((dt) => {
        if (
            dt.relationships.length > 0 &&
            !dt.relationships.some((r) => r.id === form.relationship_id)
        ) return false;

        if (age !== null) {
            if (dt.min_age !== null && age < dt.min_age) return false;
            if (dt.max_age !== null && age > dt.max_age) return false;
        }

        return true;
    });
});

const buildDocuments = (): DocumentFormItem[] =>
    applicableDocumentTypes.value.map((dt) => ({
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
        document_id:       null,
        already_uploaded:  false,
        uploaded_at:       null,
        update_mode:       false,
    }));

// Rebuild the document list whenever the relationship or birthdate changes
watch(
    [() => form.relationship_id, currentAge],
    () => {
        form.documents = buildDocuments();
    },
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

// ─── Full name for confirmation step ─────────────────────────────────────────

const fullName = computed(() =>
    [form.first_name, form.last_name, form.second_last_name]
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
                text: "Revisa los documentos requeridos.",
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

const submitExisting = async () => {
    const { valid } = await existingFormRef.value?.validate();
    if (!valid) return;

    const existingForm = useForm({
        existing_member_id: selectedGroupMemberId.value,
        relationship_id: existingRelationshipId.value,
    });

    existingForm.post(route("members.family-members.store", props.membership.id), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "Familiar agregado correctamente.",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${existingForm.errors.messageError || "No se pudo agregar el familiar"}`,
                text: `${existingForm.errors.exception || ""}`,
                icon: "error",
            });
        },
    });
};

const submit = async () => {
    if (mode.value === "existing") {
        await submitExisting();
        return;
    }

    form.transform((data) => ({
        first_name: data.first_name,
        last_name: data.last_name,
        second_last_name: data.second_last_name,
        birthdate: data.birthdate,
        birth_place: data.birth_place,
        birth_country_id: data.birth_country_id,
        birth_state_id: data.birth_state_id,
        birth_city_id: data.birth_city_id,
        city: data.city,
        state: data.state,
        nationality_id: data.nationality_id,
        marital_status_id: data.marital_status_id,
        phone: data.phone,
        email: data.email,
        occupation: data.occupation,
        school_name: data.school_name,
        relationship_id: data.relationship_id,
        address: data.address,
        employment: data.employment,
        documents: data.documents.map((doc) => ({
            document_type_id: doc.document_type_id,
            files: doc.files ?? [],
        })),
    })).post(route("members.family-members.store", props.membership.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash?.success || "Familiar agregado correctamente.",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${form.errors.messageError || "No se pudo agregar el familiar"}`,
                text: `${form.errors.exception || ""}`,
                icon: "error",
            });
        },
    });
};
</script>

<template>
    <Head title="Agregar Familiar" />

    <AppLayout>
        <template #header>Agregar Familiar</template>
        <template #options>
            <BaseButton
                :icon-only="false"
                action="cancel"
                text="Volver"
                @click="router.visit(route('members.manage.show', props.membership.id))"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12" md="10" class="mx-auto">
                    <v-container class="py-6">

                        <!-- Resumen de la cuenta familiar -->
                        <v-card class="mb-4 pa-4" variant="tonal">
                            <div class="mb-2 text-subtitle-1 font-weight-bold">
                                Cuenta familiar
                            </div>
                            <p>
                                <strong>No. cuenta:</strong>
                                {{ props.account.membership_number || "-" }}
                            </p>
                            <p>
                                <strong>Club de la cuenta:</strong>
                                {{ props.account.account_club_code || "-" }} ·
                                {{ props.account.account_club_name || "Sin club" }}
                            </p>
                            <p>
                                <strong>Titular:</strong>
                                {{ props.membership.holder_name }}
                            </p>
                            <p>
                                <strong>Membresía:</strong>
                                {{ props.membership.membership_type_name }}
                            </p>
                        </v-card>

                        <v-alert type="info" variant="tonal" class="mb-4">
                            Este flujo agrega un integrante a la misma cuenta familiar.
                        </v-alert>

                        <!-- Toggle de modo (solo si hay miembros disponibles en el grupo) -->
                        <v-btn-toggle
                            v-if="props.availableGroupMembers.length > 0"
                            v-model="mode"
                            mandatory
                            variant="outlined"
                            class="mb-6 w-100"
                        >
                            <v-btn value="existing" class="flex-1-1">
                                <v-icon start>mdi-account-group</v-icon>
                                Del grupo familiar
                            </v-btn>
                            <v-btn value="new" class="flex-1-1">
                                <v-icon start>mdi-account-plus</v-icon>
                                Nuevo integrante
                            </v-btn>
                        </v-btn-toggle>

                        <!-- ═══════════════════════════════════════════════
                             MODO: Vincular existente del grupo
                        ═══════════════════════════════════════════════ -->
                        <template v-if="mode === 'existing'">
                            <v-form ref="existingFormRef">
                                <v-card class="pa-4">
                                    <div class="mb-4 text-subtitle-1 font-weight-bold">
                                        Integrantes disponibles del grupo
                                    </div>

                                    <p class="mb-4 text-body-2 text-medium-emphasis">
                                        Estos integrantes ya existen en la otra cuenta del titular
                                        y pueden agregarse a esta cuenta sin crear un registro nuevo.
                                    </p>

                                    <v-row class="mb-4">
                                        <v-col
                                            v-for="member in props.availableGroupMembers"
                                            :key="member.member_id"
                                            cols="12"
                                            md="6"
                                        >
                                            <v-card
                                                :variant="selectedGroupMemberId === member.member_id ? 'tonal' : 'outlined'"
                                                :color="selectedGroupMemberId === member.member_id ? 'primary' : undefined"
                                                class="cursor-pointer pa-4"
                                                style="cursor: pointer;"
                                                @click="selectedGroupMemberId = member.member_id"
                                            >
                                                <div class="d-flex align-center justify-space-between">
                                                    <div>
                                                        <div class="font-weight-medium">
                                                            {{ member.full_name }}
                                                        </div>
                                                        <div class="text-caption text-medium-emphasis">
                                                            {{ member.relationship_name || "Sin parentesco" }}
                                                            · {{ member.club_code }} - {{ member.club_name }}
                                                        </div>
                                                        <div class="text-caption text-medium-emphasis">
                                                            Edad: {{ member.age ?? "-" }}
                                                        </div>
                                                    </div>
                                                    <v-icon
                                                        v-if="selectedGroupMemberId === member.member_id"
                                                        color="primary"
                                                    >
                                                        mdi-check-circle
                                                    </v-icon>
                                                </div>
                                            </v-card>
                                        </v-col>
                                    </v-row>

                                    <!-- Validación oculta: requiere selección -->
                                    <v-input
                                        :model-value="selectedGroupMemberId"
                                        :rules="[(v) => !!v || 'Debes seleccionar un integrante']"
                                        class="mb-2"
                                        style="display: none;"
                                    />

                                    <v-divider class="my-4" />

                                    <div class="mb-3 text-subtitle-2 font-weight-bold">
                                        Parentesco en esta cuenta
                                    </div>

                                    <v-row>
                                        <v-col cols="12" md="6">
                                            <v-autocomplete
                                                v-model="existingRelationshipId"
                                                :items="relationshipOptions"
                                                item-title="title"
                                                item-value="value"
                                                label="Parentesco"
                                                :rules="[selectRequired]"
                                                clearable
                                            />
                                        </v-col>
                                    </v-row>

                                    <div class="justify-end mt-4 d-flex ga-2">
                                        <v-btn
                                            variant="text"
                                            @click="router.visit(route('members.manage.show', props.membership.id))"
                                        >
                                            Cancelar
                                        </v-btn>
                                        <v-btn
                                            color="primary"
                                            :disabled="!selectedGroupMemberId || !existingRelationshipId"
                                            @click="submit"
                                        >
                                            Agregar a esta cuenta
                                        </v-btn>
                                    </div>
                                </v-card>
                            </v-form>
                        </template>

                        <!-- ═══════════════════════════════════════════════
                             MODO: Crear nuevo integrante (stepper)
                        ═══════════════════════════════════════════════ -->
                        <template v-if="mode === 'new'">
                            <v-stepper v-model="step" :items="steps" show-actions>

                                <!-- ══════════════════════════════ PASO 1 — Datos del familiar ══════════════════════════════ -->
                                <template #item.1>
                                    <v-form ref="memberStepRef">
                                        <v-container class="overflow-auto h-[500px]">
                                            <v-card class="pa-4">
                                                <div class="mb-4 text-subtitle-1 font-weight-bold">
                                                    Datos del nuevo familiar
                                                </div>

                                                <v-row>
                                                    <!-- Parentesco (aquí arriba para que determine qué docs aparecen) -->
                                                    <v-col cols="12" md="6">
                                                        <v-autocomplete
                                                            v-model="form.relationship_id"
                                                            :items="relationshipOptions"
                                                            item-title="title"
                                                            item-value="value"
                                                            label="Parentesco"
                                                            :rules="[selectRequired]"
                                                            :error-messages="form.errors.relationship_id"
                                                            clearable
                                                        />
                                                    </v-col>

                                                    <v-col cols="12" md="6">
                                                        <v-text-field
                                                            v-model="form.birthdate"
                                                            label="Fecha de nacimiento"
                                                            type="date"
                                                            :rules="[required, birthdateRule]"
                                                            :error-messages="form.errors.birthdate"
                                                        />
                                                    </v-col>

                                                    <v-col cols="12" md="4">
                                                        <v-text-field
                                                            v-model="form.first_name"
                                                            label="Nombre(s)"
                                                            :rules="[required, minLength(2), maxLength(75)]"
                                                            :error-messages="form.errors.first_name"
                                                        />
                                                    </v-col>

                                                    <v-col cols="12" md="4">
                                                        <v-text-field
                                                            v-model="form.last_name"
                                                            label="Apellido paterno"
                                                            :rules="[required, minLength(2), maxLength(50)]"
                                                            :error-messages="form.errors.last_name"
                                                        />
                                                    </v-col>

                                                    <v-col cols="12" md="4">
                                                        <v-text-field
                                                            v-model="form.second_last_name"
                                                            label="Apellido materno"
                                                            :rules="[minLength(2), maxLength(50)]"
                                                            :error-messages="form.errors.second_last_name"
                                                        />
                                                    </v-col>

                                                    <v-col cols="12" md="4">
                                                        <v-text-field
                                                            :model-value="currentAge ?? ''"
                                                            label="Edad"
                                                            readonly
                                                        />
                                                    </v-col>

                                                    <v-col cols="12" md="4">
                                                        <v-text-field
                                                            v-model="form.email"
                                                            label="Correo"
                                                            :rules="[email, maxLength(255)]"
                                                            :error-messages="form.errors.email"
                                                        />
                                                    </v-col>

                                                    <!-- Hijo: colegio -->
                                                    <v-col v-if="isChildRelationship" cols="12" md="4">
                                                        <v-text-field
                                                            v-model="form.school_name"
                                                            label="Colegio"
                                                            :rules="[required, minLength(3), maxLength(150)]"
                                                            :error-messages="form.errors.school_name"
                                                        />
                                                    </v-col>
                                                </v-row>

                                                <!-- Sección adicional para cónyuge -->
                                                <template v-if="isSpouseRelationship">
                                                    <v-row class="mt-2">
                                                        <v-col cols="12">
                                                            <h5 class="mb-2 text-subtitle-2 font-weight-bold">
                                                                Datos adicionales (cónyuge)
                                                            </h5>
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-autocomplete
                                                                v-model="form.birth_country_id"
                                                                :items="countryOptions"
                                                                item-title="title"
                                                                item-value="value"
                                                                label="País de nacimiento"
                                                                :rules="[selectRequired]"
                                                                :error-messages="form.errors.birth_country_id"
                                                                clearable
                                                                @update:modelValue="onBirthCountryChange"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-autocomplete
                                                                v-model="form.birth_state_id"
                                                                :items="getStateOptions(form.birth_country_id)"
                                                                item-title="name"
                                                                item-value="id"
                                                                label="Estado de nacimiento"
                                                                :rules="[selectRequired]"
                                                                :error-messages="form.errors.birth_state_id"
                                                                :disabled="!form.birth_country_id"
                                                                clearable
                                                                @update:modelValue="onBirthStateChange"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-autocomplete
                                                                v-model="form.birth_city_id"
                                                                :items="getCityOptions(form.birth_state_id)"
                                                                item-title="name"
                                                                item-value="id"
                                                                label="Ciudad de nacimiento"
                                                                :error-messages="form.errors.birth_city_id"
                                                                :disabled="!form.birth_state_id"
                                                                clearable
                                                                @update:modelValue="onBirthCityChange"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-autocomplete
                                                                v-model="form.nationality_id"
                                                                :items="nationalityOptions"
                                                                item-title="title"
                                                                item-value="value"
                                                                label="Nacionalidad"
                                                                :rules="[selectRequired]"
                                                                :error-messages="form.errors.nationality_id"
                                                                clearable
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-autocomplete
                                                                v-model="form.marital_status_id"
                                                                :items="maritalStatusOptions"
                                                                item-title="title"
                                                                item-value="value"
                                                                label="Estado civil"
                                                                :rules="[selectRequired]"
                                                                :error-messages="form.errors.marital_status_id"
                                                                clearable
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-text-field
                                                                v-model="form.phone"
                                                                label="Teléfono"
                                                                :rules="[validatePhone]"
                                                                :error-messages="form.errors.phone"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-text-field
                                                                v-model="form.occupation"
                                                                label="Ocupación"
                                                                :rules="[minLength(2), maxLength(100)]"
                                                                :error-messages="form.errors.occupation"
                                                            />
                                                        </v-col>
                                                    </v-row>

                                                    <!-- Domicilio (cónyuge) -->
                                                    <v-row class="mt-2">
                                                        <v-col cols="12">
                                                            <h5 class="mb-2 text-subtitle-2 font-weight-bold">
                                                                Domicilio
                                                            </h5>
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-text-field
                                                                v-model="form.address.street"
                                                                label="Calle"
                                                                :rules="[minLength(3), maxLength(150)]"
                                                                :error-messages="form.errors['address.street']"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-text-field
                                                                v-model="form.address.neighborhood"
                                                                label="Colonia"
                                                                :rules="[minLength(3), maxLength(150)]"
                                                                :error-messages="form.errors['address.neighborhood']"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-text-field
                                                                v-model="form.address.postal_code"
                                                                label="Código postal"
                                                                :rules="[postalCode]"
                                                                :error-messages="form.errors['address.postal_code']"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-autocomplete
                                                                v-model="form.address.country_id"
                                                                :items="countryOptions"
                                                                item-title="title"
                                                                item-value="value"
                                                                label="País"
                                                                clearable
                                                                @update:modelValue="onAddressCountryChange"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-autocomplete
                                                                v-model="form.address.state_id"
                                                                :items="getStateOptions(form.address.country_id)"
                                                                item-title="name"
                                                                item-value="id"
                                                                label="Estado"
                                                                clearable
                                                                :disabled="!form.address.country_id"
                                                                @update:modelValue="onAddressStateChange"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-autocomplete
                                                                v-model="form.address.city_id"
                                                                :items="getCityOptions(form.address.state_id)"
                                                                item-title="name"
                                                                item-value="id"
                                                                label="Ciudad"
                                                                clearable
                                                                :disabled="!form.address.state_id"
                                                                @update:modelValue="onAddressCityChange"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-number-input
                                                                v-model="form.address.years_in_city"
                                                                label="Años radicando en la ciudad"
                                                            />
                                                        </v-col>
                                                    </v-row>

                                                    <!-- Información laboral (cónyuge) -->
                                                    <v-row class="mt-2">
                                                        <v-col cols="12">
                                                            <h5 class="mb-2 text-subtitle-2 font-weight-bold">
                                                                Información laboral
                                                            </h5>
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-text-field
                                                                v-model="form.employment.company_name"
                                                                label="Empresa"
                                                                :rules="[minLength(2), maxLength(150)]"
                                                                :error-messages="form.errors['employment.company_name']"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-text-field
                                                                v-model="form.employment.company_address"
                                                                label="Dirección de la empresa"
                                                                :rules="[minLength(5), maxLength(255)]"
                                                                :error-messages="form.errors['employment.company_address']"
                                                            />
                                                        </v-col>

                                                        <v-col cols="12" md="4">
                                                            <v-text-field
                                                                v-model="form.employment.company_phone"
                                                                label="Teléfono de la empresa"
                                                                :rules="[validatePhone]"
                                                                :error-messages="form.errors['employment.company_phone']"
                                                            />
                                                        </v-col>
                                                    </v-row>
                                                </template>
                                            </v-card>
                                        </v-container>
                                    </v-form>
                                </template>

                                <!-- ══════════════════════════════ PASO 2 — Documentos ══════════════════════════════ -->
                                <!-- <template>
                                    <v-form ref="documentsStepRef">
                                        <v-container class="h-[500px] overflow-auto">
                                            <div class="mb-4">
                                                <h3 class="mb-1 text-h6">Documentos</h3>
                                                <p class="mb-0 text-body-2 text-medium-emphasis">
                                                    Carga los documentos requeridos para este integrante.
                                                </p>
                                            </div>

                                            <v-card class="pa-4">
                                                <h4 class="mb-4 text-subtitle-1 font-weight-bold">
                                                    {{ relationshipName || "Integrante" }}
                                                    <span
                                                        v-if="form.first_name || form.last_name"
                                                        class="font-weight-regular"
                                                    >
                                                        — {{ fullName }}
                                                    </span>
                                                </h4>

                                                <div
                                                    v-if="form.documents.length === 0"
                                                    class="text-body-2 text-medium-emphasis"
                                                >
                                                    <template v-if="!form.relationship_id">
                                                        Selecciona un parentesco en el paso anterior para
                                                        ver los documentos requeridos.
                                                    </template>
                                                    <template v-else>
                                                        No se requieren documentos para este parentesco.
                                                    </template>
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
                                                                <span v-if="doc.is_required" class="text-error">*</span>
                                                            </span>
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
                                                        </div>

                                                        <CustomFileUploadField
                                                            v-model="doc.files"
                                                            :label="`${doc.allow_multiple ? doc.number_files + ' x ' : ''}${doc.name}`"
                                                            :hint="doc.allowed_extensions.join(', ')"
                                                            :accept="doc.allowed_extensions.map((ext) => `.${ext}`).join(',')"
                                                            :multiple="doc.allow_multiple"
                                                            :rules="[
                                                                ...(doc.is_required
                                                                    ? [requiredFileRule, fileExactCountRule(doc.number_files)]
                                                                    : []),
                                                                fileTypeRule(doc.allowed_extensions),
                                                                fileMaxSizeRule((doc.max_file_size_kb ?? DEFAULT_MAX_FILE_SIZE_KB) / 1024),
                                                            ]"
                                                            clearable
                                                        />
                                                    </v-col>
                                                </v-row>
                                            </v-card>
                                        </v-container>
                                    </v-form>
                                </template> -->

                                <!-- ══════════════════════════════ PASO 3 — Confirmación ══════════════════════════════ -->
                                <template #item.2>
                                    <v-container class="h-[500px] overflow-auto">
                                        <h3 class="mb-4 text-title-large">Confirmación</h3>

                                        <v-card class="mb-4 pa-4">
                                            <p class="mb-2 text-subtitle-2 font-weight-bold">
                                                Cuenta familiar
                                            </p>
                                            <p>
                                                <strong>Titular:</strong>
                                                {{ props.membership.holder_name }}
                                            </p>
                                            <p>
                                                <strong>Membresía:</strong>
                                                {{ props.membership.membership_type_name }}
                                            </p>
                                            <p>
                                                <strong>Club:</strong>
                                                {{ props.membership.club_code }} – {{ props.membership.club_name }}
                                            </p>
                                        </v-card>

                                        <v-card class="mb-4 pa-4">
                                            <p class="mb-2 text-subtitle-2 font-weight-bold">
                                                Nuevo familiar
                                            </p>
                                            <p>
                                                <strong>Nombre:</strong> {{ fullName }}
                                            </p>
                                            <p>
                                                <strong>Parentesco:</strong> {{ relationshipName || "-" }}
                                            </p>
                                            <p v-if="form.birthdate">
                                                <strong>Fecha de nacimiento:</strong> {{ form.birthdate }}
                                                <span v-if="currentAge !== null"> ({{ currentAge }} años)</span>
                                            </p>
                                            <p v-if="form.email">
                                                <strong>Correo:</strong> {{ form.email }}
                                            </p>
                                        </v-card>

                                        <v-alert type="info" variant="tonal">
                                            Al confirmar, el familiar quedará registrado en la cuenta
                                            familiar. Esta acción no se puede deshacer.
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
                                            Confirmar y guardar
                                        </v-btn>
                                    </div>
                                </template>

                            </v-stepper>
                        </template>

                    </v-container>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>
