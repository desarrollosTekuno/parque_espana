<script setup lang="ts">

import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal, confirmRejectWithReason, customSwal } from "@/utils/swal";
import { Head, router, usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import { debounce } from "lodash";

const page = usePage();
const can = page.props.auth.permissions;

interface Props {
    ads?: any;
}

const props = defineProps<Props>();
const showDetailModal = ref(false);
const selectedAd = ref<any>(null);
const headers = [
    { title: "Negocio", key: "name" },
    { title: "Categoría", key: "category" },
    { title: "Socio", key: "member.full_name" },
    { title: "Estatus", key: "status" },
    { title: "Fecha", key: "created_at" },
    { title: "Acciones", key: "actions" }
];

const items = ref([]);
const total = ref(0);
const loading = ref(false);

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{
        key: "id",
        order: "desc"
    }]
});

const search = ref("");

const fetchItems = () => {
    loading.value = true;

    router.get(
        route("business-ads.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value
        },
        {
            preserveState: true,
            replace: true,
            only: ["ads"]
        }
    );
};

watch(
    () => props.ads,
    (val) => {
        items.value = val?.data ?? [];
        total.value = val?.total ?? 0;
        loading.value = false;
    },
    { immediate: true }
);

watch(
    [options, search],
    debounce(fetchItems, 400),
    { deep: true }
);

const viewDetail = (item: any) => {
    selectedAd.value = item;
    showDetailModal.value = true;
};

const formatDate = (val: string | null) => {
    if (!val) return "-";
    const [date, time] = val.split(" ");
    const [y, m, d] = date.split("-");
    return `${d}/${m}/${y}`;
};

const getStatusColor = (status: string) => {
    switch (status) {
        case "Pendiente": return "grey";
        case "Aprobado": return "blue";
        case "Pagado": return "orange";
        case "Publicado": return "green";
        case "Rechazado": return "red";
        case "Expirado": return "black";
        default: return "grey";
    }
};

const approve = (item: any) => {
    customConfirmSwal({ 
        title: "¿Aprobar anuncio?",
        text: "El anuncio será aprobado y el usuario podrá proceder al pago",
        confirmText: "Sí, aprobar",
        actionType: "approve"
     })
    .then(r => {
        if (r.isConfirmed) {
            router.post(route("business-ads.approve", item.id), {}, {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success,
                        icon: "success"
                    });
                    fetchItems();
                    showDetailModal.value = false;
                },
                onError: (errors) => {
                    customToastSwal({
                        title: errors.messageError,
                        icon: "error"
                    });
                }
            });
        }
    });
};

const reject = (item: any) => {
    confirmRejectWithReason()
    .then((result) => {
        if (!result.isConfirmed) return;
        const reason = result.value; 
        router.post(
            route("business-ads.reject", item.id),
            { reason },
            {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success,
                        icon: "success"
                    });
                    fetchItems();
                }
            }
        );
    });
};

const confirmPayment = (item: any) => {
    customConfirmSwal({ 
        title: "¿Confirmar pago?",
        text: "El anuncio será marcado como pagado y podrá ser publicado",
        confirmText: "Sí, confirmar",
        actionType: "payment"
     })
    .then(r => {
        if (r.isConfirmed) {
            router.post(route("business-ads.confirm-payment", item.id), {}, {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success,
                        icon: "success"
                    });
                    fetchItems();
                    showDetailModal.value = false;
                }
            });
        }
    });
};

const publish = (item: any) => {
    customConfirmSwal({ 
        title: "¿Publicar anuncio?",
        text: "El anuncio será publicado y visible para todos los usuarios",
        confirmText: "Sí, publicar",
        actionType: "publish"
     })
    .then(r => {
        if (r.isConfirmed) {
            router.post(route("business-ads.publish", item.id), {}, {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success,
                        icon: "success"
                    });
                    fetchItems();
                    showDetailModal.value = false;
                }
            });
        }
    });
};
const isExpired = (item:any) => {
    if (!item.expires_at) return false;
    return new Date(item.expires_at) < new Date();
};
</script>

