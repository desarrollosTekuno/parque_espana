<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { customToastSwal } from "@/utils/swal";

const exportExcel = (id: number) => { window.location.href = route('global-cash-cuts.export', id); };

interface Denomination {
    denomination: number;
    quantity: number;
    subtotal: number;
}

interface MethodTotal {
    code: string;
    name: string | null;
    total: number;
    count?: number;
}

interface IndividualCut {
    id: number;
    date: string;
    cashier_name: string | null;
    opening_amount: number;
    cash_expected: number | null;
    cash_counted: number | null;
    cash_difference: number | null;
    totals_per_method: MethodTotal[];
    denominations: Denomination[];
}

interface GlobalCutDetail {
    id: number;
    date: string;
    status: string;
    created_by_name: string | null;
    closed_at: string | null;
    notes: string | null;
}

interface Props {
    globalCashCut: GlobalCutDetail;
    cashCuts: IndividualCut[];
    consolidatedTotals: MethodTotal[];
    grandTotal: number;
}

const props = defineProps<Props>();
const page = usePage<any>();
const can = page.props.auth.permissions ?? [];

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
});

const formatCurrency = (v: number | null) =>
    currencyFormatter.format(Number(v ?? 0));

const formatDate = (v: string) =>
    new Intl.DateTimeFormat("es-MX", { dateStyle: "long" }).format(
        new Date(`${v}T12:00:00`),
    );

const denominationLabels: Record<number, string> = {
    1000: "$1,000",
    500: "$500",
    200: "$200",
    100: "$100",
    50: "$50",
    20: "$20",
    10: "$10",
    5: "$5",
    2: "$2",
    1: "$1",
    0.5: "$0.50",
};

const differenceColor = (diff: number | null) => {
    if (diff === null) return "default";
    if (diff === 0) return "success";
    return diff > 0 ? "warning" : "error";
};

const differenceLabel = (diff: number | null) => {
    if (diff === null) return "—";
    if (diff === 0) return "Cuadrado ✓";
    if (diff > 0) return `Sobrante: ${formatCurrency(diff)}`;
    return `Faltante: ${formatCurrency(Math.abs(diff))}`;
};

const totalDifference = computed(() =>
    props.cashCuts.reduce((sum, c) => sum + (c.cash_difference ?? 0), 0),
);

const showCloseDialog = ref(false);
const closeForm = useForm({ notes: props.globalCashCut.notes ?? "" });

const submitClose = () => {
    closeForm.post(route("global-cash-cuts.close", props.globalCashCut.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCloseDialog.value = false;
            customToastSwal({ title: "Corte global cerrado.", icon: "success" });
        },
        onError: () => {
            customToastSwal({
                title: closeForm.errors.messageError || "No se pudo cerrar el corte global.",
                icon: "error",
            });
        },
    });
};

const isClosed = computed(() => props.globalCashCut.status === "closed");
</script>

