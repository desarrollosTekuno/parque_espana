interface Routing {
    name: string | Array<string>;
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
        name: "profile.show",
        title: "Mi perfil",
        icon: "mdi-account-circle-outline",
        value: "profile",
        group: null,
        // groupItems: null
    },


    {
        name: "dashboard",
        title: "Inicio",
        icon: "mdi-home-outline",
        value: "dashboard",
        group: null,
        // groupItems: null
    },
    {
        name: ["members.index", "members.create", "members.edit", "members.additional-membership.create", "members.manage.show", "members.family-members.create", "member-access.index"],
        title: "Socios",
        icon: "mdi-account-group-outline",
        value: "sociosMenu",
        group: "Socios",
        groupItems: [
            {
                name: "members.index",
                title: "Lista de socios",
                icon: "mdi-account-group-outline",
                value: "lista-socios",
            },
            // nuevo socio
            {
                name: "members.create",
                title: "Nuevo socio",
                icon: "mdi-account-plus-outline",
                value: "nuevo-socio",
            },
            // accesos app móvil
            {
                name: "member-access.index",
                title: "Accesos App Móvil",
                icon: "mdi-cellphone-key",
                value: "accesos-app",
            },
        ],
    },
    {
        name: ["billing.index"],
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
        ],
    },
        // Clubs deportivos
    {
        name: "clubs.index",
        title: "Clubs deportivos",
        icon: "mdi-soccer",
        value: "clubs-deportivos",
        group: null,
        // groupItems: null
    },

    //permisos
    {
        name: "permissions.index",
        title: "Permisos",
        icon: "mdi-key-outline",
        value: "permisos",
        group: null,
        // groupItems: null
    },
     // Roles
    {
        name: "roles.index",
        title: "Roles",
        icon: "mdi-account-key-outline",
        value: "roles",
        group: null,
        // groupItems: null
    },
    // Usuarios
    {
        name: "users.index",
        title: "Usuarios",
        icon: "mdi-account-multiple-outline",
        value: "usuarios",
        group: null,
        // groupItems: null
    },
    // Amenidades
    /*{
        name: "amenities.index",
        title: "Amenidades",
        icon: "mdi-beach",
        value: "amenidades",
        group: null,
        // groupItems: null
    },*/
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
        ]
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
                title: "Variables del Sistema",
                icon: "mdi-cog",
                value: "variables-sistema",
            }
        ]
    },
    // Anuncios
    {
        name: "announcements.index",
        title: "Comunicación",
        icon: "mdi-bullhorn-outline",
        value: "anuncios",
        group: null,
        // groupItems: null
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
        ]
    },
    // Encuestas
    {
        name: "surveys.index",
        title: "Encuestas",
        icon: "mdi-poll",
        value: "surveys",
        group: null
    },
    // Variables de sistema
    {
        name: "system-variables.index",
        title: "Variables del Sistema",
        icon: "mdi-cog",
        value: "variables-sistema",
        group: null,
        // groupItems: null
    },
    {
        name: "pricing-rules.index",
        title: "Reglas de precio",
        icon: "mdi-currency-usd",
        value: "reglas-precio",
        group: null,
    },
    {
        name: "billing-concepts.index",
        title: "Conceptos de cobro",
        icon: "mdi-receipt-text-outline",
        value: "conceptos-cobro",
        group: null,
    },
    {
        name: "interclub-package-rules.index",
        title: "Paquetes interclub",
        icon: "mdi-swap-horizontal",
        value: "paquetes-interclub",
        group: null,
    },
    // Encuestas
    {
        name: "surveys.index",
        title: "Encuestas",
        icon: "mdi-clipboard-list-outline",
        value: "encuestas",
        group: null,
    },


    {
        name: ["feedback.index", "feedback-management.index", "feedback-categories.index", "feedback-ticket-types.index", "feedback-statuses.index", "feedback-priorities.index"],
        title: "Feedback",
        icon: "mdi-message-alert-outline",
        value: "feedbackMenu",
        group: "Feedback",
        groupItems: [
            {
                name: "feedback-categories.index",
                title: "Categorías",
                icon: "mdi-tag-outline",
                value: "feedback-categories",
            },
            {
                name: "feedback-ticket-types.index",
                title: "Tipos de ticket",
                icon: "mdi-shape-outline",
                value: "feedback-ticket-types",
            },
            {
                name: "feedback-statuses.index",
                title: "Estatus",
                icon: "mdi-progress-check",
                value: "feedback-statuses",
            },
            {
                name: "feedback-priorities.index",
                title: "Prioridades",
                icon: "mdi-flag-checkered",
                value: "feedback-priorities",
            },
            {
                name: "feedback.index",
                title: "Captura de tickets",
                icon: "mdi-message-text-outline",
                value: "feedback",
            },
            {
                name: "feedback-management.index",
                title: "Gestion de casos",
                icon: "mdi-briefcase-edit-outline",
                value: "feedback-management",
            },
        ]
    },
    /*
    {
        name: ["dashboard"],
        title: "Pagos",
        icon: "mdi-cash-multiple",
        value: "pagosMenu",
        group: "Pagos",
        groupItems: [
            {
                name: "dashboard",
                title: "Cobros y conceptos",
                icon: "mdi-credit-card-outline",
                value: "cobros-conceptos",
            },
            {
                name: "dashboard",
                title: "Pagos registrados",
                icon: "mdi-cash-check",
                value: "cobros-conceptos",
            },
            {
                name: "dashboard",
                title: "Historial y reportes",
                icon: "mdi-history",
                value: "cobros-conceptos",
            },
            {
                name: "dashboard",
                title: "Métodos de cobro",
                icon: "mdi-cog",
                value: "cobros-conceptos",
            },
        ],
    },
     */
];
export default routes;
