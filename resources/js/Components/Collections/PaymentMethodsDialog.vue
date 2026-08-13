<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { computed, ref, watch } from "vue";
import { nowAsLocalInput } from "@/constants/formatDates";
import Swal from "sweetalert2";

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
    // true si esta línea es parte de un par entre parques (la del parque de
    // la sesión con pareja, sus "otro pago" extra, o la fila espejo del otro
    // parque) — el backend la prioriza para cubrir PRIMERO el cargo que se
    // reparte entre parques (p. ej. la mensualidad combo), antes de usarla
    // para cualquier otro cargo. Ver PaymentRegistrationService::registerSplit.
    is_park_split: boolean;
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
    // A qué método del catálogo (paymentMethods) corresponde esta fila —
    // igual a option_key en las filas base, pero distinto en las filas
    // "extra" (ver addExtraLine): permite tener dos filas de "Tarjeta de
    // débito (PE1)" con montos y referencias independientes cuando una sola
    // tarjeta no alcanza a cubrir lo que le toca a ese parque.
    source_option_key: string;
    payment_method_id: number;
    checked: boolean;
    amount: number | null;
    reference: string;
    bank_name: string;
    check_number: string;
    is_extra?: boolean;
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
const formRef = ref<{ validate(): Promise<{ valid: boolean }>; resetValidation(): void } | null>(null);
let extraLineCounter = 0;

const buildLines = (): PaymentLineForm[] =>
    props.paymentMethods.map((m) => ({
        option_key: m.option_key ?? String(m.id),
        source_option_key: m.option_key ?? String(m.id),
        payment_method_id: m.id,
        checked: false,
        amount: null,
        reference: "",
        bank_name: "",
        check_number: "",
    }));

// Agrega otra fila del MISMO método (p. ej. una segunda "Tarjeta de débito
// (PE1)") para cuando un solo instrumento no alcanza a cubrir lo que le
// toca pagar al cajero — se identifica y captura por separado, pero para
// efectos de qué método representa (referencia, banco, etc.) y de emparejado
// con el otro parque, se resuelve a través de source_option_key.
const addExtraLine = (line: PaymentLineForm) => {
    extraLineCounter += 1;
    const newLine: PaymentLineForm = {
        option_key: `${line.source_option_key}__extra${extraLineCounter}`,
        source_option_key: line.source_option_key,
        payment_method_id: line.payment_method_id,
        checked: true,
        amount: null,
        reference: "",
        bank_name: "",
        check_number: "",
        is_extra: true,
    };

    // Se inserta justo después de la última fila de este mismo método (la
    // base o, si ya había otra extra agregada antes, después de esa) en vez
    // de al final de toda la tabla — así quedan agrupadas visualmente bajo
    // el método al que pertenecen.
    let insertAt = lines.value.findIndex((l) => l.option_key === line.option_key) + 1;
    while (lines.value[insertAt]?.source_option_key === line.source_option_key) {
        insertAt += 1;
    }
    lines.value.splice(insertAt, 0, newLine);
};

const removeExtraLine = (line: PaymentLineForm) => {
    lines.value = lines.value.filter((l) => l.option_key !== line.option_key);
};

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

    formRef.value?.resetValidation();
};

// El componente permanece montado aunque el diálogo esté cerrado (solo su
// v-dialog interno se oculta/muestra vía modelValue), así que `lines` no se
// pierde entre una apertura y otra. Por eso NO se reinicia cada vez que se
// abre — si ya se había configurado algo, se conserva para que el cajero lo
// revise o corrija en vez de encontrarse el formulario limpio otra vez. Solo
// se reinicia la primera vez, o si el total cambió desde la última vez que
// se preparó (montos viejos que ya no cuadran con el nuevo total).
const lastPreparedTotal = ref<number | null>(null);

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        if (lastPreparedTotal.value === null || lastPreparedTotal.value !== props.total) {
            resetForm();
            lastPreparedTotal.value = props.total;
        }
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
    const method = methodByKey(line.source_option_key);
    return !!method && method.is_session_club === false && isSplit.value && pairedKey.value.has(line.source_option_key);
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
    const partnerKey = pairedKey.value.get(line.source_option_key);
    if (!partnerKey) return;
    const partner = lines.value.find((l) => l.option_key === partnerKey);
    if (!partner) return;

    partner.checked = line.checked;
    partner.amount = line.checked
        ? (clubBreakdownAmount(methodByKey(partnerKey)?.club_code) || null)
        : null;
};

