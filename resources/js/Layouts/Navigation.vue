<script setup lang="ts">
import routes from "@/routing";
import { Link, usePage } from "@inertiajs/vue3";
import { onMounted, onUnmounted, nextTick, ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import { useDisplay } from "vuetify";

const can = usePage().props.auth.permissions;
const auth = usePage().props.auth;
const page = usePage<any>();
const pendingAds = computed(() => page.props.pendingBusinessAds ?? 0);
const pendingAgeTransitions = computed(() => (page.props as any).pendingAgeTransitions ?? 0);
const clubs = page.props.auth?.clubs ?? [];
const selectedClub = ref(page.props.auth?.currentClub ?? null);

const changeClub = () => {
    router.post(
        route("change.club"),
        {
            club_id: selectedClub.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.get(
                    route(route().current(), route().params),
                    {},
                    {
                        preserveScroll: true,
                        replace: true,
                    },
                );
            },
        },
    );
};
// Devuelve el club actualmente seleccionado (o null si no hay)
const currentClub = computed(() => {
    return clubs.find((club: any) => club.id === selectedClub.value);
});

const drawer = defineModel("drawer");
const props = defineProps<{ rail: boolean }>();
const display = useDisplay();
const navScrollRef = ref<HTMLElement | null>(null);

// ── Persistencia del estado del menú en localStorage ──────────────────────
const NAV_STORAGE_KEY = "nav_opened_groups";

const loadOpened = (): string[] => {
    try {
        return JSON.parse(localStorage.getItem(NAV_STORAGE_KEY) ?? "[]");
    } catch {
        return [];
    }
};

const saveOpened = (groups: string[]) => {
    localStorage.setItem(NAV_STORAGE_KEY, JSON.stringify(groups));
};

const opened = ref<string[]>(loadOpened());

// true cuando el drawer está en modo colapsado (solo íconos)
const isRail = computed(() =>
    display.mobile.value ? !props.rail : props.rail,
);

const existSomeRoute = (routeNames: any): boolean => {
    if (routeNames instanceof Array) {
        return routeNames.some((routeName) => can.includes(routeName));
    }
    return can.includes(routeNames);
};

const isActive = (name: string[]): boolean => name.some((n) => route().current(n));

const userInitials = computed(() => {
    const parts = (auth.user.name ?? "").trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return parts[0]?.[0]?.toUpperCase() ?? "?";
});

/** Devuelve los grupos que contienen la ruta activa */
const getActiveGroups = (): string[] =>
    routes
        .filter((ruta) =>
            ruta.groupItems?.find((groupItem) => route().current(groupItem.name)),
        )
        .map((ruta) => ruta.group)
        .filter(Boolean) as string[];

onMounted(() => {
    // Solo abre el grupo activo si NUNCA se ha guardado nada (primera visita).
    // Si el usuario ya interactuó con el menú (aunque haya cerrado todo),
    // respetamos su elección — la clave existe aunque su valor sea [].
    const neverSaved = localStorage.getItem(NAV_STORAGE_KEY) === null;
    if (neverSaved) {
        opened.value = getActiveGroups();
        saveOpened(opened.value);
    }
});

// Después de navegar: restaura exactamente el estado guardado por el usuario
// sin forzar la apertura de ningún grupo — el usuario decide qué queda abierto
const offNavigate = router.on("navigate", () => {
    opened.value = loadOpened();

    nextTick(() => {
        setTimeout(() => {
            const activeItem = navScrollRef.value?.querySelector<HTMLElement>(
                ".nav-item--active, .nav-item--active-sub",
            );
            activeItem?.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }, 150);
    });
});

onUnmounted(() => {
    offNavigate();
});
/**
 * Devuelve el conteo del badge para una ruta o grupo.
 * Agrega aquí nuevos tipos de badge según crezcan las notificaciones.
 */
