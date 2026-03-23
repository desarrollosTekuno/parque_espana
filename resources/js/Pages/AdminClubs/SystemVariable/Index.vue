<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";
import { required, maxLength } from "@/constants/validationRules";

const page = usePage();
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;
const showModal = ref(false);
const formSendRef = ref();

interface Props {
    systemVariables?: any;
}

const props = withDefaults(defineProps<Props>(), {
    systemVariables: null,
});

interface SystemVariable {
    id: number | null;
    name: string;
    value: string;
    description: string;
}

const form = useForm<SystemVariable>({
    id: null,
    name: '',
    value: '',
    description: '',
});

const create = () => {
    showModal.value = true;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }) => {
        // console.log(isValid);
        if (!isValid) {
            return;
        } else {
            if (form.id) {
                form.put(route("system-variables.update", form.id), {
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
                        // console.log(form.errors);
                    },
                });
            } else {
                form.post(route("system-variables.store"), {
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
    form.value = data.value;
    form.description = data.description;
    showModal.value = true;
}

const destroy = (data: any) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este registro?",
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("system-variables.destroy", data.id), {
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
    showModal.value = false;
};

//* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "ID", key: "id" },
    { title: "Nombre", key: "name" },
    { title: "Descripción", key: "description" },
    { title: "Valor", key: "value" },
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
const prefix = "systemVariables";
// función para cargar datos desde Laravel
const fetchItems = async () => {
    loading.value = true;
    const params = {
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
    };

    router.get(route("system-variables.index"), params, {
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

watch(() => page.props.auth.currentClub, () => {
    fetchItems();
});


</script>

<template>
    <Head title="Dashboard"/>

    <AppLayout>
        <template #header> Variables del Sistema</template>
        <template #options>
            <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="create()"
                action="add"
                v-if="can.includes('system-variables.store')"
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
                                label="Buscar"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                action="edit"
                                @click="edit(item)"
                                v-if="can.includes('system-variables.update')"
                            />
                            <BaseButton
                                @click="destroy(item)"
                                action="delete"
                                v-if="can.includes('system-variables.destroy')"
                            />
                        </template>

                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="600" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card
                    prepend-icon="mdi-cube-outline"
                    :title="`${form.id ? 'Editar Variable' : 'Nueva Variable'}`"
                >
                    <v-card-text class="overflow-y-auto h-full">
                        <v-col cols="12">
                            <v-text-field
                                v-model="form.name"
                                label="Nombre"
                                persistent-hint
                                :rules="[required, maxLength(50)]"
                                :disabled="form.id ? true : false"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-text-field
                                v-model="form.value"
                                label="Valor"
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
                                :rules="[required]"
                                auto-grow
                                variant="filled"
                            />
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

    </AppLayout>

</template>