// True si ya hay algún método con pareja marcado (y su pareja ya calculada)
// — sirve para saber si un SEGUNDO método (p. ej. porque una sola tarjeta no
// alcanzó para cubrir la parte de este parque) debe volver a marcar/rellenar
// su propia pareja del otro parque o no: si ya se cubrió, no debe.
const anySplitPartnerAlreadyClaimed = computed(() =>
    lines.value.some((l) => {
        if (!l.checked) return false;
        const partnerKey = pairedKey.value.get(l.source_option_key);
        if (!partnerKey) return false;
        return !!lines.value.find((p) => p.option_key === partnerKey)?.checked;
    }),
);

const onToggle = (line: PaymentLineForm) => {
    // Las filas "extra" (ver addExtraLine) nunca controlan la pareja del
    // otro parque — esa ya quedó fija con la fila base del mismo método;
    // una extra solo captura otro monto/referencia del mismo lado.
    const isPairedSplit = isSplit.value && !line.is_extra && pairedKey.value.has(line.source_option_key);

    if (!line.checked) {
        line.amount = null;
        line.reference = "";
        line.bank_name = "";
        line.check_number = "";
        if (isPairedSplit) syncPair(line);
        return;
    }

    if (isPairedSplit) {
        if (anySplitPartnerAlreadyClaimed.value) {
            // La parte del otro parque ya quedó cubierta por otro método
            // (p. ej. se marcó Tarjeta de débito y ahora se agrega Tarjeta
            // de crédito porque la primera no alcanzó): este método
            // adicional solo cubre lo que falte de ESTE parque, sin volver
            // a marcar ni duplicar su propia pareja.
            line.amount = remaining.value > 0 ? remaining.value : null;
            return;
        }

        // Primer método con pareja que se marca: por default se propone lo
        // que le toca a este parque según el desglose real del cobro; si
        // sobra algo más (otros conceptos no divididos), el cajero lo
        // agrega escribiendo encima — no se recalcula la fila pareja a
        // partir de eso (ver syncPair).
        line.amount = clubBreakdownAmount(methodByKey(line.source_option_key)?.club_code) || null;
        syncPair(line);
        return;
    }

    if (!line.amount) {
        line.amount = remaining.value > 0 ? remaining.value : null;
    }
};

