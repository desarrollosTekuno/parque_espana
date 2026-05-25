<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { email, maxLength, required } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";

const can = usePage().props.auth.permissions;
const page = usePage<any>();

interface Props {
    emailConfigs?: any;
    clubs?: { id: number; name: string }[];
}

interface EmailConfigForm {
    id: number | null;
    entity_id: number | null;
    profile_name: string;
    template_name: string;
    host: string;
    port: number | null;
    username: string;
    password: string;
    encryption: string | null;
    from_address: string;
    from_name: string;
    is_active: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    emailConfigs: null,
    clubs: () => [],
});

const currentClubId = computed<number | null>(() => {
    const value = page.props.auth?.currentClub;
    return value ? Number(value) : null;
});

const showModal = ref(false);
const formSendRef = ref();
const items = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref("");
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});
const prefix = "emailConfigs";

const encryptionItems = [
    { title: "Sin cifrado", value: null },
    { title: "TLS", value: "tls" },
    { title: "SSL", value: "ssl" },
];

const headers = [
    { title: "ID", key: "id" },
    { title: "Perfil", key: "profile_name" },
    { title: "Club", key: "club.name", sortable: false },
    { title: "Host", key: "host" },
    { title: "Puerto", key: "port" },
    { title: "Remitente", key: "from_address" },
    { title: "Activo", key: "is_active" },
    { title: "Acciones", key: "actions", sortable: false },
];

const form = useForm<EmailConfigForm>({
    id: null,
    entity_id: null,
    profile_name: "",
    template_name: "email_template",
    host: "",
    port: 587,
    username: "",
    password: "",
    encryption: "tls",
    from_address: "",
    from_name: "",
    is_active: true,
});

const modalTitle = computed(() => (form.id ? "Editar configuracion de correo" : "Nueva configuracion de correo"));

items.value = props.emailConfigs?.data ?? [];
total.value = props.emailConfigs?.total ?? 0;

const fetchItems = async () => {
    loading.value = true;
    const params = {
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
    };

    router.get(route("email-configs.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (inertiaPage) => {
            items.value = inertiaPage.props[prefix]?.data ?? [];
            total.value = inertiaPage.props[prefix]?.total ?? 0;
            loading.value = false;
        },
        onError: () => {
            loading.value = false;
        },
    });
};

watch([options, search], debounce(fetchItems, 400), { deep: true });

const create = () => {
    form.reset();
    form.clearErrors();
    form.entity_id = currentClubId.value;
    form.template_name = "email_template";
    form.port = 587;
    form.encryption = "tls";
    form.is_active = true;
    showModal.value = true;
};

const edit = (item: any) => {
    form.clearErrors();
    form.id = item.id;
    form.entity_id = item.entity_id;
    form.profile_name = item.profile_name;
    form.template_name = item.template_name || "email_template";
    form.host = item.host;
    form.port = item.port;
    form.username = item.username;
    form.password = "";
    form.encryption = item.encryption;
    form.from_address = item.from_address;
    form.from_name = item.from_name;
    form.is_active = !!item.is_active;
    showModal.value = true;
};

const destroy = (item: any) => {
    customConfirmSwal({
        title: "¿Esta segur@ que desea eliminar este registro?",
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        form.delete(route("email-configs.destroy", item.id), {
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
                    title: `Error: ${form.errors.messageError}`,
                    text: `${form.errors.exception ?? ""}`,
                    icon: "error",
                });
            },
        });
    });
};

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }: { valid: boolean }) => {
        if (!isValid) {
            return;
        }

        const request = form.id
            ? form.put(route("email-configs.update", form.id), {
                  preserveScroll: true,
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
                          text: `${form.errors.exception ?? ""}`,
                          icon: "error",
                      });
                  },
              })
            : form.post(route("email-configs.store"), {
                  preserveScroll: true,
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
                          text: `${form.errors.exception ?? ""}`,
                          icon: "error",
                      });
                  },
              });

        return request;
    });
};
</script>

<template>
    <Head title="Configuracion de correo" />

    <AppLayout>
        <template #header>
            <h2 class="text-h5">Configuracion de correo</h2>
        </template>

        <template #options>
            <BaseButton
                v-if="can.includes('email-configs.store')"
                variant="elevated"
                :icon-only="false"
                action="add"
                @click="create"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-data-table-server
                        v-model:options="options"
                        fixed-header
                        hover
                        height="500px"
                        :headers="headers"
                        :items="items"
                        :items-length="total"
                        :loading="loading"
                        class="elevation-1"
                        :items-per-page-options="[10, 25, 50, 100]"
                        items-per-page-text=" Mostrar"
                        no-data-text="No hay registros para mostrar"
                    >
                        <template #top>
                            <v-text-field
                                v-model="search"
                                label="Buscar configuracion"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.is_active="{ item }">
                            <v-chip :color="item.is_active ? 'success' : 'grey'" size="small">
                                {{ item.is_active ? "Si" : "No" }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('email-configs.update')"
                                action="edit"
                                @click="edit(item)"
                            />
                            <BaseButton
                                v-if="can.includes('email-configs.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <v-dialog v-model="showModal" max-width="700" persistent>
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card prepend-icon="mdi-email-cog-outline" :title="modalTitle">
                    <v-card-text class="h-full overflow-y-auto">
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.entity_id"
                                    label="Club"
                                    :items="props.clubs"
                                    item-title="name"
                                    item-value="id"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.profile_name"
                                    label="Nombre de perfil"
                                    :rules="[required, maxLength(120)]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.template_name"
                                    label="Template"
                                    :rules="[required, maxLength(50)]"
                                    disabled
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.encryption"
                                    label="Encriptacion"
                                    :items="encryptionItems"
                                    item-title="title"
                                    item-value="value"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.host"
                                    label="Host SMTP"
                                    :rules="[required, maxLength(150)]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model.number="form.port"
                                    label="Puerto"
                                    type="number"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.username"
                                    label="Usuario SMTP"
                                    :rules="[required, maxLength(150)]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.password"
                                    label="Password SMTP"
                                    type="password"
                                    :rules="form.id ? [maxLength(255)] : [required, maxLength(255)]"
                                    :hint="form.id ? 'Dejar vacio para conservar password actual' : ''"
                                    persistent-hint
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.from_address"
                                    label="Correo remitente"
                                    :rules="[required, email, maxLength(150)]"
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="form.from_name"
                                    label="Nombre remitente"
                                    :rules="[required, maxLength(150)]"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-switch
                                    v-model="form.is_active"
                                    color="success"
                                    label="Configuracion activa"
                                    inset
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-divider />

                    <v-card-actions>
                        <v-spacer />
                        <v-btn text="Cancelar" variant="plain" @click="showModal = false" />
                        <v-btn color="primary" text="Guardar" variant="tonal" type="submit" />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>
