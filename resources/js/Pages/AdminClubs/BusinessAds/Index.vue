<script setup lang="ts">

import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal, confirmRejectWithReason } from "@/utils/swal";
import { Head, router, usePage } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";
import { debounce } from "lodash";
import axios from "axios";

const page = usePage();
const can = page.props.auth.permissions;

interface Props {
    ads?: any;
    physicalAds?: any;
}
interface Ad {
    rejection_reason?: string;
    status?: { name: string };
    member?: { full_name?: string };
    [key: string]: any;
}
const props = defineProps<Props>();

// ── Tabs ─────────────────────────────────────────────────────────────────────
const activeTab = ref("digital");

// ── Tabla: Anuncios Digitales ─────────────────────────────────────────────────
const showDetailModal = ref(false);
const selectedAd = ref<Ad | null>(null);
const headers = ref([
    { title: "Negocio", key: "name" },
    { title: "Categoría", key: "category" },
    { title: "Socio", key: "member" },
    { title: "Estatus", key: "status" },
    { title: "Fecha de solicitud", key: "created_at" },
    { title: "Acciones", key: "actions" }
]);

const items = ref<Ad[]>([]);
const total = ref(0);
const loading = ref(false);
const search = ref("");

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }]
});

const fetchItems = () => {
    loading.value = true;
    router.get(
        route("business-ads.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value.trim() || null
        },
        { preserveState: true, replace: true, only: ["ads"] }
    );
};

watch(
    () => props.ads,
    (val) => {
        items.value = val?.data ?? [];
        total.value = val?.total ?? 0;
        loading.value = false;

        const hasRejected = items.value.some(i => i.status?.name === "Rechazado");
        const alreadyExists = headers.value.some(h => h.key === "rejection_reason");
        if (hasRejected && !alreadyExists) {
            headers.value.splice(4, 0, { title: "Motivo rechazo", key: "rejection_reason" });
        }
        if (!hasRejected && alreadyExists) {
            headers.value = headers.value.filter(h => h.key !== "rejection_reason");
        }
    },
    { immediate: true }
);
watch(search, debounce(() => { options.value.page = 1; fetchItems(); }, 400));
watch([options], debounce(fetchItems, 400), { deep: true });

// ── Tabla: Anuncios Físicos ───────────────────────────────────────────────────
const physicalHeaders = [
    { title: "Socio", key: "member" },
    { title: "Tamaño", key: "size" },
    { title: "Cant.", key: "quantity" },
    { title: "Monto", key: "amount" },
    { title: "Inicio", key: "starts_at" },
    { title: "Fin", key: "ends_at" },
    { title: "Estatus", key: "status" },
    { title: "Firma", key: "signed_format" },
];

const physicalItems = ref<any[]>([]);
const physicalTotal = ref(0);
const physicalLoading = ref(false);
const physicalSearch = ref("");
const physicalOptions = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }]
});

const fetchPhysicalItems = () => {
    physicalLoading.value = true;
    router.get(
        route("business-ads.index"),
        {
            physical_page: physicalOptions.value.page,
            physical_per_page: physicalOptions.value.itemsPerPage,
            physical_search: physicalSearch.value.trim() || null
        },
        { preserveState: true, replace: true, only: ["physicalAds"] }
    );
};

watch(
    () => props.physicalAds,
    (val) => {
        physicalItems.value = val?.data ?? [];
        physicalTotal.value = val?.total ?? 0;
        physicalLoading.value = false;
    },
    { immediate: true }
);
watch(physicalSearch, debounce(() => { physicalOptions.value.page = 1; fetchPhysicalItems(); }, 400));
watch([physicalOptions], debounce(fetchPhysicalItems, 400), { deep: true });

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatDate = (val: string | null) => {
    if (!val) return "-";
    return new Date(val).toLocaleDateString("es-MX", {
        day: "2-digit", month: "2-digit", year: "numeric"
    });
};

const getStatusColor = (status: string) => {
    switch (status) {
        case "Pendiente":        return "grey";
        case "Aprobado":         return "blue";
        case "Pagado":           return "orange";
        case "Publicado":        return "green";
        case "Rechazado":        return "red";
        case "Expirado":         return "black";
        case "pending_payment":  return "orange";
        case "active":           return "green";
        case "expired":          return "red";
        case "cancelled":        return "grey";
        default:                 return "grey";
    }
};

const physicalStatusLabel: Record<string, string> = {
    pending_payment: "Pendiente pago",
    active:          "Activo",
    expired:         "Expirado",
    cancelled:       "Cancelado",
};

const sizeLabel: Record<string, string> = {
    carta:        "Carta",
    oficio:       "Oficio",
    doble_carta:  "Doble Carta",
    doble_oficio: "Doble Oficio",
};

const isExpired = (item: any) => {
    if (!item.expires_at) return false;
    return new Date(item.expires_at) < new Date();
};

