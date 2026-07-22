<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";

interface PricingRuleRow {
    id: number;
    membership_type_name: string | null;
    membership_type_code: string | null;
    from_membership_type_id: number | null;
    from_membership_type_name: string | null;
    from_membership_type_code: string | null;
    min_age: number | null;
    max_age: number | null;
    requires_multiple_clubs: boolean;
    priority: number;
    is_active: boolean;
    monthly_fee: number | null;
    inscription_fee: number | null;
    has_explicit_year: boolean;
}

interface InterclubRuleRow {
    id: number;
    source_club_code: string | null;
    source_membership_type_name: string | null;
    target_membership_type_name: string | null;
    target_membership_type_code: string | null;
    package_code: string;
    priority: number;
    is_active: boolean;
    monthly_fee: number | null;
    inscription_fee: number | null;
    has_explicit_year: boolean;
}

interface CurrentClub {
    id: number;
    name: string;
    code: string;
}

interface Props {
    pricingRules?: PricingRuleRow[];
    interclubRules?: InterclubRuleRow[];
    year?: number;
    currentClub?: CurrentClub | null;
}

const props = withDefaults(defineProps<Props>(), {
    pricingRules: () => [],
    interclubRules: () => [],
    year: () => new Date().getFullYear(),
    currentClub: null,
});

const page = usePage<any>();
const can = page.props.auth.permissions;
const loading = ref(false);
const saving = ref(false);
const currentYear = new Date().getFullYear();
const selectedYear = ref<number>(props.year);
const yearOptions = Array.from({ length: currentYear + 1 - 2010 + 1 }, (_, i) => currentYear + 1 - i).map(
    (year) => ({ title: String(year), value: year }),
);

const pricingRows = ref<PricingRuleRow[]>(props.pricingRules.map((row) => ({ ...row })));
const interclubRows = ref<InterclubRuleRow[]>(props.interclubRules.map((row) => ({ ...row })));
const currentClub = ref<CurrentClub | null>(props.currentClub);

const singleClubLabel = (): string => currentClub.value?.name ?? "Club único";

// ── Filtros (client-side, la tabla ya trae todos los renglones del club) ──
const pricingDestinationFilter = ref<string | null>(null);
const pricingConditionFilter = ref<boolean | null>(null);
const pricingActiveFilter = ref<boolean | null>(null);

const destinationMembershipOptions = computed(() => {
    const names = new Set(
        pricingRows.value
            .map((row) => row.membership_type_name)
            .filter((name): name is string => !!name),
    );

    return Array.from(names)
        .sort((a, b) => a.localeCompare(b))
        .map((name) => ({ title: name, value: name }));
});

const conditionOptions = computed(() => [
    { title: "Todas", value: null },
    { title: "Ambos parques", value: true },
    { title: singleClubLabel(), value: false },
]);

const yesNoOptions = [
    { title: "Todas", value: null },
    { title: "Activas", value: true },
    { title: "Inactivas", value: false },
];

const filteredPricingRows = computed(() => {
    return pricingRows.value.filter((row) => {
        if (pricingDestinationFilter.value && row.membership_type_name !== pricingDestinationFilter.value) {
            return false;
        }

        if (pricingConditionFilter.value !== null && row.requires_multiple_clubs !== pricingConditionFilter.value) {
            return false;
        }

        if (pricingActiveFilter.value !== null && row.is_active !== pricingActiveFilter.value) {
            return false;
        }

        return true;
    });
});

const ageRangeLabel = (row: PricingRuleRow) => {
    if (row.min_age === null && row.max_age === null) {
        return "";
    }
    if (row.min_age !== null && row.max_age !== null) {
        return `${row.min_age}-${row.max_age} años`;
    }
    return row.min_age !== null ? `Desde ${row.min_age} años` : `Hasta ${row.max_age} años`;
};

const fetchItems = () => {
    loading.value = true;

    router.get(
        route("fee-schedules.index"),
        { club_id: page.props.auth.currentClub, year: selectedYear.value },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            onSuccess: (pageResponse) => {
                pricingRows.value = (pageResponse.props.pricingRules as PricingRuleRow[]).map((row) => ({ ...row }));
                interclubRows.value = (pageResponse.props.interclubRules as InterclubRuleRow[]).map((row) => ({ ...row }));
                currentClub.value = (pageResponse.props.currentClub as CurrentClub | null) ?? null;
                loading.value = false;
            },
            onError: () => {
                loading.value = false;
            },
        },
    );
};

watch(selectedYear, fetchItems);

watch(
    () => page.props.auth.currentClub,
    () => fetchItems(),
);

