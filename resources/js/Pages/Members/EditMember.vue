<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { ref, watch } from "vue";

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

interface Props {
    membership: { id: number; membership_number: string; holder_name: string };
    accountMember: AccountMember;
    countries: Country[];
    nationalities: Country[];
    relationships: Catalog[];
    maritalStatuses: Catalog[];
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

/* ─────────────────────────────
 * FORMULARIO
 * ───────────────────────────── */
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
        street:       props.accountMember.address.street ?? "",
        neighborhood: props.accountMember.address.neighborhood ?? "",
        postal_code:  props.accountMember.address.postal_code ?? "",
        country_id:   props.accountMember.address.country_id,
        state_id:     props.accountMember.address.state_id,
        city_id:      props.accountMember.address.city_id,
        years_in_city: props.accountMember.address.years_in_city,
    },
    employment: {
        company_name:    props.accountMember.employment.company_name ?? "",
        company_address: props.accountMember.employment.company_address ?? "",
        company_phone:   props.accountMember.employment.company_phone ?? "",
    },
});

/* ─────────────────────────────
 * CATÁLOGOS CASCADING
 * ───────────────────────────── */
// Lugar de nacimiento
const birthStates = ref<Catalog[]>([]);
const birthCities = ref<Catalog[]>([]);

const loadBirthStates = async (countryId: number | null) => {
    birthStates.value = [];
    birthCities.value = [];
    form.birth_state_id = null;
    form.birth_city_id = null;
    if (!countryId) return;
    const res = await fetch(route("members.location-catalogs.states", { country_id: countryId }));
    birthStates.value = await res.json();
};

const loadBirthCities = async (stateId: number | null) => {
    birthCities.value = [];
    form.birth_city_id = null;
    if (!stateId) return;
    const res = await fetch(route("members.location-catalogs.cities", { state_id: stateId }));
    birthCities.value = await res.json();
};

// Domicilio
const addressStates = ref<Catalog[]>([]);
const addressCities = ref<Catalog[]>([]);

const loadAddressStates = async (countryId: number | null) => {
    addressStates.value = [];
    addressCities.value = [];
    form.address.state_id = null;
    form.address.city_id = null;
    if (!countryId) return;
    const res = await fetch(route("members.location-catalogs.states", { country_id: countryId }));
    addressStates.value = await res.json();
};

const loadAddressCities = async (stateId: number | null) => {
    addressCities.value = [];
    form.address.city_id = null;
    if (!stateId) return;
    const res = await fetch(route("members.location-catalogs.cities", { state_id: stateId }));
    addressCities.value = await res.json();
};

// Carga inicial si ya hay valores
if (props.accountMember.birth_country_id) {
    loadBirthStates(props.accountMember.birth_country_id).then(() => {
        if (props.accountMember.birth_state_id) {
            loadBirthCities(props.accountMember.birth_state_id);
        }
    });
}
if (props.accountMember.address.country_id) {
    loadAddressStates(props.accountMember.address.country_id).then(() => {
        if (props.accountMember.address.state_id) {
            loadAddressCities(props.accountMember.address.state_id);
        }
    });
}

watch(() => form.birth_country_id, loadBirthStates);
watch(() => form.birth_state_id,   loadBirthCities);
watch(() => form.address.country_id, loadAddressStates);
watch(() => form.address.state_id,   loadAddressCities);

/* ─────────────────────────────
 * ACCIONES
 * ───────────────────────────── */
const submit = () => {
    form.put(
        route("members.member.update", {
            membership: props.membership.id,
            member: props.accountMember.member_id,
        }),
        { preserveScroll: true }
    );
};

const cancel = () => {
    router.visit(route("members.manage.show", props.membership.id));
};
</script>