const getBadgeCount = (ruta: any): number => {
    const names: string[] = Array.isArray(ruta.name) ? ruta.name : [ruta.name ?? ""];
    const subNames: string[] = (ruta.groupItems ?? []).map((g: any) => g.name);
    const allNames = [...names, ...subNames];

    if (allNames.includes("business-ads.index") && pendingAds.value > 0)
        return pendingAds.value;

    const hasAgeBadge = (ruta.groupItems ?? []).some((g: any) => g.showBadge);
    if (hasAgeBadge && pendingAgeTransitions.value > 0)
        return pendingAgeTransitions.value;

    return 0;
};

const getGroupItemBadgeCount = (groupItem: any): number => {
    if (!groupItem.showBadge) return 0;
    if (groupItem.name === "business-ads.index") return pendingAds.value;
    return pendingAgeTransitions.value;
};
// const isInLockersFlow = computed(() => {
//     return route().current("members.lockers.create")
// });
// const isInManagementAccount = computed(() => {
//     return route().current("members.manage.show");
// });
const disabledSelectClub = computed(() => {
    return route().current("members.lockers.create") || route().current("members.manage.show") || route().current("members.additional-membership.create");
});
console.log(JSON.parse(JSON.stringify(page.props.auth.clubs)))
</script>

<template>
    <v-navigation-drawer
        v-model="drawer"
        :location="$vuetify.display.mobile ? 'left' : undefined"
        :permanent="rail"
        :rail="$vuetify.display.mobile ? !props.rail : props.rail"
        theme="myDarkTheme"
        class="font-poppins"
        style="background-color: #0a2540"
    >
        <!-- ── Cabecera fija: perfil + selector de club ── -->
        <div class="nav-header-area">
            <v-list bg-color="transparent" class="pa-0">
                <v-list-item
                    class="py-4 px-4"
                    style="
                        background-color: #0d2e52;
                        border-left: 4px solid #d4172a;
                    "
                    :subtitle="auth.user.email"
                    :title="auth.user.name"
                >
                    <template #prepend>
                        <v-img
                            v-if="clubs?.length"
                            :width="50"
                            aspect-ratio="16/9"
                            cover
                            :src="currentClub.logo_url"
                            class="mr-2"
                        ></v-img>
                        <v-avatar
                            v-else
                            color="#F4B403"
                            size="40"
                            class="mr-3"
                            style="
                                font-weight: 700;
                                color: #0a2540;
                                font-size: 1rem;
                            "
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
                    :disabled="disabledSelectClub"
                    :hint="
                        !isRail && disabledSelectClub
                            ? 'No puedes cambiar de club desde esta sección'
                            : ''
                    "
                    persistent-hint
                />
            </div>

            <v-divider style="border-color: rgba(255, 255, 255, 0.08)" />
        </div>

        <!-- ── Menú de navegación (scrolleable) ── -->
        <div class="nav-scroll-area" ref="navScrollRef">
            <v-list
                open-strategy="multiple"
                :opened="opened"
                density="comfortable"
                nav
                class="px-2 pt-2 pb-1"
                bg-color="transparent"
                @update:opened="
                    (newOpened) => {
                        opened = newOpened;
                        saveOpened(newOpened);
                    }
                "
            >
                <template v-for="ruta in routes" :key="ruta.value">

                    <!-- ── Ítem simple ────────────────────────────────────── -->
                    <template v-if="ruta.group == null && existSomeRoute(ruta.name)">
                        <v-tooltip :text="ruta.title" location="end" :disabled="!isRail">
                            <template #activator="{ props: tipProps }">
                                <Link :href="route(ruta.name[0])" preserve-scroll v-bind="tipProps">
                                    <v-list-item
                                        rounded="lg"
                                        variant="text"
                                        color="#FEFEFE"
                                        :active="isActive(ruta.name)"
                                        class="nav-item mb-1"
                                        :class="isActive(ruta.name) ? 'nav-item--active' : 'nav-item--inactive'"
                                    >
                                        <template #prepend>
                                            <!-- Badge flotante sobre el ícono en modo rail -->
                                            <v-badge
                                                v-if="isRail && getBadgeCount(ruta) > 0"
                                                :content="getBadgeCount(ruta) > 9 ? '9+' : getBadgeCount(ruta)"
                                                color="#D4172A"
                                                floating
                                                offset-x="2"
                                                offset-y="2"
                                            >
                                                <v-icon :icon="ruta.icon" :color="isActive(ruta.name) ? '#0A2540' : '#FEFEFE'" />
                                            </v-badge>
                                            <v-icon
                                                v-else
                                                :icon="ruta.icon"
                                                :color="isActive(ruta.name) ? '#0A2540' : '#FEFEFE'"
                                            />
                                        </template>
                                        <v-list-item-title class="d-flex align-center justify-space-between w-100">
                                            <span :style="isActive(ruta.name) ? 'color: #0A2540; font-weight: 700; letter-spacing: 0.01em;' : 'color: #FEFEFE;'">
                                                {{ ruta.title }}
                                            </span>
                                            <!-- Badge inline en modo expandido -->
                                            <v-badge
                                                v-if="getBadgeCount(ruta) > 0"
                                                :content="getBadgeCount(ruta) > 9 ? '9+' : getBadgeCount(ruta)"
                                                color="#D4172A"
                                                inline
                                            />
                                        </v-list-item-title>
                                    </v-list-item>
                                </Link>
                            </template>
                        </v-tooltip>
                    </template>

                    <!-- ── Ítem con submenú ───────────────────────────────── -->
                    <template v-else-if="ruta.group != null && existSomeRoute(ruta.name)">

                        <!-- Modo expandido: acordeón normal -->
                        <v-list-group v-if="!isRail" :value="ruta.group" fluid>
                            <template #activator="{ props: activatorProps }">
                                <v-list-item
                                    v-bind="activatorProps"
                                    variant="text"
                                    rounded="lg"
                                    color="#FEFEFE"
                                    class="nav-item nav-item--inactive mb-1"
                                >
                                    <template #prepend>
                                        <v-badge
                                            v-if="getBadgeCount(ruta) > 0"
                                            :content="getBadgeCount(ruta) > 9 ? '9+' : getBadgeCount(ruta)"
                                            color="#D4172A"
                                            inline
                                        >
                                            <v-icon :icon="ruta.icon" color="#FEFEFE" />
                                        </v-badge>
                                        <v-icon v-else :icon="ruta.icon" color="#FEFEFE" />
                                    </template>
                                    <v-list-item-title style="color: #FEFEFE">
                                        {{ ruta.title }}
                                    </v-list-item-title>
                                </v-list-item>
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
                                >
                                    <v-list-item-title class="d-flex align-center gap-2">
                                        {{ groupItem.title }}
                                        <v-badge
                                            v-if="getGroupItemBadgeCount(groupItem) > 0"
                                            :content="getGroupItemBadgeCount(groupItem) > 9 ? '9+' : getGroupItemBadgeCount(groupItem)"
                                            color="#D4172A"
                                            inline
                                        />
                                    </v-list-item-title>
                                </v-list-item>
                            </Link>
                        </v-list-group>

                        <!-- Modo rail: popup flotante al hacer hover -->
                        <v-menu v-else location="end" :open-delay="80" :close-delay="120" open-on-hover>
                            <template #activator="{ props: menuProps }">
                                <v-list-item
                                    v-bind="menuProps"
                                    rounded="lg"
                                    variant="text"
                                    color="#FEFEFE"
                                    class="nav-item mb-1"
                                    :class="isActive((ruta.groupItems ?? []).map((g: any) => g.name)) ? 'nav-item--active' : 'nav-item--inactive'"
                                    :active="isActive((ruta.groupItems ?? []).map((g: any) => g.name))"
                                >
                                    <template #prepend>
                                        <v-badge
                                            v-if="getBadgeCount(ruta) > 0"
                                            :content="getBadgeCount(ruta) > 9 ? '9+' : getBadgeCount(ruta)"
                                            color="#D4172A"
                                            floating
                                            offset-x="2"
                                            offset-y="2"
                                        >
                                            <v-icon
                                                :icon="ruta.icon"
                                                :color="isActive((ruta.groupItems ?? []).map((g: any) => g.name)) ? '#0A2540' : '#FEFEFE'"
                                            />
                                        </v-badge>
                                        <v-icon
                                            v-else
                                            :icon="ruta.icon"
                                            :color="isActive((ruta.groupItems ?? []).map((g: any) => g.name)) ? '#0A2540' : '#FEFEFE'"
                                        />
                                    </template>
                                </v-list-item>
                            </template>

                            <!-- Panel flotante con los subitems -->
                            <v-list
                                bg-color="#0a2540"
                                class="pa-1"
                                rounded="lg"
                                min-width="210"
                                style="border: 1px solid rgba(255,255,255,0.12)"
                            >
                                <v-list-subheader style="color: rgba(255,255,255,0.45); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.09em">
                                    {{ ruta.title }}
                                </v-list-subheader>
                                <template v-for="groupItem in ruta.groupItems" :key="groupItem.value">
                                    <Link
                                        v-if="can.includes(groupItem.name)"
                                        :href="route(groupItem.name)"
                                        preserve-scroll
                                    >
                                        <v-list-item
                                            rounded="lg"
                                            variant="text"
                                            color="#FEFEFE"
                                            class="nav-item mb-1"
                                            :class="route().current(groupItem.name) ? 'nav-item--active-sub' : 'nav-item--inactive'"
                                            :active="route().current(groupItem.name)"
                                            :prepend-icon="groupItem.icon"
                                        >
                                            <v-list-item-title class="d-flex align-center gap-2">
                                                <span :style="route().current(groupItem.name) ? 'color: #0A2540; font-weight: 700' : 'color: #FEFEFE'">
                                                    {{ groupItem.title }}
                                                </span>
                                                <v-badge
                                                    v-if="getGroupItemBadgeCount(groupItem) > 0"
                                                    :content="getGroupItemBadgeCount(groupItem) > 9 ? '9+' : getGroupItemBadgeCount(groupItem)"
                                                    color="#D4172A"
                                                    inline
                                                />
                                            </v-list-item-title>
                                        </v-list-item>
                                    </Link>
                                </template>
                            </v-list>
                        </v-menu>

                    </template>

                </template>
            </v-list>
        </div>

        <!-- ── Cerrar sesión (fijo al fondo) ── -->
        <div class="nav-logout-area">
            <v-divider style="border-color: rgba(255, 255, 255, 0.08)" />
            <v-list
                density="comfortable"
                nav
                class="px-2 py-1"
                bg-color="transparent"
            >
                <v-tooltip
                    text="Cerrar Sesión"
                    location="end"
                    :disabled="!isRail"
                >
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

