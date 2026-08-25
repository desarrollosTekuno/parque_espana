<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { ref } from "vue";

const page = usePage<any>();

interface ConceptoItem {
    charge_id: number;
    concepto: string | null;
    monto_aplicado: number;
}

interface GroupPaymentItem {
    id: number;
    metodo_pago: string | null;
    metodo_pago_codigo: string | null;
    amount: number;
    reference: string | null;
    bank_name: string | null;
    check_number: string | null;
    status: string;
    conceptos: ConceptoItem[];
}

interface GroupInfo {
    payment_group_id: string;
    folio: string | null;
    paid_at: string | null;
    total: number;
    cuenta_numero: string | null;
    titular: string | null;
    cajero: string | null;
    payments: GroupPaymentItem[];
}

interface Props {
    group: GroupInfo;
    alreadyCancelled: boolean;
}

const props = defineProps<Props>();

const form = useForm({
    reason: "",
    also_cancel_charge: false,
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

const pendingCount = props.group.payments.filter((p) => p.status !== "cancelled").length;

const submit = async () => {
    const result = await formRef.value?.validate();
    if (!result?.valid) return;

    form.confirmed = confirmed.value;
    form.post(route("payments.cancel-group.store", props.group.payment_group_id), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash?.success || "Ticket cancelado correctamente.",
                icon: "success",
            });
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${form.errors.messageError || "No se pudo cancelar el ticket"}`,
                text: `${form.errors.exception || ""}`,
                icon: "error",
            });
        },
    });
};
</script>

<template>
    <Head title="Cancelar Ticket" />

    <AppLayout>
        <template #header>Cancelar Ticket completo</template>
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
                            Este ticket ya está cancelado por completo. No es necesario hacer nada más.
                        </v-alert>

                        <template v-else>
                            <!-- Información del ticket -->
                            <v-card class="pa-4 mb-4" variant="tonal">
                                <div class="text-subtitle-1 font-weight-bold mb-2">
                                    Información del ticket
                                </div>
                                <v-row dense>
                                    <v-col cols="12" sm="6">
                                        <p><strong>Folio:</strong> {{ group.folio || `Grupo #${group.payment_group_id}` }}</p>
                                        <p><strong>Fecha:</strong> {{ formatDateTime(group.paid_at) }}</p>
                                        <p><strong>Cuenta:</strong> {{ group.cuenta_numero || "-" }}</p>
                                        <p><strong>Titular:</strong> {{ group.titular || "-" }}</p>
                                    </v-col>
                                    <v-col cols="12" sm="6">
                                        <p><strong>Total:</strong> {{ formatCurrency(group.total) }}</p>
                                        <p><strong>Cajero:</strong> {{ group.cajero || "-" }}</p>
                                    </v-col>
                                </v-row>
                            </v-card>

                            <!-- Formas de pago -->
                            <v-card class="pa-4 mb-4" variant="outlined">
                                <div class="text-subtitle-1 font-weight-bold mb-3">
                                    Formas de pago que se cancelarán ({{ pendingCount }})
                                </div>
                                <v-list density="compact">
                                    <template v-for="p in group.payments" :key="p.id">
                                        <v-list-item>
                                            <template #title>
                                                {{ p.metodo_pago_codigo ?? "-" }} · {{ p.metodo_pago || `Pago #${p.id}` }} — {{ formatCurrency(p.amount) }}
                                                <v-chip v-if="p.status === 'cancelled'" size="x-small" color="grey" variant="tonal" class="ml-1">
                                                    Ya cancelado
                                                </v-chip>
                                            </template>
                                            <template #subtitle>
                                                <span v-if="p.reference || p.bank_name || p.check_number">
                                                    {{ [p.reference, p.bank_name, p.check_number].filter(Boolean).join(" · ") }}
                                                </span>
                                                <span v-if="p.conceptos.length">
                                                    {{ p.conceptos.map((c) => c.concepto || `Cargo #${c.charge_id}`).join(", ") }}
                                                </span>
                                            </template>
                                        </v-list-item>
                                    </template>
                                </v-list>
                            </v-card>

                            <!-- Advertencia -->
                            <v-alert type="error" variant="tonal" class="mb-4">
                                <strong>Esta acción es irreversible.</strong> Al cancelar el ticket completo:
                                <ul class="mt-1 ml-4">
                                    <li>TODAS las formas de pago pendientes de este ticket se cancelan (rollback completo del cobro).</li>
                                    <li v-if="form.also_cancel_charge">
                                        Los cargos que cubrieron se cancelan por completo — NO vuelven a quedar pendientes.
                                    </li>
                                    <li v-else>
                                        Los cargos que cubrieron vuelven a quedar pendientes.
                                    </li>
                                    <li>Los pagos quedan marcados como cancelados, conservando el historial.</li>
                                </ul>
                                <div class="mt-1">
                                    Si solo quieres cancelar una forma de pago específica (por ejemplo, un cheque rebotado), vuelve y usa "Cancelar forma de pago" en su lugar.
                                </div>
                            </v-alert>

                            <v-form ref="formRef" @submit.prevent="submit">
                                <v-card class="pa-4">
                                    <v-textarea
                                        v-model="form.reason"
                                        label="Motivo de la cancelación"
                                        placeholder="Ej. Se canceló la transacción completa por error de captura"
                                        rows="3"
                                        variant="outlined"
                                        class="mb-2"
                                        :rules="[(v: string) => !!v?.trim() || 'El motivo es requerido']"
                                        :error-messages="form.errors.reason"
                                    />

                                    <!-- Cancelar también los cargos -->
                                    <v-card variant="outlined" class="pa-3 mb-4">
                                        <v-checkbox
                                            v-model="form.also_cancel_charge"
                                            color="error"
                                            hide-details
                                        >
                                            <template #label>
                                                <div>
                                                    <div class="font-weight-medium">
                                                        Cancelar también los cargos
                                                    </div>
                                                    <div class="text-caption text-medium-emphasis">
                                                        Los cargos que cubrió cada forma de pago se anulan por completo, en vez de volver a quedar pendientes. Útil para pruebas o cobros capturados por error.
                                                    </div>
                                                </div>
                                            </template>
                                        </v-checkbox>
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
                                                Confirmo que este ticket completo debe cancelarse y que la información
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
                                            Confirmar cancelación del ticket
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