// ── Acciones digitales ────────────────────────────────────────────────────────
const viewDetail = (item: any) => { selectedAd.value = item; showDetailModal.value = true; };

const approve = (item: any) => {
    customConfirmSwal({
        title: "¿Aprobar anuncio?",
        text: "El anuncio será aprobado y el usuario podrá proceder al pago",
        confirmText: "Sí, aprobar",
        actionType: "approve"
    }).then(r => {
        if (!r.isConfirmed) return;
        router.post(route("business-ads.approve", item.id), {}, {
            onSuccess: () => {
                customToastSwal({ title: page.props.flash.success, icon: "success" });
                fetchItems();
                showDetailModal.value = false;
            },
            onError: (errors) => customToastSwal({ title: errors.messageError, icon: "error" })
        });
    });
};

const reject = (item: any) => {
    confirmRejectWithReason().then((result) => {
        if (!result.isConfirmed) return;
        router.post(route("business-ads.reject", item.id), { reason: result.value }, {
            onSuccess: () => {
                customToastSwal({ title: page.props.flash.success, icon: "success" });
                fetchItems();
            }
        });
    });
};

// ── Modal Anuncio Físico ──────────────────────────────────────────────────────
const showPhysicalAdModal = ref(false);

const SIZES = [
    { value: "carta",        label: "Carta",        price: 15 },
    { value: "oficio",       label: "Oficio",       price: 20 },
    { value: "doble_carta",  label: "Doble Carta",  price: 30 },
    { value: "doble_oficio", label: "Doble Oficio", price: 40 },
] as const;

const physicalForm = ref({
    member_id: null as number | null,
    membership_account_id: null as number | null,
    size: "carta" as string,
    quantity: 1,
    notes: "",
});

const memberSearch = ref("");
const memberOptions = ref<{ id: number; full_name: string; membership_account_id: number }[]>([]);
const memberLoading = ref(false);
const physicalSubmitting = ref(false);

const selectedSizePrice = computed(() => SIZES.find(s => s.value === physicalForm.value.size)?.price ?? 0);
const physicalFormTotal = computed(() => selectedSizePrice.value * physicalForm.value.quantity);

const periodLabel = computed(() => {
    const today = new Date();
    const starts = new Date(today);
    if (today.getDate() <= 21) {
        starts.setDate(21);
    } else {
        starts.setMonth(starts.getMonth() + 1);
        starts.setDate(21);
    }
    const ends = new Date(starts);
    ends.setMonth(ends.getMonth() + 1);
    const fmt = (d: Date) => d.toLocaleDateString("es-MX", { day: "2-digit", month: "2-digit", year: "numeric" });
    return `${fmt(starts)} — ${fmt(ends)}`;
});

const doMemberSearch = debounce(async (q: string) => {
    if (!q || q.length < 2) { memberOptions.value = []; return; }
    memberLoading.value = true;
    try {
        const { data } = await axios.get(route("physical-ads.members-search"), { params: { q } });
        memberOptions.value = data;
    } finally {
        memberLoading.value = false;
    }
}, 350);

watch(memberSearch, doMemberSearch);

const onMemberSelect = (id: number | null) => {
    const found = memberOptions.value.find(m => m.id === id);
    physicalForm.value.member_id = found?.id ?? null;
    physicalForm.value.membership_account_id = found?.membership_account_id ?? null;
};

const openPhysicalModal = () => {
    physicalForm.value = { member_id: null, membership_account_id: null, size: "carta", quantity: 1, notes: "" };
    memberSearch.value = "";
    memberOptions.value = [];
    showPhysicalAdModal.value = true;
};

const submitPhysicalAd = () => {
    if (!physicalForm.value.member_id) return;
    physicalSubmitting.value = true;
    router.post(route("physical-ads.store"), {
        member_id: physicalForm.value.member_id,
        size:      physicalForm.value.size,
        quantity:  physicalForm.value.quantity,
        notes:     physicalForm.value.notes,
    }, {
        onSuccess: () => {
            customToastSwal({ title: "Anuncio físico registrado correctamente", icon: "success" });
            showPhysicalAdModal.value = false;
            fetchPhysicalItems();
        },
        onError: (errors) => customToastSwal({ title: errors.messageError ?? "Error al registrar el anuncio", icon: "error" }),
        onFinish: () => { physicalSubmitting.value = false; }
    });
};
</script>

