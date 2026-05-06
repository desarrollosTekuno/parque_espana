<!-- Crud de quejas y sugerencias -->
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

interface FeedbackForm {
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

const form = useForm<FeedbackForm>({
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
    { title: "Título", key: "title" },
    { title: "Tipo", key: "type.name" },
    { title: "Categoría", key: "category.name" },
    { title: "Prioridad", key: "priority.name" },
    { title: "Estatus", key: "status.name" },
    { title: "Fecha", key: "ticket_date" },
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

const statusChipColor = (status: any): string => {
    if (status?.color) {
        return status.color;
    }

    const code = String(status?.code ?? "").toUpperCase();

    if (code === "SUBMITTED") return "info";
    if (code === "IN_PROGRESS") return "warning";
    if (code === "RESOLVED") return "success";
    if (code === "CANCELLED") return "grey";
    if (code === "REJECTED") return "error";

    return "primary";
};

const priorityChipColor = (priority: any): string => {
    if (priority?.color) {
        return priority.color;
    }

    const code = String(priority?.code ?? priority?.name ?? "").toUpperCase();

    if (code.includes("ALTA") || code === "HIGH") return "error";
    if (code.includes("MEDIA") || code === "MEDIUM") return "warning";
    if (code.includes("BAJA") || code === "LOW") return "success";

    return "primary";
};

const statusToneClass = (status: any): string => {
    const code = String(status?.code ?? "").toUpperCase();

    if (code === "SUBMITTED") return "fb-badge fb-badge--status fb-badge--info";
    if (code === "IN_PROGRESS") return "fb-badge fb-badge--status fb-badge--warn";
    if (code === "RESOLVED") return "fb-badge fb-badge--status fb-badge--ok";
    if (code === "CANCELLED") return "fb-badge fb-badge--status fb-badge--muted";
    if (code === "REJECTED") return "fb-badge fb-badge--status fb-badge--danger";

    return "fb-badge fb-badge--status fb-badge--default";
};

const priorityToneClass = (priority: any): string => {
    const code = String(priority?.code ?? priority?.name ?? "").toUpperCase();

    if (code.includes("ALTA") || code === "HIGH") return "fb-badge fb-badge--priority fb-badge--danger";
    if (code.includes("MEDIA") || code === "MEDIUM") return "fb-badge fb-badge--priority fb-badge--warn";
    if (code.includes("BAJA") || code === "LOW") return "fb-badge fb-badge--priority fb-badge--ok";

    return "fb-badge fb-badge--priority fb-badge--default";
};

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

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }) => {
        if (!isValid) {
            return;
        }

        const attachments = Array.isArray(form.attachments)
            ? form.attachments.filter(Boolean)
            : [];

        form
            .transform((data) => ({
                ...data,
                attachments,
            }))
            .post(route("feedback.store"), {
                forceFormData: true,
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

const cancelTicket = (data: any) => {
    if (data?.status?.code !== "SUBMITTED") {
        customToastSwal({
            title: "Solo puedes cancelar tickets en estatus ENVIADO",
            icon: "warning",
        });
        return;
    }

    customConfirmSwal({
        title: "Deseas cancelar este ticket?",
        text: "",
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(
                route("feedback.cancel", data.id),
                {},
                {
                    onSuccess: () => {
                        customToastSwal({
                            title: page.props.flash.success || "",
                            icon: "success",
                        });

                        fetchItems();
                    },
                    onError: () => {
                        customToastSwal({
                            title: `Error: ${page.props.errors?.messageError ?? ""}`,
                            icon: "error",
                        });
                    },
                },
            );
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
                                :class="statusToneClass(item.status)"
                                :color="statusChipColor(item.status)"
                                size="small"
                                variant="tonal"
                                prepend-icon="mdi-circle-medium"
                            >
                                {{ item.status.name }}
                            </v-chip>
                            <span v-else>-</span>
                        </template>

                        <template #item.priority.name="{ item }">
                            <v-chip
                                v-if="item.priority"
                                :class="priorityToneClass(item.priority)"
                                :color="priorityChipColor(item.priority)"
                                size="small"
                                variant="tonal"
                                prepend-icon="mdi-flag-variant"
                            >
                                {{ item.priority.name }}
                            </v-chip>
                            <span v-else>-</span>
                        </template>

                        <template #item.ticket_date="{ item }">
                            {{ item.ticket_date ?? "-" }}
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('feedback.update') && item.status?.code === 'SUBMITTED'"
                                action="cancel"
                                tooltip="Cancelar ticket"
                                @click="cancelTicket(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="800" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card prepend-icon="mdi-message-alert-outline" title="Crear queja o sugerencia">
                    <v-card-text class="h-full overflow-y-auto">
                        <v-row>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.title"
                                    label="Título"
                                    :rules="[required, maxLength(200)]"
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

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="form.ticket_type_id"
                                    :items="props.ticketTypes"
                                    item-title="name"
                                    item-value="id"
                                    label="Tipo"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="form.category_id"
                                    :items="props.categories"
                                    item-title="name"
                                    item-value="id"
                                    label="Categoría"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="form.priority_id"
                                    :items="props.priorities"
                                    item-title="name"
                                    item-value="id"
                                    label="Prioridad"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="8">
                                <v-file-input
                                    v-model="form.attachments"
                                    name="attachments[]"
                                    label="Adjuntos (puedes subir varios)"
                                    multiple
                                    chips
                                    show-size
                                    counter
                                    clearable
                                    hint="Puedes seleccionar varios archivos a la vez (Ctrl + clic en Windows o Cmd + clic en Mac). También puedes arrastrar y soltar varios archivos aquí."
                                    persistent-hint
                                    prepend-icon="mdi-paperclip"
                                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx"
                                    :rules="[
                                        fileMaxCountRule(5),
                                        fileTypeRule(['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx']),
                                        fileMaxSizeRule(10),
                                    ]"
                                />
                            </v-col>

                            <v-col cols="4">
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
                            text="Enviar"
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
