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
} from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const page = usePage<any>();

interface Props {
    membershipTypes?: MembershipType[];
    originMembershipTypes?: MembershipType[];
    clubs?: Club[];
    relationships?: Relationship[];
    nationalities?: Nationality[];
    maritalStatuses?: MaritalStatus[];
    isCrossClubRequest?: boolean;
    targetClub?: Club | null;
    sourceMembership?: SourceMembership | null;
    prefillMembers?: PrefillMember[];
}

interface Club {
    id: number;
    code: string;
    name: string;
}

interface Relationship {
    id: number;
    name: string;
}

interface Nationality {
    id: number;
    code: string;
    name: string;
    demonym: string | null;
}

interface MaritalStatus {
    id: number;
    code: string;
    name: string;
}

interface DocumentType {
    id: number;
    name: string;
    allowed_extensions: string;
    pivot: {
        membership_type_id: number;
        document_type_id: number;
        is_required: boolean;
        allow_multiple: boolean;
        number_files: number;
    };
    relationships: Relationship[];
}

interface MembershipType {
    id: number;
    club_id?: number;
    code?: string;
    name: string;
    description: string;
    allows_multiple_members: boolean;
    document_types: DocumentType[];
}

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

interface MemberAddressForm {
    street: string | null;
    neighborhood: string | null;
    postal_code: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    years_in_city: number | null;
}

interface MemberEmploymentForm {
    company_name: string | null;
    company_address: string | null;
    company_phone: string | null;
}

interface MemberDocumentForm {
    document_type_id: number;
    name: string;
    allowed_extensions: string[];
    is_required: boolean;
    allow_multiple: boolean;
    number_files: number;
    files: File[];
}

interface MemberForm {
    id?: number | null;
    local_id: number;
    first_name: string;
    last_name: string;
    second_last_name: string | null;
    birthdate: string | null;
    age: number | null;

    birth_place: string | null;
    city: string | null;
    state: string | null;

    nationality_id: number | null;
    marital_status_id: number | null;
    phone: string | null;
    email: string | null;
    occupation: string | null;
    school_name: string | null;

    relationship_id: number | null;
    relationship_name: string | null;
    is_primary_holder: boolean;
    is_locked: boolean;

    address: MemberAddressForm;
    employment: MemberEmploymentForm;
    documents: MemberDocumentForm[];
}

interface PrefillMember {
    id?: number | null;
    first_name: string;
    last_name: string;
    second_last_name: string | null;
    birthdate: string | null;
    birth_place: string | null;
    city: string | null;
    state: string | null;
    nationality_id: number | null;
    marital_status_id: number | null;
    phone: string | null;
    email: string | null;
    occupation: string | null;
    school_name: string | null;
    relationship_id: number | null;
    relationship_name: string | null;
    is_primary_holder: boolean;
    address?: Partial<MemberAddressForm> | null;
    employment?: Partial<MemberEmploymentForm> | null;
}

interface MembershipsForm {
    id: number | null;
    membershipType: MembershipType | null;
    from_membership: number | null;
    source_club_id: number | null;
    has_multiple_clubs: boolean;
    source_membership_is_active: boolean;
    years_in_source_club: number | null;
    source_membership_id: number | null;
    target_club_id: number | null;
    members: MemberForm[];
}

const props = withDefaults(defineProps<Props>(), {
    membershipTypes: () => [],
    originMembershipTypes: () => [],
    clubs: () => [],
    relationships: () => [],
    nationalities: () => [],
    maritalStatuses: () => [],
    isCrossClubRequest: false,
    targetClub: null,
    sourceMembership: null,
    prefillMembers: () => [],
});

const nationalityOptions = computed(() =>
    props.nationalities.map((nationality) => ({
        id: nationality.id,
        title: nationality.demonym
            ? `${nationality.demonym}`
            : nationality.name,
    })),
);

const maritalStatusOptions = computed(() =>
    props.maritalStatuses.map((maritalStatus) => ({
        id: maritalStatus.id,
        title: maritalStatus.name,
    })),
);

const clubOptions = computed(() =>
    props.clubs.map((club) => ({
        id: club.id,
        title: `${club.code} - ${club.name}`,
    })),
);

const originMembershipOptions = computed(() =>
    props.originMembershipTypes
        .filter((membershipType) =>
            form.source_club_id
                ? membershipType.club_id === form.source_club_id
                : true,
        )
        .map((membershipType) => ({
            id: membershipType.id,
            title: membershipType.name,
        })),
);