const save = () => {
    saving.value = true;

    router.post(
        route("fee-schedules.store"),
        {
            year: selectedYear.value,
            pricing_rules: pricingRows.value.map((row) => ({
                id: row.id,
                monthly_fee: row.monthly_fee,
                inscription_fee: row.inscription_fee,
            })),
            interclub_rules: interclubRows.value.map((row) => ({
                id: row.id,
                monthly_fee: row.monthly_fee,
                inscription_fee: row.inscription_fee,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({
                    title: page.props.flash.success || "",
                    icon: "success",
                });
                fetchItems();
            },
            onError: (errors) => {
                customToastSwal({
                    title: `Error: ${errors.messageError || 'Ocurrió un error al guardar'}`,
                    text: `${errors.exception ?? ""}`,
                    icon: "error",
                });
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Cuotas por año" />

    <AppLayout>
        <template #header>Cuotas por año</template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg pa-4">
            <v-alert type="info" variant="tonal" class="mb-4">
                Aquí se capturan y consultan las cuotas mensuales y de inscripción de todas
                las reglas de precio y paquetes interclub, año por año. Si un año no tiene
                un monto capturado, se usa el del año anterior más cercano (el que se muestra
                aquí abajo ya es ese monto vigente). Al guardar, se registra explícitamente
                para el año seleccionado.
            </v-alert>

            <v-row class="mb-2" align="center">
                <v-col cols="12" md="3">
                    <v-select
                        v-model="selectedYear"
                        :items="yearOptions"
                        label="Año"
                    />
                </v-col>
                <v-col cols="12" md="9" class="d-flex justify-end">
                    <BaseButton
                        v-if="can.includes('fee-schedules.store')"
                        :icon-only="false"
                        text="Guardar cuotas"
                        variant="flat"
                        action="save"
                        :loading="saving"
                        @click="save"
                    />
                </v-col>
            </v-row>

            <h3 class="text-h6 mb-2">Reglas de precio</h3>
            <v-row class="mb-2" dense>
                <v-col cols="12" md="5">
                    <v-autocomplete
                        v-model="pricingDestinationFilter"
                        :items="destinationMembershipOptions"
                        label="Buscar por membresía de destino"
                        density="compact"
                        clearable
                        hide-details
                    />
                </v-col>
                <v-col cols="12" md="4">
                    <v-select
                        v-model="pricingConditionFilter"
                        :items="conditionOptions"
                        label="Condición"
                        density="compact"
                        hide-details
                    />
                </v-col>
                <v-col cols="12" md="3">
                    <v-select
                        v-model="pricingActiveFilter"
                        :items="yesNoOptions"
                        label="Estado"
                        density="compact"
                        hide-details
                    />
                </v-col>
            </v-row>
            <v-table :loading="loading" density="comfortable" fixed-header height="420px">
                <thead>
                    <tr>
                        <th>Destino</th>
                        <th>Origen</th>
                        <th>Edad</th>
                        <th>Condición</th>
                        <th style="min-width: 160px">Cuota mensual</th>
                        <th style="min-width: 160px">Inscripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in filteredPricingRows" :key="`pr-${row.id}`">
                        <td>
                            <div class="font-weight-medium">
                                {{ row.membership_type_name }}
                            </div>
                        </td>
                        <td>
                            {{ row.from_membership_type_id ? row.from_membership_type_name : '--' }}
                        </td>
                        <td>{{ ageRangeLabel(row) }}</td>
                        <td>
                            <v-chip size="small" :color="row.requires_multiple_clubs ? 'success' : 'default'" variant="tonal">
                                {{ row.requires_multiple_clubs ? 'Ambos parques' : singleClubLabel() }}
                            </v-chip>
                            <v-chip v-if="!row.is_active" size="small" color="default" variant="tonal" class="ml-1">
                                Inactiva
                            </v-chip>
                        </td>
                        <td>
                            <v-text-field
                                v-model.number="row.monthly_fee"
                                type="number"
                                min="0"
                                step="0.01"
                                density="compact"
                                hide-details
                                variant="outlined"
                            />
                        </td>
                        <td>
                            <v-text-field
                                v-model.number="row.inscription_fee"
                                type="number"
                                min="0"
                                step="0.01"
                                density="compact"
                                hide-details
                                variant="outlined"
                            />
                        </td>
                    </tr>
                    <tr v-if="!pricingRows.length">
                        <td colspan="6" class="text-center text-medium-emphasis py-4">
                            No hay reglas de precio para este club.
                        </td>
                    </tr>
                    <tr v-else-if="!filteredPricingRows.length">
                        <td colspan="6" class="text-center text-medium-emphasis py-4">
                            Ningún resultado coincide con los filtros.
                        </td>
                    </tr>
                </tbody>
            </v-table>

            <h3 class="text-h6 mt-6 mb-2">Paquetes interclub</h3>
            <v-table :loading="loading" density="comfortable" fixed-header height="420px">
                <thead>
                    <tr>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Paquete</th>
                        <th style="min-width: 160px">Cuota mensual</th>
                        <th style="min-width: 160px">Inscripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in interclubRows" :key="`ic-${row.id}`">
                        <td>
                            {{ row.source_club_code }}
                            <span v-if="row.source_membership_type_name">· {{ row.source_membership_type_name }}</span>
                        </td>
                        <td>
                            <div class="font-weight-medium">
                                {{ row.target_membership_type_name }}
                            </div>
                        </td>
                        <td>
                            {{ row.package_code }}
                            <v-chip v-if="!row.is_active" size="small" color="default" variant="tonal" class="ml-1">
                                Inactivo
                            </v-chip>
                        </td>
                        <td>
                            <v-text-field
                                v-model.number="row.monthly_fee"
                                type="number"
                                min="0"
                                step="0.01"
                                density="compact"
                                hide-details
                                variant="outlined"
                            />
                        </td>
                        <td>
                            <v-text-field
                                v-model.number="row.inscription_fee"
                                type="number"
                                min="0"
                                step="0.01"
                                density="compact"
                                hide-details
                                variant="outlined"
                            />
                        </td>
                    </tr>
                    <tr v-if="!interclubRows.length">
                        <td colspan="5" class="text-center text-medium-emphasis py-4">
                            No hay paquetes interclub para este club.
                        </td>
                    </tr>
                </tbody>
            </v-table>

            <div class="d-flex justify-end mt-4">
                <BaseButton
                    v-if="can.includes('fee-schedules.store')"
                    :icon-only="false"
                    text="Guardar cuotas"
                    variant="flat"
                    action="save"
                    :loading="saving"
                    @click="save"
                />
            </div>
        </div>
    </AppLayout>
</template>
