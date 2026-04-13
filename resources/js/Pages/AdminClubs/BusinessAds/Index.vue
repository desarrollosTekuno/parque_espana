<script setup lang="ts">

import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import { debounce } from "lodash";

const page = usePage();
const can = page.props.auth.permissions;

interface Props {
    ads?: any;
}

const props = defineProps<Props>();

const headers = [
    { title: "Título", key: "title" },
    { title: "Usuario", key: "user" },
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

const formatDate = (val: string | null) => {
    if (!val) return "-";
    const [date, time] = val.split(" ");
    const [y, m, d] = date.split("-");
    return `${d}/${m}/${y}`;
};

const getStatusColor = (status: string) => {
    switch (status) {
        case "pending": return "grey";
        case "approved": return "blue";
        case "paid": return "orange";
        case "published": return "green";
        case "rejected": return "red";
        case "expired": return "black";
        default: return "grey";
    }
};

const approve = (item: any) => {
    customConfirmSwal({ title: "¿Aprobar anuncio?" })
    .then(r => {
        if (r.isConfirmed) {
            router.post(route("business-ads.approve", item.id), {}, {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success,
                        icon: "success"
                    });
                    fetchItems();
                }
            });
        }
    });
};

const reject = (item: any) => {
    customConfirmSwal({ title: "¿Rechazar anuncio?" })
    .then(r => {
        if (r.isConfirmed) {
            router.post(route("business-ads.reject", item.id), {}, {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success,
                        icon: "success"
                    });
                    fetchItems();
                }
            });
        }
    });
};

const confirmPayment = (item: any) => {
    customConfirmSwal({ title: "¿Confirmar pago?" })
    .then(r => {
        if (r.isConfirmed) {
            router.post(route("business-ads.confirmPayment", item.id), {}, {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success,
                        icon: "success"
                    });
                    fetchItems();
                }
            });
        }
    });
};

const publish = (item: any) => {
    customConfirmSwal({ title: "¿Publicar anuncio?" })
    .then(r => {
        if (r.isConfirmed) {
            router.post(route("business-ads.publish", item.id), {}, {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success,
                        icon: "success"
                    });
                    fetchItems();
                }
            });
        }
    });
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
                <v-chip :color="getStatusColor(item.status?.name)" dark>
                    {{ item.status?.name }}
                </v-chip>
            </template>

            <template #item.created_at="{ item }">
                {{ formatDate(item.created_at) }}
            </template>

            <template #item.actions="{ item }">

                <!-- pending -->
                <BaseButton
                    v-if="item.status?.name === 'pending'"
                    action="check"
                    @click="approve(item)"
                />

                <BaseButton
                    v-if="item.status?.name === 'pending'"
                    action="delete"
                    @click="reject(item)"
                />

                <!-- approved -->
                <BaseButton
                    v-if="item.status?.name === 'approved'"
                    action="payment"
                    @click="confirmPayment(item)"
                />

                <!-- paid -->
                <BaseButton
                    v-if="item.status?.name === 'paid'"
                    action="publish"
                    @click="publish(item)"
                />

            </template>

        </v-data-table-server>

    </AppLayout>
</template>