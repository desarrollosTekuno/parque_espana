<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const page = usePage<any>();

interface ConceptoItem {
    charge_id: number;
    concepto: string | null;
    monto_aplicado: number;
}

interface PaymentInfo {
    id: number;
    folio: string | null;
    paid_at: string | null;
    amount: number;
    reference: string | null;
    bank_name: string | null;
    check_number: string | null;
    metodo_pago: string | null;
    metodo_pago_codigo: string | null;
    metodo_pago_clave: string | null;
    cajero: string | null;
    cuenta_numero: string | null;
    titular: string | null;
    is_split: boolean;
    conceptos: ConceptoItem[];
}

interface Props {
    payment: PaymentInfo | null;
    alreadyCancelled: boolean;
}

const props = defineProps<Props>();

const isCheck = computed(() => props.payment?.metodo_pago_codigo === "CHECK");

const form = useForm({
    reason: "",
    is_bounced_check: false,
    confirmed: false,
});

const confirmed = ref(false);
const formRef = ref<{ validate(): Promise<{ valid: boolean }> } | null>(null);

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
});
const formatCurrency = (value: number) => currencyFormatter.format(Number(value ?? 0));

const formatDateTime = (value: string | null) => {
    if (!value) return "-";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(date);
};

const penaltyPreview = computed(() => {
    if (!props.payment || !form.is_bounced_check) return null;
    return Math.round(props.payment.amount * 1.1 * 100) / 100;
});

