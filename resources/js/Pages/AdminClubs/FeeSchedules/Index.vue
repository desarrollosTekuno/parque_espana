<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
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

interface AnnualDiscountRuleRow {
    id: number;
    year: number;
    pay_by_month: number;
    discount_months: number;
    free_month: number;
    is_active: boolean;
}

interface FamilySlot {
    membership_type_name: string;
    single_rule_ids: number[];
    multiclub_rule_ids: number[];
}

interface FamilyGroup {
    label: string;
    familiar: FamilySlot;
    individual: FamilySlot | null;
    solidaria: FamilySlot | null;
}

interface Props {
    pricingRules?: PricingRuleRow[];
    interclubRules?: InterclubRuleRow[];
    familyGroups?: FamilyGroup[];
    annualDiscountRules?: AnnualDiscountRuleRow[];
    year?: number;
    currentClub?: CurrentClub | null;
}

const props = withDefaults(defineProps<Props>(), {
    pricingRules: () => [],
    interclubRules: () => [],
    familyGroups: () => [],
    annualDiscountRules: () => [],
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
const familyGroups = ref<FamilyGroup[]>(props.familyGroups.map((g) => ({ ...g })));
const currentClub = ref<CurrentClub | null>(props.currentClub);
const annualDiscountRules = ref<AnnualDiscountRuleRow[]>(props.annualDiscountRules.map((r) => ({ ...r })));

// ── Descuento por pago de anualidad (billing.annual_discount_rules) ──
// No está ligado a un club (aplica a cualquier parque, ver
// AnnualPaymentService) — vive aquí porque ya es donde se administra la
// cuota del año, solo que en vez de "cuánto se cobra" es "cuánto se
// descuenta si se paga adelantado".
const monthOptions = [
    { title: "Enero", value: 1 }, { title: "Febrero", value: 2 }, { title: "Marzo", value: 3 },
    { title: "Abril", value: 4 }, { title: "Mayo", value: 5 }, { title: "Junio", value: 6 },
    { title: "Julio", value: 7 }, { title: "Agosto", value: 8 }, { title: "Septiembre", value: 9 },
    { title: "Octubre", value: 10 }, { title: "Noviembre", value: 11 }, { title: "Diciembre", value: 12 },
];
const monthName = (month: number) => monthOptions.find((m) => m.value === month)?.title ?? String(month);

const showAnnualDiscountDialog = ref(false);
const annualDiscountSaving = ref(false);
const editingAnnualDiscountId = ref<number | null>(null);
const annualDiscountForm = ref({
    pay_by_month: null as number | null,
    discount_months: null as number | null,
    free_month: 12 as number | null,
    is_active: true,
});

const resetAnnualDiscountForm = () => {
    editingAnnualDiscountId.value = null;
    annualDiscountForm.value = {
        pay_by_month: null,
        discount_months: null,
        free_month: 12,
        is_active: true,
    };
};

const openCreateAnnualDiscountDialog = () => {
    resetAnnualDiscountForm();
    showAnnualDiscountDialog.value = true;
};

const openEditAnnualDiscountDialog = (rule: AnnualDiscountRuleRow) => {
    editingAnnualDiscountId.value = rule.id;
    annualDiscountForm.value = {
        pay_by_month: rule.pay_by_month,
        discount_months: rule.discount_months,
        free_month: rule.free_month,
        is_active: rule.is_active,
    };
    showAnnualDiscountDialog.value = true;
};

const saveAnnualDiscountRule = () => {
    if (!annualDiscountForm.value.pay_by_month || annualDiscountForm.value.discount_months === null || !annualDiscountForm.value.free_month) {
        customToastSwal({ title: "Completa mes límite de pago, meses de descuento y mes que se libera.", icon: "warning" });
        return;
    }

    annualDiscountSaving.value = true;
    const payload = {
        year: selectedYear.value,
        pay_by_month: annualDiscountForm.value.pay_by_month,
        discount_months: annualDiscountForm.value.discount_months,
        free_month: annualDiscountForm.value.free_month,
        is_active: annualDiscountForm.value.is_active,
    };

    const onDone = {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({ title: page.props.flash?.success || "Guardado correctamente.", icon: "success" });
            showAnnualDiscountDialog.value = false;
            fetchItems();
        },
        onError: (errors: Record<string, string>) => {
            customToastSwal({
                title: `Error: ${errors.messageError || "No se pudo guardar la regla"}`,
                text: `${errors.exception ?? ""}`,
                icon: "error",
            });
        },
        onFinish: () => {
            annualDiscountSaving.value = false;
        },
    };

    if (editingAnnualDiscountId.value) {
        router.put(route("fee-schedules.annual-discount-rules.update", editingAnnualDiscountId.value), payload, onDone);
    } else {
        router.post(route("fee-schedules.annual-discount-rules.store"), payload, onDone);
    }
};

const deleteAnnualDiscountRule = async (rule: AnnualDiscountRuleRow) => {
    const result = await customConfirmSwal({
        title: "¿Eliminar esta regla?",
        text: `Se eliminará el descuento configurado para pagos hechos hasta ${monthName(rule.pay_by_month)} de ${rule.year}.`,
        icon: "warning",
        confirmText: "Sí, eliminar",
        cancelText: "Cancelar",
        showLoaderOnConfirm: false,
    });
    if (!result?.isConfirmed) return;

    router.delete(route("fee-schedules.annual-discount-rules.destroy", rule.id), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({ title: page.props.flash?.success || "Regla eliminada.", icon: "success" });
            fetchItems();
        },
        onError: () => {
            customToastSwal({ title: "No se pudo eliminar la regla.", icon: "error" });
        },
    });
};

