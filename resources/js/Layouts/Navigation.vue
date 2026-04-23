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

```html
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
        <!-- Perfil -->
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
                        style="font-weight: 700; color: #0A2540;"
                    >
                        {{ userInitials }}
                    </v-avatar>
                </template>
            </v-list-item>
        </v-list>

        <!-- Selector de club -->
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
        </div>

        <v-divider style="border-color: rgba(255,255,255,0.08);" />

        <!-- Navegación -->
        <div class="nav-scroll-area">
            <v-list
                open-strategy="multiple"
                :opened="opened"
                density="comfortable"
                nav
                class="px-2 pt-2 pb-1"
                bg-color="transparent"
                @update:opened="val => opened = val"
            >
                <template v-for="ruta in routes" :key="ruta.value">

                    <!-- Item simple -->
                    <v-tooltip
                        v-if="ruta.group == null && existSomeRoute(ruta.name)"
                        :text="ruta.title"
                        location="end"
                        :disabled="!isRail"
                    >
                        <template #activator="{ props: tipProps }">
                            <Link :href="route(ruta.name)" preserve-scroll v-bind="tipProps">
                                <v-list-item
                                    v-bind="tipProps"
                                    rounded="lg"
                                    variant="text"
                                    color="#FEFEFE"
                                    :active="isActive(ruta.name)"
                                    class="nav-item mb-1"
                                    :class="isActive(ruta.name) ? 'nav-item--active' : 'nav-item--inactive'"
                                >
                                    <template #prepend>
                                        <v-icon :icon="ruta.icon" />
                                    </template>

                                    <v-list-item-title class="d-flex justify-space-between w-100">
                                        <span>{{ ruta.title }}</span>
                                        <v-badge
                                            v-if="shouldShowBadge(ruta)"
                                            :content="pendingAds > 9 ? '9+' : pendingAds"
                                            color="#D4172A"
                                            inline
                                        />
                                    </v-list-item-title>
                                </v-list-item>
                            </Link>
                        </template>
                    </v-tooltip>

                    <!-- Item con grupo -->
                    <v-list-group
                        v-else-if="ruta.group && existSomeRoute(ruta.name)"
                        :value="ruta.group"
                        fluid
                    >
                        <template #activator="{ props }">
                            <v-tooltip :text="ruta.title" location="end" :disabled="!isRail">
                                <template #activator="{ props: tipProps }">
                                    <v-list-item
                                        v-bind="{ ...props, ...tipProps }"
                                        :title="ruta.title"
                                        :prepend-icon="ruta.icon"
                                        class="nav-item mb-1"
                                    >
                                        <template #append>
                                            <v-badge
                                                v-if="shouldShowBadge(ruta)"
                                                class="badge-pulse"
                                                :content="pendingAds > 9 ? '9+' : pendingAds"
                                                color="#D4172A"
                                                >
                                            </v-badge>
                                        </template>
                                    </v-list-item>
                                </template>
                            </v-tooltip>
                        </template>

                        <Link
                            v-for="sub in ruta.groupItems"
                            :key="sub.value"
                            :href="route(sub.name)"
                            preserve-scroll
                        >
                            <v-list-item
                                v-if="can.includes(sub.name)"
                                :title="sub.title"
                                :prepend-icon="sub.icon"
                                class="nav-item ml-3 mb-1"
                                :active="route().current(sub.name)"
                            />
                        </Link>
                    </v-list-group>

                </template>
            </v-list>
        </div>

        <!-- Logout -->
        <div class="nav-logout-area">
            <v-divider style="border-color: rgba(255,255,255,0.08);" />

            <v-list density="comfortable" nav class="px-2 py-1">
                <v-tooltip text="Cerrar Sesión" location="end" :disabled="!isRail">
                    <template #activator="{ props }">
                        <v-list-item
                            v-bind="props"
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
<style>
.badge-pulse .v-badge__badge {
  animation: pulse 1.5s infinite;
}
.v-badge__badge {
  font-size: 10px;
  min-width: 18px;
  height: 18px;
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
