<script setup lang="ts">
import { required } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { requestFirebaseNotificationPermission } from "@/firebase";
import { customToastSwal } from "@/utils/swal";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { ref } from "vue";
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;
interface Props {
    categories?: any;
}

interface Category {
    id: number | null;
    name: string;
    description: string;
}

// const props = defineProps<Props>();
const props = withDefaults(defineProps<Props>(), {
    categories: null,
});

/* refs */
let showModal = ref(false);
const formSendRef = ref();
const firebaseToken = ref("");

/* forms */
const form = useForm<Category>({
    id: null,
    name: "",
    description: "",
});

const create = () => {
    showModal.value = true;
};
const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }) => {
        console.log(isValid);
        if (!isValid) {
            return;
        } else {
            if (form.id) {
                // form.put(route('head-quarters.update', form.id), {
                //     onSuccess: () => { showModal.value = false, form.reset() }
                // });
            } else {
                // form.post(route('head-quarters.store'), {
                //     onSuccess: () => { showModal.value = false, form.reset() },
                //     onError: () => { console.log(form.errors) },
                // });
            }
        }
    });
};
const edit = (data: any) => {
    // headQuarterForm.id = data.id;
    // headQuarterForm.name = data.name;
    // headQuarterForm.latitude = data.latitude;
    // headQuarterForm.longitude = data.longitude;
    showModal.value = true;
};

const destroy = (data: any) => {
    // headQuarterForm.delete(route('head-quarters.destroy', data.id), {
    //     onSuccess: () => { },
    // });
};
const close = () => {
    form.reset();
    showModal.value = false;
};

const testNotifications = async () => {
    const token = await requestFirebaseNotificationPermission();

    if (!token) {
        customToastSwal({
            icon: "warning",
            title: "Notificaciones no disponibles",
            text: "Faltan credenciales Firebase o el permiso fue denegado.",
        });
        return;
    }

    firebaseToken.value = token;

    customToastSwal({
        icon: "success",
        title: "Notificaciones listas",
        text: "Token Firebase obtenido correctamente.",
    });
};
// rules validation example
// const rules = {
//     name: [
//         (v) => !!v || 'El nombre es requerido',
//     ],
//     latitude: [
//         (v) => !!v || 'La latitud es requerida',
//     ],
//     longitude: [
//         (v) => !!v || 'La longitud es requerida',
//     ],
// }
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header> Inicio </template>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 border-b border-gray-200">
                <v-btn color="primary" @click="testNotifications">
                    Probar notificaciones
                </v-btn>

                <div v-if="firebaseToken" class="mt-3 text-caption text-medium-emphasis">
                    Token Firebase generado correctamente.
                </div>
            </div>
        </div>
        <v-dialog v-model="showModal" max-width="600" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card
                    prepend-icon="mdi-account"
                    :title="`Form|${form.id ? 'Edit' : 'Create'}`"
                >
                    <v-card-text>
                        <v-text-field
                            v-model="form.name"
                            label="Nombre"
                            persistent-hint
                            :rules="[required]"
                        />
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn text="Cerrar" type="button" @click="close" />
                        <v-btn
                            prepend-icon="mdi-home"
                            :text="form.id ? 'update' : 'save'"
                            type="submit"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
        <!-- <Loader :overlay="form.processing" /> -->
    </AppLayout>
</template>
