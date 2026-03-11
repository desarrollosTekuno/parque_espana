<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import {
    maxLength,
    optionalLength,
    required,
    selectRequired,
} from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";
import { formatDateTime } from "../../../constants/formatDates";
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;
interface Props {
    permissions?: any;
    guards?: any;
    permissionContexts?: any;
}

interface Permissions {
    id: number | null;
    name: string;
    description: string;
    permission_contexts?: any;
}

// const props = defineProps<Props>();
const props = withDefaults(defineProps<Props>(), {
    permissions: null,
    guards: null,
    permissionContexts: null,
});

/* refs */
let showModal = ref(false);
const formSendRef = ref();

/* forms */
const form = useForm<Permissions>({
    id: null,
    name: "",
    description: "",
    permission_contexts: [],
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
                form.put(route("permissions.update", form.id), {
                    onSuccess: () => {
                        customToastSwal({
                            title: "Permiso actualizado con éxito!",
                            icon: "success",
                        });
                        showModal.value = false;
                        form.reset();
                        /* Es necesario invocar fetchPermission para refrescar el datatable */
                        fetchItems();
                    },
                    onError: () => {
                        customToastSwal({
                            title: `Error: ${form.errors.messageError}`,
                            text: `${form.errors.exception}`,
                            icon: "error",
                        });
                        // console.log(form.errors);
                    },
                });
            } else {
                form.post(route("permissions.store"), {
                    onSuccess: () => {
                        customToastSwal({
                            title: "Permiso creado con éxito!",
                            icon: "success",
                        });
                        showModal.value = false;
                        form.reset();
                        /* Es necesario invocar fetchPermission para refrescar el datatable */
                        fetchItems();
                    },
                    onError: () => {
                        customToastSwal({
                            title: `Error: ${form.errors.messageError}`,
                            text: `${form.errors.exception}`,
                            icon: "error",
                        });
                        // console.log(form.errors);
                    },
                });
            }
        }
    });
};
const edit = (data: any) => {
    form.id = data.id;
    form.name = data.name;
    form.description = data.description;
    form.permission_contexts = data.contexts.map((context: any) => context.id);
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
            form.delete(route("permissions.destroy", data.id), {
                onSuccess: () => {
                    customToastSwal({
                        title: "Registro eliminado correctamente",
                        icon: "success",
                    });
                    /* Es necesario invocar fetchPermission para refrescar el datatable */
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
    showModal.value = false;
};
/* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "Nombre de ruta(Spatie)", key: "name" },
    { title: "Disponible en", key: "contexts", sortable: false },
    // { title: "Guard", key: "guard_name" },
    { title: "Descripción", key: "description" },
    { title: "Creado el", key: "created_at" },
    { title: "Actualizado el", key: "updated_at" },
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
const prefix = "permissions";
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

    router.get(route("permissions.index"), params, {
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
    <Head title="Permisos" />

    <AppLayout>
        <template #header> Permisos </template>
        <template #options>
            <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="create"
                action="add"
                v-if="can.includes('permissions.store')"
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
                        class="elevation-1 rounded-lg"
                        :items-per-page-options="[10, 25, 50, 100]"
                        items-per-page-text=" Mostrar"
                        no-data-text="No hay registros para mostrar"
                    >
                        <template #top>
                            <v-text-field
                                v-model="search"
                                label="Buscar permisos"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>
                        <template v-slot:item.created_at="{ item }">
                            {{ formatDateTime(item.created_at) }}
                        </template>
                        <template v-slot:item.updated_at="{ item }">
                            {{ formatDateTime(item.updated_at) }}
                        </template>
                        <template v-slot:item.contexts="{ item }">
                                <v-chip
                                    v-for="context in item.contexts"
                                    :key="context.id"
                                    color="primary"
                                    class="ma-1"
                                >
                                    {{ context.name }}
                                </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <!-- <v-btn
                                icon="mdi-pencil-outline"
                                variant="text"
                                @click="edit(item)"
                                v-if="
                                    can.includes(
                                        'permissions.update'
                                    )
                                "
                            /> -->
                            <BaseButton
                                action="edit"
                                @click="edit(item)"
                                v-if="can.includes('permissions.update')"
                            />
                            <BaseButton
                                @click="destroy(item)"
                                action="delete"
                                v-if="can.includes('permissions.destroy')"
                            />
                            <!-- <v-btn
                                text="Eliminar"
                                icon="mdi-delete-outline"
                                variant="text"
                                @click="destroy(item)"
                                v-if="
                                    can.includes(
                                        'permissions.destroy'
                                    )
                                "
                            /> -->
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
            <!-- </div> -->
        </div>
        <v-dialog v-model="showModal" max-width="600" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card
                    prepend-icon="mdi-key-outline"
                    :title="`${form.id ? 'Editar Permiso' : 'Nuevo Permiso'}`"
                >
                    <v-card-text>
                        <v-row>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre de Ruta (Spatie)"
                                    persistent-hint
                                    :rules="[required, maxLength(50)]"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.description"
                                    label="Descripción"
                                    persistent-hint
                                    clearable
                                    counter
                                    rows="3"
                                    :rules="[optionalLength(0, 75)]"
                                    auto-grow
                                    variant="filled"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-autocomplete
                                    prepend-inner-icon="mdi-certificate-outline"
                                    v-model="form.permission_contexts"
                                    chips
                                    closable-chips
                                    multiple
                                    clearable
                                    item-value="id"
                                    item-title="name"
                                    :items="props.permissionContexts"
                                    :rules="[selectRequired]"
                                    hint="Disponible en"
                                    persistent-hint
                                >
                                </v-autocomplete>
                            </v-col>
                        </v-row>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <!-- <v-btn text="Cerrar" type="button" @click="close" /> -->
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
                            :v-if="
                                form.id
                                    ? can.includes('permissions.update')
                                    : can.includes('permissions.store')
                            "
                        />
                        <!--  <v-btn
                            prepend-icon="mdi-home"
                            :text="form.id ? 'update' : 'save'"
                            type="submit"
                            v-if="can.includes('permissions.store')"
                        /> -->
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
        <!-- <Loader :overlay="form.processing" /> -->
    </AppLayout>
</template>
