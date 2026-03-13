<script setup lang="ts">
import routes from '@/routing';
import { Link, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { router } from "@inertiajs/vue3";
const can = usePage().props.auth.permissions;
const auth = usePage().props.auth;

const page = usePage<any>();

const clubs = page.props.auth?.clubs ?? [];

const selectedClub = ref(page.props.auth?.currentClub ?? null);

const changeClub = () => {
    router.post(route("change.club"), {
        club_id: selectedClub.value
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            router.reload();
        }
    });
};

const drawer = defineModel('drawer');
const props = defineProps<{
    rail: boolean
}>();
const opened = ref<string[]>();

const existSomeRoute = (routeNames: any): boolean => {
    // return routeNames.some((routeName) => can.includes(routeName));
    if (routeNames instanceof Array) {
        return routeNames.some((routeName) => can.includes(routeName));
    } else {
        return can.includes(routeNames);
    }
};
onMounted(() => {
    // if can includes routeNames

    opened.value = routes.filter((ruta) => ruta.groupItems?.find((groupItem) => route().current(groupItem.name)))?.map((ruta) => ruta.group) ?? [];
    // Va a buscar la ruta activa para activar en el submenu la ruta activa
    // opened.value = routes.find((ruta) => ruta.groupItems?.find((groupItem) => route().current(groupItem.name)))?.group ?? '';
});
</script>
<template>
    <!-- <v-navigation-drawer :v-model="drawer" :location="$vuetify.display.mobile ? 'left' : undefined" :permanent="rail"
        :rail="!props.rail" theme="dark"> -->
    <v-navigation-drawer :v-model="drawer" :location="$vuetify.display.mobile ? 'left' : undefined" :permanent="rail"
        :rail="$vuetify.display.mobile ? !props.rail : props.rail" theme="myDarkTheme" class="font-poppins bg-customPrimary">
        <!-- <v-list-item prepend-avatar="https://randomuser.me/api/portraits/men/85.jpg" title="John Leider" nav>

        </v-list-item> -->
        <v-list>
            <!-- <v-list-item
            prepend-avatar="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRBwLejVDAf9IY2KE4F_YNld_QLNP0TuEw6CkzWkaPWbi-OWwmyJJfBvwgvVkEYG2WEGGU&usqp=CAU"
            :subtitle="auth.user.email"
            :title="auth.user.name"
          ></v-list-item> -->
            <!-- <v-list-item :prepend-avatar="/assets/images/Logo-icon.png" :subtitle="auth.user.email" -->
            <v-list-item class="py-2 bg-customThird" prepend-avatar="https://randomuser.me/api/portraits/men/85.jpg"  :subtitle="auth.user.email"
                :title="auth.user.name"></v-list-item>
        </v-list>
        <!-- {{ can }} -->
        <v-divider></v-divider>
            <v-select
                v-if="clubs?.length"
                v-model="selectedClub"
                :items="clubs"
                item-title="name"
                item-value="id"
                label="Club"
                density="compact"
                @update:modelValue="changeClub"
            />
        <v-list class="bg-[url(/assets/images/Logo.png)]" open-strategy="multiple" :opened="opened" @update:opened="newOpened => {
            opened = newOpened;
        }" density="comfortable" nav>
            <div v-for="ruta in routes" :key="ruta.value">
                <!-- {{ existSomeRoute(ruta.name) }} -->
                <div v-if="ruta.group == null">
                    <Link v-if="existSomeRoute(ruta.name)" :href="route(ruta.name)" preserve-scroll>
                    <v-list-item elevation="0" variant="elevated"  rounded="pill"
                         :title="ruta.title"
                        active-color="customSecondary"
                        base-color="customPrimary"
                        :active="ruta.name instanceof Array ? route().current(ruta.name[0]) : route().current(ruta.name)">
                        <template #prepend>
                            <v-icon :icon="ruta.icon"></v-icon>
                        </template>
                        <!-- :active="route().current(ruta.name)" :prepend-icon="ruta.icon" :title="ruta.title"> -->
                        <!-- is open -->
                    </v-list-item>
                    </Link>
                </div>
                <div v-else>
                    <!-- <v-list-group v-if="can.includes()" fluid :value="ruta.group"> -->
                    <!-- <v-list-group v-if="can.includes(ruta.name)" :value="ruta.group" fluid> -->
                    <v-list-group v-if="existSomeRoute(ruta.name)" :value="ruta.group" fluid>
                        <!-- {{ existSomeRoute(ruta.name) ? "Existe" : "no" }} -->
                        <template v-slot:activator="{ props, isOpen }">
                            <v-list-item color="customSecondary" v-bind="props" :title="ruta.title" :prepend-icon="ruta.icon"
                                </v-list-item>
                        </template>
                        <Link v-for="groupItem in ruta.groupItems" :key="groupItem.value" :href="route(groupItem.name)" preserve-scroll>
                        <v-list-item elevation="0 ml-2" v-if="can.includes(groupItem.name)" variant="elevated" color="customSecondary"
                            rounded="pill" :active="route().current(groupItem.name)" :prepend-icon="groupItem.icon"
                            :title="groupItem.title" active-color="customSecondary" base-color="customPrimary">
                            <!-- -->
                        </v-list-item>
                        </Link>
                    </v-list-group>
                </div>
            </div>
            <Link :href="route('logout')" method="post" as="button" class="flex items-start justify-start w-full bg-customPrimary">
            <v-list-item elevation="0" variant="elevated" base-color="customPrimary" rounded="pill"
                :active="route().current('logout')" prepend-icon="mdi-logout" title="Cerrar Sesión">
                <!-- -->
            </v-list-item>
            </Link>
        </v-list>
        <!-- {{ can }} -->
    </v-navigation-drawer>
</template>
