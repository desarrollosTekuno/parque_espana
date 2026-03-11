<script setup lang="ts">
import Loader from "@/Components/Loader.vue";
import Navigation from "@/Layouts/Navigation.vue";
import { router, usePage } from "@inertiajs/vue3";
import { isLoading } from "@/loading";
import { ref } from "vue";

const page = usePage<any>();

const clubs = page.props.auth?.clubs ?? [];
const selectedClub = ref(page.props.auth?.currentClub ?? null);

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
</script>

<template>
    <v-responsive class="border rounded">
        <v-app>
            <v-app-bar prominent>
                <v-app-bar-nav-icon
                    :icon="
                        $vuetify.display.mobile
                            ? !drawer
                                ? 'mdi-backburger'
                                : 'mdi-menu'
                            : 'mdi-menu'
                    "
                    variant="text"
                    @click.stop="clicStop($vuetify.display.mobile)"
                ></v-app-bar-nav-icon>
                <v-spacer></v-spacer>
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
