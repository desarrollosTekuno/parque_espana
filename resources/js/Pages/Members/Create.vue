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
    countries?: CountryCatalog[];
    nationalities?: Nationality[];
    maritalStatuses?: MaritalStatus[];
    isCrossClubRequest?: boolean;
    isMembershipTransition?: boolean;
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

interface CountryCatalog {
    id: number;
    code: string;
    name: string;
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
    country_id: number | null;
    state_id: number | null;
    city_id: number | null;
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

    birth_country_id: number | null;
    birth_state_id: number | null;
    birth_city_id: number | null;
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
    is_from_source_membership: boolean;

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
    birth_country_id: number | null;
    birth_state_id: number | null;
    birth_city_id: number | null;
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
    is_from_source_membership?: boolean;
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

interface PricingPreview {
    membership_type_id: number;
    membership_type_name: string;
    membership_type_code: string | null;
    monthly_fee: number;
    inscription_fee: number;
    total_due: number;
    rule_type: string | null;
    source_membership_becomes_non_billable: boolean;
    current_monthly_fee: number | null;
    additional_monthly_charge: number | null;
    charge_explanation: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    membershipTypes: () => [],
    originMembershipTypes: () => [],
    clubs: () => [],
    relationships: () => [],
    countries: () => [],
    nationalities: () => [],
    maritalStatuses: () => [],
    isCrossClubRequest: false,
    isMembershipTransition: false,
    targetClub: null,
    sourceMembership: null,
    prefillMembers: () => [],
});

const countryOptions = computed(() =>
    props.countries.map((country) => ({
        id: country.id,
        title: country.name,
    })),
);

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

const statesByCountry = ref<Record<number, StateCatalog[]>>({});
const citiesByState = ref<Record<number, CityCatalog[]>>({});