// ── Captura rápida por familia (Familiar/Individual/Solidaria de una misma
// categoría) — lee y escribe directo sobre pricingRows, así que el flujo de
// guardado no cambia y la tabla de abajo siempre refleja lo mismo.
const quickFillValue = (ruleIds: number[]): number | null => {
    if (!ruleIds.length) return null;
    const row = pricingRows.value.find((r) => ruleIds.includes(r.id));
    return row ? row.monthly_fee : null;
};

const applyQuickFill = (ruleIds: number[], value: number | null) => {
    pricingRows.value.forEach((row) => {
        if (ruleIds.includes(row.id)) {
            row.monthly_fee = value;
        }
    });
};

const singleClubLabel = (): string => currentClub.value?.name ?? "Club único";

// ── Captura rápida — Individual-Familiar interclub: un solo campo que
// sobrescribe la cuota mensual de TODOS los paquetes interclub de la tabla
// de abajo, sin importar la categoría/tipo específico de cada uno. Muestra
// el valor del primer renglón como referencia (no intenta detectar si ya
// están en valores distintos).
const interclubQuickFillValue = computed((): number | null =>
    interclubRows.value.length ? interclubRows.value[0].monthly_fee : null,
);

const applyInterclubQuickFill = (value: number | null) => {
    interclubRows.value.forEach((row) => {
        row.monthly_fee = value;
    });
};

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
                familyGroups.value = (pageResponse.props.familyGroups as FamilyGroup[]).map((g) => ({ ...g }));
                annualDiscountRules.value = (pageResponse.props.annualDiscountRules as AnnualDiscountRuleRow[]).map((r) => ({ ...r }));
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

// ── Copiar cuotas de otro año ──────────────────────────────────────────────
const showCopyDialog = ref(false);
const copySourceYear = ref<number | null>(null);
const copying = ref(false);

const copySourceYearOptions = computed(() =>
    yearOptions.filter((option) => option.value !== selectedYear.value),
);

const openCopyDialog = () => {
    copySourceYear.value = null;
    showCopyDialog.value = true;
};

