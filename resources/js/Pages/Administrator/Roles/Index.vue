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

        if (!form.permissions.length) {
            customToastSwal({ title: "Selecciona al menos un permiso", icon: "warning" });
            return;
        }

        if (form.id) {
            form.put(route("roles.update", form.id), {
                onSuccess: () => {
                    customToastSwal({ title: page.props.flash.success || "", icon: "success" });
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
                    customToastSwal({ title: page.props.flash.success || "", icon: "success" });
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
    // El watch de context_id (pensado para el modal de crear/editar) corre
    // de forma diferida y, si no encuentra nada en caché para este
    // contexto, resetea form.permissions a [] — sin esto, los permisos que
    // se acaban de asignar arriba se perdían antes de que el usuario
    // alcanzara a confirmar el duplicado.
    permissionsByContext.value[data.context_id] = [...form.permissions];

    customConfirmSwal({
        title: "¿Está segur@ que desea duplicar este rol?",
        confirmButtonText: "Sí, duplicar",
    }).then((result) => {
        if (result.isConfirmed) {
            form.post(route("roles.duplicate"), {
                onSuccess: () => {
                    customToastSwal({ title: page.props.flash.success || "", icon: "success" });
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
                    customToastSwal({ title: page.props.flash.success || "", icon: "success" });
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
        if (newContext && permissionsByContext.value[newContext]) {
            form.permissions = [...permissionsByContext.value[newContext]];
        } else {
            form.permissions = [];
        }
    }
);

/* permisos filtrados por contexto */
const permissionsList = computed(() =>
    props.permissions.filter((permission: any) =>
        permission.contexts.some((context: any) => context.id === form.context_id)
    )
);

/* permisos agrupados por módulo */
const permissionsGroupedByModule = computed(() => {
    const grouped: Record<string, any[]> = {};
    permissionsList.value.forEach((permission: any) => {
        const mod = permission.module || "Sin módulo";
        if (!grouped[mod]) grouped[mod] = [];
        grouped[mod].push(permission);
    });
    return grouped;
});

const isModuleFullySelected = (permissions: any[]) =>
    permissions.length > 0 && permissions.every((p) => form.permissions.includes(p.id));

const isModulePartiallySelected = (permissions: any[]) =>
    permissions.some((p) => form.permissions.includes(p.id)) && !isModuleFullySelected(permissions);

const toggleModule = (permissions: any[]) => {
    const ids = permissions.map((p) => p.id);
    if (isModuleFullySelected(permissions)) {
        form.permissions = form.permissions.filter((id) => !ids.includes(id));
    } else {
        form.permissions = [...new Set([...form.permissions, ...ids])];
    }
};

const togglePermission = (id: number, val: boolean) => {
    if (val) {
        if (!form.permissions.includes(id)) form.permissions = [...form.permissions, id];
    } else {
        form.permissions = form.permissions.filter((p) => p !== id);
    }
};

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
                                :disabled="canRole.includes(item.name) || ['socio_dependiente','socio_titular'].includes(item.name)"
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
        </div>

        <v-dialog v-model="showModal" max-width="800" persistent scrollable>
            <v-card prepend-icon="mdi-cube-outline" :title="`${form.id ? 'Editar Rol' : 'Nuevo Rol'}`">
                <v-card-text>
                    <v-form @submit.prevent="save" ref="formSendRef">
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre"
                                    :rules="[required, minLength(4), maxLength(50)]"
                                    :disabled="form.id && ['socio_dependiente','socio_titular'].includes(form.name)"
                                />
                            </v-col>
                            <v-col cols="12" md="6">
                                <v-autocomplete
                                    prepend-inner-icon="mdi-cog"
                                    v-model="form.context_id"
                                    item-value="id"
                                    item-title="name"
                                    :items="contexts"
                                    :rules="[selectRequired]"
                                    label="Contexto"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.description"
                                    label="Descripción"
                                    clearable
                                    counter
                                    rows="2"
                                    :rules="[optionalLength(0, 75)]"
                                    auto-grow
                                    variant="filled"
                                />
                            </v-col>
                        </v-row>

                        <!-- Permisos agrupados por módulo -->
                        <div v-if="form.context_id" class="mt-2">
                            <div class="d-flex align-center justify-space-between mb-3">
                                <span class="text-subtitle-2 font-weight-bold">Permisos</span>
                                <span class="text-caption text-medium-emphasis">
                                    {{ form.permissions.length }} seleccionados de {{ permissionsList.length }}
                                </span>
                            </div>

                            <v-expansion-panels variant="accordion" multiple>
                                <v-expansion-panel
                                    v-for="(modulePermissions, moduleName) in permissionsGroupedByModule"
                                    :key="moduleName"
                                >
                                    <v-expansion-panel-title>
                                        <div class="d-flex align-center ga-2 w-100 pr-2">
                                            <v-checkbox
                                                :model-value="isModuleFullySelected(modulePermissions)"
                                                :indeterminate="isModulePartiallySelected(modulePermissions)"
                                                color="primary"
                                                hide-details
                                                density="compact"
                                                @click.stop="toggleModule(modulePermissions)"
                                            />
                                            <span class="font-weight-medium">{{ moduleName }}</span>
                                            <v-chip
                                                size="x-small"
                                                :color="isModulePartiallySelected(modulePermissions) || isModuleFullySelected(modulePermissions) ? 'primary' : 'default'"
                                                variant="tonal"
                                                class="ml-auto"
                                            >
                                                {{ modulePermissions.filter((p) => form.permissions.includes(p.id)).length }}
                                                / {{ modulePermissions.length }}
                                            </v-chip>
                                        </div>
                                    </v-expansion-panel-title>
                                    <v-expansion-panel-text>
                                        <v-row dense>
                                            <v-col
                                                v-for="permission in modulePermissions"
                                                :key="permission.id"
                                                cols="12"
                                                sm="6"
                                            >
                                                <v-checkbox
                                                    :model-value="form.permissions.includes(permission.id)"
                                                    :label="permission.description"
                                                    color="primary"
                                                    hide-details
                                                    density="compact"
                                                    @update:modelValue="(val) => togglePermission(permission.id, Boolean(val))"
                                                />
                                            </v-col>
                                        </v-row>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </div>

                        <v-alert v-else type="info" variant="tonal" class="mt-3">
                            Selecciona un contexto para ver los permisos disponibles.
                        </v-alert>
                    </v-form>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <BaseButton :icon-only="false" variant="tonal" action="cancel" @click="close" />
                    <BaseButton
                        :text="form.id ? 'Actualizar' : 'Guardar'"
                        variant="flat"
                        :icon-only="false"
                        action="save"
                        @click="save"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
