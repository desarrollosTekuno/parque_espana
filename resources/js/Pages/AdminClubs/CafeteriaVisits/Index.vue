<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { debounce } from 'lodash'
import { customToastSwal } from '@/utils/swal'
import BaseButton from '@/Components/BaseButton.vue'
import { nowAsLocalInput } from '@/constants/formatDates'

const page = usePage()
const can = page.props.auth.permissions

// Types
interface CafeteriaVisit {
    id: number
    visitor_name: string
    document_type: string
    document_number: string
    entered_at: string
    expires_at: string
    min_consumption: string | number
    consumption_amount: string | number | null
    access_fee_waived: boolean | null
    access_fee_charged: string | number | null
    checked_out_at: string | null
    status: 'active' | 'completed'
    notes: string | null
}

interface PaymentMethodItem {
    id: number
    code: string
    name: string
    requires_reference: boolean
    requires_bank_name: boolean
    requires_check_number: boolean
    affects_cash_cut: boolean
}

interface Props {
    activeVisits?: CafeteriaVisit[]
    visitHistory?: any
    cafeteriaConfig?: { min_consumption: number; duration_hours: number }
    clubPaymentMethods?: PaymentMethodItem[]
}

const props = withDefaults(defineProps<Props>(), {
    activeVisits:       () => [],
    visitHistory:       null,
    cafeteriaConfig:    () => ({ min_consumption: 300, duration_hours: 2 }),
    clubPaymentMethods: () => [],
})

// Clock
const now = ref(new Date())
let clockInterval: ReturnType<typeof setInterval>
onMounted(() => {
    clockInterval = setInterval(() => { now.value = new Date() }, 30_000)
})
onUnmounted(() => clearInterval(clockInterval))

// Tabs
const activeTab = ref('active')

// Headers
const DOCUMENT_LABELS: Record<string, string> = {
    INE:                'INE / IFE',
    pasaporte:          'Pasaporte',
    licencia:           'Licencia de conducir',
    credencial_trabajo: 'Credencial de trabajo',
    otro:               'Otro',
}

const documentTypeOptions = [
    { value: 'INE',                title: 'INE / IFE' },
    { value: 'pasaporte',          title: 'Pasaporte' },
    { value: 'licencia',           title: 'Licencia de conducir' },
    { value: 'credencial_trabajo', title: 'Credencial de trabajo' },
    { value: 'otro',               title: 'Otro' },
]