const submit = async () => {
    const result = await formRef.value?.validate();
    if (!result?.valid) return;

    if (!props.payment) return;

    form.confirmed = confirmed.value;
    form.post(route("payments.cancel.store", props.payment.id), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash?.success || "Pago cancelado correctamente.",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${form.errors.messageError || form.errors.is_bounced_check || "No se pudo cancelar el pago"}`,
                text: `${form.errors.exception || ""}`,
                icon: "error",
            });
        },
    });
};
</script>

<template>
    <Head title="Cancelar Pago" />

    <AppLayout>
        <template #header>Cancelar Pago</template>
        <template #options>
            <BaseButton
                :icon-only="false"
                action="cancel"
                text="Volver"
                @click="router.visit(route('tickets.index'))"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12" md="8" class="mx-auto">
                    <v-container class="py-6">

                        <v-alert v-if="alreadyCancelled" type="warning" variant="tonal">
                            Este pago ya está cancelado. No es necesario hacer nada más.
                        </v-alert>

                        <template v-else-if="payment">
                            <!-- Información del pago -->
                            <v-card class="pa-4 mb-4" variant="tonal">
                                <div class="text-subtitle-1 font-weight-bold mb-2">
                                    Información del pago
                                </div>
                                <v-row dense>
                                    <v-col cols="12" sm="6">
                                        <p><strong>Folio:</strong> {{ payment.folio || `Pago #${payment.id}` }}</p>
                                        <p><strong>Fecha:</strong> {{ formatDateTime(payment.paid_at) }}</p>
                                        <p><strong>Cuenta:</strong> {{ payment.cuenta_numero || "-" }}</p>
                                        <p><strong>Titular:</strong> {{ payment.titular || "-" }}</p>
                                    </v-col>
                                    <v-col cols="12" sm="6">
                                        <p><strong>Monto:</strong> {{ formatCurrency(payment.amount) }}</p>
                                        <p><strong>Método:</strong> {{ payment.metodo_pago_clave ?? "-" }} · {{ payment.metodo_pago || "-" }}</p>
                                        <p v-if="payment.bank_name"><strong>Banco:</strong> {{ payment.bank_name }}</p>
                                        <p v-if="payment.check_number"><strong>No. cheque:</strong> {{ payment.check_number }}</p>
                                        <p><strong>Cajero:</strong> {{ payment.cajero || "-" }}</p>
                                    </v-col>
                                </v-row>
                                <v-alert
                                    v-if="payment.is_split"
                                    type="info"
                                    variant="tonal"
                                    density="compact"
                                    class="mt-2"
                                >
                                    Este pago es parte de un cobro dividido en varias formas de pago.
                                    Solo se cancelará esta forma de pago ({{ formatCurrency(payment.amount) }}), las demás no se ven afectadas.
                                </v-alert>
                            </v-card>

                            <!-- Conceptos que cubrió -->
                            <v-card class="pa-4 mb-4" variant="outlined">
                                <div class="text-subtitle-1 font-weight-bold mb-3">
                                    Cargos que cubrió este pago
                                </div>
                                <v-list density="compact">
                                    <v-list-item
                                        v-for="c in payment.conceptos"
                                        :key="c.charge_id"
                                        :title="c.concepto || `Cargo #${c.charge_id}`"
                                        :subtitle="formatCurrency(c.monto_aplicado)"
                                    />
                                    <v-list-item v-if="!payment.conceptos.length" title="Sin cargos asociados" />
                                </v-list>
                            </v-card>

                            <!-- Advertencia -->
                            <v-alert type="error" variant="tonal" class="mb-4">
                                <strong>Esta acción es irreversible.</strong> Al cancelar:
                                <ul class="mt-1 ml-4">
                                    <li>Los cargos que cubrió este pago vuelven a quedar pendientes.</li>
                                    <li>El pago queda marcado como cancelado, conservando el historial.</li>
                                </ul>
                            </v-alert>

                            <v-form ref="formRef" @submit.prevent="submit">
                                <v-card class="pa-4">
                                    <v-textarea
                                        v-model="form.reason"
                                        label="Motivo de la cancelación"
                                        placeholder="Ej. Cheque rebotado por fondos insuficientes"
                                        rows="3"
                                        variant="outlined"
                                        class="mb-2"
                                        :rules="[(v: string) => !!v?.trim() || 'El motivo es requerido']"
                                        :error-messages="form.errors.reason"
                                    />

                                    <!-- Cheque rebotado -->
                                    <v-card
                                        v-if="isCheck"
                                        variant="outlined"
                                        class="pa-3 mb-4"
                                    >
                                        <v-checkbox
                                            v-model="form.is_bounced_check"
                                            color="warning"
                                            hide-details
                                        >
                                            <template #label>
                                                <div>
                                                    <div class="font-weight-medium">
                                                        Es un cheque rebotado
                                                    </div>
                                                    <div class="text-caption text-medium-emphasis">
                                                        Se generará un cargo pendiente por el monto original más 10% de recargo.
                                                    </div>
                                                </div>
                                            </template>
                                        </v-checkbox>
                                        <v-alert
                                            v-if="penaltyPreview !== null"
                                            type="warning"
                                            variant="tonal"
                                            density="compact"
                                            class="mt-2"
                                        >
                                            Se generará un cargo pendiente por {{ formatCurrency(penaltyPreview) }}
                                            ({{ formatCurrency(payment.amount) }} + 10%).
                                        </v-alert>
                                        <div
                                            v-if="form.errors.is_bounced_check"
                                            class="text-error text-caption mt-1"
                                        >
                                            {{ form.errors.is_bounced_check }}
                                        </div>
                                    </v-card>

                                    <!-- Confirmación -->
                                    <v-checkbox
                                        v-model="confirmed"
                                        color="error"
                                        class="mb-2"
                                        :rules="[(v: boolean) => v || 'Debes confirmar antes de continuar']"
                                    >
                                        <template #label>
                                            <span class="text-body-2">
                                                Confirmo que este pago debe cancelarse y que la información
                                                capturada es correcta.
                                            </span>
                                        </template>
                                    </v-checkbox>

                                    <div class="d-flex justify-end ga-2 mt-2">
                                        <v-btn
                                            variant="text"
                                            @click="router.visit(route('tickets.index'))"
                                        >
                                            Cancelar
                                        </v-btn>
                                        <v-btn
                                            color="error"
                                            type="submit"
                                            :loading="form.processing"
                                        >
                                            Confirmar cancelación de pago
                                        </v-btn>
                                    </div>
                                </v-card>
                            </v-form>
                        </template>

                    </v-container>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>