const copyFromYear = async () => {
    if (!copySourceYear.value) return;

    copying.value = true;

    try {
        const params = new URLSearchParams({
            club_id: String(page.props.auth.currentClub),
            year: String(copySourceYear.value),
        });
        const res = await fetch(`${route("fee-schedules.preview")}?${params.toString()}`, {
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        });

        if (!res.ok) throw new Error("No se pudo obtener las cuotas de ese año.");

        const data = await res.json();
        const sourcePricingRules = data.pricingRules as PricingRuleRow[];
        const sourceInterclubRules = data.interclubRules as InterclubRuleRow[];

        pricingRows.value = pricingRows.value.map((row) => {
            const source = sourcePricingRules.find((sourceRow) => sourceRow.id === row.id);
            return source
                ? { ...row, monthly_fee: source.monthly_fee, inscription_fee: source.inscription_fee }
                : row;
        });

        interclubRows.value = interclubRows.value.map((row) => {
            const source = sourceInterclubRules.find((sourceRow) => sourceRow.id === row.id);
            return source
                ? { ...row, monthly_fee: source.monthly_fee, inscription_fee: source.inscription_fee }
                : row;
        });

        customToastSwal({
            title: `Cuotas de ${copySourceYear.value} copiadas a ${selectedYear.value}. Revisa y da clic en "Guardar cuotas" para confirmar.`,
            icon: "success",
        });
        showCopyDialog.value = false;
    } catch (e: any) {
        customToastSwal({
            title: e.message ?? "Ocurrió un error al copiar las cuotas.",
            icon: "error",
        });
    } finally {
        copying.value = false;
    }
};

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
                <v-col cols="12" md="9" class="d-flex justify-end ga-2">
                    <BaseButton
                        v-if="can.includes('fee-schedules.store')"
                        :icon-only="false"
                        text="Copiar cuotas de otro año"
                        icon="mdi-content-copy"
                        variant="tonal"
                        @click="openCopyDialog"
                    />
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

            <v-card variant="outlined" class="mb-6">
                <v-card-title class="d-flex justify-space-between align-center">
                    <span>Descuento por pago de anualidad ({{ selectedYear }})</span>
                    <BaseButton
                        v-if="can.includes('fee-schedules.store')"
                        :icon-only="false"
                        text="Agregar regla"
                        icon="mdi-plus"
                        variant="tonal"
                        size="small"
                        @click="openCreateAnnualDiscountDialog"
                    />
                </v-card-title>
                <v-card-text>
                    <p class="text-body-2 text-medium-emphasis mb-3">
                        Si el socio paga la anualidad completa (hasta diciembre de {{ selectedYear }}) antes o durante
                        el mes indicado, se le descuentan los meses configurados — aplica a cualquier parque, no es
                        por club. Ver Collections → "¿Es pago de anualidad?".
                    </p>

                    <v-table density="compact" v-if="annualDiscountRules.length">
                        <thead>
                            <tr>
                                <th>Pagando hasta</th>
                                <th>Descuento</th>
                                <th>Mes que se libera</th>
                                <th>Estatus</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="rule in annualDiscountRules" :key="rule.id">
                                <td>{{ monthName(rule.pay_by_month) }}</td>
                                <td>{{ rule.discount_months }} mes(es)</td>
                                <td>{{ monthName(rule.free_month) }}</td>
                                <td>
                                    <v-chip size="small" :color="rule.is_active ? 'success' : 'default'" variant="tonal">
                                        {{ rule.is_active ? "Activa" : "Inactiva" }}
                                    </v-chip>
                                </td>
                                <td class="text-end">
                                    <BaseButton
                                        v-if="can.includes('fee-schedules.store')"
                                        action="edit"
                                        tooltip="Editar"
                                        @click="openEditAnnualDiscountDialog(rule)"
                                    />
                                    <BaseButton
                                        v-if="can.includes('fee-schedules.store')"
                                        action="delete"
                                        tooltip="Eliminar"
                                        @click="deleteAnnualDiscountRule(rule)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                    <p v-else class="text-body-2 text-medium-emphasis mb-0">
                        No hay reglas de descuento configuradas para {{ selectedYear }} — la anualidad se cobrará sin descuento.
                    </p>
                </v-card-text>
            </v-card>

            <h3 class="text-h6 mb-2">Captura rápida</h3>
            <p class="text-body-2 text-medium-emphasis mb-3">
                Cada tarjeta agrupa Familiar, Individual y Solidaria de una misma categoría.
                Al escribir aquí se actualiza directo la tabla de "Reglas de precio" de abajo
                (incluyendo las reglas de transición que llegan al mismo destino) — sigue
                siendo necesario dar clic en "Guardar cuotas" para confirmar.
            </p>

            <v-row class="mb-6" dense>
                <v-col cols="12" md="6" lg="4">
                    <v-card variant="outlined" class="pa-3 h-100">
                        <div class="text-subtitle-2 font-weight-bold mb-2">Mensualidad Intermedia(Individual en un parque y Familiar en otro)</div>
                        <p class="text-caption text-medium-emphasis mb-2">
                            Sobrescribe la cuota mensual de todos los paquetes intermedios
                            (tabla "Paquetes intermedios" de abajo).
                        </p>
                        <v-text-field
                            :model-value="interclubQuickFillValue"
                            @update:model-value="(v) => applyInterclubQuickFill(v === '' ? null : Number(v))"
                            type="number"
                            min="0"
                            step="0.01"
                            density="compact"
                            variant="outlined"
                            hide-details
                            prefix="$"
                        />
                    </v-card>
                </v-col>

                <v-col
                    v-for="group in familyGroups"
                    :key="group.label"
                    cols="12"
                    md="6"
                    lg="4"
                >
                    <v-card variant="outlined" class="pa-3 h-100">
                        <div class="text-subtitle-2 font-weight-bold mb-2">{{ group.label }}</div>

                        <v-row dense>
                            <v-col cols="6">
                                <div class="text-caption text-medium-emphasis">Familiar — este parque</div>
                                <v-text-field
                                    :model-value="quickFillValue(group.familiar.single_rule_ids)"
                                    @update:model-value="(v) => applyQuickFill(group.familiar.single_rule_ids, v === '' ? null : Number(v))"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                    prefix="$"
                                />
                            </v-col>
                            <v-col cols="6">
                                <div class="text-caption text-medium-emphasis">Familiar — ambos parques</div>
                                <v-text-field
                                    :model-value="quickFillValue(group.familiar.multiclub_rule_ids)"
                                    @update:model-value="(v) => applyQuickFill(group.familiar.multiclub_rule_ids, v === '' ? null : Number(v))"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                    prefix="$"
                                />
                            </v-col>

                            <template v-if="group.individual">
                                <v-col cols="6">
                                    <div class="text-caption text-medium-emphasis">Individual — este parque</div>
                                    <v-text-field
                                        :model-value="quickFillValue(group.individual.single_rule_ids)"
                                        @update:model-value="(v) => applyQuickFill(group.individual!.single_rule_ids, v === '' ? null : Number(v))"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        density="compact"
                                        variant="outlined"
                                        hide-details
                                        prefix="$"
                                    />
                                </v-col>
                                <v-col cols="6">
                                    <div class="text-caption text-medium-emphasis">Individual — ambos parques</div>
                                    <v-text-field
                                        :model-value="quickFillValue(group.individual.multiclub_rule_ids)"
                                        @update:model-value="(v) => applyQuickFill(group.individual!.multiclub_rule_ids, v === '' ? null : Number(v))"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        density="compact"
                                        variant="outlined"
                                        hide-details
                                        prefix="$"
                                    />
                                </v-col>
                            </template>

                            <template v-if="group.solidaria">
                                <v-col cols="6">
                                    <div class="text-caption text-medium-emphasis">Solidaria — este parque</div>
                                    <v-text-field
                                        :model-value="quickFillValue(group.solidaria.single_rule_ids)"
                                        @update:model-value="(v) => applyQuickFill(group.solidaria!.single_rule_ids, v === '' ? null : Number(v))"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        density="compact"
                                        variant="outlined"
                                        hide-details
                                        prefix="$"
                                    />
                                </v-col>
                                <v-col cols="6">
                                    <div class="text-caption text-medium-emphasis">Solidaria — ambos parques</div>
                                    <v-text-field
                                        :model-value="quickFillValue(group.solidaria.multiclub_rule_ids)"
                                        @update:model-value="(v) => applyQuickFill(group.solidaria!.multiclub_rule_ids, v === '' ? null : Number(v))"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        density="compact"
                                        variant="outlined"
                                        hide-details
                                        prefix="$"
                                    />
                                </v-col>
                            </template>
                        </v-row>
                    </v-card>
                </v-col>

                <v-col v-if="!familyGroups.length" cols="12">
                    <p class="text-medium-emphasis text-center py-2">
                        No hay familias de membresías detectadas para este club.
                    </p>
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

            <h3 class="text-h6 mt-6 mb-2">Paquetes intermedios</h3>
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

        <v-dialog v-model="showCopyDialog" max-width="420" persistent>
            <v-card prepend-icon="mdi-content-copy" title="Copiar cuotas de otro año">
                <v-card-text>
                    <p class="text-body-2 text-medium-emphasis mb-4">
                        Copia los montos vigentes de otro año hacia
                        <strong>{{ selectedYear }}</strong>. Se copian a la tabla, pero
                        no se guardan hasta que des clic en "Guardar cuotas".
                    </p>
                    <v-select
                        v-model="copySourceYear"
                        :items="copySourceYearOptions"
                        label="Copiar desde el año"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        :icon-only="false"
                        variant="tonal"
                        action="cancel"
                        @click="showCopyDialog = false"
                    />
                    <BaseButton
                        :icon-only="false"
                        text="Copiar"
                        icon="mdi-content-copy"
                        variant="flat"
                        color="primary"
                        :loading="copying"
                        :disabled="!copySourceYear"
                        @click="copyFromYear"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showAnnualDiscountDialog" max-width="480" persistent>
            <v-card
                prepend-icon="mdi-calendar-check"
                :title="editingAnnualDiscountId ? 'Editar regla de descuento' : 'Nueva regla de descuento'"
            >
                <v-card-text>
                    <v-select
                        v-model="annualDiscountForm.pay_by_month"
                        :items="monthOptions"
                        label="Pagando hasta (mes límite)"
                        hide-details="auto"
                        class="mb-3"
                    />
                    <v-text-field
                        v-model.number="annualDiscountForm.discount_months"
                        label="Meses de descuento"
                        type="number"
                        min="0"
                        max="12"
                        step="0.5"
                        hint="1 = un mes completo libre, 0.5 = medio mes"
                        persistent-hint
                        class="mb-3"
                    />
                    <v-select
                        v-model="annualDiscountForm.free_month"
                        :items="monthOptions"
                        label="Mes que recibe el descuento"
                        hint="Normalmente diciembre — a ese cargo se le aplica primero si el descuento es de mes completo."
                        persistent-hint
                        hide-details="auto"
                        class="mb-3"
                    />
                    <v-switch
                        v-model="annualDiscountForm.is_active"
                        label="Activa"
                        color="success"
                        hide-details
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        :icon-only="false"
                        variant="tonal"
                        action="cancel"
                        @click="showAnnualDiscountDialog = false"
                    />
                    <BaseButton
                        :icon-only="false"
                        text="Guardar"
                        icon="mdi-content-save-outline"
                        variant="flat"
                        color="primary"
                        :loading="annualDiscountSaving"
                        @click="saveAnnualDiscountRule"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
