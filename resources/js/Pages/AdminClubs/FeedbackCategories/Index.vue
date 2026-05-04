<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { required } from "@/constants/validationRules";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";

const can = usePage().props.auth.permissions;
const page = usePage<any>();

interface Props {
    categories?: any;
    messageError?: string;
}

interface Category {
    id: number | null;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    categories: null,
    messageError: "",
});

const showModal = ref(false);
const formSendRef = ref();

const form = useForm<Category>({
    id: null,
    name: "",
    code: "",
    description: "",
    is_active: true,
});

const headers = [
    { title: "ID", key: "id" },
    { title: "Nombre", key: "name" },
    { title: "Código", key: "code" },
    { title: "Descripción", key: "description" },
    { title: "Activo", key: "is_active" },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref(props.categories?.data ?? []);
const total = ref(props.categories?.total ?? 0);
const loading = ref(false);
const search = ref("");

const options = ref({
    page: props.categories?.current_page ?? 1,
    itemsPerPage: props.categories?.per_page ?? 10,
    sortBy: [{ key: "id", order: "desc" }],
});

const fetchItems = async () => {
    loading.value = true;

    router.get(
        route("feedback-categories.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value,
            sort: options.value.sortBy?.[0]?.key ?? "id",
            order: options.value.sortBy?.[0]?.order ?? "desc",
        },
        {
            preserveState: true,
            replace: true,
            onSuccess: (page) => {
                const categories = page.props.categories as any;

                items.value = categories?.data ?? [];
                total.value = categories?.total ?? 0;
                loading.value = false;
            },
            onError: () => {
                loading.value = false;
            },
        },
    );
};

const create = () => {
    form.reset();
    form.clearErrors();
    form.is_active = true;
    showModal.value = true;
};

const edit = (data: any) => {
    form.clearErrors();
    form.id = data.id;
    form.name = data.name;
    form.code = data.code;
    form.description = data.description;
    form.is_active = Boolean(data.is_active);
    showModal.value = true;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid }: any) => {
        if (!valid) {
            return;
        }

        if (form.id) {
            form.put(route("feedback-categories.update", form.id), {
                preserveScroll: true,
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "",
                        icon: "success",
                    });
                    close();
                    fetchItems();
                },
                onError: () => {
                    customToastSwal({
                        title: `Error: ${form.errors.messageError ?? ""}`,
                        text: `${form.errors.exception ?? ""}`,
                        icon: "error",
                    });
                },
            });

            return;
        }

        form.post(route("feedback-categories.store"), {
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({
                    title: page.props.flash.success || "",
                    icon: "success",
                });
                close();
                fetchItems();
            },
            onError: () => {
                customToastSwal({
                    title: `Error: ${form.errors.messageError ?? ""}`,
                    text: `${form.errors.exception ?? ""}`,
                    icon: "error",
                });
            },
        });
    });
};

const destroy = (data: any) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este registro?",
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("feedback-categories.destroy", data.id), {
                preserveScroll: true,
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "",
                        icon: "success",
                    });
                    fetchItems();
                },
                onError: () => {
                    customToastSwal({
                        title: `Error: ${form.errors.messageError ?? ""}`,
                        text: `${form.errors.exception ?? ""}`,
                        icon: "error",
                    });
                },
            });
        }
    });
};

const close = () => {
    form.reset();
    form.clearErrors();
    showModal.value = false;
};

watch([options, search], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Categorías de feedback" />

    <AppLayout>
        <template #header> Categorías de feedback </template>

        <template #options>
            <BaseButton
                v-if="can.includes('feedback-categories.store')"
                variant="elevated"
                :icon-only="false"
                @click="create"
                action="add"
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
                                label="Buscar categorías"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.is_active="{ item }">
                            <v-chip
                                :color="item.is_active ? 'success' : 'error'"
                                size="small"
                                variant="tonal"
                            >
                                {{ item.is_active ? "Activo" : "Inactivo" }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('feedback-categories.update')"
                                action="edit"
                                @click="edit(item)"
                            />

                            <BaseButton
                                v-if="can.includes('feedback-categories.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="700" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card
                    prepend-icon="mdi-tag-outline"
                    :title="form.id ? 'Editar categoría' : 'Crear categoría'"
                >
                    <v-card-text class="h-full overflow-y-auto">
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre"
                                    :rules="[required]"
                                    :error-messages="form.errors.name"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.code"
                                    label="Código"
                                    :rules="[required]"
                                    :error-messages="form.errors.code"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.description"
                                    label="Descripción"
                                    rows="3"
                                    :error-messages="form.errors.description"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-switch
                                    v-model="form.is_active"
                                    label="Activo"
                                    color="success"
                                    :error-messages="form.errors.is_active"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />

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
