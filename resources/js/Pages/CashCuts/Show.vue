<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { customToastSwal } from "@/utils/swal";
import { utcToLocalDisplay, utcToLocalTime } from "@/constants/formatDates";

const exportExcel = (id: number) => { window.location.href = route('cash-cuts.export', id); };
import { formatCurrency } from "@/constants/formatCurrency";

interface Denomination {
    denomination: number;
    quantity: number;
    subtotal: number;
}

interface PaymentItem {
    id: number;
    paid_at: string | null;
    amount: number;
    payment_method_name: string | null;
    payment_method_code: string | null;
    membership_number: string | null;
    reference: string | null;
}

interface MethodTotal {
    code: string;
    name: string | null;
    count: number;
    total: number;
}

interface CancelledPaymentItem extends PaymentItem {
    cancelled_at: string | null;
    cancellation_reason: string | null;
}

interface CashCutDetail {
    id: number;
    date: string;
    status: string;
    opening_amount: number;
    cash_expected: number;
    cash_counted: number | null;
    cash_difference: number | null;
    opened_at: string | null;
    closed_at: string | null;
    notes: string | null;
    cashier_name: string | null;
    global_cash_cut_id: number | null;
}

interface Props {
    cashCut: CashCutDetail;
    payments: PaymentItem[];
    totalsPerMethod: MethodTotal[];
    cancelledPayments: CancelledPaymentItem[];
    denominations: Denomination[];
}

const props = defineProps<Props>();
const page = usePage<any>();
const can = page.props.auth.permissions ?? [];

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
});

const formatDateTime = utcToLocalDisplay;

const denominations = ref<Denomination[]>(
    props.denominations.map((d) => ({ ...d })),
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
    0.2: "$0.20",
    0.1: "$0.10",
};

const updateSubtotal = (den: Denomination) => {
    den.subtotal = parseFloat(
        (den.denomination * (den.quantity || 0)).toFixed(2),
    );
};

const cashCounted = computed(() =>
    denominations.value.reduce((sum, d) => sum + (d.subtotal || 0), 0),
);

const cashDifference = computed(() =>
    parseFloat((cashCounted.value - props.cashCut.cash_expected).toFixed(2)),
);

const differenceColor = computed(() => {
    if (cashDifference.value === 0) return "success";
    return cashDifference.value > 0 ? "warning" : "error";
});

const differenceLabel = computed(() => {
    if (cashDifference.value === 0) return "Cuadrado ✓";
    if (cashDifference.value > 0)
        return `Sobrante: ${formatCurrency(cashDifference.value)}`;
    return `Faltante: ${formatCurrency(Math.abs(cashDifference.value))}`;
});

const showCloseDialog = ref(false);
const notesRef = ref("");
const closeFormRef = ref();

const closeForm = useForm({
    notes: props.cashCut.notes ?? "",
    denominations: [] as { denomination: number; quantity: number }[],
});

const submitClose = async () => {
    closeForm.denominations = denominations.value.map((d) => ({
        denomination: d.denomination,
        quantity: d.quantity || 0,
    }));

    closeForm.post(route("cash-cuts.close", props.cashCut.id), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({ title: "Corte cerrado correctamente.", icon: "success" });
        },
        onError: () => {
            customToastSwal({
                title: closeForm.errors.messageError || "No se pudo cerrar el corte.",
                icon: "error",
            });
        },
    });
};

const paymentHeaders = [
    { title: "Hora", key: "paid_at", sortable: false },
    { title: "No. Cuenta", key: "membership_number", sortable: false },
    { title: "Método", key: "payment_method_name", sortable: false },
    { title: "Referencia", key: "reference", sortable: false },
    { title: "Importe", key: "amount", sortable: false },
];

const isClosed = computed(() => props.cashCut.status === "closed");
</script>

