interface Routing {
    name: Array<string>;
    title: string;
    icon: string;
    value: string;
    group: string | null;
    showBadge?: boolean;
    groupItems?: Array<{
        name: string;
        title: string;
        icon: string;
        value: string;
    }>;
}
const routes: Routing[] = [
    /* Rutas para superadministrador */
    {
        name: ["profile.show"],
        title: "Mi perfil",
        icon: "mdi-account-circle-outline",
        value: "profile",
        group: null,
    },

    {
        name: ["dashboard"],
        title: "Inicio",
        icon: "mdi-home-outline",
        value: "dashboard",
        group: null,
    },
    {
        name: [
            "members.index",
            "members.create",
            "members.edit",
            "members.additional-membership.create",
            "members.manage.show",
            "members.family-members.create",
            "member-access.index",
        ],
        title: "Membresías",
        icon: "mdi-account-group-outline",
        value: "membresiasMenu",
        group: "Membresías",
        groupItems: [
            {
                name: "members.index",
                title: "Membresías activas",
                icon: "mdi-account-group-outline",
                value: "membresias-activas",
            },
            {
                name: "members.create",
                title: "Nueva membresía",
                icon: "mdi-account-plus-outline",
                value: "nueva-membresia",
            },
        ],
    },
    {
        name: ["member-access.index"],
        title: "Accesos App Móvil",
        icon: "mdi-cellphone-key",
        value: "accesos-app",
        group: null,
    },
    {
        name: ["billing-concepts.index"],
        title: "Conceptos de cobro",
        icon: "mdi-receipt-text-outline",
        value: "conceptos-cobro",
        group: null,
    },
    {
        name: ["billing.index", "cash-cuts.index", "cash-cuts.show", "global-cash-cuts.index", "global-cash-cuts.show"],
        title: "Cobranza",
        icon: "mdi-cash-multiple",
        value: "cobranzaMenu",
        group: "Cobranza",
        groupItems: [
            {
                name: "billing.index",
                title: "Cargos pendientes",
                icon: "mdi-receipt-text-outline",
                value: "cargos-pendientes",
            },
            {
                name: "cash-cuts.index",
                title: "Mis cortes de caja",
                icon: "mdi-cash-register",
                value: "cortes-caja",
            },
            {
                name: "global-cash-cuts.index",
                title: "Cortes globales",
                icon: "mdi-calculator-variant-outline",
                value: "cortes-globales",
            },
        ],
    },
    // Clubs deportivos
    {
        name: ["clubs.index"],
        title: "Clubs deportivos",
        icon: "mdi-soccer",
        value: "clubs-deportivos",
        group: null,
    },

    //permisos
    {
        name: ["permissions.index"],
        title: "Permisos",
        icon: "mdi-key-outline",
        value: "permisos",
        group: null,
    },
    // Roles
    {
        name: ["roles.index"],
        title: "Roles",
        icon: "mdi-account-key-outline",
        value: "roles",
        group: null,
    },
    // Usuarios
    {
        name: ["users.index"],
        title: "Usuarios",
        icon: "mdi-account-multiple-outline",
        value: "usuarios",
        group: null,
    },
    // Amenidades
    {
        name: ["amenities.index", "blockedPeriods.index"],
        title: "Amenidades",
        icon: "mdi-beach",
        value: "amenidades",
        group: "Amenidades",
        groupItems: [
            {
                name: "amenities.index",
                title: "Amenidades y recursos",
                icon: "mdi-beach",
                value: "amenidades",
            },
            {
                name: "blockedPeriods.index",
                title: "Bloqueo de recursos",
                icon: "mdi-calendar-clock-outline",
                value: "bloqueos",
            },
        ],
    },
    // Reservaciones
    {
        name: ["reservations.index", "guest-lists.index"],
        title: "Reservaciones",
        icon: "mdi-calendar-check",
        value: "reservacionesMenu",
        group: "Reservaciones",
        groupItems: [
            {
                name: "reservations.index",
                title: "Reservaciones",
                icon: "mdi-calendar-check",
                value: "reservaciones",
            },
            {
                name: "guest-lists.index",
                title: "Listas de invitados",
                icon: "mdi-account-group-outline",
                value: "listas-invitados",
            },
            {
                name: "system-variables.index",
                title: "Configuración",
                icon: "mdi-cog",
                value: "variables-sistema",
            },
        ],
    },
    // Anuncios
    {
        name: ["announcements.index"],
        title: "Comunicación",
        icon: "mdi-bullhorn-outline",
        value: "anuncios",
        group: null,
    },
    // Publicidad de negocios
    {
        name: ["business-ads.index", "business-categories.index"],
        title: "Publicidad",
        icon: "mdi-storefront-outline",
        value: "publicidad-negocios",
        group: "Publicidad de negocios",
        showBadge: true,
        groupItems: [
            {
                name: "business-categories.index",
                title: "Categorías de negocios",
                icon: "mdi-shape-outline",
                value: "categorias-negocios",
            },
            {
                name: "business-ads.index",
                title: "Publicaciones de negocios",
                icon: "mdi-storefront-outline",
                value: "anuncios-activos",
            },
        ],
    },
    // Encuestas
    {
        name: ["surveys.index", "surveys.create", "surveys.edit"],
        title: "Encuestas",
        icon: "mdi-clipboard-list-outline",
        value: "encuestas",
        group: null,
    },

    {
        name: ["pricing-rules.index"],
        title: "Reglas de precio",
        icon: "mdi-currency-usd",
        value: "reglas-precio",
        group: null,
    },
    {
        name: ["interclub-package-rules.index"],
        title: "Paquetes interclub",
        icon: "mdi-swap-horizontal",
        value: "paquetes-interclub",
        group: null,
    },
];
export default routes;
