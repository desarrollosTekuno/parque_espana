<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { required } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";

const can = usePage().props.auth.permissions;
const page = usePage<any>();

interface Props {
    tickets?: any;
    categories?: any[];
    ticketTypes?: any[];
    statuses?: any[];
    priorities?: any[];
}

const props = withDefaults(defineProps<Props>(), {
    tickets: null,
    categories: () => [],
    ticketTypes: () => [],
    statuses: () => [],
    priorities: () => [],
});

interface FeedbackForm {
    id: number | null;
    ticket_type_id: number | null;
    category_id: number | null;
    status_id: number | null;
    priority_id: number | null;
    member_id: number | null;
    assigned_to_user_id: number | null;
    title: string;
    description: string;
    resolution_notes: string;
    is_anonymous: boolean;
}

const showModal = ref(false);
const formSendRef = ref();

const form = useForm<FeedbackForm>({
    id: null,
    ticket_type_id: null,
    category_id: null,
    status_id: null,
    priority_id: null,
    member_id: null,
    assigned_to_user_id: null,
    title: "",
    description: "",
    resolution_notes: "",
    is_anonymous: false,
});

const headers = [
    { title: "Folio", key: "ticket_number" },
    { title: "Título", key: "title" },
    { title: "Tipo", key: "type.name" },
    { title: "Categoría", key: "category.name" },
    { title: "Prioridad", key: "priority.name" },
    { title: "Estatus", key: "status.name" },
    { title: "Fecha", key: "submitted_at" },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref(props.tickets?.data ?? []);
const total = ref(props.tickets?.total ?? 0);
const loading = ref(false);
const search = ref("");

const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});

const prefix = "tickets";

const fetchItems = async () => {
    loading.value = true;

    const params = {
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
    };

    router.get(route("feedback.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            const data = page.props[prefix]?.data ?? [];
            const totalCount = page.props[prefix]?.total ?? 0;

            items.value = data;
            total.value = totalCount;
            loading.value = false;
        },
        onError: () => {
            loading.value = false;
        },
    });
};

const create = () => {
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const edit = (data: any) => {
    form.id = data.id;
    form.ticket_type_id = data.ticket_type_id;
    form.category_id = data.category_id;
    form.status_id = data.status_id;
    form.priority_id = data.priority_id;
    form.member_id = data.member_id;
    form.assigned_to_user_id = data.assigned_to_user_id;
    form.title = data.title;
    form.description = data.description;
    form.resolution_notes = data.resolution_notes ?? "";
    form.is_anonymous = Boolean(data.is_anonymous);

    showModal.value = true;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }) => {
        if (!isValid) {
            return;
        }

        if (form.id) {
            form.put(route("feedback.update", form.id), {
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
        } else {
            form.post(route("feedback.store"), {
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
        }
    });
};

const destroy = (data: any) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este registro?",
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("feedback.destroy", data.id), {
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
    <Head title="Quejas y sugerencias" />

    <AppLayout>
        <template #header> Quejas y sugerencias </template>

        <template #options>
            <BaseButton
                v-if="can.includes('feedback.store')"
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
                        items-per-page-text="Mostrar"
                        no-data-text="No hay registros para mostrar"
                    >
                        <template #top>
                            <v-text-field
                                v-model="search"
                                label="Buscar quejas o sugerencias"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.status.name="{ item }">
                            <v-chip
                                v-if="item.status"
                                :color="item.status.color"
                                size="small"
                                variant="flat"
                            >
                                {{ item.status.name }}
                            </v-chip>
                            <span v-else>-</span>
                        </template>

                        <template #item.submitted_at="{ item }">
                            {{ item.submitted_at ?? "-" }}
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('feedback.update')"
                                action="edit"
                                @click="edit(item)"
                            />

                            <BaseButton
                                v-if="can.includes('feedback.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="800" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card prepend-icon="mdi-message-alert-outline" :title="form.id ? 'Editar queja o sugerencia' : 'Crear queja o sugerencia'">
                    <v-card-text class="h-full overflow-y-auto">
                        <v-row>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.title"
                                    label="Título"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.description"
                                    label="Descripción"
                                    rows="4"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.ticket_type_id"
                                    :items="props.ticketTypes"
                                    item-title="name"
                                    item-value="id"
                                    label="Tipo"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.category_id"
                                    :items="props.categories"
                                    item-title="name"
                                    item-value="id"
                                    label="Categoría"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.priority_id"
                                    :items="props.priorities"
                                    item-title="name"
                                    item-value="id"
                                    label="Prioridad"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col v-if="form.id" cols="12" md="6">
                                <v-select
                                    v-model="form.status_id"
                                    :items="props.statuses"
                                    item-title="name"
                                    item-value="id"
                                    label="Estatus"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col v-if="form.id" cols="12">
                                <v-textarea
                                    v-model="form.resolution_notes"
                                    label="Notas de resolución"
                                    rows="3"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-switch
                                    v-model="form.is_anonymous"
                                    label="Registro anónimo"
                                    color="primary"
                                    hide-details
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