const familyStepRef = ref();
const documentsStepRef = ref();

const TITULAR_RELATIONSHIP_ID = 1;

const step = ref(1);
const steps = ["Membresía", "Datos y Familia", "Documentos", "Confirmación"];

const form = useForm<MembershipsForm>({
    id: null,
    membershipType: null,
    from_membership: null,
    source_club_id: null,
    has_multiple_clubs: false,
    source_membership_is_active: false,
    years_in_source_club: null,
    source_membership_id: props.sourceMembership?.id ?? null,
    target_club_id: props.targetClub?.id ?? null,
    members: [],
});

const createEmptyAddress = (): MemberAddressForm => ({
    street: "",
    neighborhood: "",
    postal_code: "",
    city: "",
    state: "",
    country: "México",
    years_in_city: null,
});

const createEmptyEmployment = (): MemberEmploymentForm => ({
    company_name: "",
    company_address: "",
    company_phone: "",
});

const createEmptyMember = (
    relationshipId: number | null,
    relationshipName: string | null,
    isPrimaryHolder = false,
    isLocked = false,
): MemberForm => ({
    id: null,
    local_id: Date.now() + Math.floor(Math.random() * 1000),
    first_name: "",
    last_name: "",
    second_last_name: "",
    birthdate: null,
    age: null,

    birth_place: "",
    city: "",
    state: "",

    nationality_id: null,
    marital_status_id: null,
    phone: "",
    email: "",
    occupation: "",
    school_name: "",

    relationship_id: relationshipId,
    relationship_name: relationshipName,
    is_primary_holder: isPrimaryHolder,
    is_locked: isLocked,

    address: createEmptyAddress(),
    employment: createEmptyEmployment(),
    documents: [],
});

const buildMemberFromPrefill = (prefillMember: PrefillMember): MemberForm => {
    const member = createEmptyMember(
        prefillMember.relationship_id,
        prefillMember.relationship_name,
        prefillMember.is_primary_holder,
        prefillMember.is_primary_holder,
    );

    member.id = prefillMember.id ?? null;
    member.first_name = prefillMember.first_name ?? "";
    member.last_name = prefillMember.last_name ?? "";
    member.second_last_name = prefillMember.second_last_name ?? "";
    member.birthdate = prefillMember.birthdate ?? null;
    member.age = calculateAge(prefillMember.birthdate ?? null);
    member.birth_place = prefillMember.birth_place ?? "";
    member.city = prefillMember.city ?? "";
    member.state = prefillMember.state ?? "";
    member.nationality_id = prefillMember.nationality_id ?? null;
    member.marital_status_id = prefillMember.marital_status_id ?? null;
    member.phone = prefillMember.phone ?? "";
    member.email = prefillMember.email ?? "";
    member.occupation = prefillMember.occupation ?? "";
    member.school_name = prefillMember.school_name ?? "";
    member.address = {
        ...createEmptyAddress(),
        ...(prefillMember.address ?? {}),
    };
    member.employment = {
        ...createEmptyEmployment(),
        ...(prefillMember.employment ?? {}),
    };
    member.documents = member.is_primary_holder
        ? buildMemberDocuments(member)
        : member.relationship_id
          ? buildDocumentsForRelationship(member.relationship_id, member.age)
          : [];

    return member;
};

const getRelationshipIdForDocuments = (member: MemberForm) => {
    if (member.is_primary_holder) {
        return TITULAR_RELATIONSHIP_ID;
    }
    return member.relationship_id;
};

const shouldIncludeDocumentByAge = (doc: DocumentType, age: number | null) => {
    if (age === null) return true;

    if (doc.name === "INE") {
        return age >= 18;
    }

    return true;
};

const getDocumentsForMember = (member: MemberForm) => {
    if (!form.membershipType) return [];

    const relationshipId = getRelationshipIdForDocuments(member);

    return form.membershipType.document_types
        .filter((doc) =>
            doc.relationships.some((rel) => rel.id === relationshipId),
        )
        .filter((doc) => shouldIncludeDocumentByAge(doc, member.age));
};

const buildMemberDocuments = (member: MemberForm): MemberDocumentForm[] => {
    return getDocumentsForMember(member).map((doc) => ({
        document_type_id: doc.id,
        name: doc.name,
        allowed_extensions: doc.allowed_extensions
            ? doc.allowed_extensions
                  .split(",")
                  .map((ext) => ext.trim().toLowerCase())
            : [],
        is_required: doc.pivot.is_required,
        allow_multiple: doc.pivot.allow_multiple,
        number_files: doc.pivot.number_files,
        files: [],
    }));
};