<template>
    <Head title="Anuncios" />
    <AppLayout>
        <template #header>
            Anuncios de negocios
        </template>

        <!-- ── Tabs ────────────────────────────────────────────────────────── -->
        <v-tabs v-model="activeTab" color="primary" class="mb-4">
            <v-tab value="digital">
                <v-icon start>mdi-monitor</v-icon>
                Anuncios digitales
            </v-tab>
            <v-tab value="physical" v-if="can.includes('physical-ads.index')">
                <v-icon start>mdi-printer-pos</v-icon>
                Anuncios físicos
            </v-tab>
        </v-tabs>

        <v-tabs-window v-model="activeTab">

            <!-- ── Tab: Anuncios Digitales ─────────────────────────────────── -->
            <v-tabs-window-item value="digital">
                <v-data-table-server
                    :headers="headers"
                    :items="items"
                    :items-length="total"
                    :loading="loading"
                    loading-text="Cargando anuncios..."
                    v-model:options="options"
                    class="elevation-1"
                    no-data-text="No hay anuncios registrados"
                >
                    <template #top>
                        <v-text-field v-model="search" label="Buscar anuncio" density="compact" hide-details class="ma-2" />
                    </template>

                    <template #item.category="{ item }">{{ item.category?.name ?? '-' }}</template>
                    <template #item.member="{ item }">{{ item.member?.full_name ?? '-' }}</template>
                    <template #item.status="{ item }">
                        <v-chip :color="isExpired(item) ? 'red' : getStatusColor(item.status?.name)" dark>
                            {{ isExpired(item) ? 'Expirado' : item.status?.name }}
                        </v-chip>
                    </template>
                    <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
                    <template #item.rejection_reason="{ item }: { item: Ad }">
                        <span v-if="item.rejection_reason" class="text-red-darken-2">{{ item.rejection_reason }}</span>
                        <span v-else>-</span>
                    </template>
                    <template #item.actions="{ item }">
                        <BaseButton action="view" color="green" @click="viewDetail(item)" />
                        <BaseButton
                            v-if="can.includes('business-ads.approve') && item.status?.name === 'Pendiente'"
                            icon="mdi-check" action="Aprobar" @click="approve(item)"
                        />
                        <BaseButton
                            v-if="can.includes('business-ads.reject') && item.status?.name === 'Pendiente'"
                            icon="mdi-close" action="Rechazar" color="red" @click="reject(item)"
                        />
                    </template>
                </v-data-table-server>
            </v-tabs-window-item>

            <!-- ── Tab: Anuncios Físicos ───────────────────────────────────── -->
            <v-tabs-window-item value="physical" v-if="can.includes('physical-ads.index')">
                <v-data-table-server
                    :headers="physicalHeaders"
                    :items="physicalItems"
                    :items-length="physicalTotal"
                    :loading="physicalLoading"
                    loading-text="Cargando anuncios físicos..."
                    v-model:options="physicalOptions"
                    class="elevation-1"
                    no-data-text="No hay anuncios físicos registrados"
                >
                    <template #top>
                        <div class="d-flex align-center gap-2 pa-2">
                            <v-text-field
                                v-model="physicalSearch"
                                label="Buscar por socio"
                                density="compact"
                                hide-details
                                class="flex-grow-1"
                            />
                            <BaseButton
                                v-if="can.includes('physical-ads.store')"
                                text="Nuevo anuncio físico"
                                action="create"
                                :icon-only="false"
                                icon="mdi-printer-pos"
                                @click="openPhysicalModal"
                            />
                        </div>
                    </template>

                    <template #item.member="{ item }">
                        {{ item.member?.full_name ?? '-' }}
                    </template>
                    <template #item.size="{ item }">
                        {{ sizeLabel[item.size] ?? item.size }}
                    </template>
                    <template #item.amount="{ item }">
                        ${{ Number(item.amount).toFixed(2) }}
                    </template>
                    <template #item.starts_at="{ item }">
                        {{ formatDate(item.starts_at) }}
                    </template>
                    <template #item.ends_at="{ item }">
                        {{ formatDate(item.ends_at) }}
                    </template>
                    <template #item.status="{ item }">
                        <v-chip :color="getStatusColor(item.status)" size="small">
                            {{ physicalStatusLabel[item.status] ?? item.status }}
                        </v-chip>
                    </template>
                    <template #item.signed_format="{ item }">
                        <v-icon v-if="item.signed_format" color="green">mdi-check-circle</v-icon>
                        <v-icon v-else color="orange">mdi-clock-outline</v-icon>
                    </template>
                </v-data-table-server>
            </v-tabs-window-item>

        </v-tabs-window>

        <!-- ── Modal Detalle Digital ──────────────────────────────────────── -->
        <v-dialog v-model="showDetailModal" max-width="600" persistent>
            <v-card title="Detalle del anuncio">
                <v-card-text v-if="selectedAd" class="overflow-y-auto" style="max-height:70vh;">
                    <v-alert v-if="isExpired(selectedAd)" type="error" variant="tonal" class="mb-3">
                        Este anuncio está vencido
                    </v-alert>
                    <v-card elevation="3">
                        <v-img v-if="selectedAd.image" :src="`${selectedAd.image}`" height="180" cover />
                        <v-card-text>
                            <div class="text-h6 font-weight-bold">{{ selectedAd.name }}</div>
                            <div class="text-caption text-grey mb-2">{{ selectedAd.category?.name ?? 'Sin categoría' }}</div>
                            <div class="text-body-2 mb-3">{{ selectedAd.description }}</div>
                            <div class="text-body-2">
                                <div v-if="selectedAd.phone">📞 {{ selectedAd.phone }}</div>
                                <div v-if="selectedAd.email">✉️ {{ selectedAd.email }}</div>
                                <div v-if="selectedAd.website">🌐 {{ selectedAd.website }}</div>
                            </div>
                            <v-alert v-if="selectedAd.rejection_reason" type="error" variant="tonal" class="mt-3">
                                <strong>Motivo de rechazo:</strong><br>{{ selectedAd.rejection_reason }}
                            </v-alert>
                        </v-card-text>
                        <v-card-actions class="justify-space-between">
                            <v-chip :color="isExpired(selectedAd) ? 'red' : getStatusColor(selectedAd.status?.name)" size="small">
                                {{ isExpired(selectedAd) ? 'Expirado' : selectedAd.status?.name }}
                            </v-chip>
                            <div class="text-body-2 text-grey">Expira: {{ formatDate(selectedAd.expires_at) }}</div>
                        </v-card-actions>
                    </v-card>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        v-if="selectedAd?.status?.name === 'Pendiente'"
                        text="Aprobar" action="check" :icon-only="false" icon="mdi-check"
                        @click="approve(selectedAd)"
                    />
                    <BaseButton
                        v-if="selectedAd?.status?.name === 'Pendiente'"
                        text="Rechazar" action="delete" :icon-only="false" icon="mdi-close"
                        @click="reject(selectedAd)"
                    />
                    <BaseButton text="Cerrar" action="cancel" :icon-only="false" @click="showDetailModal = false" />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- ── Modal Anuncio Físico ───────────────────────────────────────── -->
        <v-dialog v-model="showPhysicalAdModal" max-width="520" persistent>
            <v-card title="Nuevo anuncio físico">
                <v-card-text>
                    <v-alert type="info" variant="tonal" class="mb-4" density="compact">
                        El socio deberá presentarse a firmar el formato físico para activar el anuncio.
                    </v-alert>

                    <v-autocomplete
                        v-model="physicalForm.member_id"
                        v-model:search="memberSearch"
                        :items="memberOptions"
                        item-title="full_name"
                        item-value="id"
                        label="Socio *"
                        placeholder="Buscar por nombre..."
                        :loading="memberLoading"
                        no-data-text="Sin resultados"
                        clearable
                        class="mb-2"
                        @update:model-value="onMemberSelect($event)"
                    />

                    <div class="text-subtitle-2 mb-1">Tamaño *</div>
                    <v-radio-group v-model="physicalForm.size" inline hide-details class="mb-3">
                        <v-radio
                            v-for="s in SIZES" :key="s.value"
                            :value="s.value" :label="`${s.label} — $${s.price}`"
                            class="mr-4"
                        />
                    </v-radio-group>

                    <v-text-field
                        v-model.number="physicalForm.quantity"
                        label="Cantidad *"
                        type="number"
                        :min="1" :max="99"
                        density="compact"
                        class="mb-3"
                    />

                    <v-sheet rounded color="grey-lighten-4" class="pa-3 mb-3">
                        <div class="d-flex justify-space-between text-body-2">
                            <span>Precio unitario:</span>
                            <strong>${{ selectedSizePrice }}.00</strong>
                        </div>
                        <div class="d-flex justify-space-between text-body-2">
                            <span>Cantidad:</span>
                            <strong>{{ physicalForm.quantity }}</strong>
                        </div>
                        <v-divider class="my-2" />
                        <div class="d-flex justify-space-between text-body-1 font-weight-bold">
                            <span>Total a cobrar:</span>
                            <span class="text-green-darken-2">${{ physicalFormTotal }}.00</span>
                        </div>
                        <div class="d-flex justify-space-between text-caption mt-1 text-grey">
                            <span>Periodo:</span>
                            <span>{{ periodLabel }}</span>
                        </div>
                    </v-sheet>

                    <v-textarea
                        v-model="physicalForm.notes"
                        label="Notas (opcional)"
                        rows="2"
                        auto-grow
                        hide-details
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        text="Cancelar" action="cancel" :icon-only="false"
                        :disabled="physicalSubmitting"
                        @click="showPhysicalAdModal = false"
                    />
                    <BaseButton
                        text="Registrar y cobrar" action="create" :icon-only="false" icon="mdi-check"
                        :disabled="!physicalForm.member_id || physicalSubmitting"
                        :loading="physicalSubmitting"
                        @click="submitPhysicalAd"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