const isLineValid = (line: PaymentLineForm): boolean => {
    if (!line.amount || line.amount <= 0) return false;
    const m = methodByKey(line.source_option_key);
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

// Reglas Vuetify por campo — un campo no marcado/deshabilitado siempre pasa
// (no debe bloquear el formulario por un método que ni siquiera se está
// usando). El cuadre exacto del total (canConfirm) es una validación
// cruzada entre filas, no de un campo individual, así que se sigue
// mostrando aparte en "Restante por asignar".
const paidAtRules = [(v: string) => !!v || "La fecha y hora del pago son requeridas"];

const amountRules = (line: PaymentLineForm) => [
    (v: number | null) => !line.checked || (v !== null && Number(v) > 0) || "El importe debe ser mayor a cero",
];

const referenceRules = (line: PaymentLineForm) => [
    (v: string) => {
        if (!line.checked || isMirrored(line)) return true;
        if (!methodByKey(line.source_option_key)?.requires_reference) return true;
        return !!v || "La referencia es requerida para este método";
    },
];

const bankNameRules = [(v: string) => !!v || "El banco es requerido"];
const checkNumberRules = [(v: string) => !!v || "El número de cheque es requerido"];

// Este botón solo CONFIGURA con qué formas de pago se va a cobrar — no
// registra nada todavía (eso pasa hasta que el cajero de clic en "Registrar
// cobro" afuera del diálogo, con su propia confirmación). Por eso pide
// confirmación aquí también: una vez que se acepta y se cierra el diálogo,
// revisar/corregir la forma de pago implica volver a abrirlo desde cero.
const confirmPayment = async () => {
    const result = await formRef.value?.validate();
    if (!result?.valid) return;
    if (!canConfirm.value) return;

    const swalResult = await Swal.fire({
        icon: "question",
        title: "¿Confirmar formas de pago?",
        html: `Se configuraron ${checkedLines.value.length} forma(s) de pago por un total de ${formatCurrency(assignedTotal.value)}. Después de aceptar podrás revisar todo antes de registrar el cobro.`,
        showCancelButton: true,
        confirmButtonText: "Sí, aceptar",
        cancelButtonText: "Revisar de nuevo",
    });

    if (!swalResult.isConfirmed) return;

    emit("confirm", {
        paid_at: paidAt.value,
        payments: checkedLines.value.map((l) => ({
            payment_method_id: l.payment_method_id,
            amount: round2(Number(l.amount)),
            reference: l.reference,
            bank_name: l.bank_name,
            check_number: l.check_number,
            is_park_split: isSplit.value && pairedKey.value.has(l.source_option_key),
        })),
    });

    dialogModel.value = false;
};

const close = () => {
    dialogModel.value = false;
};
</script>

<template>
    <v-dialog v-model="dialogModel" max-width="1200" persistent scrollable>
        <v-card style="max-height: 90vh;">
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
                    style="flex: none;"
                >
                    <div class="text-body-2 mb-1" style="white-space: normal;">
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

                <v-form ref="formRef">
                <v-text-field
                    v-model="paidAt"
                    label="Fecha y hora del pago"
                    type="datetime-local"
                    hide-details="auto"
                    :rules="paidAtRules"
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

                    <v-table v-else density="comfortable" style="table-layout: fixed; width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 40px;" />
                                <th style="width: 70px;">Clave</th>
                                <th style="width: 190px;">Método</th>
                                <th style="width: 150px;">Cantidad</th>
                                <th>Referencia</th>
                                <th style="width: 40px;" />
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
                                    <td class="text-medium-emphasis">
                                        {{ methodByKey(line.source_option_key)?.internal_key ?? "" }}
                                    </td>
                                    <td>
                                        {{ methodLabel(line.source_option_key) }}
                                        <v-icon
                                            v-if="isMirrored(line)"
                                            icon="mdi-link-variant"
                                            size="14"
                                            class="text-medium-emphasis ml-1"
                                        />
                                        <span v-if="line.is_extra" class="text-caption text-medium-emphasis">
                                            (otro pago)
                                        </span>
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
                                            :rules="amountRules(line)"
                                        />
                                    </td>
                                    <td>
                                        <v-text-field
                                            v-model="line.reference"
                                            density="compact"
                                            hide-details="auto"
                                            :disabled="!line.checked || !methodByKey(line.source_option_key)?.requires_reference"
                                            :rules="referenceRules(line)"
                                        />
                                    </td>
                                    <td>
                                        <BaseButton
                                            v-if="line.is_extra"
                                            :icon-only="true"
                                            action="delete"
                                            icon="mdi-close"
                                            variant="text"
                                            size="x-small"
                                            tooltip="Quitar este pago"
                                            @click="removeExtraLine(line)"
                                        />
                                        <BaseButton
                                            v-else-if="!isMirrored(line)"
                                            :icon-only="true"
                                            action="add"
                                            icon="mdi-plus"
                                            variant="text"
                                            size="x-small"
                                            tooltip="Agregar otro pago con este método (p. ej. otra tarjeta)"
                                            @click="addExtraLine(line)"
                                        />
                                    </td>
                                </tr>
                                <tr v-if="line.checked && (methodByKey(line.source_option_key)?.requires_bank_name || methodByKey(line.source_option_key)?.requires_check_number)">
                                    <td />
                                    <td />
                                    <td colspan="4" class="pb-2 pt-0">
                                        <div class="d-flex flex-wrap ga-3">
                                            <v-text-field
                                                v-if="methodByKey(line.source_option_key)?.requires_bank_name"
                                                v-model="line.bank_name"
                                                label="Banco"
                                                density="compact"
                                                variant="outlined"
                                                hide-details="auto"
                                                prepend-inner-icon="mdi-bank-outline"
                                                style="max-width: 220px;"
                                                :rules="bankNameRules"
                                            />
                                            <v-text-field
                                                v-if="methodByKey(line.source_option_key)?.requires_check_number"
                                                v-model="line.check_number"
                                                label="Número de cheque"
                                                density="compact"
                                                variant="outlined"
                                                hide-details="auto"
                                                prepend-inner-icon="mdi-pound"
                                                style="max-width: 220px;"
                                                :rules="checkNumberRules"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </v-table>
                </div>
                </v-form>
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
                    icon="mdi-check"
                    text="Aceptar"
                    :loading="loading"
                    :disabled="!canConfirm"
                    @click="confirmPayment"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style>
/* El v-dialog de Vuetify queda por encima del swal2 por default (mismo
   ajuste que ya se usa en Amenities/Index.vue y Members/Show.vue) — sin
   esto, la confirmación de "Aceptar" aparece detrás de este modal. */
.swal2-container {
    z-index: 9999 !important;
}
</style>
