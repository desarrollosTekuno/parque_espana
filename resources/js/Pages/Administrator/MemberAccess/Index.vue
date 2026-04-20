<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import PasswordField from "@/Components/PasswordField.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";
import { required, email } from "@/constants/validationRules";

const can = usePage().props.auth.permissions;
const page = usePage<any>();

interface Props {
    members_access?: any;
}

const props = withDefaults(defineProps<Props>(), {
    members_access: null,
});

/* ── Modal ── */
let showModal = ref(false);
const formSendRef = ref();
const selectedMember = ref<any>(null);

/* ── Form ── */
const form = useForm({
    member_id: null as number | null,
    email: "",
    password: "",
    password_confirmation: "",
});

const openGrantAccess = (member: any) => {
    selectedMember.value = member;
    form.member_id = member.id;
    form.email = member.email ?? "";
    showModal.value = true;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }: { valid: boolean }) => {
        if (!isValid) return;

        form.post(route("member-access.store"), {
            onSuccess: () => {
                customToastSwal({
                    title: page.props.flash.success || "Acceso otorgado con éxito",
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
    });
};

const revokeAccess = (member: any) => {
    customConfirmSwal({
        title: `¿Revocar acceso de ${member.full_name}?`,
        text: "Se eliminará el usuario y todos sus tokens activos.",
    }).then((result: any) => {
        if (result.isConfirmed) {
            form.delete(route("member-access.destroy", member.id), {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "Acceso revocado",
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
    selectedMember.value = null;
    showModal.value = false;
};

/* ── Regla de confirmación de contraseña ── */
const passwordMatchRule = (v: string) =>
    v === form.password || "Las contraseñas no coinciden";

/* ── DataTable server-side ── */
const headers = [
    { title: "Nombre",  key: "full_name",  sortable: false },
    { title: "Email",   key: "email" },
    { title: "Clubs",   key: "clubs",      sortable: false },
    { title: "Acceso",  key: "access",     sortable: false },
    { title: "Acciones",key: "actions",    sortable: false },
];

const items   = ref(props.members_access?.data ?? []);
const total   = ref(props.members_access?.total ?? 0);
const loading = ref(false);
const search  = ref("");
const accessFilter = ref("all"); // all | with | without
const prefix  = "members_access";

const options = ref({
    page: 1,
    itemsPerPage: 15,
    sortBy: [{ key: "last_name", order: "asc" }],
});

const fetchItems = async () => {
    loading.value = true;
    const params: Record<string, any> = {
        [`${prefix}_page`]:     options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]:   search.value,
        [`${prefix}_sort`]:     options.value.sortBy?.[0]?.key ?? "last_name",
        [`${prefix}_order`]:    options.value.sortBy?.[0]?.order ?? "asc",
    };
    if (accessFilter.value !== "all") {
        params[`${prefix}_access`] = accessFilter.value;
    }

    router.get(route("member-access.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (p) => {
            items.value = p.props[prefix]?.data ?? [];
            total.value = p.props[prefix]?.total ?? 0;
            loading.value = false;
        },
    });
};

watch([options, search, accessFilter], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Accesos App Móvil" />
    <AppLayout>
        <template #header>Accesos App Móvil</template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row class="pa-4" align="center">
                <!-- Búsqueda -->
                <v-col cols="12" md="6">
                    <v-text-field
                        v-model="search"
                        label="Buscar miembro"
                        prepend-inner-icon="mdi-magnify"
                        clearable
                        hide-details
                        density="compact"
                    />
                </v-col>

                <!-- Filtro de acceso -->
                <v-col cols="12" md="4">
                    <v-btn-toggle
                        v-model="accessFilter"
                        mandatory
                        density="compact"
                        color="primary"
                    >
                        <v-btn value="all">Todos</v-btn>
                        <v-btn value="with">
                            <v-icon start>mdi-check-circle</v-icon>
                            Con acceso
                        </v-btn>
                        <v-btn value="without">
                            <v-icon start>mdi-close-circle</v-icon>
                            Sin acceso
                        </v-btn>
                    </v-btn-toggle>
                </v-col>
            </v-row>

            <v-data-table-server
                fixed-header
                hover
                height="520px"
                :headers="headers"
                :items="items"
                :items-length="total"
                :loading="loading"
                v-model:options="options"
                class="elevation-1"
                :items-per-page-options="[15, 25, 50, 100]"
                items-per-page-text="Mostrar"
                no-data-text="No hay miembros para mostrar"
            >
                <!-- Clubs -->
                <template #item.clubs="{ item }">
                    <v-chip
                        v-for="am in item.account_memberships"
                        :key="am.id"
                        class="ma-1"
                        color="green"
                        size="small"
                    >
                        {{ am.membership_account?.club?.name ?? "—" }}
                    </v-chip>
                </template>

                <!-- Estado de acceso -->
                <template #item.access="{ item }">
                    <v-chip
                        :color="item.user_id ? 'success' : 'default'"
                        size="small"
                    >
                        <v-icon start>
                            {{ item.user_id ? "mdi-cellphone-check" : "mdi-cellphone-off" }}
                        </v-icon>
                        {{ item.user_id ? "Activo" : "Sin acceso" }}
                    </v-chip>
                </template>

                <!-- Acciones -->
                <template #item.actions="{ item }">
                    <!-- Dar acceso -->
                    <BaseButton
                        v-if="!item.user_id && can.includes('members.store')"
                        action="add"
                        tooltip="Dar acceso a la app"
                        @click="openGrantAccess(item)"
                    />
                    <!-- Revocar acceso -->
                    <BaseButton
                        v-if="item.user_id && can.includes('members.destroy')"
                        action="delete"
                        tooltip="Revocar acceso"
                        @click="revokeAccess(item)"
                    />
                </template>
            </v-data-table-server>
        </div>

        <!-- Modal: Dar acceso -->
        <v-dialog v-model="showModal" max-width="500" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card
                    prepend-icon="mdi-cellphone-key"
                    :title="`Dar acceso a ${selectedMember?.full_name ?? ''}`"
                >
                    <v-card-text>
                        <v-row dense>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.email"
                                    label="Correo electrónico"
                                    type="email"
                                    :rules="[required, email]"
                                    :error-messages="form.errors.email"
                                    @input="form.clearErrors('email')"
                                    density="comfortable"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12">
                                <PasswordField
                                    v-model="form.password"
                                    label="Contraseña"
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.password_confirmation"
                                    label="Confirmar contraseña"
                                    type="password"
                                    :rules="[required, passwordMatchRule]"
                                    density="comfortable"
                                    variant="outlined"
                                    clearable
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
                            text="Dar acceso"
                            variant="flat"
                            :icon-only="false"
                            type="submit"
                            action="save"
                            :loading="form.processing"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>
