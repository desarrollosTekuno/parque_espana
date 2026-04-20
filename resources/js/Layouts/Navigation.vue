<script setup lang="ts">
import routes from '@/routing';
import { Link, usePage } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import { router } from "@inertiajs/vue3";
import { useDisplay } from 'vuetify';

const can = usePage().props.auth.permissions;
const auth = usePage().props.auth;
const page = usePage<any>();
const pendingAds = computed(() => page.props.pendingBusinessAds ?? 0);
const clubs = page.props.auth?.clubs ?? [];
const selectedClub = ref(page.props.auth?.currentClub ?? null);

const changeClub = () => {
    router.post(route("change.club"), {
        club_id: selectedClub.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            router.get(route(route().current(), route().params), {}, {
                preserveScroll: true,
                replace: true
            });
        }
    });
};

const drawer = defineModel('drawer');
const props = defineProps<{ rail: boolean }>();
const opened = ref<string[]>();
const display = useDisplay();

// true cuando el drawer está en modo colapsado (solo íconos)
const isRail = computed(() =>
    display.mobile.value ? !props.rail : props.rail
);

const existSomeRoute = (routeNames: any): boolean => {
    if (routeNames instanceof Array) {
        return routeNames.some((routeName) => can.includes(routeName));
    }
    return can.includes(routeNames);
};

const isActive = (name: string | string[]): boolean =>
    name instanceof Array ? route().current(name[0]) : route().current(name);

const userInitials = computed(() => {
    const parts = (auth.user.name ?? '').trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return parts[0]?.[0]?.toUpperCase() ?? '?';
});

onMounted(() => {
    opened.value = routes
        .filter((ruta) => ruta.groupItems?.find((groupItem) => route().current(groupItem.name)))
        ?.map((ruta) => ruta.group) ?? [];
});
const shouldShowBadge = (ruta:any) => {
    if (!ruta.showBadge || pendingAds.value <= 0) return false;
    if (ruta.groupItems) {
        return ruta.groupItems.some((sub:any) =>
            sub.name === 'business-ads.index'
        );
    }
    if (Array.isArray(ruta.name)) {
        return ruta.name.includes('business-ads.index');
    }
    return ruta.name === 'business-ads.index';
};
</script>

<template>
    <v-navigation-drawer
        v-model="drawer"
        :location="$vuetify.display.mobile ? 'left' : undefined"
        :permanent="rail"
        :rail="$vuetify.display.mobile ? !props.rail : props.rail"
        theme="myDarkTheme"
        class="font-poppins"
        style="background-color: #0A2540;"
    >
        <!-- ── Banda de perfil con acento rojo ── -->
        <v-list bg-color="transparent" class="pa-0">
            <v-list-item
                class="py-4 px-4"
                style="background-color: #0D2E52; border-left: 4px solid #D4172A;"
                :subtitle="auth.user.email"
                :title="auth.user.name"
            >
                <template #prepend>
                    <v-avatar
                        color="#F4B403"
                        size="40"
                        class="mr-3"
                        style="font-weight: 700; color: #0A2540; font-size: 1rem;"
                    >
                        {{ userInitials }}
                    </v-avatar>
                </template>
            </v-list-item>
        </v-list>

        <!-- ── Selector de club ── -->
        <div v-if="clubs?.length" class="px-3 pt-3 pb-1">
            <v-select
                v-model="selectedClub"
                :items="clubs"
                item-title="name"
                item-value="id"
                label="Club"
                density="compact"
                variant="outlined"
                color="#F4B403"
                base-color="rgba(255,255,255,0.6)"
                @update:modelValue="changeClub"
            />
