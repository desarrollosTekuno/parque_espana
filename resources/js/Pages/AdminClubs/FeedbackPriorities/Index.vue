<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { required, maxLength, alphaNumeric } from "@/constants/validationRules";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";

const can = usePage().props.auth.permissions;
const page = usePage<any>();

interface Props {
    priorities?: any;
    messageError?: string;
}

interface FeedbackPriority {
    id: number | null;
    name: string;
    code: string;
    sort_order: number | null;
    is_active: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    priorities: null,
    messageError: "",
});

const showModal = ref(false);
const formSendRef = ref();

const form = useForm<FeedbackPriority>({
    id: null,
    name: "",
    code: "",
    sort_order: 0,
    is_active: true,
});

const codeRule = (v: string) => !v || /^[A-Za-z0-9_]+$/.test(v) || "Solo se permiten letras, numeros y guion bajo (_)";

const integerRule = (v: number | string | null) => {
    if (v === null || v === "") return "El campo es requerido";
    return /^\d+$/.test(String(v)) || "Solo se permiten numeros enteros";
};

const headers = [
    { title: "ID", key: "id" },
    { title: "Nombre", key: "name" },
    { title: "Codigo", key: "code" },
    { title: "Orden", key: "sort_order" },
    { title: "Activo", key: "is_active" },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref(props.priorities?.data ?? []);
const total = ref(props.priorities?.total ?? 0);
const loading = ref(false);
const search = ref("");

const options = ref({
    page: props.priorities?.current_page ?? 1,
    itemsPerPage: props.priorities?.per_page ?? 10,
    sortBy: [{ key: "sort_order", order: "asc" }],
});

const fetchItems = async () => {
    loading.value = true;

    router.get(
        route("feedback-priorities.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value,
            sort: options.value.sortBy?.[0]?.key ?? "sort_order",
            order: options.value.sortBy?.[0]?.order ?? "asc",
        },
        {
            preserveState: true,
            replace: true,
            onSuccess: (page) => {
                const priorities = page.props.priorities as any;
                items.value = priorities?.data ?? [];
                total.value = priorities?.total ?? 0;
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
    form.sort_order = 0;
    form.is_active = true;
    showModal.value = true;
};

const edit = (data: any) => {
    form.clearErrors();
    form.id = data.id;
    form.name = data.name;
    form.code = data.code;
    form.sort_order = data.sort_order;
    form.is_active = Boolean(data.is_active);
    showModal.value = true;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid }: any) => {
        if (!valid) {
            return;
        }

        if (form.id) {
            form.put(route("feedback-priorities.update", form.id), {
                preserveScroll: true,
                onSuccess: () => {
                    customToastSwal({ title: page.props.flash.success || "", icon: "success" });
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

        form.post(route("feedback-priorities.store"), {
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({ title: page.props.flash.success || "", icon: "success" });
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
    customConfirmSwal({ title: "Esta segur@ que desea eliminar este registro?" }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("feedback-priorities.destroy", data.id), {
                preserveScroll: true,
                onSuccess: () => {
                    customToastSwal({ title: page.props.flash.success || "", icon: "success" });
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
    <Head title="Prioridades de feedback" />

    <AppLayout>
        <template #header> Prioridades de feedback </template>

        <template #options>
            <BaseButton
                v-if="can.includes('feedback-priorities.store')"
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
                                label="Buscar prioridades"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.is_active="{ item }">
                            <v-chip :color="item.is_active ? 'success' : 'error'" size="small" variant="tonal">
                                {{ item.is_active ? "Activo" : "Inactivo" }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('feedback-priorities.update')"
                                action="edit"
                                @click="edit(item)"
                            />

                            <BaseButton
                                v-if="can.includes('feedback-priorities.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="760" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card prepend-icon="mdi-flag-checkered" :title="form.id ? 'Editar prioridad' : 'Crear prioridad'">
                    <v-card-text class="h-full overflow-y-auto">
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre"
                                    :rules="[required, alphaNumeric, maxLength(30)]"
                                    :error-messages="form.errors.name"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.code"
                                    label="Codigo"
                                    :rules="[required, codeRule, maxLength(10)]"
                                    :error-messages="form.errors.code"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model.number="form.sort_order"
                                    label="Orden"
                                    type="number"
                                    min="0"
                                    :rules="[required, integerRule]"
                                    :error-messages="form.errors.sort_order"
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
                        <BaseButton :icon-only="false" variant="tonal" action="cancel" @click="close" />
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
