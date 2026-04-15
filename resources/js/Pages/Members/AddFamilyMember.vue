<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { required, selectRequired, validatePhone, email } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

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

interface MemberAddress {
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

interface MemberEmployment {
    company_name: string | null;
    company_address: string | null;
    company_phone: string | null;
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

interface Props {
    membership: SourceMembership;
    account: MembershipAccount;
    relationships?: Relationship[];
    countries?: CountryCatalog[];
    nationalities?: Nationality[];
    maritalStatuses?: MaritalStatus[];
}

const props = withDefaults(defineProps<Props>(), {
    relationships: () => [],
    countries: () => [],
    nationalities: () => [],
    maritalStatuses: () => [],
});

const formRef = ref();
const statesByCountry = ref<Record<number, StateCatalog[]>>({});
const citiesByState = ref<Record<number, CityCatalog[]>>({});

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
        city: "",
        state: "",
        country: "",
        years_in_city: null as number | null,
    },
    employment: {
        company_name: "",
        company_address: "",
        company_phone: "",
    },
});

const relationshipName = computed(
    () =>
        props.relationships.find(
            (relationship) => relationship.id === form.relationship_id,
        )?.name ?? "",
);

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

if (defaultCountry.value) {
    form.address.country_id = defaultCountry.value.id;
    form.address.country = defaultCountry.value.name;
}

const accountHasSpouse = computed(() =>
    props.account.members.some((member) =>
        ["conyuge", "esposo", "esposa"].includes(
            normalizeText(member.relationship_name),
        ),
    ),
);

const relationshipOptions = computed(() =>
    props.relationships
        .filter((relationship) => {
            if (!accountHasSpouse.value) return true;

            return !["conyuge", "esposo", "esposa"].includes(
                normalizeText(relationship.name),
            );
        })
        .map((relationship) => ({
            value: relationship.id,
            title: relationship.name,
        })),
);

const countryOptions = computed(() =>
    props.countries.map((country) => ({
        value: country.id,
        title: country.name,
    })),
);

const nationalityOptions = computed(() =>
    props.nationalities.map((nationality) => ({
        value: nationality.id,
        title: nationality.demonym || nationality.name,
    })),
);

const maritalStatusOptions = computed(() =>
    props.maritalStatuses.map((maritalStatus) => ({
        value: maritalStatus.id,
        title: maritalStatus.name,
    })),
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

const onBirthCountryChange = async (countryId: number | null) => {
    form.birth_country_id = countryId;
    form.birth_place = getCountryName(countryId);
    form.birth_state_id = null;
    form.state = "";
    form.birth_city_id = null;
    form.city = "";

    if (countryId) {
        await fetchStates(countryId);
    }
};

const onBirthStateChange = async (stateId: number | null) => {
    form.birth_state_id = stateId;
    form.state = getStateName(form.birth_country_id, stateId);
    form.birth_city_id = null;
    form.city = "";

    if (stateId) {
        await fetchCities(stateId);
    }
};

const onBirthCityChange = (cityId: number | null) => {
    form.birth_city_id = cityId;
    form.city = getCityName(form.birth_state_id, cityId);
};

const onAddressCountryChange = async (countryId: number | null) => {
    form.address.country_id = countryId;
    form.address.country = getCountryName(countryId);
    form.address.state_id = null;
    form.address.state = "";
    form.address.city_id = null;
    form.address.city = "";

    if (countryId) {
        await fetchStates(countryId);
    }
};

const onAddressStateChange = async (stateId: number | null) => {
    form.address.state_id = stateId;
    form.address.state = getStateName(form.address.country_id, stateId);
    form.address.city_id = null;
    form.address.city = "";

    if (stateId) {
        await fetchCities(stateId);
    }
};

const onAddressCityChange = (cityId: number | null) => {
    form.address.city_id = cityId;
    form.address.city = getCityName(form.address.state_id, cityId);
};

if (form.address.country_id) {
    void fetchStates(form.address.country_id);
}

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
    ) {
        return null;
    }

    return parsedDate;
};