<template>
    <Head :title="`Corte de caja – ${cashCut.date}`" />

    <AppLayout>
        <template #header>
            <div class="d-flex justify-space-between align-center w-100">
                <span>
                    Corte de caja —
                    {{
                        new Intl.DateTimeFormat("es-MX", { dateStyle: "long" }).format(
                            new Date(`${cashCut.date}T12:00:00`),
                        )
                    }}
                </span>
                <BaseButton
                    v-if="isClosed"
                    :icon-only="false"
                    action="export"
                    text="Exportar Excel"
                    variant="tonal"
                    @click="exportExcel(cashCut.id)"
                />
            </div>
        </template>

        <div class="d-flex flex-column ga-4">
            <!-- Resumen -->
            <v-row>
                <v-col cols="12" md="3">
                    <v-card color="primary" variant="tonal">
                        <v-card-text>
                            <div class="text-caption text-medium-emphasis">Cajero/a</div>
                            <div class="font-weight-bold mt-1">{{ cashCut.cashier_name }}</div>
                            <div class="text-caption text-medium-emphasis mt-2">Apertura</div>
                            <div class="text-caption">{{ formatDateTime(cashCut.opened_at) }}</div>
                            <div v-if="cashCut.closed_at" class="text-caption text-medium-emphasis mt-1">Cierre</div>
                            <div v-if="cashCut.closed_at" class="text-caption">{{ formatDateTime(cashCut.closed_at) }}</div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="3">
                    <v-card color="secondary" variant="tonal">
                        <v-card-text>
                            <div class="text-caption text-medium-emphasis">Fondo inicial</div>
                            <div class="text-h6 font-weight-bold mt-1">{{ formatCurrency(cashCut.opening_amount) }}</div>
                            <div class="text-caption text-medium-emphasis mt-2">Efectivo esperado</div>
                            <div class="text-h6 font-weight-bold">{{ formatCurrency(cashCut.cash_expected) }}</div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="3">
                    <v-card color="info" variant="tonal">
                        <v-card-text>
                            <div class="text-caption text-medium-emphasis">Total cobrado</div>
                            <div
                                v-for="method in totalsPerMethod"
                                :key="method.code"
                                class="d-flex justify-space-between mt-1"
                            >
                                <span class="text-body-2">{{ method.name }}</span>
                                <span class="font-weight-medium">{{ formatCurrency(method.total) }}</span>
                            </div>
                            <v-divider class="my-2" />
                            <div class="d-flex justify-space-between font-weight-bold">
                                <span>Total</span>
                                <span>{{ formatCurrency(totalsPerMethod.reduce((s, m) => s + m.total, 0)) }}</span>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>

                <v-col cols="12" md="3">
                    <v-card :color="isClosed ? differenceColor : 'default'" variant="tonal">
                        <v-card-text>
                            <template v-if="isClosed">
                                <div class="text-caption text-medium-emphasis">Efectivo contado</div>
                                <div class="text-h6 font-weight-bold mt-1">{{ formatCurrency(cashCut.cash_counted) }}</div>
                                <div class="text-caption text-medium-emphasis mt-2">Diferencia</div>
                                <div class="text-h6 font-weight-bold">{{ formatCurrency(cashCut.cash_difference) }}</div>
                                <v-chip size="small" :color="differenceColor" variant="tonal" class="mt-2">
                                    {{ differenceLabel }}
                                </v-chip>
                            </template>
                            <template v-else>
                                <div class="text-caption text-medium-emphasis">Efectivo contado (en tiempo real)</div>
                                <div class="text-h6 font-weight-bold mt-1">{{ formatCurrency(cashCounted) }}</div>
                                <div class="text-caption text-medium-emphasis mt-2">Diferencia estimada</div>
                                <v-chip size="small" :color="differenceColor" variant="tonal" class="mt-1">
                                    {{ differenceLabel }}
                                </v-chip>
                            </template>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-row>
                <!-- Desglose de denominaciones -->
                <v-col cols="12" md="5">
                    <v-card>
                        <v-card-title>Conteo de efectivo</v-card-title>
                        <v-card-subtitle>
                            {{ isClosed ? "Desglose registrado al cerrar." : "Captura las piezas por denominación." }}
                        </v-card-subtitle>
                        <v-card-text>
                            <v-table density="compact">
                                <thead>
                                    <tr>
                                        <th>Denominación</th>
                                        <th>Cantidad</th>
                                        <th class="text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="den in denominations" :key="den.denomination">
                                        <td class="font-weight-medium">
                                            {{ denominationLabels[den.denomination] ?? `$${den.denomination}` }}
                                        </td>
                                        <td>
                                            <v-text-field
                                                v-if="!isClosed"
                                                v-model.number="den.quantity"
                                                type="number"
                                                min="0"
                                                density="compact"
                                                hide-details
                                                variant="underlined"
                                                style="max-width: 80px"
                                                @input="updateSubtotal(den)"
                                            />
                                            <span v-else>{{ den.quantity }}</span>
                                        </td>
                                        <td class="text-right">{{ formatCurrency(den.subtotal) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="font-weight-bold">Total contado</td>
                                        <td class="text-right font-weight-bold">
                                            {{ formatCurrency(isClosed ? cashCut.cash_counted : cashCounted) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </v-table>
                        </v-card-text>

                        <v-card-actions v-if="!isClosed && can.includes('cash-cuts.close')">
                            <v-spacer />
                            <BaseButton
                                :icon-only="false"
                                action="save"
                                text="Cerrar corte de caja"
                                :loading="closeForm.processing"
                                @click="showCloseDialog = true"
                            />
                        </v-card-actions>
                    </v-card>
                </v-col>

                <!-- Pagos del día -->
                <v-col cols="12" md="7">
                    <v-card>
                        <v-card-title>Cobros del día</v-card-title>
                        <v-card-subtitle>{{ payments.length }} transacciones registradas.</v-card-subtitle>
                        <v-data-table
                            :headers="paymentHeaders"
                            :items="payments"
                            :items-per-page="-1"
                            hide-default-footer
                            density="compact"
                            class="elevation-0"
                        >
                            <template #item.paid_at="{ item }">
                                <span class="text-caption">{{ utcToLocalTime(item.paid_at) }}</span>
                            </template>
                            <template #item.amount="{ item }">
                                <span class="font-weight-medium">{{ formatCurrency(item.amount) }}</span>
                            </template>
                            <template #item.reference="{ item }">
                                {{ item.reference || "—" }}
                            </template>
                            <template #no-data>
                                <div class="pa-4 text-center text-medium-emphasis">Sin cobros registrados.</div>
                            </template>
                        </v-data-table>
                    </v-card>

                    <!-- Pagos cancelados: ya no cuentan en los totales de arriba
                         (ver CashCutController::getPaymentsForCut), se muestran
                         aparte para que quede claro por qué falta ese dinero en
                         vez de que el pago desaparezca sin dejar rastro. -->
                    <v-card v-if="cancelledPayments.length" class="mt-4" variant="outlined">
                        <v-card-title class="text-error">Pagos cancelados</v-card-title>
                        <v-card-subtitle>
                            {{ cancelledPayments.length }} pago(s) cancelado(s) ese día — ya no cuentan en los totales.
                        </v-card-subtitle>
                        <v-list density="compact">
                            <v-list-item
                                v-for="item in cancelledPayments"
                                :key="item.id"
                                :title="`${item.payment_method_name || '-'} · ${formatCurrency(item.amount)}`"
                            >
                                <template #subtitle>
                                    <div>{{ item.membership_number || "-" }} — {{ item.cancellation_reason || "Sin motivo capturado" }}</div>
                                    <div class="text-caption">Cancelado: {{ utcToLocalDisplay(item.cancelled_at) }}</div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-card>
                </v-col>
            </v-row>
        </div>

        <!-- Diálogo confirmar cierre -->
        <v-dialog v-model="showCloseDialog" max-width="480" persistent>
            <v-card>
                <v-card-title>Confirmar cierre de corte</v-card-title>
                <v-card-text>
                    <v-alert
                        :type="cashDifference === 0 ? 'success' : cashDifference > 0 ? 'warning' : 'error'"
                        variant="tonal"
                        class="mb-4"
                    >
                        {{ differenceLabel }}
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
