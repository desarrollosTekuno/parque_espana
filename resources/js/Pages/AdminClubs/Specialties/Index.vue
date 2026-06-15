<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { required, maxLength, alphaNumeric } from "@/constants/validationRules";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";

const page = usePage<any>();
const can = page.props.auth.permissions;

interface Props {
    specialties?: any;
    messageError?: string;
}

interface SpecialtyForm {
    id: number | null;
    name: string;
    code: string;
}

const props = withDefaults(defineProps<Props>(), {
    specialties: null,
    messageError: "",
});

const showModal = ref(false);
const formSendRef = ref();
const loading = ref(false);
const search = ref("");
const items = ref<any[]>(props.specialties?.data ?? []);
const total = ref(props.specialties?.total ?? 0);
const options = ref({
    page: props.specialties?.current_page ?? 1,
    itemsPerPage: props.specialties?.per_page ?? 10,
    sortBy: [{ key: "name", order: "asc" }],
});

const form = useForm<SpecialtyForm>({
    id: null,
    name: "",
    code: "",
});

const headers = [
    { title: "ID", key: "id" },
    { title: "Nombre", key: "name" },
    { title: "Codigo", key: "code" },
    { title: "Acciones", key: "actions", sortable: false },
];

const fetchItems = () => {
    loading.value = true;

    router.get(
        route("specialties.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value,
            sort: options.value.sortBy?.[0]?.key ?? "name",
            order: options.value.sortBy?.[0]?.order ?? "asc",
        },
        {
            preserveState: true,
            replace: true,
            onSuccess: (res) => {
                const specialties = res.props.specialties as any;
                items.value = specialties?.data ?? [];
                total.value = specialties?.total ?? 0;
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

const edit = (item: any) => {
    form.clearErrors();
    form.id = item.id;
    form.name = item.name;
    form.code = item.code;
    showModal.value = true;
};

const close = () => {
    form.reset();
    form.clearErrors();
    showModal.value = false;
};

const save = async () => {
    const { valid } = await formSendRef.value?.validate();
    if (!valid) return;

    if (form.id) {
        form.put(route("specialties.update", form.id), {
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

    form.post(route("specialties.store"), {
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
};

const destroy = (item: any) => {
    customConfirmSwal({
        title: "Eliminar especialidad?",
    }).then((result) => {
        if (!result.isConfirmed) return;

        form.delete(route("specialties.destroy", item.id), {
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
    });
};

watch([options, search], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Especialidades" />

    <AppLayout>
        <template #header>Especialidades</template>

        <template #options>
            <BaseButton
                v-if="can.includes('specialties.store')"
                variant="elevated"
                :icon-only="false"
                action="add"
                @click="create"
            />
        </template>

        <v-alert
            v-if="props.messageError"
            type="error"
            variant="tonal"
            class="mb-4"
        >
            {{ props.messageError }}
        </v-alert>

        <v-data-table-server
            v-model:options="options"
            :headers="headers"
            :items="items"
            :items-length="total"
            :loading="loading"
            loading-text="Cargando especialidades..."
            no-data-text="No hay especialidades registradas"
            class="elevation-1"
        >
            <template #top>
                <v-text-field
                    v-model="search"
                    label="Buscar especialidad"
                    prepend-inner-icon="mdi-magnify"
                    clearable
                    hide-details
                    class="ma-2"
                />
            </template>

            <template #item.actions="{ item }">
                <BaseButton
                    v-if="can.includes('specialties.update')"
                    action="edit"
                    @click="edit(item)"
                />
                <BaseButton
                    v-if="can.includes('specialties.destroy')"
                    action="delete"
                    @click="destroy(item)"
                />
            </template>
        </v-data-table-server>

        <v-dialog v-model="showModal" max-width="700" persistent>
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card :title="form.id ? 'Editar especialidad' : 'Nueva especialidad'">
                    <v-card-text>
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre"
                                    :rules="[required, maxLength(120)]"
                                    :error-messages="form.errors.name"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.code"
                                    label="Codigo"
                                    :rules="[required, alphaNumeric, maxLength(60)]"
                                    :error-messages="form.errors.code"
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
