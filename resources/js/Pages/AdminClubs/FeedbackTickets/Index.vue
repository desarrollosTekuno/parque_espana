<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import {
    required,
    maxLength,
    fileTypeRule,
    fileMaxSizeRule,
    fileMaxCountRule,
} from "@/constants/validationRules";
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

interface FeedbackTicketForm {
    id: number | null;
    ticket_type_id: number | null;
    category_id: number | null;
    status_id: number | null;
    priority_id: number | null;
    title: string;
    description: string;
    is_anonymous: boolean;
    attachments: File[];
}

const showModal = ref(false);
const formSendRef = ref();

const form = useForm<FeedbackTicketForm>({
    id: null,
    ticket_type_id: null,
    category_id: null,
    status_id: null,
    priority_id: null,
    title: "",
    description: "",
    is_anonymous: false,
    attachments: [],
});

const headers = [
    { title: "Folio", key: "ticket_number" },
    { title: "Titulo", key: "title" },
    { title: "Tipo", key: "type.name" },
    { title: "Categoria", key: "category.name" },
    { title: "Prioridad", key: "priority.name" },
    { title: "Estatus", key: "status.name" },
    { title: "Fecha ticket", key: "ticket_date" },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref(props.tickets?.data ?? []);
const total = ref(props.tickets?.total ?? 0);
const loading = ref(false);
const search = ref("");

const options = ref({
    page: props.tickets?.current_page ?? 1,
    itemsPerPage: props.tickets?.per_page ?? 10,
    sortBy: [{ key: "id", order: "desc" }],
});

const filters = ref({
    ticket_type_id: null as number | null,
    category_id: null as number | null,
    status_id: null as number | null,
    priority_id: null as number | null,
});

const fetchItems = async () => {
    loading.value = true;

    router.get(
        route("feedback-tickets.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value,
            ticket_type_id: filters.value.ticket_type_id,
            category_id: filters.value.category_id,
            status_id: filters.value.status_id,
            priority_id: filters.value.priority_id,
        },
        {
            preserveState: true,
            replace: true,
            onSuccess: (page) => {
                const tickets = page.props.tickets as any;
                items.value = tickets?.data ?? [];
                total.value = tickets?.total ?? 0;
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
    showModal.value = true;
};

const edit = (data: any) => {
    form.id = data.id;
    form.ticket_type_id = data.ticket_type_id;
    form.category_id = data.category_id;
    form.status_id = data.status_id;
    form.priority_id = data.priority_id;
    form.title = data.title;
    form.description = data.description;
    form.is_anonymous = Boolean(data.is_anonymous);
    form.attachments = [];

    showModal.value = true;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }) => {
        if (!isValid) {
            return;
        }

        if (form.id) {
            form.put(route("feedback-tickets.update", form.id), {
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
        } else {
            form.post(route("feedback-tickets.store"), {
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
        }
    });
};

const destroy = (data: any) => {
    customConfirmSwal({ title: "Esta segur@ que desea eliminar este registro?" }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("feedback-tickets.destroy", data.id), {
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

watch([options, search, filters], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Quejas y sugerencias" />

    <AppLayout>
        <template #header> Quejas y sugerencias </template>

        <template #options>
            <BaseButton
                v-if="can.includes('feedback-tickets.store')"
                variant="elevated"
                :icon-only="false"
                @click="create"
                action="add"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row class="px-4 pt-4">
                <v-col cols="12" md="3">
                    <v-select v-model="filters.ticket_type_id" :items="props.ticketTypes" item-title="name" item-value="id" label="Tipo" clearable />
                </v-col>
                <v-col cols="12" md="3">
                    <v-select v-model="filters.category_id" :items="props.categories" item-title="name" item-value="id" label="Categoria" clearable />
                </v-col>
                <v-col cols="12" md="3">
                    <v-select v-model="filters.priority_id" :items="props.priorities" item-title="name" item-value="id" label="Prioridad" clearable />
                </v-col>
                <v-col cols="12" md="3">
                    <v-select v-model="filters.status_id" :items="props.statuses" item-title="name" item-value="id" label="Estatus" clearable />
                </v-col>
            </v-row>

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
                            <v-text-field v-model="search" label="Buscar tickets" class="mx-4 mt-2" clearable />
                        </template>

                        <template #item.status.name="{ item }">
                            <v-chip v-if="item.status" :color="item.status.color" size="small" variant="flat">
                                {{ item.status.name }}
                            </v-chip>
                            <span v-else>-</span>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton v-if="can.includes('feedback-tickets.update')" action="edit" @click="edit(item)" />
                            <BaseButton v-if="can.includes('feedback-tickets.destroy')" action="delete" @click="destroy(item)" />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="750" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card prepend-icon="mdi-message-alert-outline" :title="form.id ? 'Editar ticket' : 'Crear ticket'">
                    <v-card-text class="h-full overflow-y-auto">
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-select v-model="form.category_id" :items="props.categories" item-title="name" item-value="id" label="Categoria" :rules="[required]" />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select v-model="form.ticket_type_id" :items="props.ticketTypes" item-title="name" item-value="id" label="Tipo" :rules="[required]" />
                            </v-col>

                            <v-col cols="12">
                                <v-text-field v-model="form.title" label="Titulo" :rules="[required, maxLength(200)]" />
                            </v-col>

                            <v-col cols="12">
                                <v-textarea v-model="form.description" label="Descripcion" rows="4" :rules="[required]" />
                            </v-col>

                            <v-col cols="12" md="12">
                                <v-select v-model="form.priority_id" :items="props.priorities" item-title="name" item-value="id" label="Prioridad" :rules="[required]" />
                            </v-col>

                            <v-col cols="12" md="8">
                                <v-file-input
                                    v-model="form.attachments"
                                    label="Adjuntos"
                                    multiple
                                    chips
                                    show-size
                                    prepend-icon="mdi-paperclip"
                                    :rules="[
                                        fileMaxCountRule(5),
                                        fileTypeRule(['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx']),
                                        fileMaxSizeRule(10),
                                    ]"
                                />
                            </v-col>

                            <v-col cols="4">
                                <v-switch v-model="form.is_anonymous" label="Registro anonimo" color="primary" hide-details />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        <BaseButton :icon-only="false" variant="tonal" action="cancel" @click="close" />
                        <BaseButton :text="form.id ? 'Actualizar' : 'Guardar'" variant="flat" :icon-only="false" type="submit" action="save" />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>
