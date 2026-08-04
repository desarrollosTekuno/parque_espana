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

export interface PaymentConfirmPayload {
    payment_method_id: number;
    paid_at: string;
    reference: string;
    bank_name: string;
    check_number: string;
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

const selectedMethodId = ref<number | null>(null);
const paidAt = ref(nowAsLocalInput());
const reference = ref("");
const bankName = ref("");
const checkNumber = ref("");

const resetForm = () => {
    selectedMethodId.value = null;
    paidAt.value = nowAsLocalInput();
    reference.value = "";
    bankName.value = "";
    checkNumber.value = "";
};

watch(
    () => props.modelValue,
    (open) => {
        if (open) resetForm();
    },
);

const selectedMethod = computed(
    () =>
        props.paymentMethods.find((m) => m.id === selectedMethodId.value) ??
        null,
);

const selectMethod = (methodId: number) => {
    selectedMethodId.value = methodId;
};

const isSplit = computed(() => props.clubBreakdown.length > 1);

const canConfirm = computed(() => {
    if (!selectedMethodId.value) return false;
    const m = selectedMethod.value;
    if (m?.requires_reference && !reference.value) return false;
    if (m?.requires_bank_name && !bankName.value) return false;
    if (m?.requires_check_number && !checkNumber.value) return false;
    return true;
});

const confirmPayment = () => {
    if (!canConfirm.value || !selectedMethodId.value) return;

    emit("confirm", {
        payment_method_id: selectedMethodId.value,
        paid_at: paidAt.value,
        reference: reference.value,
        bank_name: bankName.value,
        check_number: checkNumber.value,
    });
};

const close = () => {
    dialogModel.value = false;
};
</script>

<template>
    <v-dialog v-model="dialogModel" max-width="640" persistent>
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

                <div>
                    <div class="text-caption text-medium-emphasis mb-2">
                        Método de pago
                    </div>
                    <v-row dense>
                        <v-col
                            v-for="method in paymentMethods"
                            :key="method.id"
                            cols="6"
                            md="4"
                        >
                            <v-card
                                :variant="selectedMethodId === method.id ? 'flat' : 'outlined'"
                                :color="selectedMethodId === method.id ? 'primary' : undefined"
                                class="pa-3 cursor-pointer"
                                @click="selectMethod(method.id)"
                            >
                                <div class="text-body-2 font-weight-medium">
                                    {{ method.name }}
                                </div>
                                <div class="text-caption">
                                    {{
                                        selectedMethodId === method.id
                                            ? formatCurrency(total)
                                            : formatCurrency(0)
                                    }}
                                </div>
                            </v-card>
                        </v-col>
                        <v-col v-if="!paymentMethods.length" cols="12">
                            <v-alert type="warning" variant="tonal" density="compact">
                                Este parque no tiene métodos de pago habilitados.
                            </v-alert>
                        </v-col>
                    </v-row>
                </div>

                <v-text-field
                    v-model="paidAt"
                    label="Fecha y hora del pago"
                    type="datetime-local"
                    hide-details="auto"
                />
                <v-text-field
                    v-model="reference"
                    label="Referencia"
                    :disabled="!selectedMethod?.requires_reference"
                    hide-details="auto"
                />
                <v-text-field
                    v-model="bankName"
                    label="Banco"
                    :disabled="!selectedMethod?.requires_bank_name"
                    hide-details="auto"
                />
                <v-text-field
                    v-model="checkNumber"
                    label="Número de cheque"
                    :disabled="!selectedMethod?.requires_check_number"
                    hide-details="auto"
                />
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
