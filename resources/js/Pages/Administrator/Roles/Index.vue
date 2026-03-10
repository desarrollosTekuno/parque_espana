<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { formatDateTime } from "@/constants/formatDates";
import {
    maxLength,
    minLength,
    optionalLength,
    required,
    selectRequired,
} from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;

interface Props {
    roles?: any;
    permissions?: any;
    guards?: any;
}

interface Role {
    id: number | null;
    name: string;
    description: string;
    permissions: any[];
    guard_name: string;
}

// const props = defineProps<Props>();
const props = withDefaults(defineProps<Props>(), {
    roles: null,
    permissions: null,
    guards: null,
});

/* refs */
let showModal = ref(false);
const formSendRef = ref();

/* forms */
const form = useForm<Role>({
    id: null,
    name: "",
    description: "",
    permissions: [],
    guard_name: "web",
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
                form.put(route("roles.update", form.id), {
                    onSuccess: () => {
                        customToastSwal({
                            title: "Rol actualizado con éxito!",
                            icon: "success",
                        });
                        showModal.value = false;
                        form.reset();
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
                form.post(route("roles.store"), {
                    onSuccess: () => {
                        customToastSwal({
                            title: "Rol creado con éxito!",
                            icon: "success",
                        });
                        showModal.value = false;
                        form.reset();
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
    form.permissions = data.permissions.map((permission: any) => permission.id);
    form.guard_name = data.guard_name;
    console.log(data);

    // headQuarterForm.id = data.id;
    // headQuarterForm.name = data.name;
    // headQuarterForm.latitude = data.latitude;
    // headQuarterForm.longitude = data.longitude;
    showModal.value = true;
};

const duplicate = (data: any) => {
    form.id = data.id;
    form.name = data.name;
    form.description = data.description;
    form.permissions = data.permissions.map((permission: any) => permission.id);
    customConfirmSwal({
        title: "¿Está segur@ que desea duplicar este rol?",
        confirmButtonText: "Sí, duplicar",
    }).then((result) => {
        if (result.isConfirmed) {
            form.post(route("roles.duplicate"), {
                onSuccess: () => {
                    customToastSwal({
                        title: "Rol creado correctamente",
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

const destroy = (data: any) => {
    // headQuarterForm.delete(route('head-quarters.destroy', data.id), {
    //     onSuccess: () => { },
    // });
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este registro?",
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("roles.destroy", data.id), {
                onSuccess: () => {
                    customToastSwal({
                        title: "Rol eliminado correctamente",
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
    showModal.value = false;
};

/* Watches and observers */
watch(
    () => form.guard_name,
    (newId) => {
        form.permissions = [];
    },
);
/* Computed */
const permissionsList = computed(() => {
    // if (form.permissions) {
    //     return form.permissions.map((permissionId) =>
    //         props.permissions.find((permission) => permission.id === permissionId)
    //     );
    // }
    // return [];
    return props.permissions.filter(
        (permission) => permission.guard_name === form.guard_name,
    );
});
//* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "Nombre", key: "name" },
    { title: "Guard", key: "guard_name" },
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
const prefix = "roles";
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

    router.get(route("roles.index"), params, {
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
    <Head title="Roles" />

    <AppLayout>
        <template #header> Roles </template>
        <template #options>
            <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="create"
                action="add"
                v-if="can.includes('roles.store')"
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
                                label="Buscar roles"
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
                        <template #item.actions="{ item }">
                            <BaseButton
                                @click="edit(item)"
                                action="edit"
                                v-if="can.includes('roles.update')"
                            />
                            <BaseButton
                                :disabled="canRole.includes(item.name)"
                                @click="destroy(item)"
                                action="delete"
                                v-if="can.includes('roles.destroy')"
                            />
                            <BaseButton
                                icon="mdi-content-duplicate"
                                @click="duplicate(item)"
                                tooltip="Duplicar"
                                action="other"
                                v-if="can.includes('roles.duplicate')"
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
                    prepend-icon="mdi-cube-outline"
                    :title="`${form.id ? 'Editar Rol' : 'Nuevo Rol'}`"
                >
                    <v-card-text class="overflow-y-auto h-full">
                        <!-- <v-text-field
                            v-model="form.name"
                            label="Nombre"
                            persistent-hint
                            :rules="validationRules.required"
                        /> -->
                        <v-row>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre"
                                    persistent-hint
                                    :rules="[
                                        required,
                                        minLength(4),
                                        maxLength(50),
                                    ]"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.description"
                                    label="Descripción"
                                    persistent-hint
                                    :rules="[optionalLength(0, 75)]"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-select
                                    v-model="form.guard_name"
                                    placeholder="Guard"
                                    hint="Guard"
                                    persistent-hint
                                    :items="props.guards"
                                    :rules="[selectRequired]"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-autocomplete
                                    prepend-inner-icon="mdi-key"
                                    v-model="form.permissions"
                                    chips
                                    closable-chips
                                    multiple
                                    clearable
                                    item-value="id"
                                    item-title="description"
                                    :items="permissionsList"
                                    :rules="[selectRequired]"
                                    hint="Permisos"
                                    persistent-hint
                                >
                                </v-autocomplete>
                                <!-- <v-file-input
                                    label="Subir archivo"
                                    :rules="[

                                    ]"
                                /> -->
                            </v-col>
                        </v-row>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <!-- <v-btn text="Cerrar" type="button" @click="close" />
                        <v-btn
                            prepend-icon="mdi-home"
                            :text="form.id ? 'update' : 'save'"
                            type="submit"
                            v-if="can.includes('roles.store')"
                        /> -->
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
                                    ? can.includes('roles.update')
                                    : can.includes('roles.store')
                            "
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
        <!-- <Loader :overlay="form.processing" /> -->
    </AppLayout>
</template>
