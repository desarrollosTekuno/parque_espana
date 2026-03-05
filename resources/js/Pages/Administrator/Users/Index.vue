<script setup lang="ts">
import { required } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";
import BaseButton from '@/Components/BaseButton.vue';
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;
interface Props {
    users?: any;
    filters: Object;
}

interface User {
    id: number | null;
    name: string;
    description: string;
}

// const props = defineProps<Props>();
const props = withDefaults(defineProps<Props>(), {
    users: null,
    filters: null,
});

/* refs */
let showModal = ref(false);
const formSendRef = ref();

/* forms */
const form = useForm<User>({
    id: null,
    name: "",
    description: "",
});

const create = () => {
    showModal.value = true;
};
const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }) => {
        console.log(isValid);
        if (!isValid) {
            return;
        } else {
            if (form.id) {
                // form.put(route("head-quarters.update", form.id), {
                //     onSuccess: () => {
                //         customToastSwal({
                //             title: "Rol actualizado con éxito!",
                //             icon: "success",
                //         });
                //         showModal.value = false;
                //         form.reset();
                //     },
                //     onError: () => {
                //         customToastSwal({
                //             title: `Error: ${form.errors.messageError}`,
                //             text: `${form.errors.exception}`,
                //             icon: "error",
                //         });
                //         // console.log(form.errors);
                //     },
                // });
            } else {
                // form.post(route("head-quarters.store"), {
                //     onSuccess: () => {
                //         customToastSwal({
                //             title: "Rol creado con éxito!",
                //             icon: "success",
                //         });
                //         showModal.value = false;
                //         form.reset();
                //     },
                //     onError: () => {
                //         customToastSwal({
                //             title: `Error: ${form.errors.messageError}`,
                //             text: `${form.errors.exception}`,
                //             icon: "error",
                //         });
                //         // console.log(form.errors);
                //     },
                // });
            }
        }
    });
};
const edit = (data: any) => {
    console.log(data);

    // headQuarterForm.id = data.id;
    // headQuarterForm.name = data.name;
    // headQuarterForm.latitude = data.latitude;
    // headQuarterForm.longitude = data.longitude;
    showModal.value = true;
};

const destroy = (data: any) => {
    // headQuarterForm.delete(route('head-quarters.destroy', data.id), {
    //     onSuccess: () => { },
    // });
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este registro?",
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("Modules.destroy", data.id), {
                onSuccess: () => {
                    customToastSwal({
                        title: "Registro eliminado correctamente",
                        icon: "success",
                    });
                },
                onError: (err) => {
                    console.error(err);
                    customToastSwal({
                        title: "Error al eliminar el registro",
                        icon: "error",
                    });
                },
            });
        }
    });
};
const close = () => {
    form.reset();
    showModal.value = false;
};
// rules validation example
// const rules = {
//     name: [
//         (v) => !!v || 'El nombre es requerido',
//     ],
//     latitude: [
//         (v) => !!v || 'La latitud es requerida',
//     ],
//     longitude: [
//         (v) => !!v || 'La longitud es requerida',
//     ],
// }
/* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "ID", key: "id" },
    { title: "Nombre", key: "name" },
    { title: "Email", key: "email" },
    { title: "Acciones", key: "actions", sortable: false },
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
const prefix = "users";
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

    router.get(route("users.index"), params, {
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
    <Head title="Usuarios" />

    <AppLayout>
        <template #header> Usuarios </template>
        <template #options>
            <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="create"
                action="add"
                v-if="can.includes('users.store')"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
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
                                label="Buscar usuarios"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.actions="{ item }">
                            <v-btn
                                text="Restablecer contraseña"
                                @click="edit(item)"
                                v-if="can.includes('users.update')"
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
                    prepend-icon="mdi-account"
                    :title="`Form|${form.id ? 'Edit' : 'Create'}`"
                >
                    <v-card-text>
                        <v-text-field
                            v-model="form.name"
                            label="Nombre"
                            persistent-hint
                            :rules="[required]"
                        />
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text="Cerrar" type="button" @click="close" />
                        <v-btn
                            prepend-icon="mdi-home"
                            :text="form.id ? 'update' : 'save'"
                            type="submit"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
        <!-- <Loader :overlay="form.processing" /> -->
    </AppLayout>
</template>