<template>
    <Head :title="`Corte global – ${globalCashCut.date}`" />

    <AppLayout>
        <template #header>
            <div class="d-flex justify-space-between align-center w-100">
                <span>Corte global — {{ formatDate(globalCashCut.date) }}</span>
                <BaseButton
                    :icon-only="false"
                    action="export"
                    text="Exportar Excel"
                    variant="tonal"
                    @click="exportExcel(globalCashCut.id)"
                />
            </div>
        </template>

        <div class="d-flex flex-column ga-4">
            <!-- Totales consolidados -->
            <v-row>
                <v-col cols="12" md="4">
                    <v-card color="primary" variant="tonal">
                        <v-card-title>Total consolidado</v-card-title>
                        <v-card-text>
                            <div class="text-h4 font-weight-bold">{{ formatCurrency(grandTotal) }}</div>
                            <div class="text-caption text-medium-emphasis mt-2">
                                {{ cashCuts.length }} corte(s) individual(es)
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                Creado por: {{ globalCashCut.created_by_name }}
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="4">
                    <v-card color="info" variant="tonal">
                        <v-card-title>Por método de pago</v-card-title>
                        <v-card-text>
                            <div
                                v-for="method in consolidatedTotals"
                                :key="method.code"
                                class="d-flex justify-space-between mt-1"
                            >
                                <span class="text-body-2">{{ method.name }}</span>
                                <span class="font-weight-medium">{{ formatCurrency(method.total) }}</span>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="4">
                    <v-card :color="differenceColor(totalDifference)" variant="tonal">
                        <v-card-title>Diferencia global</v-card-title>
                        <v-card-text>
                            <div class="text-h5 font-weight-bold">{{ formatCurrency(totalDifference) }}</div>
                            <v-chip size="small" :color="differenceColor(totalDifference)" variant="tonal" class="mt-2">
                                {{ differenceLabel(totalDifference) }}
                            </v-chip>
                        </v-card-text>
                        <v-card-actions v-if="!isClosed && can.includes('global-cash-cuts.close')">
                            <v-spacer />
                            <BaseButton
                                :icon-only="false"
                                action="save"
                                text="Cerrar corte global"
                                @click="showCloseDialog = true"
                            />
                        </v-card-actions>
                    </v-card>
                </v-col>
            </v-row>

            <!-- Cortes individuales -->
            <div class="text-subtitle-1 font-weight-bold">Cortes individuales</div>

            <v-row>
                <v-col
                    v-for="cut in cashCuts"
                    :key="cut.id"
                    cols="12"
                    md="6"
                >
                    <v-card variant="outlined">
                        <v-card-title class="d-flex justify-space-between align-center">
                            <span>{{ cut.cashier_name }}</span>
                            <v-chip
                                size="small"
                                :color="differenceColor(cut.cash_difference)"
                                variant="tonal"
                            >
                                {{ differenceLabel(cut.cash_difference) }}
                            </v-chip>
                        </v-card-title>

                        <v-card-text>
                            <v-row dense>
                                <v-col cols="6">
                                    <div class="text-caption text-medium-emphasis">Fondo inicial</div>
                                    <div class="font-weight-medium">{{ formatCurrency(cut.opening_amount) }}</div>
                                </v-col>
                                <v-col cols="6">
                                    <div class="text-caption text-medium-emphasis">Efectivo esperado</div>
                                    <div class="font-weight-medium">{{ formatCurrency(cut.cash_expected) }}</div>
                                </v-col>
                                <v-col cols="6">
                                    <div class="text-caption text-medium-emphasis">Efectivo contado</div>
                                    <div class="font-weight-bold">{{ formatCurrency(cut.cash_counted) }}</div>
                                </v-col>
                            </v-row>

                            <v-divider class="my-3" />

                            <div class="text-caption text-medium-emphasis mb-2">Por método de pago</div>
                            <div
                                v-for="method in cut.totals_per_method"
                                :key="method.code"
                                class="d-flex justify-space-between text-body-2"
                            >
                                <span>{{ method.name }}</span>
                                <span class="font-weight-medium">{{ formatCurrency(method.total) }}</span>
                            </div>

                            <v-divider class="my-3" />

                            <div class="text-caption text-medium-emphasis mb-2">Desglose de efectivo</div>
                            <v-table density="compact">
                                <tbody>
                                    <tr v-for="den in cut.denominations" :key="den.denomination">
                                        <td>{{ denominationLabels[den.denomination] ?? `$${den.denomination}` }}</td>
                                        <td>× {{ den.quantity }}</td>
                                        <td class="text-right">{{ formatCurrency(den.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </v-table>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </div>

        <!-- Diálogo cerrar corte global -->
        <v-dialog v-model="showCloseDialog" max-width="480" persistent>
            <v-card>
                <v-card-title>Cerrar corte global</v-card-title>
                <v-card-text>
                    <v-alert type="info" variant="tonal" class="mb-4">
                        Al cerrar el corte global se registra el cierre definitivo del día.
                    </v-alert>
                    <v-textarea
                        v-model="closeForm.notes"
                        label="Notas (opcional)"
                        rows="2"
                        auto-grow
                        hide-details="auto"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        :icon-only="false"
                        action="cancel"
                        variant="tonal"
                        @click="showCloseDialog = false"
                    />
                    <BaseButton
                        :icon-only="false"
                        action="save"
                        text="Confirmar cierre"
                        :loading="closeForm.processing"
                        @click="submitClose"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
