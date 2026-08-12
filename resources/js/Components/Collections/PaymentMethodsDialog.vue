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
    club_id?: number;
    club_code?: string;
    is_session_club?: boolean;
    option_key?: string;
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

const round2 = (value: number) => Math.round(value * 100) / 100;

interface PaymentLineForm {
    option_key: string;
    payment_method_id: number;
    checked: boolean;
    amount: number | null;
    reference: string;
    bank_name: string;
    check_number: string;
}

// Dos filas distintas (una por parque) pueden compartir el mismo
// payment_method_id de catálogo (p. ej. "Tarjeta de crédito" habilitada en
// ambos parques) — se identifican por option_key, no por id, para poder
// mostrarlas y capturarlas como medios de cobro independientes.
const methodByKey = (key: string): PaymentMethodItem | null =>
    props.paymentMethods.find((m) => m.option_key === key) ?? null;

const methodLabel = (key: string): string => {
    const m = methodByKey(key);
    if (!m) return "";
    return m.is_session_club === false ? `${m.name} (${m.club_code})` : m.name;
};

const paidAt = ref(nowAsLocalInput());
const lines = ref<PaymentLineForm[]>([]);

const buildLines = (): PaymentLineForm[] =>
    props.paymentMethods.map((m) => ({
        option_key: m.option_key ?? String(m.id),
        payment_method_id: m.id,
        checked: false,
        amount: null,
        reference: "",
        bank_name: "",
        check_number: "",
    }));

// Por default se propone pagar todo en efectivo (el método de pago
// predeterminado del mostrador); el cajero puede desmarcarlo y elegir otros.
const resetForm = () => {
    paidAt.value = nowAsLocalInput();
    lines.value = buildLines();

    const cashLine = lines.value.find((l) => methodByKey(l.option_key)?.code === "CASH");
    if (cashLine) {
        cashLine.checked = true;
        cashLine.amount = round2(props.total);
    }
};

watch(
    () => props.modelValue,
    (open) => {
        if (open) resetForm();
    },
);

const isSplit = computed(() => props.clubBreakdown.length > 1);

// Monto real que le toca a cada parque según el desglose del cobro (p. ej.
// la mensualidad combo repartida 50/50) — NO una proporción sobre el total
// del diálogo, que puede incluir además otros conceptos que no se dividen
// (p. ej. un concepto nuevo capturado a mano, propio del parque de la
// sesión). Antes se usaba `entry.amount / props.total`, lo que daba una
// proporción incorrecta en cuanto el total incluía algo más que el
// desglosable, y además hacía que escribir un monto distinto en la fila de
// la sesión (para cubrir ese extra) recalculara también la fila pareja.
const clubBreakdownAmount = (clubCode: string | null | undefined): number => {
    if (!clubCode) return 0;
    return props.clubBreakdown.find((c) => c.club_code === clubCode)?.amount ?? 0;
};

// Empareja cada método del parque de la sesión con su equivalente en el
// otro parque (mismo payment_method_id de catálogo). Cuando el cobro está
// dividido, marcar o capturar la fila de la sesión marca y calcula
// automáticamente la del otro parque con su parte proporcional — ya no
// tiene sentido pedirle al cajero que las llene por separado.
const pairedKey = computed<Map<string, string>>(() => {
    const map = new Map<string, string>();
    const sessionOptions = props.paymentMethods.filter((m) => m.is_session_club);
    const otherOptions = props.paymentMethods.filter((m) => m.is_session_club === false);

    sessionOptions.forEach((s) => {
        const pair = otherOptions.find((o) => o.id === s.id);
        if (pair && s.option_key && pair.option_key) {
            map.set(s.option_key, pair.option_key);
            map.set(pair.option_key, s.option_key);
        }
    });

    return map;
});

// La fila del OTRO parque queda bloqueada (de solo lectura) cuando está
// emparejada con una del parque de la sesión y el cobro está dividido: su
// estado se controla desde la fila de la sesión, no se captura dos veces.
const isMirrored = (line: PaymentLineForm): boolean => {
    const method = methodByKey(line.option_key);
    return !!method && method.is_session_club === false && isSplit.value && pairedKey.value.has(line.option_key);
};

const checkedLines = computed(() => lines.value.filter((l) => l.checked));

const assignedTotal = computed(() =>
    round2(checkedLines.value.reduce((sum, l) => sum + Number(l.amount ?? 0), 0)),
);
const remaining = computed(() => round2(props.total - assignedTotal.value));