<<<<<<< HEAD
        <v-list class="bg-[url(/assets/images/Logo.png)]" open-strategy="multiple" :opened="opened" @update:opened="newOpened => {
            opened = newOpened;
        }" density="comfortable" nav>
            <div v-for="ruta in routes" :key="ruta.value">
                <!-- {{ existSomeRoute(ruta.name) }} -->
                <div v-if="ruta.group == null">
                    <Link v-if="existSomeRoute(ruta.name)" :href="route(ruta.name)" preserve-scroll>
                    <!--<v-list-item elevation="0" variant="elevated"  rounded="pill"
                         :title="ruta.title"
                        active-color="customSecondary"
                        base-color="customPrimary"
                        :active="ruta.name instanceof Array ? route().current(ruta.name[0]) : route().current(ruta.name)">
                        <template #prepend>
                            <v-icon :icon="ruta.icon"></v-icon>
                        </template>
                    </v-list-item>-->
                    <v-list-item elevation="0" variant="elevated" rounded="pill"
                        active-color="customSecondary"
                        base-color="customPrimary"
                        :active="Array.isArray(ruta.name) ? ruta.name.some(n => route().current(n)) : route().current(ruta.name)">
                        <template #prepend>
                            <v-icon :icon="ruta.icon"></v-icon>
                        </template>
                        <v-list-item-title class="d-flex align-center justify-space-between w-100">
                            <span>{{ ruta.title }}</span>
                            <v-badge
                                v-if="shouldShowBadge(ruta)"
                                :content="pendingAds > 9 ? '9+' : pendingAds"
                                color="red"
                                inline
                            />
                        </v-list-item-title>
                    </v-list-item>
                    </Link>
                </div>
                <div v-else>
                    <!-- <v-list-group v-if="can.includes()" fluid :value="ruta.group"> -->
                    <!-- <v-list-group v-if="can.includes(ruta.name)" :value="ruta.group" fluid> -->
                    <v-list-group v-if="existSomeRoute(ruta.name)" :value="ruta.group" fluid>
                        <!-- {{ existSomeRoute(ruta.name) ? "Existe" : "no" }} -->
                        <template v-slot:activator="{ props }">
                            <v-list-item
                                v-bind="props"
                                :title="ruta.title"
                                :prepend-icon="ruta.icon"
                            >
                                <template v-slot:append>
                                    <v-badge
                                        v-if="shouldShowBadge(ruta)"
                                        :content="pendingAds.value > 9 ? '9+' : pendingAds"
                                        color="red"
                                        class="badge-pulse"
                                    />
                                </template>
                            </v-list-item>
=======
        </div>

        <v-divider style="border-color: rgba(255,255,255,0.08);" />

        <!-- ── Menú de navegación (scrolleable) ── -->
        <div class="nav-scroll-area">
            <v-list
                open-strategy="multiple"
                :opened="opened"
                density="comfortable"
                nav
                class="px-2 pt-2 pb-1"
                bg-color="transparent"
                @update:opened="newOpened => { opened = newOpened; }"
            >
                <template v-for="ruta in routes" :key="ruta.value">

                    <!-- Ítem simple -->
                    <v-tooltip
                        v-if="ruta.group == null && existSomeRoute(ruta.name)"
                        :text="ruta.title"
                        location="end"
                        :disabled="!isRail"
                    >
                        <template #activator="{ props: tipProps }">
                            <Link :href="route(ruta.name)" preserve-scroll v-bind="tipProps">
                                <v-list-item
                                    rounded="lg"
                                    variant="text"
                                    color="#FEFEFE"
                                    :active="isActive(ruta.name)"
                                    class="nav-item mb-1"
                                    :class="isActive(ruta.name) ? 'nav-item--active' : 'nav-item--inactive'"
                                >
                                    <template #prepend>
                                        <v-icon
                                            :icon="ruta.icon"
                                            :color="isActive(ruta.name) ? '#0A2540' : '#FEFEFE'"
                                        />
                                    </template>
                                    <v-list-item-title class="d-flex align-center justify-space-between w-100">
                                        <span :style="isActive(ruta.name) ? 'color: #0A2540; font-weight: 700; letter-spacing: 0.01em;' : 'color: #FEFEFE;'">
                                            {{ ruta.title }}
                                        </span>
                                        <v-badge
                                            v-if="ruta.showBadge && pendingAds > 0"
                                            :content="pendingAds > 9 ? '9+' : pendingAds"
                                            color="#D4172A"
                                            inline
                                        />
                                    </v-list-item-title>
                                </v-list-item>
                            </Link>