const buildDocumentsForRelationship = (
    relationshipId: number,
    age: number | null = null,
): MemberDocumentForm[] => {
    if (!form.membershipType) return [];

    return form.membershipType.document_types
        .filter((doc) =>
            doc.relationships.some((rel) => rel.id === relationshipId),
        )
        .filter((doc) => shouldIncludeDocumentByAge(doc, age))
        .map((doc) => ({
            document_type_id: doc.id,
            name: doc.name,
            allowed_extensions: doc.allowed_extensions
                ? doc.allowed_extensions
                      .split(",")
                      .map((ext) => ext.trim().toLowerCase())
                : [],
            is_required: doc.pivot.is_required,
            allow_multiple: doc.pivot.allow_multiple,
            number_files: doc.pivot.number_files,
            files: [],
        }));
};

const createPrimaryHolder = (): MemberForm => {
    const member = createEmptyMember(null, null, true, true);
    member.documents = buildMemberDocuments(member);
    return member;
};

const createSpouseMember = (): MemberForm | null => {
    const spouseRelationship = props.relationships.find(
        (rel) => rel.name === "Cónyuge",
    );

    if (!spouseRelationship) return null;

    const member = createEmptyMember(
        spouseRelationship.id,
        spouseRelationship.name,
        false,
        true,
    );

    member.documents = buildDocumentsForRelationship(spouseRelationship.id);
    return member;
};

const addFamilyMember = () => {
    form.members.push(createEmptyMember(null, null, false, false));
};

const onRelationshipChange = (member: MemberForm) => {
    if (!member.relationship_id) {
        member.relationship_name = null;
        member.documents = [];
        return;
    }

    const relationship = props.relationships.find(
        (rel) => rel.id === member.relationship_id,
    );

    member.relationship_name = relationship?.name ?? null;
    member.documents = buildDocumentsForRelationship(
        member.relationship_id,
        member.age,
    );
};

const removeMember = (localId: number) => {
    const member = form.members.find((m) => m.local_id === localId);
    if (!member || member.is_locked) return;

    form.members = form.members.filter((m) => m.local_id !== localId);
};

const selectType = (membershipType: MembershipType) => {
    form.membershipType = { ...membershipType };
    if (!props.isCrossClubRequest) {
        form.from_membership = null;
        form.source_club_id = null;
        form.has_multiple_clubs = false;
        form.source_membership_is_active = false;
        form.years_in_source_club = null;
    }

    if (props.isCrossClubRequest && props.prefillMembers.length > 0) {
        const members = props.prefillMembers
            .filter(
                (member) =>
                    membershipType.allows_multiple_members ||
                    member.is_primary_holder,
            )
            .map((member) => buildMemberFromPrefill(member));

        form.members = members;
        return;
    }

    const members: MemberForm[] = [createPrimaryHolder()];

    if (membershipType.allows_multiple_members) {
        const spouse = createSpouseMember();
        if (spouse) {
            members.push(spouse);
        }
    }

    form.members = members;
};

const onSourceClubChange = () => {
    if (!form.from_membership) return;

    const selectedOriginMembership = props.originMembershipTypes.find(
        (membershipType) => membershipType.id === form.from_membership,
    );

    if (
        selectedOriginMembership &&
        form.source_club_id &&
        selectedOriginMembership.club_id !== form.source_club_id
    ) {
        form.from_membership = null;
    }
};

const isPe1PackageSelected = computed(() =>
    form.membershipType?.code?.endsWith("_PE1") ?? false,
);

const crossClubSourceSummary = computed(() => {
    if (!props.sourceMembership) return null;

    return `${props.sourceMembership.club_code} · ${props.sourceMembership.membership_type_name}`;
});

const calculateAge = (birthdate: string | null): number | null => {
    if (!birthdate) return null;

    const today = new Date();
    const birth = new Date(birthdate);

    if (Number.isNaN(birth.getTime())) return null;

    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();

    if (
        monthDiff < 0 ||
        (monthDiff === 0 && today.getDate() < birth.getDate())
    ) {
        age--;
    }

    return age;
};