const calculateAge = (birthdate: string | null): number | null => {
    const birth = parseDateInput(birthdate);

    if (!birth) return null;

    const today = new Date();
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

const currentAge = computed(() => calculateAge(form.birthdate));

const isChildRelationship = computed(() =>
    ["hijo(a)", "hijo", "hija"].includes(normalizeText(relationshipName.value)),
);

const birthdateRule = (value: string | null) => {
    if (!value) return "La fecha de nacimiento es requerida";

    const age = calculateAge(value);

    if (age === null || age < 0) {
        return "La fecha de nacimiento no es válida";
    }

    if (isChildRelationship.value && age >= 24) {
        return "Los hijos no pueden ser mayores de 24 años";
    }

    return true;
};

const submit = async () => {
    const { valid } = await formRef.value?.validate();

    if (!valid) return;

    form.post(route("members.family-members.store", props.membership.id), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "",
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
                        <v-card class="pa-4 mb-4" variant="tonal">
                            <div class="text-subtitle-1 font-weight-bold mb-2">
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
                                <strong>Membresia:</strong>
                                {{ props.membership.membership_type_name }}
                            </p>
                        </v-card>

                        <v-alert type="info" variant="tonal" class="mb-4">
                            Este flujo agrega un integrante a la misma cuenta familiar.
                            La documentacion puede quedar pendiente por ahora.
                        </v-alert>

                        <v-form ref="formRef" @submit.prevent="submit">
                            <v-card class="pa-4">
                                <div class="text-subtitle-1 font-weight-bold mb-4">
                                    Datos del nuevo familiar
                                </div>

                                <v-row>
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
                                            :rules="[birthdateRule]"
                                            :error-messages="form.errors.birthdate"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="form.first_name"
                                            label="Nombre(s)"
                                            :rules="[required]"
                                            :error-messages="form.errors.first_name"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="form.last_name"
                                            label="Apellido paterno"
                                            :rules="[required]"
                                            :error-messages="form.errors.last_name"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="form.second_last_name"
                                            label="Apellido materno"
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
                                        <v-autocomplete
                                            v-model="form.birth_country_id"
                                            :items="countryOptions"
                                            item-title="title"
                                            item-value="value"
                                            label="País de nacimiento"
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
                                            v-model="form.email"
                                            label="Correo"
                                            :rules="[email]"
                                            :error-messages="form.errors.email"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="form.occupation"
                                            label="Ocupación"
                                            :error-messages="form.errors.occupation"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="form.school_name"
                                            label="Escuela"
                                            :error-messages="form.errors.school_name"
                                        />
                                    </v-col>
                                </v-row>

                                <v-divider class="my-6" />

                                <div class="text-subtitle-1 font-weight-bold mb-4">
                                    Domicilio
                                </div>

                                <v-row>
                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.address.street"
                                            label="Calle"
                                            :error-messages="form.errors['address.street']"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="6">
                                        <v-text-field
                                            v-model="form.address.neighborhood"
                                            label="Colonia"
                                            :error-messages="form.errors['address.neighborhood']"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="3">
                                        <v-text-field
                                            v-model="form.address.postal_code"
                                            label="Código postal"
                                            :error-messages="form.errors['address.postal_code']"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="3">
                                        <v-autocomplete
                                            v-model="form.address.country_id"
                                            :items="countryOptions"
                                            item-title="title"
                                            item-value="value"
                                            label="País"
                                            :error-messages="form.errors['address.country_id']"
                                            clearable
                                            @update:modelValue="onAddressCountryChange"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="3">
                                        <v-autocomplete
                                            v-model="form.address.state_id"
                                            :items="getStateOptions(form.address.country_id)"
                                            item-title="name"
                                            item-value="id"
                                            label="Estado"
                                            :error-messages="form.errors['address.state_id']"
                                            :disabled="!form.address.country_id"
                                            clearable
                                            @update:modelValue="onAddressStateChange"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="3">
                                        <v-autocomplete
                                            v-model="form.address.city_id"
                                            :items="getCityOptions(form.address.state_id)"
                                            item-title="name"
                                            item-value="id"
                                            label="Ciudad"
                                            :error-messages="form.errors['address.city_id']"
                                            :disabled="!form.address.state_id"
                                            clearable
                                            @update:modelValue="onAddressCityChange"
                                        />
                                    </v-col>
                                </v-row>

                                <v-divider class="my-6" />

                                <div class="text-subtitle-1 font-weight-bold mb-4">
                                    Trabajo
                                </div>

                                <v-row>
                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="form.employment.company_name"
                                            label="Empresa"
                                            :error-messages="form.errors['employment.company_name']"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="form.employment.company_address"
                                            label="Dirección empresa"
                                            :error-messages="form.errors['employment.company_address']"
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-text-field
                                            v-model="form.employment.company_phone"
                                            label="Teléfono empresa"
                                            :rules="[validatePhone]"
                                            :error-messages="form.errors['employment.company_phone']"
                                        />
                                    </v-col>
                                </v-row>

                                <div class="d-flex justify-end ga-2 mt-6">
                                    <v-btn
                                        variant="text"
                                        @click="
                                            router.visit(
                                                route(
                                                    'members.manage.show',
                                                    props.membership.id,
                                                ),
                                            )
                                        "
                                    >
                                        Cancelar
                                    </v-btn>
                                    <v-btn
                                        color="primary"
                                        :loading="form.processing"
                                        @click="submit"
                                    >
                                        Guardar familiar
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
