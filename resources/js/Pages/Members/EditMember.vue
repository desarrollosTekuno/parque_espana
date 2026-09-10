<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import {
    required,
    selectRequired,
    validatePhone,
    email,
    minLength,
    maxLength,
    postalCode,
} from "@/constants/validationRules";
import { customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
const page = usePage<any>();
/* ─────────────────────────────
 * INTERFACES
 * ───────────────────────────── */
interface Catalog { id: number; name: string; code?: string; }
interface Country { id: number; name: string; code?: string; translations?: Record<string, string>; demonym?: string; }

interface AccountMember {
    member_id: number;
    is_primary_holder: boolean;
    relationship_id: number | null;
    first_name: string;
    last_name: string;
    second_last_name: string | null;
    birthdate: string | null;
    phone: string | null;
    email: string | null;
    birth_country_id: number | null;
    birth_state_id: number | null;
    birth_city_id: number | null;
    nationality_id: number | null;
    marital_status_id: number | null;
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

interface AccountFiscalData {
    fiscal_name: string;
    rfc: string;
    cfdi_use: string;
    fiscal_regime: string;
    postal_code: string;
}

interface Props {
    membership: { id: number; membership_number: string; holder_name: string };
    accountMember: AccountMember;
    countries: Country[];
    nationalities: Country[];
    relationships: Catalog[];
    maritalStatuses: Catalog[];
    fiscalData: AccountFiscalData | null;
}

/* ─────────────────────────────
 * PROPS
 * ───────────────────────────── */
const props = defineProps<Props>();

/* ─────────────────────────────
 * HELPERS
 * ───────────────────────────── */
const countryName = (c: Country) =>
    (c.translations?.["es-MX"] ?? c.translations?.["es"] ?? c.name);

const nationalityTitle = (c: Country) =>
    c.demonym || c.translations?.["es-MX"] || c.translations?.["es"] || c.name;

const normalizeText = (value: string | null | undefined) =>
    (value ?? "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();

/* ─────────────────────────────
 * FORMULARIO
 * ───────────────────────────── */
const formRef = ref();

const form = useForm({
    first_name:        props.accountMember.first_name,
    last_name:         props.accountMember.last_name,
    second_last_name:  props.accountMember.second_last_name ?? "",
    birthdate:         props.accountMember.birthdate ?? "",
    phone:             props.accountMember.phone ?? "",
    email:             props.accountMember.email ?? "",
    birth_country_id:  props.accountMember.birth_country_id,
    birth_state_id:    props.accountMember.birth_state_id,
    birth_city_id:     props.accountMember.birth_city_id,
    nationality_id:    props.accountMember.nationality_id,
    marital_status_id: props.accountMember.marital_status_id,
    occupation:        props.accountMember.occupation ?? "",
    school_name:       props.accountMember.school_name ?? "",
    relationship_id:   props.accountMember.relationship_id,
    address: {
        street:        props.accountMember.address.street ?? "",
        neighborhood:  props.accountMember.address.neighborhood ?? "",
        postal_code:   props.accountMember.address.postal_code ?? "",
        country_id:    props.accountMember.address.country_id,
        state_id:      props.accountMember.address.state_id,
        city_id:       props.accountMember.address.city_id,
        years_in_city: props.accountMember.address.years_in_city,
    },
    employment: {
        company_name:    props.accountMember.employment.company_name ?? "",
        company_address: props.accountMember.employment.company_address ?? "",
        company_phone:   props.accountMember.employment.company_phone ?? "",
    },
});

const fiscalDataForm = useForm({
    fiscal_name: props.fiscalData?.fiscal_name ?? "",
    rfc: props.fiscalData?.rfc ?? "",
    cfdi_use: props.fiscalData?.cfdi_use ?? "",
    fiscal_regime: props.fiscalData?.fiscal_regime ?? "",
    postal_code: props.fiscalData?.postal_code ?? "",
});

/* ─────────────────────────────
 * RELACIÓN / VISIBILIDAD
 * ───────────────────────────── */
const isPrimaryHolder = computed(() => props.accountMember.is_primary_holder);

const relationshipName = computed(() =>
    props.relationships.find(r => r.id === form.relationship_id)?.name ?? ""
);

const isSpouseRelationship = computed(() =>
    ["conyuge", "esposo", "esposa"].includes(normalizeText(relationshipName.value))
);

const isChildRelationship = computed(() =>
    ["hijo(a)", "hijo", "hija"].includes(normalizeText(relationshipName.value))
);

/* ─────────────────────────────
 * REGLA FECHA DE NACIMIENTO
 * ───────────────────────────── */
const parseDateInput = (value: string | null): Date | null => {
    if (!value) return null;
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) return null;
    const [, year, month, day] = match;
    const d = new Date(Number(year), Number(month) - 1, Number(day));
    return d.getFullYear() === Number(year) &&
           d.getMonth() === Number(month) - 1 &&
           d.getDate() === Number(day) ? d : null;
};

const calculateAge = (birthdate: string | null): number | null => {
    const birth = parseDateInput(birthdate);
    if (!birth) return null;
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    return age;
};

const birthdateRule = (value: string | null) => {
    if (!value) return "La fecha de nacimiento es requerida";
    const age = calculateAge(value);
    if (age === null || age < 0) return "La fecha de nacimiento no es válida";
    if (isChildRelationship.value && age >= 24) return "Los hijos no pueden ser mayores de 24 años";
    return true;
};

/* ─────────────────────────────
 * CATÁLOGOS CASCADING
 * ───────────────────────────── */
const birthStates   = ref<Catalog[]>([]);
const birthCities   = ref<Catalog[]>([]);
const addressStates = ref<Catalog[]>([]);
const addressCities = ref<Catalog[]>([]);

const fetchStates = async (countryId: number): Promise<Catalog[]> => {
    const res = await fetch(route("members.location-catalogs.states", { country_id: countryId }));
    return res.json();
};

const fetchCities = async (stateId: number): Promise<Catalog[]> => {
    const res = await fetch(route("members.location-catalogs.cities", { state_id: stateId }));
    return res.json();
};

// Carga inicial — solo rellena catálogos sin tocar los valores del form
if (props.accountMember.birth_country_id) {
    fetchStates(props.accountMember.birth_country_id).then(states => {
        birthStates.value = states;
        if (props.accountMember.birth_state_id) {
            fetchCities(props.accountMember.birth_state_id).then(cities => {
                birthCities.value = cities;
            });
        }
    });
}
if (props.accountMember.address.country_id) {
    fetchStates(props.accountMember.address.country_id).then(states => {
        addressStates.value = states;
        if (props.accountMember.address.state_id) {
            fetchCities(props.accountMember.address.state_id).then(cities => {
                addressCities.value = cities;
            });
        }
    });
}

// Handlers de cambio de usuario — sí resetean los niveles inferiores
const onBirthCountryChange = async (countryId: number | null) => {
    form.birth_state_id = null;
    form.birth_city_id  = null;
    birthStates.value   = [];
    birthCities.value   = [];
    if (countryId) birthStates.value = await fetchStates(countryId);
};

const onBirthStateChange = async (stateId: number | null) => {
    form.birth_city_id = null;
    birthCities.value  = [];
    if (stateId) birthCities.value = await fetchCities(stateId);
};

const onAddressCountryChange = async (countryId: number | null) => {
    form.address.state_id = null;
    form.address.city_id  = null;
    addressStates.value   = [];
    addressCities.value   = [];
    if (countryId) addressStates.value = await fetchStates(countryId);
};

const onAddressStateChange = async (stateId: number | null) => {
    form.address.city_id = null;
    addressCities.value  = [];
    if (stateId) addressCities.value = await fetchCities(stateId);
};

/* ─────────────────────────────
 * ACCIONES
 * ───────────────────────────── */
const submit = async () => {
    const { valid } = await formRef.value?.validate();
    if (!valid) {
        customToastSwal({
            text: "Revisa los datos del formulario. Hay campos requeridos o con formato incorrecto.",
            icon: "warning",
        });
        return;
    }

    form.put(
        route("members.member.update", {
            membership: props.membership.id,
            member: props.accountMember.member_id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({
                    title: page.props.flash.success || "Integrante actualizado exitosamente",
                    icon: "success",
                });
            },
            onError: () => {
                customToastSwal({
                    title: `Error: ${form.errors.messageError || "No se pudo actualizar la información"}`,
                    text: form.errors.exception || "",
                    icon: "error",
                });
            },
        }
    );
};

const saveFiscalData = () => {
    fiscalDataForm.patch(route("members.fiscal-data.update", props.membership.id), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: "Datos fiscales actualizados",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: "No se pudieron guardar los datos fiscales",
                icon: "error",
            });
        },
    });
};