const CHILD_RELATIONSHIP_ID = computed(() => {
    const child = props.relationships.find((rel) => rel.name === "Hijo(a)");
    return child?.id ?? null;
});

const isChildRelationship = (member: MemberForm) => {
    return member.relationship_id === CHILD_RELATIONSHIP_ID.value;
};

const isSpouseRelationship = (member: MemberForm) => {
    return member.relationship_name === "Cónyuge";
};

const birthdateRule = (member: MemberForm) => {
    return (value: string | null) => {
        if (!value) return "La fecha de nacimiento es requerida";

        const age = calculateAge(value);
        member.age = age;

        if (age === null) return "La fecha de nacimiento no es válida";
        if (age < 0) return "La fecha de nacimiento no es válida";

        if (isChildRelationship(member) && age > 24) {
            return "Los hijos no pueden ser mayores de 24 años";
        }

        return true;
    };
};

const onBirthdateChange = (member: MemberForm) => {
    member.age = calculateAge(member.birthdate);

    if (!member.is_primary_holder && member.relationship_id) {
        member.documents = buildDocumentsForRelationship(
            member.relationship_id,
            member.age,
        );
        return;
    }

    member.documents = buildMemberDocuments(member);
};

const isNextDisabled = computed(() => {
    if (step.value === 1) {
        return !form.membershipType;
    }
    return false;
});

const handleNext = async (next: () => void) => {
    if (step.value === 1) {
        if (!form.membershipType) return;
        next();
        return;
    }

    if (step.value === 2) {
        const { valid } = await familyStepRef.value?.validate();
        if (!valid) return;
        next();
        return;
    }

    if (step.value === 3) {
        const { valid } = await documentsStepRef.value?.validate();
        if (!valid) return;
        next();
        return; 
       
    }

    next();
};

const handlePrev = (prev: () => void) => {
    if (step.value === 3) {
        documentsStepRef.value?.resetValidation();
    }

    if (step.value === 2) {
        familyStepRef.value?.resetValidation();
    }

    prev();
};

const submit = () => {
    form.transform((data) => ({
        source_membership_id: data.source_membership_id,
        target_club_id: data.target_club_id,
        membership_type_id: data.membershipType?.id ?? null,
        from_membership_type_id: data.from_membership,
        source_club_id: data.source_club_id,
        has_multiple_clubs: data.has_multiple_clubs,
        source_membership_is_active: data.source_membership_is_active,
        years_in_source_club: data.years_in_source_club,
        members: data.members.map((member) => ({
            id: member.id ?? null,
            first_name: member.first_name,
            last_name: member.last_name,
            second_last_name: member.second_last_name,
            birthdate: member.birthdate,
            age: member.age,
            birth_place: member.birth_place,
            city: member.city,
            state: member.state,
            nationality_id: member.nationality_id,
            marital_status_id: member.marital_status_id,
            phone: member.phone,
            email: member.email,
            occupation: member.occupation,
            school_name: member.school_name,
            relationship_id: member.relationship_id,
            relationship_name: member.relationship_name,
            is_primary_holder: member.is_primary_holder,
            address: member.address,
            employment: member.employment,
        })),
    }));

    form.post(route("members.store"), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "",
                icon: "success",
            });
            // form.reset();
        },
        onError: (err) => {
            console.error(err);
            customToastSwal({
                title: `Error: ${form.errors.messageError}`,
                text: `${form.errors.exception}`,
                icon: "error",
            });
        },
    });
};

const memberLabel = (member: MemberForm) => {
    if (member.is_primary_holder) return "Titular";
    if (member.relationship_name) return member.relationship_name;
    return "Familiar";
};
</script>