// Fija la fila pareja (del otro parque) a su monto real del desglose,
// independiente de lo que traiga tecleado la fila de la sesión — así el
// cajero puede agregar de más en su propia fila (para cubrir otros
// conceptos no divididos) sin que se altere lo que le toca al otro parque.
const syncPair = (line: PaymentLineForm) => {
    const partnerKey = pairedKey.value.get(line.option_key);
    if (!partnerKey) return;
    const partner = lines.value.find((l) => l.option_key === partnerKey);
    if (!partner) return;

    partner.checked = line.checked;
    partner.amount = line.checked
        ? (clubBreakdownAmount(methodByKey(partnerKey)?.club_code) || null)
        : null;
};

const onToggle = (line: PaymentLineForm) => {
    const isPairedSplit = isSplit.value && pairedKey.value.has(line.option_key);

    if (!line.checked) {
        line.amount = null;
        line.reference = "";
        line.bank_name = "";
        line.check_number = "";
        if (isPairedSplit) syncPair(line);
        return;
    }

    if (isPairedSplit) {
        // Por default se propone lo que le toca a este parque según el
        // desglose real del cobro; si sobra algo más (otros conceptos no
        // divididos), el cajero lo agrega escribiendo encima — no se
        // recalcula la fila pareja a partir de eso (ver syncPair).
        line.amount = clubBreakdownAmount(methodByKey(line.option_key)?.club_code) || null;
        syncPair(line);
        return;
    }

    if (!line.amount) {
        line.amount = remaining.value > 0 ? remaining.value : null;
    }
};

const isLineValid = (line: PaymentLineForm): boolean => {
    if (!line.amount || line.amount <= 0) return false;
    const m = methodByKey(line.option_key);
    if (m?.requires_reference && !line.reference) return false;
    if (m?.requires_bank_name && !line.bank_name) return false;
    if (m?.requires_check_number && !line.check_number) return false;
    return true;
};

const canConfirm = computed(() => {
    if (!checkedLines.value.length) return false;
    if (Math.abs(remaining.value) >= 0.01) return false;
    return checkedLines.value.every(isLineValid);
});

const confirmPayment = () => {
    if (!canConfirm.value) return;

    emit("confirm", {
        paid_at: paidAt.value,
        payments: checkedLines.value.map((l) => ({
            payment_method_id: l.payment_method_id,
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
    <v-dialog v-model="dialogModel" max-width="1200" persistent>
        <v-card>
            <v-card-title class="d-flex justify-space-between align-center">
                <span>Método de pago</span>
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

                    <v-alert
                        v-if="!paymentMethods.length"
                        type="warning"
                        variant="tonal"
                        density="compact"
                    >
                        Este parque no tiene métodos de pago habilitados.
                    </v-alert>

                    <v-table v-else density="comfortable">
                        <thead>
                            <tr>
                                <th style="width: 40px;" />
                                <th>Método</th>
                                <th style="min-width: 150px;">Cantidad</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="line in lines" :key="line.option_key">
                                <tr>
                                    <td>
                                        <v-checkbox
                                            v-model="line.checked"
                                            density="compact"
                                            hide-details
                                            :disabled="isMirrored(line)"
                                            @update:model-value="onToggle(line)"
                                        />
                                    </td>
                                    <td>
                                        {{ methodLabel(line.option_key) }}
                                        <v-icon
                                            v-if="isMirrored(line)"
                                            icon="mdi-link-variant"
                                            size="14"
                                            class="text-medium-emphasis ml-1"
                                        />
                                    </td>
                                    <td>
                                        <v-text-field
                                            v-model.number="line.amount"
                                            type="number"
                                            min="0"
                                            prefix="$"
                                            density="compact"
                                            hide-details="auto"
                                            :disabled="!line.checked || isMirrored(line)"
                                        />
                                    </td>
                                    <td>
                                        <v-text-field
                                            v-model="line.reference"
                                            density="compact"
                                            hide-details="auto"
                                            :disabled="!line.checked || !methodByKey(line.option_key)?.requires_reference"
                                        />
                                    </td>
                                </tr>
                                <tr v-if="line.checked && (methodByKey(line.option_key)?.requires_bank_name || methodByKey(line.option_key)?.requires_check_number)">
                                    <td />
                                    <td colspan="3" class="pb-3">
                                        <div class="d-flex flex-wrap ga-3">
                                            <v-text-field
                                                v-if="methodByKey(line.option_key)?.requires_bank_name"
                                                v-model="line.bank_name"
                                                label="Banco"
                                                density="compact"
                                                hide-details="auto"
                                                style="max-width: 220px;"
                                            />
                                            <v-text-field
                                                v-if="methodByKey(line.option_key)?.requires_check_number"
                                                v-model="line.check_number"
                                                label="Número de cheque"
                                                density="compact"
                                                hide-details="auto"
                                                style="max-width: 220px;"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </v-table>
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
