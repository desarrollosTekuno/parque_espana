<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import CustomFileUploadField from "@/Components/CustomFileUploadField.vue";
import { required, maxLength } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;
const page = usePage<any>();
interface Props {
    clubs?: any;
}

interface Club {
    id: number | null;
    name: string;
    address: string;
    is_active: boolean;
    logo: File | null;
}

const props = withDefaults(defineProps<Props>(), {
    clubs: null,
});

/* refs */
let showModal = ref(false);
const formSendRef  = ref();
const currentLogoUrl = ref<string | null>(null);

/* forms */
const form = useForm<Club>({
    id: null,
    name: "",
    address: "",
    is_active: true,
    logo: null,
});

const create = () => {
    showModal.value = true;
};
const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }) => {
        if (!isValid) return;

        const onSuccess = () => {
            customToastSwal({ title: page.props.flash.success || "", icon: "success" });
            showModal.value = false;
            currentLogoUrl.value = null;
            form.reset();
            fetchItems();
        };
        const onError = () => {
            customToastSwal({
                title: `Error: ${form.errors.messageError}`,
                text: `${form.errors.exception}`,
                icon: "error",
            });
        };

        // CustomFileUploadField emite File[] — extraemos el File individual antes de enviar
        const normalizeFile = (data: any) => ({
            ...data,
            logo: Array.isArray(data.logo) ? (data.logo[0] ?? null) : data.logo,
        });

        if (form.id) {
            form.transform((data) => ({ ...normalizeFile(data), _method: "PUT" }))
                .post(route("clubs.update", form.id), { onSuccess, onError });
        } else {
            form.transform((data) => normalizeFile(data))
                .post(route("clubs.store"), { onSuccess, onError });
        }
    });
};
const edit = (data: any) => {
    form.name        = data.name;
    form.address     = data.address;
    form.is_active   = data.is_active;
    form.id          = data.id;
    form.logo        = null;
    currentLogoUrl.value = data.logo_url ?? null;
    showModal.value  = true;
};

const destroy = (data: any) => {
    // headQuarterForm.delete(route('head-quarters.destroy', data.id), {
    //     onSuccess: () => { },
    // });
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este registro?",
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("clubs.destroy", data.id), {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "",
                        icon: "success",
                    });
                    fetchItems();
                },
                onError: (err) => {
                    console.error(err);
                    customToastSwal({
                        title: `Error: ${form.errors.messageError}`,
                        text: `${form.errors.exception}`,
                        icon: "error",
                    });
                },
            });
        }
    });
};
const close = () => {
    form.reset();
    currentLogoUrl.value = null;
    showModal.value = false;
};
//* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "ID",        key: "id" },
    { title: "Logo",      key: "logo_url",  sortable: false },
    { title: "Nombre",    key: "name" },
    { title: "Dirección", key: "address" },
    { title: "Activo",    key: "is_active" },
    { title: "Acciones",  key: "actions",  sortable: false },
];

// variables reactivas
const items = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref("");
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});
const prefix = "clubs";
// función para cargar datos desde Laravel
const fetchItems = async () => {
    loading.value = true;
    const params = {
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
    };

    router.get(route("clubs.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            const data = page.props[prefix]?.data ?? [];
            const totalCount = page.props[prefix]?.total ?? 0;

            items.value = data;
            total.value = totalCount;
            loading.value = false;
        },
    });
};

// 🔁 Observadores con debounce para evitar muchas peticiones
watch([options, search], debounce(fetchItems, 400), { deep: true });
/* FIN DATATABLE SERVER SIDE */
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header> Clubes </template>
        <template #options>
            <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="create"
                action="add"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <!-- <div class="p-6 border-b border-gray-200"> -->
            <v-row>
                <v-col cols="12">
                    <v-data-table-server
                        fixed-header
                        hover
                        height="500px"
                        :headers="headers"
                        :items="items"
                        :items-length="total"
                        :loading="loading"
                        v-model:options="options"
                        class="elevation-1"
                        :items-per-page-options="[10, 25, 50, 100]"
                        items-per-page-text=" Mostrar"
                        no-data-text="No hay registros para mostrar"
                    >
                        <template #top>
                            <v-text-field
                                v-model="search"
                                label="Buscar club"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.logo_url="{ item }">
                            <v-avatar v-if="item.logo_url" size="36" rounded="sm">
                                <v-img :src="item.logo_url" :alt="item.name" cover />
                            </v-avatar>
                            <v-icon v-else color="grey-lighten-1">mdi-image-off-outline</v-icon>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                action="edit"
                                @click="edit(item)"
                                v-if="can.includes('clubs.update')"
                            />
                            <BaseButton
                                @click="destroy(item)"
                                action="delete"
                                v-if="can.includes('clubs.destroy')"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
            <!-- </div> -->
        </div>
        <v-dialog v-model="showModal" max-width="600" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card
                    prepend-icon="mdi-soccer"
                    :title="`${form.id ? 'Editar Club' : 'Nuevo Club'}`"
                >
                    <v-card-text class="overflow-y-auto h-full">
                        <v-col cols="12">
                            <v-text-field
                                v-model="form.name"
                                label="Nombre"
                                persistent-hint
                                :rules="[required, maxLength(20)]"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-textarea
                                v-model="form.address"
                                label="Dirección"
                                persistent-hint
                                clearable
                                counter
                                rows="3"
                                :rules="[required]"
                                auto-grow
                                variant="filled"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-switch
                                v-model="form.is_active"
                                color="green"
                                :label="form.is_active ? 'Activo' : 'Inactivo'"
                                hide-details
                                inset
                            />
                        </v-col>

                        <!-- Logo -->
                        <v-col cols="12">
                            <!-- Preview del logo actual al editar -->
                            <div v-if="currentLogoUrl && !form.logo" class="mb-3 d-flex align-center ga-3">
                                <v-img
                                    :src="currentLogoUrl"
                                    width="72"
                                    height="72"
                                    cover
                                    rounded="lg"
                                    class="border"
                                />
                                <div class="text-body-2 text-medium-emphasis">
                                    Logo actual. Sube uno nuevo para reemplazarlo.
                                </div>
                            </div>

                            <CustomFileUploadField
                                v-model="form.logo as any"
                                label="Logo del club"
                                hint="JPG, PNG o WEBP · máx. 2 MB"
                                accept="image/jpeg,image/png,image/webp"
                            />
                            <div v-if="form.errors.logo" class="text-error text-caption mt-1">
                                {{ form.errors.logo }}
                            </div>
                        </v-col>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <BaseButton
                            :icon-only="false"
                            variant="tonal"
                            action="cancel"
                            @click="close"
                        />
                        <BaseButton
                            :text="form.id ? 'Actualizar' : 'Guardar'"
                            variant="flat"
                            :icon-only="false"
                            type="submit"
                            action="save"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
        <!-- <Loader :overlay="form.processing" /> -->
    </AppLayout>
</template>