<style scoped>
/* ── Layout del drawer en columna ── */
:deep(.v-navigation-drawer__content) {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

/* Cabecera: nunca hace scroll */
.nav-header-area {
    flex-shrink: 0;
}

/* Área del menú: ocupa todo el espacio restante y hace scroll */
.nav-scroll-area {
    flex: 1;
    overflow-y: auto;
    min-height: 0; /* necesario para que flex respete el overflow */
}

/* Logout: nunca hace scroll */
.nav-logout-area {
    flex-shrink: 0;
}

.nav-item--inactive {
    opacity: 0.6;
}

/* Activo principal (sin submenú): fondo dorado, texto navy bold */
.nav-item--active {
    background-color: #f4b403 !important;
    opacity: 1;
}

/* Activo submenú: mismo fondo dorado, texto navy bold */
.nav-item--active-sub {
    background-color: #f4b403 !important;
    opacity: 1;
    font-weight: 700;
}

.nav-item--logout {
    opacity: 0.75;
}

.nav-item:hover {
    opacity: 1 !important;
    background-color: rgba(255, 255, 255, 0.08) !important;
    transition:
        background-color 0.18s ease,
        opacity 0.18s ease;
}

.nav-item--active:hover {
    background-color: #e0a500 !important;
}

.nav-item--logout:hover {
    opacity: 1 !important;
    background-color: rgba(212, 23, 42, 0.22) !important;
}
</style>
