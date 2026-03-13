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
const page = usePage<any>();

interface Props {
    roles?: any;
    permissions?: any;
    guards?: any;
    contexts?: any;
}

interface Role {
    id: number | null;
    name: string;
    description: string;
    permissions: any[];
    guard_name: string;
    context_id: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    roles: null,
    permissions: null,
    guards: null,
});

/* refs */
let showModal = ref(false);
const formSendRef = ref();

/* almacenamiento temporal de permisos por contexto */
const permissionsByContext = ref<Record<number, number[]>>({});

/* forms */
const form = useForm<Role>({
    id: null,
    name: "",
    description: "",
    permissions: [],
    guard_name: "web",
    context_id: null,
});

/* acciones */

const create = () => {
    permissionsByContext.value = {};
    showModal.value = true;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }) => {
        if (!isValid) return;

        if (form.id) {
            form.put(route("roles.update", form.id), {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "",
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
                },
            });
        } else {
            form.post(route("roles.store"), {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "",
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
                },
            });
        }
    });
};

const edit = (data: any) => {
    permissionsByContext.value = {};

    form.id = data.id;
    form.name = data.name;
    form.description = data.description;
    form.guard_name = data.guard_name;
    form.context_id = data.context_id;

    form.permissions = data.permissions.map((permission: any) => permission.id);

    permissionsByContext.value[data.context_id] = [...form.permissions];

    showModal.value = true;
};

const duplicate = (data: any) => {
    form.id = data.id;
    form.name = data.name;
    form.description = data.description;
    form.permissions = data.permissions.map((permission: any) => permission.id);
    form.context_id = data.context_id;

    customConfirmSwal({
        title: "¿Está segur@ que desea duplicar este rol?",
        confirmButtonText: "Sí, duplicar",
    }).then((result) => {
        if (result.isConfirmed) {
            form.post(route("roles.duplicate"), {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "",
                        icon: "success",
                    });
                    fetchItems();
                },
                onError: () => {
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
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este registro?",
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("roles.destroy", data.id), {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "",
                        icon: "success",
                    });
                    fetchItems();
                },
                onError: () => {
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
    permissionsByContext.value = {};
    showModal.value = false;
};

/* watch contexto */

watch(
    () => form.context_id,
    (newContext, oldContext) => {
        if (oldContext) {
            permissionsByContext.value[oldContext] = [...form.permissions];
        }

        if (permissionsByContext.value[newContext]) {
            form.permissions = [...permissionsByContext.value[newContext]];
        } else {
            form.permissions = [];
        }
    }
);

/* permisos filtrados por contexto */

const permissionsList = computed(() => {
    return props.permissions.filter((permission) =>
        permission.contexts.some(
            (context) => context.id === form.context_id
        )
    );
});

/* DATATABLE */

const headers = [
    { title: "Nombre", key: "name" },
    { title: "Descripción", key: "description" },
    { title: "Contexto", key: "context" },
    { title: "Creado el", key: "created_at" },
    { title: "Actualizado el", key: "updated_at" },
    { title: "Acciones", key: "actions", sortable: false },
];

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
            items.value = page.props[prefix]?.data ?? [];
            total.value = page.props[prefix]?.total ?? 0;
            loading.value = false;
        },
    });
};

watch([options, search], debounce(fetchItems, 400), { deep: true });
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
                        <template v-slot:item.context="{ item }">
                            <v-chip color="primary">{{ item.context?.name }}</v-chip>
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
                            <!-- Contexto -->
                            
                            <v-col cols="12">
                                <v-autocomplete
                                    prepend-inner-icon="mdi-cog"
                                    v-model="form.context_id"
                                    item-value="id"
                                    item-title="name"
                                    :items="contexts"
                                    :rules="[selectRequired]"
                                    hint="Contexto"
                                    persistent-hint
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