>>>>>>> cd59c9eb4dde067e7c6be5ed804a2c7895eea34b
                        </template>
                    </v-tooltip>

                    <!-- Ítem con submenú -->
                    <v-list-group
                        v-else-if="ruta.group != null && existSomeRoute(ruta.name)"
                        :value="ruta.group"
                        fluid
                    >
                        <template #activator="{ props: activatorProps }">
                            <v-tooltip :text="ruta.title" location="end" :disabled="!isRail">
                                <template #activator="{ props: tipProps }">
                                    <v-list-item
                                        v-bind="{ ...activatorProps, ...tipProps }"
                                        variant="text"
                                        rounded="lg"
                                        color="#FEFEFE"
                                        :title="ruta.title"
                                        :prepend-icon="ruta.icon"
                                        class="nav-item nav-item--inactive mb-1"
                                    />
                                </template>
                            </v-tooltip>
                        </template>

                        <Link
                            v-for="groupItem in ruta.groupItems"
                            :key="groupItem.value"
                            :href="route(groupItem.name)"
                            preserve-scroll
                        >
                            <v-list-item
                                v-if="can.includes(groupItem.name)"
                                variant="text"
                                rounded="lg"
                                :color="route().current(groupItem.name) ? '#0A2540' : '#FEFEFE'"
                                class="nav-item ml-3 mb-1"
                                :class="route().current(groupItem.name) ? 'nav-item--active-sub' : 'nav-item--inactive'"
                                :active="route().current(groupItem.name)"
                                :prepend-icon="groupItem.icon"
                                :title="groupItem.title"
                            />
                        </Link>
                    </v-list-group>

                </template>
            </v-list>
        </div>

        <!-- ── Cerrar sesión (fijo al fondo) ── -->
        <div class="nav-logout-area">
            <v-divider style="border-color: rgba(255,255,255,0.08);" />
            <v-list density="comfortable" nav class="px-2 py-1" bg-color="transparent">
                <v-tooltip text="Cerrar Sesión" location="end" :disabled="!isRail">
                    <template #activator="{ props: tipProps }">
                        <v-list-item
                            v-bind="tipProps"
                            variant="text"
                            rounded="lg"
                            color="#D4172A"
                            class="nav-item nav-item--logout"
                            prepend-icon="mdi-logout"
                            title="Cerrar Sesión"
                            @click="router.post(route('logout'))"
                        />
                    </template>
                </v-tooltip>
            </v-list>
        </div>
    </v-navigation-drawer>
</template>
<<<<<<< HEAD
<style>
.badge-pulse .v-badge__badge {
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0% {
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7);
  }
  70% {
    transform: scale(1.2);
    box-shadow: 0 0 0 10px rgba(244, 67, 54, 0);
  }
  100% {
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(244, 67, 54, 0);
  }
}
</style>
=======

<style scoped>
.nav-item--inactive {
    opacity: 0.6;
}

/* Activo principal (sin submenú): fondo dorado, texto navy bold */
.nav-item--active {
    background-color: #F4B403 !important;
    opacity: 1;
}

/* Activo submenú: mismo fondo dorado, texto navy bold */
.nav-item--active-sub {
    background-color: #F4B403 !important;
    opacity: 1;
    font-weight: 700;
}

.nav-item--logout {
    opacity: 0.75;
}

.nav-item:hover {
    opacity: 1 !important;
    background-color: rgba(255, 255, 255, 0.08) !important;
    transition: background-color 0.18s ease, opacity 0.18s ease;
}

.nav-item--active:hover {
    background-color: #e0a500 !important;
}

.nav-item--logout:hover {
    opacity: 1 !important;
    background-color: rgba(212, 23, 42, 0.22) !important;
}
</style>
>>>>>>> cd59c9eb4dde067e7c6be5ed804a2c7895eea34b