<template>
    <Head
        :title="
            props.isCrossClubRequest
                ? 'Solicitud Otro Parque'
                : 'Alta de Socios'
        "
    />

    <AppLayout>
        <template #header>
            {{
                props.isCrossClubRequest
                    ? "Solicitud para el Otro Parque"
                    : "Alta de Socios"
            }}
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-stepper v-model="step" :items="steps" show-actions>
                        <!-- STEP 1 -->
                        <template #item.1>
                            <v-container class="h-[500px] overflow-auto">
                                <h3 class="text-lg font-medium text-center">
                                    ¿Qué tipo de membresía necesita?
                                </h3>

                                <p
                                    class="mb-6 text-center text-sm text-gray-600"
                                >
                                    El tipo de membresía determina los
                                    documentos e información que se solicitarán.
                                </p>

                                <v-alert
                                    v-if="props.isCrossClubRequest && props.sourceMembership"
                                    type="info"
                                    variant="tonal"
                                    class="mb-6"
                                >
                                    <strong>Origen:</strong>
                                    {{ props.sourceMembership.holder_name }}
                                    · {{ crossClubSourceSummary }}
                                    · Folio
                                    {{ props.sourceMembership.membership_number || "-" }}
                                </v-alert>

                                <v-row>
                                    <v-col
                                        v-for="membershipType in membershipTypes"
                                        :key="membershipType.id"
                                        cols="12"
                                        md="6"
                                    >
                                        <v-card
                                            class="pa-6 membership-card"
                                            elevation="2"
                                            :class="{
                                                'selected-membership-card':
                                                    form.membershipType?.id ===
                                                    membershipType.id,
                                            }"
                                            @click="selectType(membershipType)"
                                        >
                                            <v-row no-gutters>
                                                <v-col cols="auto">
                                                    <v-avatar
                                                        size="56"
                                                        color="grey-lighten-3"
                                                    >
                                                        <v-icon
                                                            :icon="
                                                                membershipType.allows_multiple_members
                                                                    ? 'mdi-account-multiple'
                                                                    : 'mdi-account'
                                                            "
                                                        />
                                                    </v-avatar>
                                                </v-col>

                                                <v-col class="ml-4">
                                                    <h3
                                                        class="text-h6 font-weight-medium"
                                                    >
                                                        {{
                                                            membershipType.name
                                                        }}
                                                    </h3>

                                                    <p class="text-body-2 mt-2">
                                                        {{
                                                            membershipType.description
                                                        }}
                                                    </p>

                                                    <div
                                                        class="mt-4 h-64 overflow-auto"
                                                    >
                                                        <strong
                                                            >Documentos
                                                            requeridos:</strong
                                                        >
                                                        <ul class="mt-2">
                                                            <li
                                                                v-for="doc in membershipType.document_types"
                                                                :key="doc.id"
                                                            >
                                                                {{
                                                                    doc.pivot
                                                                        .number_files ===
                                                                    1
                                                                        ? ""
                                                                        : `${doc.pivot.number_files} x `
                                                                }}
                                                                {{ doc.name }}
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </v-col>
                                            </v-row>
                                        </v-card>
                                    </v-col>
                                </v-row>

                                <v-card
                                    v-if="form.membershipType && !props.isCrossClubRequest"
                                    class="mt-6 pa-4"
                                    variant="outlined"
                                >
                                    <div class="text-subtitle-1 font-weight-bold mb-1">
                                        Datos para calcular reglas
                                    </div>

                                    <p class="text-body-2 text-medium-emphasis mb-4">
                                        Capture el origen solo si aplica una transicion,
                                        interclub, paquete PE1 o tarifa por ambos parques.
                                    </p>

                                    <v-row>
                                        <v-col cols="12" md="6">
                                            <v-autocomplete
                                                v-model="form.source_club_id"
                                                :items="clubOptions"
                                                item-title="title"
                                                item-value="id"
                                                label="Club de origen"
                                                clearable
                                                @update:modelValue="onSourceClubChange"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="6">
                                            <v-autocomplete
                                                v-model="form.from_membership"
                                                :items="originMembershipOptions"
                                                item-title="title"
                                                item-value="id"
                                                label="Membresia de origen"
                                                clearable
                                            />
                                        </v-col>

                                        <v-col cols="12" md="6">
                                            <v-switch
                                                v-model="form.has_multiple_clubs"
                                                color="primary"
                                                label="Pertenece a ambos parques"
                                                inset
                                            />
                                        </v-col>

                                        <v-col cols="12" md="6" v-if="form.source_club_id">
                                            <v-switch
                                                v-model="form.source_membership_is_active"
                                                color="primary"
                                                label="La membresia origen esta activa"
                                                inset
                                            />
                                        </v-col>

                                        <v-col
                                            cols="12"
                                            md="6"
                                            v-if="form.source_club_id || isPe1PackageSelected"
                                        >
                                            <v-text-field
                                                v-model="form.years_in_source_club"
                                                type="number"
                                                min="0"
                                                label="Antiguedad en club origen (anos)"
                                            />
                                        </v-col>
                                    </v-row>

                                    <v-alert
                                        v-if="isPe1PackageSelected"
                                        type="info"
                                        variant="tonal"
                                        class="mt-2"
                                    >
                                        Este paquete requiere origen en PE1 y al menos 5 anos
                                        de antiguedad.
                                    </v-alert>
                                </v-card>
                            </v-container>
                        </template>

                        <!-- STEP 2 -->
                        <template #item.2>
                            <v-form ref="familyStepRef">
                                <v-container class="overflow-auto h-[500px]">
                                    <div
                                        v-for="member in form.members"
                                        :key="member.local_id"
                                        class="mb-6"
                                    >
                                        <v-card class="pa-4">
                                            <div
                                                class="d-flex justify-space-between align-center mb-4"
                                            >
                                                <h4>
                                                    {{ memberLabel(member) }}
                                                </h4>

                                                <v-btn
                                                    v-if="!member.is_locked"
                                                    color="error"
                                                    variant="text"
                                                    @click="
                                                        removeMember(
                                                            member.local_id,
                                                        )
                                                    "
                                                >
                                                    Eliminar
                                                </v-btn>
                                            </div>

                                            <!-- DATOS PERSONALES -->
                                            <v-row>
                                                <v-col cols="12">
                                                    <h5
                                                        class="text-subtitle-1 font-weight-bold mb-2"
                                                    >
                                                        Datos personales
                                                    </h5>
                                                </v-col>

                                                <v-col
                                                    v-if="
                                                        !member.is_primary_holder
                                                    "
                                                    cols="12"
                                                    md="4"
                                                >
                                                    <v-select
                                                        v-model="
                                                            member.relationship_id
                                                        "
                                                        :items="
                                                            props.relationships
                                                        "
                                                        item-title="name"
                                                        item-value="id"
                                                        label="Parentesco"
                                                        :rules="[
                                                            selectRequired,
                                                        ]"
                                                        :disabled="
                                                            member.is_locked
                                                        "
                                                        @update:modelValue="
                                                            onRelationshipChange(
                                                                member,
                                                            )
                                                        "
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.first_name
                                                        "
                                                        label="Nombre(s)"
                                                        :rules="[required]"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.last_name
                                                        "
                                                        label="Apellido paterno"
                                                        :rules="[required]"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.second_last_name
                                                        "
                                                        label="Apellido materno"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.birthdate
                                                        "
                                                        type="date"
                                                        label="Fecha de nacimiento"
                                                        :rules="[
                                                            required,
                                                            birthdateRule(
                                                                member,
                                                            ),
                                                        ]"
                                                        @update:modelValue="
                                                            onBirthdateChange(
                                                                member,
                                                            )
                                                        "
                                                    />
                                                </v-col>

                                                <v-col
                                                    cols="12"
                                                    md="4"
                                                    v-if="member.age !== null"
                                                >
                                                    <v-text-field
                                                        :model-value="
                                                            member.age
                                                        "
                                                        label="Edad"
                                                        readonly
                                                    />
                                                </v-col>

                                                <v-col
                                                    v-if="
                                                        member.is_primary_holder ||
                                                        isSpouseRelationship(
                                                            member,
                                                        )
                                                    "
                                                    cols="12"
                                                    md="4"
                                                >
                                                    <v-text-field
                                                        v-model="
                                                            member.birth_place
                                                        "
                                                        label="Lugar de nacimiento"
                                                    />
                                                </v-col>

                                                <v-col
                                                    v-if="
                                                        member.is_primary_holder ||
                                                        isSpouseRelationship(
                                                            member,
                                                        )
                                                    "
                                                    cols="12"
                                                    md="4"
                                                >
                                                    <v-text-field
                                                        v-model="member.city"
                                                        label="Ciudad"
                                                    />
                                                </v-col>

                                                <v-col
                                                    v-if="
                                                        member.is_primary_holder ||
                                                        isSpouseRelationship(
                                                            member,
                                                        )
                                                    "
                                                    cols="12"
                                                    md="4"
                                                >
                                                    <v-text-field
                                                        v-model="member.state"
                                                        label="Estado"
                                                    />
                                                </v-col>

                                                <v-col
                                                    v-if="
                                                        member.is_primary_holder ||
                                                        isSpouseRelationship(
                                                            member,
                                                        )
                                                    "
                                                    cols="12"
                                                    md="4"
                                                >
                                                    <v-autocomplete
                                                        v-model="
                                                            member.nationality_id
                                                        "
                                                        :items="
                                                            nationalityOptions
                                                        "
                                                        item-title="title"
                                                        item-value="id"
                                                        label="Nacionalidad"
                                                        clearable
                                                        auto-select-first
                                                    />
                                                </v-col>

                                                <v-col
                                                    cols="12"
                                                    md="4"
                                                    v-if="
                                                        member.is_primary_holder ||
                                                        isSpouseRelationship(
                                                            member,
                                                        )
                                                    "
                                                >
                                                    <v-autocomplete
                                                        v-model="
                                                            member.marital_status_id
                                                        "
                                                        :items="
                                                            maritalStatusOptions
                                                        "
                                                        item-title="title"
                                                        item-value="id"
                                                        label="Estado civil"
                                                        clearable
                                                        auto-select-first
                                                    />
                                                </v-col>

                                                <v-col
                                                    cols="12"
                                                    md="4"
                                                    v-if="
                                                        member.is_primary_holder ||
                                                        isSpouseRelationship(
                                                            member,
                                                        )
                                                    "
                                                >
                                                    <v-text-field
                                                        v-model="member.phone"
                                                        label="Teléfono"
                                                        :rules="
                                                            member.is_primary_holder
                                                                ? [
                                                                      required,
                                                                      validatePhone,
                                                                  ]
                                                                : []
                                                        "
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="member.email"
                                                        label="Correo"
                                                        :rules="
                                                            member.is_primary_holder
                                                                ? [
                                                                      required,
                                                                      email,
                                                                  ]
                                                                : [email]
                                                        "
                                                    />
                                                </v-col>

                                                <v-col
                                                    cols="12"
                                                    md="4"
                                                    v-if="
                                                        member.is_primary_holder
                                                    "
                                                >
                                                    <v-text-field
                                                        v-model="
                                                            member.occupation
                                                        "
                                                        label="Ocupación"
                                                    />
                                                </v-col>

                                                <v-col
                                                    cols="12"
                                                    md="4"
                                                    v-if="
                                                        isChildRelationship(
                                                            member,
                                                        )
                                                    "
                                                >
                                                    <v-text-field
                                                        v-model="
                                                            member.school_name
                                                        "
                                                        label="Colegio"
                                                    />
                                                </v-col>
                                            </v-row>

                                            <!-- DOMICILIO SOLO TITULAR -->
                                            <v-row
                                                v-if="member.is_primary_holder"
                                                class="mt-2"
                                            >
                                                <v-col cols="12">
                                                    <h5
                                                        class="text-subtitle-1 font-weight-bold mb-2"
                                                    >
                                                        Domicilio
                                                    </h5>
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.address
                                                                .street
                                                        "
                                                        label="Calle"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.address
                                                                .neighborhood
                                                        "
                                                        label="Colonia"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.address
                                                                .postal_code
                                                        "
                                                        label="Código postal"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.address.city
                                                        "
                                                        label="Ciudad domicilio"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.address.state
                                                        "
                                                        label="Estado domicilio"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.address
                                                                .country
                                                        "
                                                        label="País"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.address
                                                                .years_in_city
                                                        "
                                                        type="number"
                                                        label="Años radicando en la ciudad"
                                                    />
                                                </v-col>
                                            </v-row>

                                            <!-- EMPLEO SOLO TITULAR -->
                                            <v-row
                                                v-if="member.is_primary_holder"
                                                class="mt-2"
                                            >
                                                <v-col cols="12">
                                                    <h5
                                                        class="text-subtitle-1 font-weight-bold mb-2"
                                                    >
                                                        Información laboral
                                                    </h5>
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.employment
                                                                .company_name
                                                        "
                                                        label="Empresa"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.employment
                                                                .company_address
                                                        "
                                                        label="Dirección empresa"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.employment
                                                                .company_phone
                                                        "
                                                        label="Teléfono empresa"
                                                    />
                                                </v-col>
                                            </v-row>
                                        </v-card>
                                    </div>

                                    <v-btn
                                        v-if="
                                            form.membershipType
                                                ?.allows_multiple_members
                                        "
                                        color="primary"
                                        @click="addFamilyMember"
                                    >
                                        Agregar familiar
                                    </v-btn>
                                </v-container>
                            </v-form>
                        </template>

                        <!-- STEP 3 -->
                        <template #item.3>
                            <v-form ref="documentsStepRef">
                                <v-container class="h-[500px] overflow-auto">
                                    <div class="mb-4">
                                        <h3 class="text-h6 mb-1">Documentos</h3>
                                        <p
                                            class="text-body-2 text-medium-emphasis mb-0"
                                        >
                                            Cargue los documentos requeridos por
                                            cada integrante.
                                        </p>
                                    </div>

                                    <div
                                        v-for="member in form.members"
                                        :key="member.local_id"
                                        class="mb-6"
                                    >
                                        <v-card class="pa-4">
                                            <h4
                                                class="text-subtitle-1 font-weight-bold mb-4"
                                            >
                                                {{ memberLabel(member) }}
                                            </h4>

                                            <v-row>
                                                <v-col
                                                    v-for="doc in member.documents"
                                                    :key="`${member.local_id}-${doc.document_type_id}`"
                                                    cols="12"
                                                    md="6"
                                                >
                                                    <div
                                                        class="mb-2 font-weight-medium"
                                                    >
                                                        {{ doc.name }}
                                                        <span
                                                            v-if="
                                                                doc.is_required
                                                            "
                                                            class="text-error"
                                                            >*</span
                                                        >
                                                    </div>

                                                    <CustomFileUploadField
                                                        v-model="doc.files"
                                                        :label="doc.name"
                                                        :hint="
                                                            doc.allowed_extensions.join(
                                                                ', ',
                                                            )
                                                        "
                                                        :accept="
                                                            doc.allowed_extensions
                                                                .map(
                                                                    (ext) =>
                                                                        `.${ext}`,
                                                                )
                                                                .join(',')
                                                        "
                                                        :multiple="
                                                            doc.allow_multiple
                                                        "
                                                        :rules="[
                                                            fileExactCountRule(
                                                                doc.number_files,
                                                            ),
                                                            requiredFileRule,
                                                            fileTypeRule(
                                                                doc.allowed_extensions,
                                                            ),
                                                        ]"
                                                        clearable
                                                    />
                                                </v-col>
                                            </v-row>
                                        </v-card>
                                    </div>
                                </v-container>
                            </v-form>
                        </template>

                        <!-- STEP 4 -->
                        <template #item.4>
                            <v-container class="h-[500px] overflow-auto">
                                <h3 class="text-title-large mb-4">
                                    Confirmación
                                </h3>

                                <v-card class="pa-4 mb-4">
                                    <p>
                                        <strong>Membresía:</strong>
                                        {{ form.membershipType?.name }}
                                    </p>
                                    <p>
                                        <strong>Total de integrantes:</strong>
                                        {{ form.members.length }}
                                    </p>
                                </v-card>

                                <v-card
                                    v-for="member in form.members"
                                    :key="member.local_id"
                                    class="pa-4 mb-4"
                                >
                                    <h4 class="font-weight-bold mb-2">
                                        {{ memberLabel(member) }}
                                    </h4>
                                    <p>
                                        {{ member.first_name }}
                                        {{ member.last_name }}
                                        {{ member.second_last_name || "" }}
                                    </p>
                                    <p v-if="member.birthdate">
                                        <strong>Fecha de nacimiento:</strong>
                                        {{ member.birthdate }}
                                    </p>
                                    <p v-if="member.phone">
                                        <strong>Teléfono:</strong>
                                        {{ member.phone }}
                                    </p>
                                    <p v-if="member.email">
                                        <strong>Correo:</strong>
                                        {{ member.email }}
                                    </p>
                                    <p v-if="member.school_name">
                                        <strong>Colegio:</strong>
                                        {{ member.school_name }}
                                    </p>
                                </v-card>

                                <v-btn color="success" @click="submit">
                                    Confirmar y guardar
                                </v-btn>
                            </v-container>
                        </template>

                        <template #actions="{ next, prev }">
                            <div class="d-flex w-100">
                                <v-btn
                                    variant="text"
                                    @click="handlePrev(prev)"
                                    :disabled="step === 1"
                                >
                                    Anterior
                                </v-btn>

                                <v-spacer />

                                <v-btn
                                    color="primary"
                                    @click="handleNext(next)"
                                    :disabled="isNextDisabled"
                                >
                                    Siguiente
                                </v-btn>
                            </div>
                        </template>
                    </v-stepper>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>

<style>
.membership-card {
    cursor: pointer;
    border-radius: 12px;
    transition: all 0.2s ease;
}

.membership-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.selected-membership-card {
    border: 2px solid #1976d2;
    background-color: #e3f2fd;
    transition: all 0.2s ease;
}
</style>
