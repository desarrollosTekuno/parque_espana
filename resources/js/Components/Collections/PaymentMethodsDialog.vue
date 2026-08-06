<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { computed, ref, watch } from "vue";
import { nowAsLocalInput } from "@/constants/formatDates";

interface PaymentMethodItem {
    id: number;
    code: string;
    name: string;
    requires_reference: boolean;
    requires_bank_name: boolean;
    requires_check_number: boolean;
    affects_cash_cut: boolean;
    internal_key: string | null;
}

interface ClubBreakdownItem {
    club_id: number | null;
    club_code: string | null;
    club_name: string | null;
    amount: number;
}

export interface PaymentLinePayload {
    payment_method_id: number;
    amount: number;
    reference: string;
    bank_name: string;
    check_number: string;
}

export interface PaymentConfirmPayload {
    paid_at: string;
    payments: PaymentLinePayload[];
}

interface Props {
    modelValue: boolean;
    total: number;
    clubBreakdown?: ClubBreakdownItem[];
    paymentMethods?: PaymentMethodItem[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    clubBreakdown: () => [],
    paymentMethods: () => [],
    loading: false,
});

const emit = defineEmits<{
    (e: "update:modelValue", value: boolean): void;
    (e: "confirm", payload: PaymentConfirmPayload): void;
}>();

const dialogModel = computed({
    get: () => props.modelValue,
    set: (value: boolean) => emit("update:modelValue", value),
});

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});
const formatCurrency = (value: number | null | undefined) =>
    currencyFormatter.format(Number(value ?? 0));

interface PaymentLineForm {
    key: string;
    payment_method_id: number | null;
    amount: number | null;
    reference: string;
    bank_name: string;
    check_number: string;
}

let lineSeq = 0;
const newLine = (amount: number | null = null): PaymentLineForm => ({
    key: `line-${++lineSeq}`,
    payment_method_id: null,
    amount,
    reference: "",
    bank_name: "",
    check_number: "",
});

const paidAt = ref(nowAsLocalInput());
const paymentLines = ref<PaymentLineForm[]>([newLine()]);

const round2 = (value: number) => Math.round(value * 100) / 100;

const resetForm = () => {
    paidAt.value = nowAsLocalInput();
    paymentLines.value = [newLine(round2(props.total))];
};

watch(
    () => props.modelValue,
    (open) => {
        if (open) resetForm();
    },
);

const isSplit = computed(() => props.clubBreakdown.length > 1);

const methodFor = (line: PaymentLineForm): PaymentMethodItem | null =>
    props.paymentMethods.find((m) => m.id === line.payment_method_id) ?? null;

const assignedTotal = computed(() =>
    round2(
        paymentLines.value.reduce((sum, l) => sum + Number(l.amount ?? 0), 0),
    ),
);
const remaining = computed(() => round2(props.total - assignedTotal.value));

const addPaymentLine = () => {
    paymentLines.value.push(newLine(remaining.value > 0 ? remaining.value : null));
};
const removePaymentLine = (key: string) => {
    if (paymentLines.value.length <= 1) return;
    paymentLines.value = paymentLines.value.filter((l) => l.key !== key);
};

const isLineValid = (line: PaymentLineForm): boolean => {
    if (!line.payment_method_id) return false;
    if (!line.amount || line.amount <= 0) return false;
    const m = methodFor(line);
    if (m?.requires_reference && !line.reference) return false;
    if (m?.requires_bank_name && !line.bank_name) return false;
    if (m?.requires_check_number && !line.check_number) return false;
    return true;
};

const canConfirm = computed(() => {
    if (!paymentLines.value.length) return false;
    if (Math.abs(remaining.value) >= 0.01) return false;
    return paymentLines.value.every(isLineValid);
});

const confirmPayment = () => {
    if (!canConfirm.value) return;

    emit("confirm", {
        paid_at: paidAt.value,
        payments: paymentLines.value.map((l) => ({
            payment_method_id: l.payment_method_id as number,
            amount: round2(Number(l.amount)),
            reference: l.reference,
            bank_name: l.bank_name,
            check_number: l.check_number,
        })),
    });
};

const close = () => {
    dialogModel.value = false;
};
</script>