<template>
    <Head title="Anuncios" />
    <AppLayout>
        <template #header>
            Anuncios de negocios
        </template>
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
                <v-text-field v-model="search" label="Buscar anuncio" />
            </template>
            <template #item.user="{ item }">
                {{ item.user?.name ?? '-' }}
            </template>
            <template #item.status="{ item }">
                <v-chip
                    :color="isExpired(item) ? 'red' : getStatusColor(item.status?.name)"
                    dark
                >
                    {{ isExpired(item) ? 'expired' : item.status?.name }}
                </v-chip>
            </template>
            <template #item.created_at="{ item }">
                {{ formatDate(item.created_at) }}
            </template>
            <template #item.actions="{ item }">

                <BaseButton
                    action="view"
                    @click="viewDetail(item)"
                />
                <!-- pending -->
                <BaseButton
                    v-if="item.status?.name === 'Pendiente'" icon="mdi-check"
                    action="Aprobar"
                    @click="approve(item)"
                />

                <BaseButton
                    v-if="item.status?.name === 'Pendiente'" icon="mdi-close"
                    action="delete"
                    @click="reject(item)"
                />

                <!-- approved -->
                <BaseButton
                    v-if="item.status?.name === 'Aprobado'" icon="mdi-cash"
                    action="Confirmar pago"
                    @click="confirmPayment(item)"
                />

                <!-- paid -->
                <BaseButton
                    v-if="item.status?.name === 'Pagado'" icon="mdi-publish"
                    action="Publicar"
                    @click="publish(item)"
                />
            </template>
        </v-data-table-server>
        <v-dialog v-model="showDetailModal" max-width="600" persistent>
            <v-card title="Detalle del anuncio">
                <v-card-text v-if="selectedAd" class="overflow-y-auto h-full" style="max-height:70vh; overflow-y:auto;">
                    <v-alert v-if="isExpired(selectedAd)" type="error" variant="tonal">
                        Este anuncio está vencido
                    </v-alert>
                    <v-row>
                        <v-col cols="12">
                            <strong>Vista previa:</strong>

                            <v-card class="mt-2" elevation="3">

                                <!-- Imagen -->
                                <v-img
                                    v-if="selectedAd.image"
                                    :src="`/storage/${selectedAd.image}`"
                                    height="180"
                                    cover
                                />

                                <v-card-text>

                                    <!-- Título -->
                                    <div class="text-h6 font-weight-bold">
                                        {{ selectedAd.name }}
                                    </div>

                                    <!-- Categoría -->
                                    <div class="text-caption text-grey mb-2">
                                        {{ selectedAd.category ?? 'Sin categoría' }}
                                    </div>

                                    <!-- Descripción -->
                                    <div class="text-body-2 mb-3">
                                        {{ selectedAd.description }}
                                    </div>

                                    <!-- Contacto -->
                                    <div class="text-body-2">
                                        <div v-if="selectedAd.phone">
                                            📞 {{ selectedAd.phone }}
                                        </div>

                                        <div v-if="selectedAd.email">
                                            ✉️ {{ selectedAd.email }}
                                        </div>

                                        <div v-if="selectedAd.website">
                                            🌐 {{ selectedAd.website }}
                                        </div>
                                    </div>

                                </v-card-text>

                                <!-- Footer -->
                                <v-card-actions class="justify-space-between">

                                    <!-- Estatus -->
                                    <v-chip
                                        :color="isExpired(selectedAd) ? 'red' : getStatusColor(selectedAd.status?.name)"
                                        size="small"
                                    >
                                        {{ isExpired(selectedAd) ? 'expired' : selectedAd.status?.name }}
                                    </v-chip>

                                    <!-- Vigencia -->
                                    <div class="text-caption">
                                        Expira: {{ formatDate(selectedAd.expires_at) }}
                                    </div>

                                </v-card-actions>

                            </v-card>
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <!-- pending -->
                    <BaseButton
                        v-if="selectedAd?.status?.name === 'Pendiente'"
                        text="Aprobar"
                        action="check"
                        :icon-only="false"
                        icon="mdi-check"
                        @click="approve(selectedAd)"
                    />
                    <BaseButton
                        v-if="selectedAd?.status?.name === 'Pendiente'"
                        text="Rechazar"
                        action="delete"
                        :icon-only="false"
                        icon="mdi-close"
                        @click="reject(selectedAd)"
                    />
                    <!-- approved -->
                    <BaseButton
                        v-if="selectedAd?.status?.name === 'Aprobado'"
                        text="Confirmar pago"
                        action="payment"
                        :icon-only="false"
                        icon="mdi-cash"
                        @click="confirmPayment(selectedAd)"
                    />
                    <!-- paid -->
                    <BaseButton
                        v-if="selectedAd?.status?.name === 'Pagado'"
                        text="Publicar"
                        action="publish"
                        :icon-only="false"
                        icon="mdi-cash"
                        @click="publish(selectedAd)"
                    />
                    <BaseButton
                        text="Cerrar"
                        action="cancel"
                        :icon-only="false"
                        @click="showDetailModal = false"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>