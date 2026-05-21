<script setup lang="ts">
import Loader from "@/Components/Loader.vue";
import Navigation from "@/Layouts/Navigation.vue";
import { requestFirebaseNotificationPermission } from "@/firebase";
import { router, usePage } from "@inertiajs/vue3";
import { customToastSwal } from "@/utils/swal";
import { isLoading } from "@/loading";
import axios from "axios";
import { ref } from "vue";

const page = usePage<any>();

const clubs = page.props.auth?.clubs ?? [];
const selectedClub = ref(page.props.auth?.currentClub ?? null);
const sendingNotification = ref(false);

const changeClub = () => {
    router.post(route("change.club"), {
        club_id: selectedClub.value
    });
};
const drawer = ref(true);
const rail = ref(false);
const clicStop = (displayMobile: boolean) => {
    rail.value = !rail.value;
    if (displayMobile) {
        drawer.value = !drawer.value;
    } else {
        drawer.value = true;
    }
};

const sendPushNotification = async () => {
    const token = await requestFirebaseNotificationPermission();

    if (!token) {
        customToastSwal({
            icon: "warning",
            title: "Notificaciones no disponibles",
            text: "Faltan credenciales Firebase o el permiso fue denegado.",
        });
        return;
    }

    try {
        sendingNotification.value = true;

        await axios.post("/api/v1/firebase/test", {
            token: token,
            title: "Notificacion de prueba",
            body: "La notificacion push funciona correctamente.",
        });

        customToastSwal({
            icon: "success",
            title: "Notificacion enviada",
            text: "Se envio una notificacion push de prueba.",
        });
    } catch (e) {
        console.error(e);

        customToastSwal({
            icon: "error",
            title: "Error",
            text: "No se pudo enviar la notificacion push.",
        });
    } finally {
        sendingNotification.value = false;
    }
};
</script>

<template>
    <v-responsive class="border rounded">
        <v-app>
            <v-app-bar
                prominent
                elevation="0"
                style="background-color: #0A2540; border-bottom: 3px solid #F4B403;"
            >
                <v-app-bar-nav-icon
                    :icon="
                        $vuetify.display.mobile
                            ? !drawer
                                ? 'mdi-backburger'
                                : 'mdi-menu'
                            : 'mdi-menu'
                    "
                    variant="text"
                    color="#FEFEFE"
                    @click.stop="clicStop($vuetify.display.mobile)"
                ></v-app-bar-nav-icon>
                <v-spacer></v-spacer>
                <v-btn
                    class="mr-2"
                    color="#F4B403"
                    variant="flat"
                    prepend-icon="mdi-bell"
                    :loading="sendingNotification"
                    :disabled="sendingNotification"
                    @click="sendPushNotification"
                >
                    Push
                </v-btn>
                <!-- <v-btn icon="mdi-dots-vertical" variant="text"></v-btn> -->
                <!-- <v-btn icon="mdi-dots-vertical" id="menu-activator" color="primary">
                </v-btn> -->

                <!-- <v-menu activator="#menu-activator">
                    <v-list>
                        <v-list-item>
                            <Link :href="route('profile.edit')">
                            <v-list-item-title>Perfil</v-list-item-title>
                            </Link>
                        </v-list-item>
                        <v-list-item>
                            <Link :href="route('logout')" method="post">
                            <v-list-item-title>Cerrar Sesión</v-list-item-title>
                            </Link>
                        </v-list-item>
                    </v-list>
                </v-menu> -->
            </v-app-bar>
            <Navigation :rail="rail" v-model:drawer="drawer" />
            <v-main >
                <!-- <v-container> -->
                <div class="p-6 w-full">
                    <div class="flex justify-between items-center">
                        <h3
                            class="mb-4 text-3xl font-medium text-gray-700 font-poppins"
                        >
                            <slot name="header" />
                        </h3>
                        <slot name="options"/>
                    </div>

                    <slot />
                </div>
                <!-- </v-container> -->
            </v-main>
            <Loader :overlay="isLoading" />
        </v-app>
    </v-responsive>
</template>