const cancel = () => {
    router.visit(route("members.manage.show", props.membership.id));
};
</script>

<template>
    <Head title="Editar integrante" />

    <AppLayout>
        <template #header>Editar integrante</template>

        <v-form ref="formRef">
            <div class="d-flex flex-column ga-4">

                <!-- Datos personales -->
                <v-card class="pa-4">
                    <div class="text-subtitle-1 font-weight-bold mb-4">Datos personales</div>
                    <v-row>
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
                                v-model="form.birthdate"
                                label="Fecha de nacimiento"
                                type="date"
                                :rules="[birthdateRule]"
                                :error-messages="form.errors.birthdate"
                            />
                        </v-col>
                        <v-col v-if="isPrimaryHolder || isSpouseRelationship" cols="12" md="4">
                            <v-autocomplete
                                v-model="form.marital_status_id"
                                :items="props.maritalStatuses"
                                item-title="name"
                                item-value="id"
                                label="Estado civil"
                                :rules="[selectRequired]"
                                clearable
                                :error-messages="form.errors.marital_status_id"
                            />
                        </v-col>
                        <v-col v-if="!isPrimaryHolder" cols="12" md="4">
                            <v-autocomplete
                                v-model="form.relationship_id"
                                :items="props.relationships"
                                item-title="name"
                                item-value="id"
                                label="Parentesco"
                                :rules="[selectRequired]"
                                clearable
                                :error-messages="form.errors.relationship_id"
                            />
                        </v-col>
                    </v-row>
                </v-card>

                <!-- Contacto -->
                <v-card class="pa-4">
                    <div class="text-subtitle-1 font-weight-bold mb-4">Contacto</div>
                    <v-row>
                        <v-col v-if="isPrimaryHolder || isSpouseRelationship" cols="12" md="6">
                            <v-text-field
                                v-model="form.phone"
                                label="Teléfono"
                                :rules="isPrimaryHolder ? [required, validatePhone] : [validatePhone]"
                                :error-messages="form.errors.phone"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.email"
                                label="Correo electrónico"
                                type="email"
                                :rules="isPrimaryHolder ? [required, email, maxLength(255)] : [email, maxLength(255)]"
                                :error-messages="form.errors.email"
                            />
                        </v-col>
                        <v-col v-if="isPrimaryHolder || isSpouseRelationship" cols="12" md="6">
                            <v-autocomplete
                                v-model="form.nationality_id"
                                :items="props.nationalities"
                                :item-title="nationalityTitle"
                                item-value="id"
                                label="Nacionalidad"
                                :rules="[selectRequired]"
                                clearable
                                :error-messages="form.errors.nationality_id"
                            />
                        </v-col>
                        <v-col v-if="isPrimaryHolder" cols="12" md="6">
                            <v-text-field
                                v-model="form.occupation"
                                label="Ocupación"
                                :rules="[required, minLength(2), maxLength(100)]"
                                :error-messages="form.errors.occupation"
                            />
                        </v-col>
                        <v-col v-if="isChildRelationship" cols="12" md="6">
                            <v-text-field
                                v-model="form.school_name"
                                label="Colegio"
                                :rules="[required, minLength(3), maxLength(150)]"
                                :error-messages="form.errors.school_name"
                            />
                        </v-col>
                    </v-row>
                </v-card>

                <!-- Lugar de nacimiento (titular y cónyuge) -->
                <v-card v-if="isPrimaryHolder || isSpouseRelationship" class="pa-4">
                    <div class="text-subtitle-1 font-weight-bold mb-4">Lugar de nacimiento</div>
                    <v-row>
                        <v-col cols="12" md="4">
                            <v-autocomplete
                                v-model="form.birth_country_id"
                                :items="props.countries"
                                :item-title="countryName"
                                item-value="id"
                                label="País de nacimiento"
                                :rules="[selectRequired]"
                                clearable
                                :error-messages="form.errors.birth_country_id"
                                @update:modelValue="onBirthCountryChange"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-autocomplete
                                v-model="form.birth_state_id"
                                :items="birthStates"
                                item-title="name"
                                item-value="id"
                                label="Estado de nacimiento"
                                :rules="[selectRequired]"
                                clearable
                                :disabled="!form.birth_country_id"
                                :error-messages="form.errors.birth_state_id"
                                @update:modelValue="onBirthStateChange"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-autocomplete
                                v-model="form.birth_city_id"
                                :items="birthCities"
                                item-title="name"
                                item-value="id"
                                label="Ciudad de nacimiento"
                                clearable
                                :disabled="!form.birth_state_id"
                                :error-messages="form.errors.birth_city_id"
                            />
                        </v-col>
                    </v-row>
                </v-card>

                <!-- Domicilio (solo titular) -->
                <v-card v-if="isPrimaryHolder" class="pa-4">
                    <div class="text-subtitle-1 font-weight-bold mb-4">Domicilio</div>
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.address.street"
                                label="Calle y número"
                                :rules="[required, minLength(3), maxLength(150)]"
                                :error-messages="(form.errors as any)['address.street']"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.address.neighborhood"
                                label="Colonia"
                                :rules="[required, minLength(3), maxLength(150)]"
                                :error-messages="(form.errors as any)['address.neighborhood']"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.address.postal_code"
                                label="Código postal"
                                :rules="[postalCode]"
                                :error-messages="(form.errors as any)['address.postal_code']"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-autocomplete
                                v-model="form.address.country_id"
                                :items="props.countries"
                                :item-title="countryName"
                                item-value="id"
                                label="País"
                                :rules="[selectRequired]"
                                clearable
                                :error-messages="(form.errors as any)['address.country_id']"
                                @update:modelValue="onAddressCountryChange"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-autocomplete
                                v-model="form.address.state_id"
                                :items="addressStates"
                                item-title="name"
                                item-value="id"
                                label="Estado del domicilio"
                                :rules="[selectRequired]"
                                clearable
                                :disabled="!form.address.country_id"
                                :error-messages="(form.errors as any)['address.state_id']"
                                @update:modelValue="onAddressStateChange"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-autocomplete
                                v-model="form.address.city_id"
                                :items="addressCities"
                                item-title="name"
                                item-value="id"
                                label="Ciudad del domicilio"
                                clearable
                                :disabled="!form.address.state_id"
                                :error-messages="(form.errors as any)['address.city_id']"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-number-input
                                v-model="form.address.years_in_city"
                                label="Años radicando en la ciudad"
                                :rules="[required]"
                                :error-messages="(form.errors as any)['address.years_in_city']"
                            />
                        </v-col>
                    </v-row>
                </v-card>

                <!-- Datos fiscales (solo titular) -->
                <v-card v-if="isPrimaryHolder" class="pa-4">
                    <div class="text-subtitle-1 font-weight-bold mb-1">Datos fiscales</div>
                    <div class="text-body-2 text-medium-emphasis mb-4">Información del receptor utilizada en los tickets.</div>
                    <v-row>
                        <v-col cols="12">
                            <v-text-field
                                v-model="fiscalDataForm.fiscal_name"
                                label="Nombre o razón social"
                                :error-messages="fiscalDataForm.errors.fiscal_name"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="fiscalDataForm.rfc"
                                label="RFC"
                                maxlength="20"
                                :error-messages="fiscalDataForm.errors.rfc"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="fiscalDataForm.postal_code"
                                label="Código postal fiscal"
                                maxlength="10"
                                :error-messages="fiscalDataForm.errors.postal_code"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="fiscalDataForm.fiscal_regime"
                                label="Régimen fiscal"
                                maxlength="10"
                                :error-messages="fiscalDataForm.errors.fiscal_regime"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="fiscalDataForm.cfdi_use"
                                label="Uso de CFDI"
                                maxlength="10"
                                :error-messages="fiscalDataForm.errors.cfdi_use"
                            />
                        </v-col>
                    </v-row>
                    <div class="d-flex justify-end">
                        <v-btn
                            color="primary"
                            variant="tonal"
                            :loading="fiscalDataForm.processing"
                            @click="saveFiscalData"
                        >
                            Guardar datos fiscales
                        </v-btn>
                    </div>
                </v-card>

                <!-- Empleo (solo titular) -->
                <v-card v-if="isPrimaryHolder" class="pa-4">
                    <div class="text-subtitle-1 font-weight-bold mb-4">Información laboral</div>
                    <v-row>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.employment.company_name"
                                label="Empresa"
                                :rules="[required, minLength(2), maxLength(150)]"
                                :error-messages="(form.errors as any)['employment.company_name']"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.employment.company_address"
                                label="Dirección de la empresa"
                                :rules="[required, minLength(5), maxLength(255)]"
                                :error-messages="(form.errors as any)['employment.company_address']"
                            />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.employment.company_phone"
                                label="Teléfono de la empresa"
                                :rules="[required, validatePhone]"
                                :error-messages="(form.errors as any)['employment.company_phone']"
                            />
                        </v-col>
                    </v-row>
                </v-card>

                <!-- Acciones -->
                <div class="d-flex justify-end ga-3 pb-4">
                    <v-btn variant="text" @click="cancel">Cancelar</v-btn>
                    <v-btn
                        color="primary"
                        variant="elevated"
                        :loading="form.processing"
                        @click="submit"
                    >
                        Guardar cambios
                    </v-btn>
                </div>

            </div>
        </v-form>
    </AppLayout>
</template>