const normalizeText = (value: string | null | undefined) =>
    (value ?? "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();

const defaultCountry = computed(
    () =>
        props.countries.find(
            (country) =>
                country.code === "MX" ||
                normalizeText(country.name) === "mexico",
        ) ?? null,
);

const getCountryName = (countryId: number | null) =>
    props.countries.find((country) => country.id === countryId)?.name ?? "";

const getStateOptions = (countryId: number | null) =>
    countryId ? statesByCountry.value[countryId] ?? [] : [];

const getStateName = (countryId: number | null, stateId: number | null) =>
    getStateOptions(countryId).find((state) => state.id === stateId)?.name ??
    "";

const getCityOptions = (stateId: number | null) =>
    stateId ? citiesByState.value[stateId] ?? [] : [];

const getCityName = (stateId: number | null, cityId: number | null) =>
    getCityOptions(stateId).find((city) => city.id === cityId)?.name ?? "";

const fetchStates = async (countryId: number | null) => {
    if (!countryId) return [];
    if (statesByCountry.value[countryId]) {
        return statesByCountry.value[countryId];
    }

    const response = await fetch(
        route("members.location-catalogs.states", {
            country_id: countryId,
        }),
        {
            method: "GET",
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        },
    );

    if (!response.ok) {
        throw new Error("No se pudieron cargar los estados.");
    }

    const payload = (await response.json()) as StateCatalog[];
    statesByCountry.value[countryId] = payload;
    return payload;
};

const fetchCities = async (stateId: number | null) => {
    if (!stateId) return [];
    if (citiesByState.value[stateId]) {
        return citiesByState.value[stateId];
    }

    const response = await fetch(
        route("members.location-catalogs.cities", {
            state_id: stateId,
        }),
        {
            method: "GET",
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        },
    );

    if (!response.ok) {
        throw new Error("No se pudieron cargar las ciudades.");
    }

    const payload = (await response.json()) as CityCatalog[];
    citiesByState.value[stateId] = payload;
    return payload;
};

const pageTitle = computed(() =>
    props.isCrossClubRequest
        ? "Solicitud Otro Parque"
        : props.isMembershipTransition
          ? "Cambio de Membresía"
          : "Alta de Socios",
);

const pageHeader = computed(() =>
    props.isCrossClubRequest
        ? "Solicitud para el Otro Parque"
        : props.isMembershipTransition
          ? "Cambio de Membresía"
          : "Alta de Socios",
);

const usesSourceMembership = computed(
    () => props.isCrossClubRequest || props.isMembershipTransition,
);

const familyStepRef = ref();
const documentsStepRef = ref();
const pricingPreview = ref<PricingPreview | null>(null);
const pricingPreviewLoading = ref(false);
const pricingPreviewError = ref<string | null>(null);
const membershipTypeSearch = ref("");
let pricingPreviewRequestId = 0;

const TITULAR_RELATIONSHIP_ID = 1;

const step = ref(1);
const steps = computed(() => [
    "Membresía",
    form.membershipType?.allows_multiple_members
        ? "Datos y familia"
        : "Datos del titular",
    "Documentos",
    "Confirmación",
]);

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

const filteredMembershipTypes = computed(() => {
    const search = membershipTypeSearch.value.trim().toLowerCase();

    if (!search) {
        return props.membershipTypes;
    }

    return props.membershipTypes.filter((membershipType) => {
        const code = membershipType.code?.toLowerCase() ?? "";
        const name = membershipType.name.toLowerCase();
        const description = membershipType.description?.toLowerCase() ?? "";

        return (
            code.includes(search) ||
            name.includes(search) ||
            description.includes(search)
        );
    });
});

const form = useForm<MembershipsForm>({
    id: null,
    membershipType: null,
    from_membership: null,
    source_club_id: null,
    has_multiple_clubs: false,
    source_membership_is_active: props.sourceMembership?.status === "active",
    years_in_source_club: null,
    source_membership_id: props.sourceMembership?.id ?? null,
    target_club_id: props.targetClub?.id ?? null,
    members: [],
});

const createEmptyAddress = (
    defaultCountryId: number | null = null,
): MemberAddressForm => ({
    country_id: defaultCountryId,
    state_id: null,
    city_id: null,
    street: "",
    neighborhood: "",
    postal_code: "",
    city: "",
    state: "",
    country: defaultCountryId ? getCountryName(defaultCountryId) : "",
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

    birth_country_id: null,
    birth_state_id: null,
    birth_city_id: null,
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
    is_from_source_membership: false,

    address: createEmptyAddress(
        isPrimaryHolder ? (defaultCountry.value?.id ?? null) : null,
    ),
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
    member.birth_country_id = prefillMember.birth_country_id ?? null;
    member.birth_state_id = prefillMember.birth_state_id ?? null;
    member.birth_city_id = prefillMember.birth_city_id ?? null;
    member.birth_place = prefillMember.birth_place ?? "";
    member.city = prefillMember.city ?? "";
    member.state = prefillMember.state ?? "";
    member.nationality_id = prefillMember.nationality_id ?? null;
    member.marital_status_id = prefillMember.marital_status_id ?? null;
    member.phone = prefillMember.phone ?? "";
    member.email = prefillMember.email ?? "";
    member.occupation = prefillMember.occupation ?? "";
    member.school_name = prefillMember.school_name ?? "";
    member.is_from_source_membership =
        prefillMember.is_from_source_membership ?? false;
    member.address = {
        ...createEmptyAddress(
            prefillMember.address?.country_id ??
                (prefillMember.is_primary_holder
                    ? (defaultCountry.value?.id ?? null)
                    : null),
        ),
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

const memberFieldError = (index: number, field: string) =>
    page.props.errors?.[`members.${index}.${field}`] ??
    form.errors[`members.${index}.${field}`];

const memberAddressFieldError = (index: number, field: string) =>
    page.props.errors?.[`members.${index}.address.${field}`] ??
    form.errors[`members.${index}.address.${field}`];

const isExistingSourceMember = (member: MemberForm) =>
    usesSourceMembership.value &&
    member.is_from_source_membership &&
    member.id !== null &&
    member.id !== undefined;

const isIdentityLocked = (member: MemberForm) =>
    usesSourceMembership.value &&
    (member.is_primary_holder || isExistingSourceMember(member));

const onBirthCountryChange = async (
    member: MemberForm,
    countryId: number | null,
) => {
    member.birth_country_id = countryId;
    member.birth_place = getCountryName(countryId);
    member.birth_state_id = null;
    member.state = "";
    member.birth_city_id = null;
    member.city = "";

    if (countryId) {
        await fetchStates(countryId);
    }
};

const onBirthStateChange = async (member: MemberForm, stateId: number | null) => {
    member.birth_state_id = stateId;
    member.state = getStateName(member.birth_country_id, stateId);
    member.birth_city_id = null;
    member.city = "";

    if (stateId) {
        await fetchCities(stateId);
    }
};

const onBirthCityChange = (member: MemberForm, cityId: number | null) => {
    member.birth_city_id = cityId;
    member.city = getCityName(member.birth_state_id, cityId);
};

const onAddressCountryChange = async (
    member: MemberForm,
    countryId: number | null,
) => {
    member.address.country_id = countryId;
    member.address.country = getCountryName(countryId);
    member.address.state_id = null;
    member.address.state = "";
    member.address.city_id = null;
    member.address.city = "";

    if (countryId) {
        await fetchStates(countryId);
    }
};

const onAddressStateChange = async (
    member: MemberForm,
    stateId: number | null,
) => {
    member.address.state_id = stateId;
    member.address.state = getStateName(member.address.country_id, stateId);
    member.address.city_id = null;
    member.address.city = "";

    if (stateId) {
        await fetchCities(stateId);
    }
};

const onAddressCityChange = (member: MemberForm, cityId: number | null) => {
    member.address.city_id = cityId;
    member.address.city = getCityName(member.address.state_id, cityId);
};

const initializeLocationCatalogsForMembers = async (members: MemberForm[]) => {
    const countryIds = Array.from(
        new Set(
            members
                .flatMap((member) => [
                    member.birth_country_id,
                    member.address.country_id,
                ])
                .filter((value): value is number => value !== null),
        ),
    );

    await Promise.all(countryIds.map((countryId) => fetchStates(countryId)));

    const stateIds = Array.from(
        new Set(
            members
                .flatMap((member) => [
                    member.birth_state_id,
                    member.address.state_id,
                ])
                .filter((value): value is number => value !== null),
        ),
    );

    await Promise.all(stateIds.map((stateId) => fetchCities(stateId)));
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

const getPrimaryMember = () =>
    form.members.find((member) => member.is_primary_holder) ?? null;

const fetchPricingPreview = async () => {
    if (!form.membershipType) {
        pricingPreview.value = null;
        pricingPreviewError.value = null;
        pricingPreviewLoading.value = false;
        return;
    }

    const requestId = ++pricingPreviewRequestId;
    pricingPreviewLoading.value = true;
    pricingPreview.value = null;
    pricingPreviewError.value = null;

    const primaryMember = getPrimaryMember();
    const age =
        primaryMember?.age ?? calculateAge(primaryMember?.birthdate ?? null);
    const params = Object.fromEntries(
        Object.entries({
            membership_type_id: form.membershipType.id,
            source_membership_id: form.source_membership_id,
            target_club_id: form.target_club_id ?? props.targetClub?.id ?? null,
            from_membership_type_id: form.from_membership,
            source_club_id: form.source_club_id,
            has_multiple_clubs: form.has_multiple_clubs ? 1 : 0,
            source_membership_is_active: form.source_membership_is_active
                ? 1
                : 0,
            years_in_source_club: form.years_in_source_club,
            age,
        }).filter(
            ([, value]) =>
                value !== null && value !== undefined && value !== "",
        ),
    );

    try {
        const response = await fetch(route("members.pricing-preview", params), {
            method: "GET",
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
        const payload = await response.json();

        if (requestId !== pricingPreviewRequestId) return;

        if (!response.ok) {
            pricingPreview.value = null;
            pricingPreviewError.value =
                payload?.message || "No se pudo calcular el precio.";
            return;
        }

        pricingPreview.value = payload as PricingPreview;
        pricingPreviewError.value = null;
    } catch (error) {
        if (requestId !== pricingPreviewRequestId) return;

        console.error(error);
        pricingPreview.value = null;
        pricingPreviewError.value = "No se pudo calcular el precio.";
    } finally {
        if (requestId === pricingPreviewRequestId) {
            pricingPreviewLoading.value = false;
        }
    }
};

const selectType = (membershipType: MembershipType) => {
    form.membershipType = { ...membershipType };
    if (!usesSourceMembership.value) {
        form.from_membership = null;
        form.source_club_id = null;
        form.has_multiple_clubs = false;
        form.source_membership_is_active = false;
        form.years_in_source_club = null;
    }

    if (usesSourceMembership.value && props.prefillMembers.length > 0) {
        const members = props.prefillMembers
            .filter(
                (member) =>
                    membershipType.allows_multiple_members ||
                    member.is_primary_holder,
            )
            .map((member) => buildMemberFromPrefill(member));

        form.members = members;
        void initializeLocationCatalogsForMembers(members);
        void fetchPricingPreview();
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
    void initializeLocationCatalogsForMembers(members);
    void fetchPricingPreview();
};

const sourceMembershipSummary = computed(() => {
    if (!props.sourceMembership) return null;

    return `${props.sourceMembership.club_code} · ${props.sourceMembership.membership_type_name}`;
});

const crossClubTargetSummary = computed(() => {
    if (!props.targetClub) return null;

    return `${props.targetClub.code} - ${props.targetClub.name}`;
});

const submitButtonLabel = computed(() =>
    props.isCrossClubRequest
        ? "Confirmar y generar solicitud"
        : props.isMembershipTransition
          ? "Confirmar cambio"
          : "Confirmar y guardar",
);

const nextButtonLabel = computed(() =>
    step.value === steps.length ? submitButtonLabel.value : "Siguiente",
);

const parseDateInput = (value: string | null): Date | null => {
    if (!value) return null;

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (match) {
        const [, year, month, day] = match;
        const parsedDate = new Date(
            Number(year),
            Number(month) - 1,
            Number(day),
        );

        if (
            parsedDate.getFullYear() === Number(year) &&
            parsedDate.getMonth() === Number(month) - 1 &&
            parsedDate.getDate() === Number(day)
        ) {
            return parsedDate;
        }

        return null;
    }

    const parsedDate = new Date(value);

    return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
};

const formatDate = (value: string | null): string => {
    if (!value) return "-";

    const date = parseDateInput(value);

    if (!date) return value;

    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    }).format(date);
};

const calculateAge = (birthdate: string | null): number | null => {
    if (!birthdate) return null;

    const today = new Date();
    const birth = parseDateInput(birthdate);

    if (!birth) return null;

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

        if (isChildRelationship(member) && age >= 24) {
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

    if (member.is_primary_holder) {
        void fetchPricingPreview();
    }
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
        if (!valid) {
            // Show an error toast if validation fails
            customToastSwal({
                text: "Revisa los datos del formulario. Hay campos requeridos o con formato incorrecto.",
                icon: "warning",
            });
            return;
            
        }
        next();
        return;
    }

    if (step.value === 3) {
        const { valid } = await documentsStepRef.value?.validate();
        /* if (!valid) {
            // Show an error toast if validation fails
            customToastSwal({
                text: "Revisa los documentos requeridos. Hay campos con errores o documentos faltantes.",
                icon: "warning",
            });
            return;
        } */
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
            birth_country_id: member.birth_country_id,
            birth_state_id: member.birth_state_id,
            birth_city_id: member.birth_city_id,
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
            address: {
                ...member.address,
                country_id: member.address.country_id,
                state_id: member.address.state_id,
                city_id: member.address.city_id,
            },
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
    <Head :title="pageTitle" />

    <AppLayout>
        <template #header>{{ pageHeader }}</template>

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
                                    v-if="
                                        usesSourceMembership &&
                                        props.sourceMembership
                                    "
                                    type="info"
                                    variant="tonal"
                                    class="mb-6"
                                >
                                    <strong>Titular:</strong>
                                    {{ props.sourceMembership.holder_name }}
                                    · {{ sourceMembershipSummary }}
                                    · No. cuenta:
                                    {{
                                        props.sourceMembership
                                            .membership_number || "-"
                                    }}
                                </v-alert>

                                <template
                                    v-if="
                                        props.isCrossClubRequest &&
                                        props.sourceMembership
                                    "
                                >
                                    <v-row class="mb-2">
                                        <v-col cols="12" md="4">
                                            <v-card
                                                variant="tonal"
                                                class="pa-4 h-100"
                                            >
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    Solicitud
                                                </div>
                                                <div
                                                    class="text-subtitle-1 font-weight-bold"
                                                >
                                                    Nuevo trámite para el otro
                                                    parque
                                                </div>
                                                <div class="text-body-2 mt-2">
                                                    Se creara una nueva cuenta
                                                    en el club destino.
                                                </div>
                                            </v-card>
                                        </v-col>

                                        <v-col cols="12" md="4">
                                            <v-card
                                                variant="tonal"
                                                class="pa-4 h-100"
                                            >
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    Membresía de origen
                                                </div>
                                                <div
                                                    class="text-subtitle-1 font-weight-bold"
                                                >
                                                    {{
                                                        sourceMembershipSummary
                                                    }}
                                                </div>
                                                <div class="text-body-2 mt-2">
                                                    Titular:
                                                    {{
                                                        props.sourceMembership
                                                            .holder_name
                                                    }}
                                                </div>
                                                <div class="text-body-2">
                                                    No. cuenta:
                                                    {{
                                                        props.sourceMembership
                                                            .membership_number ||
                                                        "-"
                                                    }}
                                                </div>
                                                <div class="text-body-2">
                                                    Inicio:
                                                    {{
                                                        formatDate(
                                                            props
                                                                .sourceMembership
                                                                .start_date,
                                                        )
                                                    }}
                                                </div>
                                            </v-card>
                                        </v-col>

                                        <v-col cols="12" md="4">
                                            <v-card
                                                variant="tonal"
                                                class="pa-4 h-100"
                                            >
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    Club destino
                                                </div>
                                                <div
                                                    class="text-subtitle-1 font-weight-bold"
                                                >
                                                    {{ crossClubTargetSummary }}
                                                </div>
                                                <div class="text-body-2 mt-2">
                                                    El costo se calculará según
                                                    la membresía de origen y el
                                                    tipo destino.
                                                </div>
                                            </v-card>
                                        </v-col>
                                    </v-row>

                                    <v-alert
                                        type="info"
                                        variant="tonal"
                                        class="mb-6"
                                    >
                                        La captura manual de origen se omite en
                                        este flujo porque la solicitud ya parte
                                        de una membresía activa real.
                                    </v-alert>
                                </template>

                                <template
                                    v-else-if="
                                        props.isMembershipTransition &&
                                        props.sourceMembership
                                    "
                                >
                                    <v-row class="mb-2">
                                        <v-col cols="12" md="6">
                                            <v-card
                                                variant="tonal"
                                                class="pa-4 h-100"
                                            >
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    Cambio
                                                </div>
                                                <div
                                                    class="text-subtitle-1 font-weight-bold"
                                                >
                                                    Misma cuenta, mismo no. cuenta
                                                </div>
                                                <div class="text-body-2 mt-2">
                                                    Se conservara el no. cuenta
                                                    {{
                                                        props.sourceMembership
                                                            .membership_number ||
                                                        "-"
                                                    }}
                                                    y se actualizará la cuota de
                                                    esta cuenta.
                                                </div>
                                            </v-card>
                                        </v-col>

                                        <v-col cols="12" md="6">
                                            <v-card
                                                variant="tonal"
                                                class="pa-4 h-100"
                                            >
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    Membresía actual
                                                </div>
                                                <div
                                                    class="text-subtitle-1 font-weight-bold"
                                                >
                                                    {{
                                                        sourceMembershipSummary
                                                    }}
                                                </div>
                                                <div class="text-body-2 mt-2">
                                                    Inicio:
                                                    {{
                                                        formatDate(
                                                            props
                                                                .sourceMembership
                                                                .start_date,
                                                        )
                                                    }}
                                                </div>
                                                <div class="text-body-2">
                                                    Club:
                                                    {{
                                                        props.sourceMembership
                                                            .club_name
                                                    }}
                                                </div>
                                            </v-card>
                                        </v-col>
                                    </v-row>

                                    <v-alert
                                        type="info"
                                        variant="tonal"
                                        class="mb-6"
                                    >
                                        Este flujo actualiza la membresía actual
                                        dentro de la misma cuenta, por eso no se
                                        captura un origen manual.
                                    </v-alert>
                                </template>

                                <v-card
                                    v-if="
                                        form.membershipType &&
                                        (pricingPreviewLoading ||
                                            pricingPreview ||
                                            pricingPreviewError)
                                    "
                                    class="pa-4 mb-6"
                                    variant="tonal"
                                >
                                    <div
                                        class="d-flex flex-wrap align-center justify-space-between ga-2"
                                    >
                                        <div>
                                            <div
                                                class="text-caption text-medium-emphasis"
                                            >
                                                Vista previa de cobro
                                            </div>
                                            <div
                                                class="text-subtitle-1 font-weight-bold"
                                            >
                                                {{
                                                    form.membershipType?.name ||
                                                    "Membresía seleccionada"
                                                }}
                                            </div>
                                        </div>

                                        <v-chip
                                            v-if="pricingPreview?.rule_type"
                                            size="small"
                                            color="primary"
                                            variant="tonal"
                                        >
                                            {{
                                                pricingPreview.rule_type ===
                                                "interclub"
                                                    ? "Paquete interclub"
                                                    : "Regla de precio"
                                            }}
                                        </v-chip>
                                    </div>

                                    <v-row class="mt-2">
                                        <v-col cols="12" md="4">
                                            <v-skeleton-loader
                                                v-if="pricingPreviewLoading"
                                                type="paragraph"
                                            />
                                            <template v-else>
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    Mensualidad
                                                </div>
                                                <div
                                                    class="text-h6 font-weight-bold"
                                                >
                                                    {{
                                                        pricingPreview
                                                            ? currencyFormatter.format(
                                                                  pricingPreview.monthly_fee,
                                                              )
                                                            : "-"
                                                    }}
                                                </div>
                                            </template>
                                        </v-col>

                                        <v-col cols="12" md="4">
                                            <v-skeleton-loader
                                                v-if="pricingPreviewLoading"
                                                type="paragraph"
                                            />
                                            <template v-else>
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    Inscripción
                                                </div>
                                                <div
                                                    class="text-h6 font-weight-bold"
                                                >
                                                    {{
                                                        pricingPreview
                                                            ? currencyFormatter.format(
                                                                  pricingPreview.inscription_fee,
                                                              )
                                                            : "-"
                                                    }}
                                                </div>
                                            </template>
                                        </v-col>

                                        <v-col cols="12" md="4">
                                            <v-skeleton-loader
                                                v-if="pricingPreviewLoading"
                                                type="paragraph"
                                            />
                                            <template v-else>
                                                <div
                                                    class="text-caption text-medium-emphasis"
                                                >
                                                    Total estimado inicial
                                                </div>
                                                <div
                                                    class="text-h6 font-weight-bold"
                                                >
                                                    {{
                                                        pricingPreview
                                                            ? currencyFormatter.format(
                                                                  pricingPreview.total_due,
                                                              )
                                                            : "-"
                                                    }}
                                                </div>
                                            </template>
                                        </v-col>
                                    </v-row>

                                    <v-alert
                                        v-if="
                                            pricingPreviewError &&
                                            !pricingPreviewLoading
                                        "
                                        type="warning"
                                        variant="tonal"
                                        class="mt-4"
                                    >
                                        {{ pricingPreviewError }}
                                    </v-alert>

                                    <v-alert
                                        v-else-if="
                                            pricingPreview?.charge_explanation
                                        "
                                        type="info"
                                        variant="tonal"
                                        class="mt-4"
                                    >
                                        {{ pricingPreview.charge_explanation }}
                                    </v-alert>

                                    <v-alert
                                        v-else-if="
                                            pricingPreview?.source_membership_becomes_non_billable
                                        "
                                        type="info"
                                        variant="tonal"
                                        class="mt-4"
                                    >
                                        Al aplicar esta regla, la membresía
                                        origen dejará de ser cobrable y solo se
                                        considerará este nuevo monto.
                                    </v-alert>
                                </v-card>

                                <v-text-field
                                    v-model="membershipTypeSearch"
                                    label="Buscar membresía"
                                    placeholder="Escribe el código, nombre o descripción"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="outlined"
                                    clearable
                                    class="mb-4"
                                />

                                <v-alert
                                    v-if="filteredMembershipTypes.length === 0"
                                    type="info"
                                    variant="tonal"
                                    class="mb-4"
                                >
                                    No se encontraron membresías con ese
                                    criterio.
                                </v-alert>

                                <v-row>
                                    <v-col
                                        v-for="membershipType in filteredMembershipTypes"
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
                            </v-container>
                        </template>

                        <!-- STEP 2 -->
                        <template #item.2>
                            <v-form ref="familyStepRef">
                                <v-container class="overflow-auto h-[500px]">
                                    <v-alert
                                        v-if="usesSourceMembership"
                                        type="info"
                                        variant="tonal"
                                        class="mb-4"
                                    >
                                        {{
                                            props.isCrossClubRequest
                                                ? "Los integrantes se precargaron desde la membresía de origen para agilizar la solicitud. Puedes ajustar la captura antes de enviarla."
                                                : "Los integrantes se precargaron desde la cuenta actual para agilizar el cambio. Puedes ajustar la captura antes de confirmarla."
                                        }}
                                    </v-alert>

                                    <div
                                        v-for="(member, index) in form.members"
                                        :key="member.local_id"
                                        class="mb-6"
                                    >
                                        <v-card class="pa-4">
                                            <div
                                                class="d-flex justify-space-between align-center mb-4"
                                            >
                                                <div class="d-flex align-center ga-2">
                                                    <h4>
                                                        {{ memberLabel(member) }}
                                                    </h4>
                                                    <v-chip
                                                        v-if="
                                                            isExistingSourceMember(
                                                                member,
                                                            )
                                                        "
                                                        size="small"
                                                        color="info"
                                                        variant="tonal"
                                                    >
                                                        Existente
                                                    </v-chip>
                                                    <v-chip
                                                        v-else-if="
                                                            usesSourceMembership
                                                        "
                                                        size="small"
                                                        color="success"
                                                        variant="tonal"
                                                    >
                                                        Nuevo
                                                    </v-chip>
                                                </div>

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
                                                            member.is_locked ||
                                                            isExistingSourceMember(
                                                                member,
                                                            )
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
                                                        :disabled="
                                                            isIdentityLocked(
                                                                member,
                                                            )
                                                        "
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.last_name
                                                        "
                                                        label="Apellido paterno"
                                                        :rules="[required]"
                                                        :disabled="
                                                            isIdentityLocked(
                                                                member,
                                                            )
                                                        "
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.second_last_name
                                                        "
                                                        label="Apellido materno"
                                                        :disabled="
                                                            isIdentityLocked(
                                                                member,
                                                            )
                                                        "
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
                                                        :disabled="
                                                            isIdentityLocked(
                                                                member,
                                                            )
                                                        "
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
                                                    <v-autocomplete
                                                        v-model="
                                                            member.birth_country_id
                                                        "
                                                        :items="countryOptions"
                                                        item-title="title"
                                                        item-value="id"
                                                        label="País de nacimiento"
                                                        :error-messages="
                                                            memberFieldError(
                                                                index,
                                                                'birth_country_id',
                                                            )
                                                        "
                                                        :disabled="
                                                            isIdentityLocked(
                                                                member,
                                                            )
                                                        "
                                                        clearable
                                                        @update:modelValue="
                                                            onBirthCountryChange(
                                                                member,
                                                                $event,
                                                            )
                                                        "
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
                                                            member.birth_state_id
                                                        "
                                                        :items="
                                                            getStateOptions(
                                                                member.birth_country_id,
                                                            )
                                                        "
                                                        item-title="name"
                                                        item-value="id"
                                                        label="Estado de nacimiento"
                                                        :error-messages="
                                                            memberFieldError(
                                                                index,
                                                                'birth_state_id',
                                                            )
                                                        "
                                                        :disabled="
                                                            isIdentityLocked(
                                                                member,
                                                            ) ||
                                                            !member.birth_country_id
                                                        "
                                                        clearable
                                                        @update:modelValue="
                                                            onBirthStateChange(
                                                                member,
                                                                $event,
                                                            )
                                                        "
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
                                                            member.birth_city_id
                                                        "
                                                        :items="
                                                            getCityOptions(
                                                                member.birth_state_id,
                                                            )
                                                        "
                                                        item-title="name"
                                                        item-value="id"
                                                        label="Ciudad de nacimiento"
                                                        :error-messages="
                                                            memberFieldError(
                                                                index,
                                                                'birth_city_id',
                                                            )
                                                        "
                                                        :disabled="
                                                            isIdentityLocked(
                                                                member,
                                                            ) ||
                                                            !member.birth_state_id
                                                        "
                                                        clearable
                                                        @update:modelValue="
                                                            onBirthCityChange(
                                                                member,
                                                                $event,
                                                            )
                                                        "
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
                                                        :disabled="
                                                            isIdentityLocked(
                                                                member,
                                                            )
                                                        "
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
                                                    <v-autocomplete
                                                        v-model="
                                                            member.address
                                                                .country_id
                                                        "
                                                        :items="countryOptions"
                                                        item-title="title"
                                                        item-value="id"
                                                        label="País"
                                                        :error-messages="
                                                            memberAddressFieldError(
                                                                index,
                                                                'country_id',
                                                            )
                                                        "
                                                        clearable
                                                        @update:modelValue="
                                                            onAddressCountryChange(
                                                                member,
                                                                $event,
                                                            )
                                                        "
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-autocomplete
                                                        v-model="
                                                            member.address
                                                                .state_id
                                                        "
                                                        :items="
                                                            getStateOptions(
                                                                member.address
                                                                    .country_id,
                                                            )
                                                        "
                                                        item-title="name"
                                                        item-value="id"
                                                        label="Estado del domicilio"
                                                        :error-messages="
                                                            memberAddressFieldError(
                                                                index,
                                                                'state_id',
                                                            )
                                                        "
                                                        :disabled="
                                                            !member.address
                                                                .country_id
                                                        "
                                                        clearable
                                                        @update:modelValue="
                                                            onAddressStateChange(
                                                                member,
                                                                $event,
                                                            )
                                                        "
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-autocomplete
                                                        v-model="
                                                            member.address
                                                                .city_id
                                                        "
                                                        :items="
                                                            getCityOptions(
                                                                member.address
                                                                    .state_id,
                                                            )
                                                        "
                                                        item-title="name"
                                                        item-value="id"
                                                        label="Ciudad del domicilio"
                                                        :error-messages="
                                                            memberAddressFieldError(
                                                                index,
                                                                'city_id',
                                                            )
                                                        "
                                                        :disabled="
                                                            !member.address
                                                                .state_id
                                                        "
                                                        clearable
                                                        @update:modelValue="
                                                            onAddressCityChange(
                                                                member,
                                                                $event,
                                                            )
                                                        "
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
                                                        label="Dirección de la empresa"
                                                    />
                                                </v-col>

                                                <v-col cols="12" md="4">
                                                    <v-text-field
                                                        v-model="
                                                            member.employment
                                                                .company_phone
                                                        "
                                                        label="Teléfono de la empresa"
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
                                            Carga los documentos requeridos para
                                            cada integrante.
                                        </p>
                                    </div>

                                    <v-alert
                                        v-if="usesSourceMembership"
                                        type="info"
                                        variant="tonal"
                                        class="mb-4"
                                    >
                                        {{
                                            props.isCrossClubRequest
                                                ? "Estos documentos corresponden a la membresía de destino, aunque el titular ya exista en el otro parque."
                                                : "Estos documentos corresponden al nuevo tipo de membresía que quedará vigente en esta misma cuenta."
                                        }}
                                    </v-alert>

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
                                    <p v-if="props.isCrossClubRequest">
                                        <strong>Tipo de trámite:</strong>
                                        Solicitud para el otro parque
                                    </p>
                                    <p v-if="props.isMembershipTransition">
                                        <strong>Tipo de trámite:</strong>
                                        Cambio de membresía
                                    </p>
                                    <p
                                        v-if="
                                            usesSourceMembership &&
                                            props.sourceMembership
                                        "
                                    >
                                        <strong>Origen:</strong>
                                        {{ sourceMembershipSummary }}
                                    </p>
                                    <p
                                        v-if="
                                            usesSourceMembership &&
                                            props.sourceMembership
                                        "
                                    >
                                        <strong>No. cuenta:</strong>
                                        {{
                                            props.sourceMembership
                                                .membership_number || "-"
                                        }}
                                    </p>
                                    <p v-if="props.isCrossClubRequest">
                                        <strong>Club destino:</strong>
                                        {{ crossClubTargetSummary }}
                                    </p>
                                    <p
                                        v-if="
                                            props.isMembershipTransition &&
                                            props.sourceMembership
                                        "
                                    >
                                        <strong>Club actual:</strong>
                                        {{ props.sourceMembership.club_name }}
                                    </p>
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

                                <v-alert
                                    v-if="props.isCrossClubRequest"
                                    type="info"
                                    variant="tonal"
                                >
                                    Al confirmar, se generará una nueva cuenta
                                    en el parque destino y la solicitud usará la
                                    membresía de origen para resolver el precio.
                                </v-alert>
                                <v-alert
                                    v-if="props.isMembershipTransition"
                                    type="info"
                                    variant="tonal"
                                >
                                    Al confirmar, se actualizará la misma cuenta
                                    y el mismo no. cuenta con el nuevo tipo de
                                    membresía y su cuota correspondiente.
                                </v-alert>
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
                                    v-if="step < steps.length"
                                    color="primary"
                                    @click="handleNext(next)"
                                    :disabled="isNextDisabled"
                                >
                                    {{ nextButtonLabel }}
                                </v-btn>

                                <v-btn v-else color="success" @click="submit">
                                    {{ submitButtonLabel }}
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