<template>
    <Head title="Editar integrante" />

    <AppLayout>
        <template #header>Editar integrante</template>

        <div class="d-flex flex-column ga-4">

            <!-- Datos personales -->
            <v-card class="pa-4">
                <div class="text-subtitle-1 font-weight-bold mb-4">Datos personales</div>
                <v-row>
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.first_name"
                            label="Nombre(s)"
                            :error-messages="form.errors.first_name"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.last_name"
                            label="Apellido paterno"
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
                            v-model="form.birthdate"
                            label="Fecha de nacimiento"
                            type="date"
                            :error-messages="form.errors.birthdate"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="form.marital_status_id"
                            :items="props.maritalStatuses"
                            item-title="name"
                            item-value="id"
                            label="Estado civil"
                            clearable
                            :error-messages="form.errors.marital_status_id"
                        />
                    </v-col>
                    <v-col v-if="!props.accountMember.is_primary_holder" cols="12" md="4">
                        <v-autocomplete
                            v-model="form.relationship_id"
                            :items="props.relationships"
                            item-title="name"
                            item-value="id"
                            label="Parentesco"
                            clearable
                            :error-messages="form.errors.relationship_id"
                        />
                    </v-col>
                </v-row>
            </v-card>

            <!-- Contacto y ocupación -->
            <v-card class="pa-4">
                <div class="text-subtitle-1 font-weight-bold mb-4">Contacto</div>
                <v-row>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.phone"
                            label="Teléfono"
                            :error-messages="form.errors.phone"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.email"
                            label="Correo electrónico"
                            type="email"
                            :error-messages="form.errors.email"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-autocomplete
                            v-model="form.nationality_id"
                            :items="props.nationalities"
                            :item-title="countryName"
                            item-value="id"
                            label="Nacionalidad"
                            clearable
                            :error-messages="form.errors.nationality_id"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.occupation"
                            label="Ocupación"
                            :error-messages="form.errors.occupation"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.school_name"
                            label="Escuela (si aplica)"
                            :error-messages="form.errors.school_name"
                        />
                    </v-col>
                </v-row>
            </v-card>

            <!-- Lugar de nacimiento -->
            <v-card class="pa-4">
                <div class="text-subtitle-1 font-weight-bold mb-4">Lugar de nacimiento</div>
                <v-row>
                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="form.birth_country_id"
                            :items="props.countries"
                            :item-title="countryName"
                            item-value="id"
                            label="País"
                            clearable
                            :error-messages="form.errors.birth_country_id"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="form.birth_state_id"
                            :items="birthStates"
                            item-title="name"
                            item-value="id"
                            label="Estado"
                            clearable
                            :disabled="!form.birth_country_id"
                            :error-messages="form.errors.birth_state_id"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="form.birth_city_id"
                            :items="birthCities"
                            item-title="name"
                            item-value="id"
                            label="Ciudad"
                            clearable
                            :disabled="!form.birth_state_id"
                            :error-messages="form.errors.birth_city_id"
                        />
                    </v-col>
                </v-row>
            </v-card>

            <!-- Domicilio -->
            <v-card class="pa-4">
                <div class="text-subtitle-1 font-weight-bold mb-4">Domicilio</div>
                <v-row>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.address.street"
                            label="Calle y número"
                            :error-messages="(form.errors as any)['address.street']"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.address.neighborhood"
                            label="Colonia"
                            :error-messages="(form.errors as any)['address.neighborhood']"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.address.postal_code"
                            label="Código postal"
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
                            clearable
                            :error-messages="(form.errors as any)['address.country_id']"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="form.address.state_id"
                            :items="addressStates"
                            item-title="name"
                            item-value="id"
                            label="Estado"
                            clearable
                            :disabled="!form.address.country_id"
                            :error-messages="(form.errors as any)['address.state_id']"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-autocomplete
                            v-model="form.address.city_id"
                            :items="addressCities"
                            item-title="name"
                            item-value="id"
                            label="Ciudad"
                            clearable
                            :disabled="!form.address.state_id"
                            :error-messages="(form.errors as any)['address.city_id']"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model.number="form.address.years_in_city"
                            label="Años viviendo en la ciudad"
                            type="number"
                            min="0"
                            :error-messages="(form.errors as any)['address.years_in_city']"
                        />
                    </v-col>
                </v-row>
            </v-card>

            <!-- Empleo -->
            <v-card class="pa-4">
                <div class="text-subtitle-1 font-weight-bold mb-4">Información de empleo</div>
                <v-row>
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.employment.company_name"
                            label="Empresa"
                            :error-messages="(form.errors as any)['employment.company_name']"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.employment.company_address"
                            label="Dirección de la empresa"
                            :error-messages="(form.errors as any)['employment.company_address']"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.employment.company_phone"
                            label="Teléfono de la empresa"
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
    </AppLayout>
</template>
