<script setup lang="ts">
import { ref, computed, watch } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { customToastSwal } from "@/utils/swal";
import { imprimirTicket, descargarTicket, obtenerDatosTicket } from "@/utils/ticket";
import BaseButton from "@/Components/BaseButton.vue";
import TicketPreview from "@/Components/TicketPreview.vue";

interface Props {
    tickets?: any;
    filters?: Record<string, string | number | null>;
}

const props = withDefaults(defineProps<Props>(), {
    tickets: null,
    filters: () => ({}),
});

const page = usePage<any>();
const prefix = "tickets";

const items = ref(props.tickets?.data ?? []);
const total = ref(props.tickets?.total ?? 0);
const search = ref(String(props.filters?.search ?? ""));
const loading = ref(false);

const mostrarModalPreview = ref(false);
const datosPreview = ref(null);

const options = ref({
    page: 1,
    itemsPerPage: 10,
});

const headers = computed(() => [
    { title: "Folio", key: "folio" },
    { title: "Fecha", key: "fecha" },
    { title: "Cuenta", key: "cuenta_numero" },
    { title: "Titular", key: "titular" },
    { title: "Parque", key: "club_nombre" },
    { title: "Monto", key: "monto" },
    { title: "Forma de pago", key: "forma_pago" },
    { title: "Cajero", key: "cajero" },
    { title: "Acciones", key: "actions", sortable: false },
]);

const currencyFormatter = new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
    maximumFractionDigits: 2,
});

function formatearFechaCorta(fechaTexto) {
    if (!fechaTexto) {
        return "";
    }

    const fecha = new Date(fechaTexto);

    return fecha.toLocaleDateString("es-MX");
}

const fetchItems = () => {
    loading.value = true;

    const params = {
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
    };

    router.get(route("tickets.index"), params, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
        onSuccess: (pageResponse) => {
            items.value = pageResponse.props[prefix]?.data ?? [];
            total.value = pageResponse.props[prefix]?.total ?? 0;
            loading.value = false;
        },
        onError: () => {
            loading.value = false;
        },
    });
};

watch([options, search], debounce(fetchItems, 400), { deep: true });

watch(
    () => page.props.auth.currentClub,
    () => {
        fetchItems();
    },
);

async function handleReimprimir(item) {
    try {
        await imprimirTicket(item.id);
    } catch (error) {
        customToastSwal({
            icon: "error",
            title: "No se pudo reimprimir el ticket.",
        });
    }
}

async function handleVistaPrevia(item) {
    try {
        datosPreview.value = await obtenerDatosTicket(item.id);
        mostrarModalPreview.value = true;
    } catch (error) {
        customToastSwal({
            icon: "error",
            title: "No se pudo generar la vista previa.",
        });
    }
}

async function handleDescargar(item) {
    try {
        await descargarTicket(item.id);
    } catch (error) {
        customToastSwal({
            icon: "error",
            title: "No se pudo descargar el ticket.",
        });
    }
}
</script>

<template>
    <Head title="Tickets" />

    <AppLayout>
        <template #header>Tickets</template>

        <v-card>
            <v-data-table-server
                fixed-header
                hover
                height="600px"
                :headers="headers"
                :items="items"
                :items-length="total"
                :loading="loading"
                v-model:options="options"
                class="elevation-1"
                :items-per-page-options="[10, 25, 50, 100]"
                items-per-page-text="Mostrar"
                no-data-text="No hay tickets para mostrar"
            >
                <template #top>
                    <v-text-field
                        v-model="search"
                        label="Buscar por folio, cuenta o titular"
                        class="mx-4 mt-2"
                        clearable
                    />
                </template>

                <template #item.fecha="{ item }">
                    {{ formatearFechaCorta(item.fecha) }}
                </template>

                <template #item.monto="{ item }">
                    {{ currencyFormatter.format(item.monto) }}
                </template>

                <template #item.actions="{ item }">
                    <BaseButton
                        icon="mdi-printer-outline"
                        color="primary"
                        tooltip="Reimprimir"
                        @click="handleReimprimir(item)"
                    />

                    <BaseButton
                        action="view"
                        tooltip="Vista previa"
                        @click="handleVistaPrevia(item)"
                    />

                    <BaseButton
                        action="download"
                        tooltip="Descargar"
                        @click="handleDescargar(item)"
                    />
                </template>
            </v-data-table-server>
        </v-card>

        <v-dialog v-model="mostrarModalPreview" max-width="500">
            <v-card>
                <v-card-title>Vista previa del ticket</v-card-title>
                <v-card-text>
                    <TicketPreview :datos="datosPreview" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="mostrarModalPreview = false">Cerrar</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