<template>
    <v-dialog v-model="dialogModel" max-width="720" persistent>
        <v-card>
            <v-card-title class="d-flex justify-space-between align-center">
                <span>Forma de pago</span>
                <BaseButton
                    :icon-only="true"
                    action="close"
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    tooltip="Cerrar"
                    @click="close"
                />
            </v-card-title>

            <v-card-text class="d-flex flex-column ga-4">
                <v-alert
                    v-if="isSplit"
                    type="info"
                    variant="tonal"
                    density="compact"
                >
                    <div class="text-body-2 mb-1">
                        Este cobro corresponde a una mensualidad dividida
                        entre parques. Todo el dinero se registrará en este
                        parque, pero quedará el detalle de cómo se reparte:
                    </div>
                    <div class="d-flex flex-wrap ga-4">
                        <div v-for="club in clubBreakdown" :key="club.club_id ?? club.club_code">
                            <span class="font-weight-bold">
                                {{ club.club_code ?? club.club_name ?? "Parque" }}
                            </span>
                            ·
                            {{ formatCurrency(club.amount) }}
                        </div>
                    </div>
                </v-alert>

                <div class="d-flex justify-space-between align-center">
                    <span class="text-subtitle-1 font-weight-bold">Total a pagar</span>
                    <span class="text-h5 font-weight-bold text-primary">
                        {{ formatCurrency(total) }}
                    </span>
                </div>

                <v-text-field
                    v-model="paidAt"
                    label="Fecha y hora del pago"
                    type="datetime-local"
                    hide-details="auto"
                />

                <div>
                    <div class="d-flex justify-space-between align-center mb-2">
                        <span class="text-caption text-medium-emphasis">
                            Formas de pago
                        </span>
                        <span
                            class="text-caption font-weight-bold"
                            :class="Math.abs(remaining) >= 0.01 ? 'text-error' : 'text-success'"
                        >
                            Restante por asignar: {{ formatCurrency(remaining) }}
                        </span>
                    </div>

                    <v-card
                        v-for="(line, idx) in paymentLines"
                        :key="line.key"
                        variant="outlined"
                        class="pa-3 mb-3"
                    >
                        <div class="d-flex justify-space-between align-center mb-2">
                            <span class="text-caption text-medium-emphasis">
                                Forma de pago {{ idx + 1 }}
                            </span>
                            <BaseButton
                                v-if="paymentLines.length > 1"
                                action="delete"
                                icon="mdi-close"
                                color="error"
                                variant="text"
                                size="small"
                                tooltip="Quitar esta forma de pago"
                                @click="removePaymentLine(line.key)"
                            />
                        </div>
                        <v-row dense>
                            <v-col cols="12" md="5">
                                <v-select
                                    v-model="line.payment_method_id"
                                    :items="paymentMethods"
                                    item-title="name"
                                    item-value="id"
                                    label="Método de pago"
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model.number="line.amount"
                                    label="Importe"
                                    type="number"
                                    min="0"
                                    prefix="$"
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="line.reference"
                                    label="Referencia"
                                    :disabled="!methodFor(line)?.requires_reference"
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="12" md="6" v-if="methodFor(line)?.requires_bank_name">
                                <v-text-field
                                    v-model="line.bank_name"
                                    label="Banco"
                                    hide-details="auto"
                                />
                            </v-col>
                            <v-col cols="12" md="6" v-if="methodFor(line)?.requires_check_number">
                                <v-text-field
                                    v-model="line.check_number"
                                    label="Número de cheque"
                                    hide-details="auto"
                                />
                            </v-col>
                        </v-row>
                    </v-card>

                    <BaseButton
                        :icon-only="false"
                        action="add"
                        icon="mdi-plus"
                        text="Agregar otra forma de pago"
                        variant="tonal"
                        size="small"
                        :disabled="remaining <= 0"
                        @click="addPaymentLine"
                    />

                    <v-alert
                        v-if="!paymentMethods.length"
                        type="warning"
                        variant="tonal"
                        density="compact"
                        class="mt-3"
                    >
                        Este parque no tiene métodos de pago habilitados.
                    </v-alert>
                </div>
            </v-card-text>

            <v-card-actions class="justify-end">
                <BaseButton
                    :icon-only="false"
                    action="cancel"
                    text="Cancelar"
                    variant="text"
                    :disabled="loading"
                    @click="close"
                />
                <BaseButton
                    :icon-only="false"
                    action="save"
                    icon="mdi-cash-check"
                    text="Confirmar pago"
                    :loading="loading"
                    :disabled="!canConfirm"
                    @click="confirmPayment"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