const timeInfo = (expiresAt: string) => {
    const expiration = new Date(expiresAt);
    const diff = expiration.getTime() - now.value.getTime();
    console.log('expiresAt:', expiresAt);
    console.log('now local:', now.value.toString());
    console.log('expiration local:', expiration.toString());

    if (diff <= 0) {
        const overMin = Math.floor(-diff / 60000);

        const overText =
            overMin >= 60
                ? `${Math.floor(overMin / 60)}h ${overMin % 60}m`
                : `${overMin}m`;

        return {
            text: `Vencido hace ${overText}`,
            color: 'error',
            expired: true,
        };
    }

    const hours = Math.floor(diff / 3600000);
    const minutes = Math.floor((diff % 3600000) / 60000);

    return {
        text: hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`,
        color: diff < 30 * 60 * 1000 ? 'warning' : 'success',
        expired: false,
    };
};

const formatDateTime = (val: string | null) => {
    if (!val) return '-';

    const [date, time] = val.split('T');
    const [year, month, day] = date.split('-');

    return `${day}/${month}/${year} ${time.substring(0, 5)}`;
};

// Visitantes activos
const activeHeaders = [
    { title: 'Visitante',        key: 'visitor_name',   sortable: false },
    { title: 'Identificación',   key: 'document',       sortable: false },
    { title: 'Ingresó',          key: 'entered_at',     sortable: false },
    { title: 'Tiempo restante',  key: 'time_remaining', sortable: false },
    { title: 'Acciones',         key: 'actions',        sortable: false },
]

const activeItems = ref<CafeteriaVisit[]>([])
watch(() => props.activeVisits, val => { activeItems.value = val ?? [] }, { immediate: true })

const fetchActiveVisits = () => {
    router.get(route('cafeteria-visits.index'), {}, {
        preserveState: true,
        replace: true,
        only: ['activeVisits'],
    })
}

const expiredCount = computed(() =>
    activeItems.value.filter(v => new Date(v.expires_at.replace('Z', '')) < now.value).length
)

// Historial de visitas
const historyHeaders = [
    { title: 'Visitante',      key: 'visitor_name',     sortable: false },
    { title: 'Identificación', key: 'document',          sortable: false },
    { title: 'Ingresó',        key: 'entered_at',        sortable: false },
    { title: 'Salió',          key: 'checked_out_at',    sortable: false },
    { title: 'Consumo',        key: 'consumption_amount',sortable: false },
    { title: 'Acceso',         key: 'access_fee_waived', sortable: false },
    { title: 'Cobrado',        key: 'access_fee_charged',sortable: false },
]

const historyItems   = ref<CafeteriaVisit[]>([])
const historyTotal   = ref(0)
const historyLoading = ref(false)
const historySearch  = ref('')
const historyOptions = ref({ page: 1, itemsPerPage: 15 })

const fetchHistory = () => {
    historyLoading.value = true
    router.get(route('cafeteria-visits.index'), {
        history_page:     historyOptions.value.page,
        history_per_page: historyOptions.value.itemsPerPage,
        history_search:   historySearch.value.trim() || null,
    }, {
        preserveState: true,
        replace: true,
        only: ['visitHistory'],
    })
}

watch(() => props.visitHistory, val => {
    historyItems.value   = val?.data ?? []
    historyTotal.value   = val?.total ?? 0
    historyLoading.value = false
}, { immediate: true })

watch(historySearch, debounce(() => { historyOptions.value.page = 1; fetchHistory() }, 400))
watch([historyOptions], debounce(fetchHistory, 400), { deep: true })

// Modal de registro
const showRegisterModal   = ref(false)
const registerSubmitting  = ref(false)
const registerForm = ref({
    visitor_name:    '',
    document_type:   'INE',
    document_number: '',
    notes:           '',
})

const estimatedExpiry = computed(() => {
    const d = new Date()
    d.setHours(d.getHours() + (props.cafeteriaConfig?.duration_hours ?? 2))
    return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
})

const canRegister = computed(() =>
    registerForm.value.visitor_name.trim().length > 0 &&
    registerForm.value.document_number.trim().length > 0
)

const openRegisterModal = () => {
    registerForm.value = { visitor_name: '', document_type: 'INE', document_number: '', notes: '' }
    showRegisterModal.value = true
}

const submitRegister = () => {
    if (!canRegister.value) return
    registerSubmitting.value = true
    router.post(route('cafeteria-visits.store'), {
        visitor_name:    registerForm.value.visitor_name.trim(),
        document_type:   registerForm.value.document_type,
        document_number: registerForm.value.document_number.trim(),
        notes:           registerForm.value.notes.trim() || null,
    }, {
        onSuccess: () => {
            customToastSwal({ title: (page.props.flash as any).success, icon: 'success' })
            showRegisterModal.value = false
            fetchActiveVisits()
        },
        onError: (errors: any) => {
            customToastSwal({
                title: errors.messageError ?? Object.values(errors).flat().join(' ') ?? 'Error al registrar',
                icon: 'error',
            })
        },
        onFinish: () => { registerSubmitting.value = false },
    })
}

// Modal salida
const showCheckoutModal   = ref(false)
const selectedVisit       = ref<CafeteriaVisit | null>(null)
const checkoutSubmitting  = ref(false)
const consumptionInput    = ref('')

const consumptionNumber = computed(() => {
    const n = parseFloat(consumptionInput.value)
    return isNaN(n) || n < 0 ? 0 : n
})

const minConsumption = computed(() =>
    Number(selectedVisit.value?.min_consumption ?? props.cafeteriaConfig?.min_consumption ?? 300)
)

const accessWaived = computed(() => consumptionNumber.value >= minConsumption.value)

// Datos de pago (solo aplica cuando el acceso no queda exento)
const paymentForm = ref({
    payment_method_id: null as number | null,
    paid_at:           nowAsLocalInput(),
    reference:         '',
    bank_name:         '',
    check_number:      '',
})

const paymentErrors = ref<Record<string, string>>({})

const selectedPaymentMethod = computed(() =>
    (props.clubPaymentMethods ?? []).find(m => m.id === paymentForm.value.payment_method_id) ?? null
)

const canCheckout = computed(() => {
    if (consumptionInput.value === '' || isNaN(parseFloat(consumptionInput.value))) return false
    if (accessWaived.value) return true

    if (!paymentForm.value.payment_method_id || !paymentForm.value.paid_at) return false
    if (selectedPaymentMethod.value?.requires_bank_name && !paymentForm.value.bank_name.trim()) return false
    if (selectedPaymentMethod.value?.requires_reference && !paymentForm.value.reference.trim()) return false
    if (selectedPaymentMethod.value?.requires_check_number && !paymentForm.value.check_number.trim()) return false
    return true
})

const openCheckout = (visit: CafeteriaVisit) => {
    selectedVisit.value  = visit
    consumptionInput.value = ''
    paymentForm.value = {
        payment_method_id: null,
        paid_at:           nowAsLocalInput(),
        reference:         '',
        bank_name:         '',
        check_number:      '',
    }
    paymentErrors.value = {}
    showCheckoutModal.value = true
}

const submitCheckout = () => {
    if (!selectedVisit.value || !canCheckout.value) return
    checkoutSubmitting.value = true
    paymentErrors.value = {}
    router.post(route('cafeteria-visits.checkout', selectedVisit.value.id), {
        consumption_amount: consumptionNumber.value,
        payment_method_id:  accessWaived.value ? null : paymentForm.value.payment_method_id,
        paid_at:            accessWaived.value ? null : paymentForm.value.paid_at,
        reference:          accessWaived.value ? null : paymentForm.value.reference,
        bank_name:          accessWaived.value ? null : paymentForm.value.bank_name,
        check_number:       accessWaived.value ? null : paymentForm.value.check_number,
    }, {
        onSuccess: () => {
            customToastSwal({ title: (page.props.flash as any).success, icon: 'success' })
            showCheckoutModal.value = false
            fetchActiveVisits()
        },
        onError: (errors: any) => {
            paymentErrors.value = errors
            customToastSwal({
                title: errors.messageError ?? Object.values(errors).flat().join(' ') ?? 'Error al procesar la salida',
                icon: 'error',
            })
        },
        onFinish: () => { checkoutSubmitting.value = false },
    })
}
</script>

<template>
    <Head title="Ingresos Cafetería" />
    <AppLayout>
        <template #header>Ingresos Cafetería</template>
        <template #options>
            <BaseButton
                v-if="can.includes('cafeteria-visits.store')"
                text="Nuevo ingreso"
                action="create"
                :icon-only="false"
                icon="mdi-coffee-plus"
                @click="openRegisterModal"
            />
        </template>

        <!-- Tabs -->
        <v-tabs v-model="activeTab" color="primary" class="mb-4">
            <v-tab value="active">
                <v-icon start>mdi-account-clock</v-icon>
                En parque
                <v-badge
                    v-if="activeItems.length"
                    :content="activeItems.length"
                    :color="expiredCount > 0 ? 'error' : 'primary'"
                    inline class="ml-2"
                />
            </v-tab>
            <v-tab value="history">
                <v-icon start>mdi-history</v-icon>
                Historial
            </v-tab>
        </v-tabs>

        <v-tabs-window v-model="activeTab">

            <!-- activos -->
            <v-tabs-window-item value="active">
                <v-alert
                    v-if="expiredCount > 0"
                    type="error" variant="tonal" class="mb-3"
                    icon="mdi-alert-circle"
                >
                    <strong>{{ expiredCount }} visitante(s) con tiempo vencido.</strong>
                    Verificar situación y procesar salida.
                </v-alert>

                <v-alert
                    v-if="activeItems.length === 0"
                    type="info" variant="tonal" icon="mdi-coffee-outline"
                    class="mb-3"
                >
                    No hay visitantes activos en el parque por consumo de cafetería.
                </v-alert>

                <v-data-table
                    v-else
                    :headers="activeHeaders"
                    :items="activeItems"
                    class="elevation-1"
                    :items-per-page="-1"
                    hide-default-footer
                    no-data-text="Sin visitantes activos"
                >
                    <template #item.document="{ item }">
                        <div class="text-body-2 font-weight-medium">
                            {{ DOCUMENT_LABELS[item.document_type] ?? item.document_type }}
                        </div>
                        <div class="text-caption text-grey">{{ item.document_number }}</div>
                    </template>

                    <template #item.entered_at="{ item }">
                        {{ formatDateTime(item.entered_at) }}
                    </template>

                    <template #item.time_remaining="{ item }">
                        <v-chip :color="timeInfo(item.expires_at).color" size="small">
                            <v-icon start size="14">
                                {{ timeInfo(item.expires_at).expired ? 'mdi-alert' : 'mdi-clock-outline' }}
                            </v-icon>
                            {{ timeInfo(item.expires_at).text }}
                        </v-chip>
                    </template>

                    <template #item.actions="{ item }">
                        <BaseButton
                            v-if="can.includes('cafeteria-visits.checkout')"
                            text="Salida"
                            action="check"
                            :icon-only="false"
                            icon="mdi-exit-run"
                            @click="openCheckout(item)"
                        />
                    </template>
                </v-data-table>

                <div class="d-flex justify-end mt-2">
                    <v-btn variant="text" size="small" prepend-icon="mdi-refresh" @click="fetchActiveVisits">
                        Actualizar lista
                    </v-btn>
                </div>
            </v-tabs-window-item>

            <!--Historial -->
            <v-tabs-window-item value="history">
                <v-data-table-server
                    :headers="historyHeaders"
                    :items="historyItems"
                    :items-length="historyTotal"
                    :loading="historyLoading"
                    v-model:options="historyOptions"
                    class="elevation-1"
                    no-data-text="Sin registros en el historial"
                >
                    <template #top>
                        <v-text-field
                            v-model="historySearch"
                            label="Buscar por nombre o número de identificación"
                            density="compact"
                            hide-details
                            class="ma-2"
                            clearable
                        />
                    </template>

                    <template #item.document="{ item }">
                        <div class="text-body-2">{{ DOCUMENT_LABELS[item.document_type] ?? item.document_type }}</div>
                        <div class="text-caption text-grey">{{ item.document_number }}</div>
                    </template>

                    <template #item.entered_at="{ item }">{{ formatDateTime(item.entered_at) }}</template>
                    <template #item.checked_out_at="{ item }">{{ formatDateTime(item.checked_out_at) }}</template>

                    <template #item.consumption_amount="{ item }">
                        ${{ Number(item.consumption_amount ?? 0).toFixed(2) }}
                    </template>

                    <template #item.access_fee_waived="{ item }">
                        <v-chip :color="item.access_fee_waived ? 'green' : 'red'" size="small">
                            {{ item.access_fee_waived ? 'Exento' : 'Cobrado' }}
                        </v-chip>
                    </template>

                    <template #item.access_fee_charged="{ item }">
                        {{ item.access_fee_charged != null ? '$' + Number(item.access_fee_charged).toFixed(2) : '—' }}
                    </template>
                </v-data-table-server>
            </v-tabs-window-item>

        </v-tabs-window>

        <!-- Modal de registro -->
        <v-dialog v-model="showRegisterModal" max-width="520" persistent>
            <v-card title="Registrar ingreso a cafetería" prepend-icon="mdi-coffee">
                <v-card-text>
                    <v-alert type="info" variant="tonal" density="compact" class="mb-4" icon="mdi-card-account-details">
                        El visitante deberá dejar una <strong>identificación oficial</strong> como garantía
                        durante su estancia en el parque.
                    </v-alert>

                    <v-text-field
                        v-model="registerForm.visitor_name"
                        label="Nombre completo del visitante *"
                        placeholder="Nombre y apellidos"
                        maxlength="200"
                        class="mb-2"
                    />
                    <v-select
                        v-model="registerForm.document_type"
                        :items="documentTypeOptions"
                        item-title="title"
                        item-value="value"
                        label="Tipo de identificación *"
                        class="mb-2"
                    />
                    <v-text-field
                        v-model="registerForm.document_number"
                        label="Número / folio de la identificación *"
                        placeholder="Ej. CURP, número de folio..."
                        maxlength="100"
                        class="mb-2"
                    />
                    <v-textarea
                        v-model="registerForm.notes"
                        label="Notas (opcional)"
                        rows="2"
                        auto-grow
                        hide-details
                        class="mb-3"
                    />

                    <v-sheet rounded color="grey-lighten-4" class="pa-3">
                        <div class="d-flex justify-space-between text-body-2">
                            <span>Consumo mínimo para exención:</span>
                            <strong class="text-green-darken-2">
                                ${{ Number(cafeteriaConfig?.min_consumption ?? 300).toFixed(2) }}
                            </strong>
                        </div>
                        <div class="d-flex justify-space-between text-body-2 mt-1">
                            <span>Duración máxima de estancia:</span>
                            <strong>{{ cafeteriaConfig?.duration_hours ?? 2 }} hora(s)</strong>
                        </div>
                        <div class="d-flex justify-space-between text-body-2 mt-1">
                            <span>Vence aproximadamente a las:</span>
                            <strong>{{ estimatedExpiry }}</strong>
                        </div>
                    </v-sheet>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        text="Cancelar" action="cancel" :icon-only="false"
                        :disabled="registerSubmitting"
                        @click="showRegisterModal = false"
                    />
                    <BaseButton
                        text="Registrar ingreso" action="create" :icon-only="false"
                        icon="mdi-check"
                        :disabled="!canRegister || registerSubmitting"
                        :loading="registerSubmitting"
                        @click="submitRegister"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Modal de salida-->
        <v-dialog v-model="showCheckoutModal" max-width="600" persistent>
            <v-card title="Registrar salida" prepend-icon="mdi-exit-run">
                <v-card-text v-if="selectedVisit">

                    <!-- Info del visitante -->
                    <v-list-item
                        :title="selectedVisit.visitor_name"
                        :subtitle="`${DOCUMENT_LABELS[selectedVisit.document_type] ?? selectedVisit.document_type}: ${selectedVisit.document_number}`"
                        prepend-icon="mdi-account"
                        class="px-0 mb-2"
                    />

                    <v-chip :color="timeInfo(selectedVisit.expires_at).color" class="mb-4" size="small">
                        <v-icon start size="14">mdi-clock-outline</v-icon>
                        {{ timeInfo(selectedVisit.expires_at).text }}
                        &nbsp;·&nbsp; Ingresó: {{ formatDateTime(selectedVisit.entered_at) }}
                    </v-chip>

                    <!-- Consumo -->
                    <v-text-field
                        v-model="consumptionInput"
                        label="Consumo en cafetería *"
                        type="number"
                        min="0"
                        step="0.01"
                        prefix="$"
                        placeholder="0.00"
                        class="mb-3"
                        autofocus
                    />

                    <!-- Resultado dinámico -->
                    <v-sheet
                        rounded
                        :color="accessWaived ? 'green-lighten-5' : 'red-lighten-5'"
                        class="pa-3"
                    >
                        <div class="d-flex justify-space-between text-body-2">
                            <span>Consumo mínimo requerido:</span>
                            <strong>${{ minConsumption.toFixed(2) }}</strong>
                        </div>
                        <div class="d-flex justify-space-between text-body-2 mt-1">
                            <span>Consumo registrado:</span>
                            <strong>${{ consumptionNumber.toFixed(2) }}</strong>
                        </div>
                        <v-divider class="my-2" />
                        <div class="d-flex align-center justify-space-between text-body-1 font-weight-bold">
                            <span>Resultado:</span>
                            <v-chip :color="accessWaived ? 'green' : 'error'" size="small">
                                <v-icon start>
                                    {{ accessWaived ? 'mdi-check-circle' : 'mdi-cash-alert' }}
                                </v-icon>
                                {{ accessWaived ? 'ACCESO EXENTO ✓' : `COBRAR $${minConsumption.toFixed(2)}` }}
                            </v-chip>
                        </div>
                    </v-sheet>

                    <!-- Datos del pago -->
                    <template v-if="!accessWaived">
                        <div class="d-flex align-center ga-2 mt-4 mb-2">
                            <v-icon color="primary" size="18">mdi-cash-register</v-icon>
                            <span class="text-subtitle-2 font-weight-bold text-uppercase text-medium-emphasis">Datos del pago</span>
                        </div>

                        <v-sheet class="pa-3 rounded-lg border" color="grey-lighten-5">
                            <v-row dense>
                                <v-col cols="12" md="6">
                                    <v-select
                                        v-model="paymentForm.payment_method_id"
                                        :items="(clubPaymentMethods ?? []).map(m => ({ title: m.name, value: m.id }))"
                                        label="Método de pago *"
                                        prepend-inner-icon="mdi-credit-card-outline"
                                        hide-details="auto"
                                        :error-messages="paymentErrors.payment_method_id"
                                    />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model="paymentForm.paid_at"
                                        label="Fecha y hora de pago *"
                                        type="datetime-local"
                                        prepend-inner-icon="mdi-clock-check-outline"
                                        hide-details="auto"
                                        :error-messages="paymentErrors.paid_at"
                                    />
                                </v-col>
                                <v-col v-if="selectedPaymentMethod?.requires_bank_name" cols="12" md="6">
                                    <v-text-field
                                        v-model="paymentForm.bank_name"
                                        label="Banco *"
                                        prepend-inner-icon="mdi-bank-outline"
                                        hide-details="auto"
                                        :error-messages="paymentErrors.bank_name"
                                    />
                                </v-col>
                                <v-col v-if="selectedPaymentMethod?.requires_reference" cols="12" md="6">
                                    <v-text-field
                                        v-model="paymentForm.reference"
                                        label="Referencia *"
                                        prepend-inner-icon="mdi-pound"
                                        hide-details="auto"
                                        :error-messages="paymentErrors.reference"
                                    />
                                </v-col>
                                <v-col v-if="selectedPaymentMethod?.requires_check_number" cols="12" md="6">
                                    <v-text-field
                                        v-model="paymentForm.check_number"
                                        label="Número de cheque *"
                                        prepend-inner-icon="mdi-checkbook"
                                        hide-details="auto"
                                        :error-messages="paymentErrors.check_number"
                                    />
                                </v-col>
                            </v-row>
                        </v-sheet>
                    </template>

                    <v-alert
                        v-if="!accessWaived && canCheckout"
                        type="warning" variant="tonal" density="compact" class="mt-3" icon="mdi-cash"
                    >
                        Cobrar <strong>${{ minConsumption.toFixed(2) }}</strong> de acceso al parque antes de devolver la identificación.
                    </v-alert>

                    <v-alert type="info" variant="tonal" density="compact" class="mt-2" icon="mdi-card-account-details">
                        Devolver la identificación al visitante al confirmar la salida.
                    </v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        text="Cancelar" action="cancel" :icon-only="false"
                        :disabled="checkoutSubmitting"
                        @click="showCheckoutModal = false"
                    />
                    <BaseButton
                        :text="accessWaived ? 'Confirmar salida' : 'Confirmar y cobrar'"
                        :action="accessWaived ? 'save' : 'check'"
                        :icon-only="false"
                        :icon="accessWaived ? 'mdi-check' : 'mdi-cash-check'"
                        :disabled="!canCheckout || checkoutSubmitting"
                        :loading="checkoutSubmitting"
                        @click="submitCheckout"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

    </AppLayout>
</template>
