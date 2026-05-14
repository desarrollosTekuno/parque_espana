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
            "membership-types.index",
            "document-types.index",
            "pricing-rules.index",
            "interclub-package-rules.index",
        ],
        title: "Configuración de membresías",
        icon: "mdi-cog-outline",
        value: "configuracionMembresiasMenu",
        group: "Configuración de membresías",
        groupItems: [
            {
                name: "document-types.index",
                title: "Tipos de documento",
                icon: "mdi-file-document-outline",
                value: "tipos-documento",
            },
            {
                name: "membership-types.index",
                title: "Tipos de membresía",
                icon: "mdi-card-account-details-outline",
                value: "tipos-membresia",
            },
            {
                name: "pricing-rules.index",
                title: "Reglas de precio",
                icon: "mdi-currency-usd",
                value: "reglas-precio",
            },
            {
                name: "interclub-package-rules.index",
                title: "Paquetes interclub",
                icon: "mdi-swap-horizontal",
                value: "paquetes-interclub",
            },
        ],
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
            "members.cancellations.index",
            "members.age-transitions.index",
        ],
        title: "Membresías",
        icon: "mdi-account-group-outline",
        value: "membresiasMenu",
        group: "Membresías",
        groupItems: [
            {
                name: "members.index",
                title: "Membresías",
                icon: "mdi-account-group-outline",
                value: "membresias",
            },
            {
                name: "members.create",
                title: "Nueva membresía",
                icon: "mdi-account-plus-outline",
                value: "nueva-membresia",
            },
            {
                name: "members.cancellations.index",
                title: "Historial de bajas",
                icon: "mdi-account-off-outline",
                value: "historial-bajas",
            },
            {
                name: "members.age-transitions.index",
                title: "Transiciones por edad",
                icon: "mdi-account-clock-outline",
                value: "transiciones-edad",
                showBadge: true,
            },
            {
                name: "member-access.index",
                title: "Accesos App Móvil",
                icon: "mdi-cellphone-key",
                value: "accesos-app",
            },
        ],
    },

    {
        name: [
            "billing.index",
            "billing.charges.index",
            "billing-concepts.index",
            "payment-methods.index",
            "cash-cuts.index",
            "cash-cuts.show",
            "global-cash-cuts.index",
            "global-cash-cuts.show",
        ],
        title: "Cobranza",
        icon: "mdi-cash-multiple",
        value: "cobranzaMenu",
        group: "Cobranza",
        groupItems: [
            {
                name: "billing-concepts.index",
                title: "Conceptos de cobro",
                icon: "mdi-receipt-text-outline",
                value: "conceptos-cobro",
            },
            {
                name: "payment-methods.index",
                title: "Métodos de pago",
                icon: "mdi-credit-card-outline",
                value: "metodos-pago",
            },
            {
                name: "billing.index",
                title: "Cargos pendientes",
                icon: "mdi-receipt-text-outline",
                value: "cargos-pendientes",
            },
            {
                name: "billing.charges.index",
                title: "Desglose de cargos",
                icon: "mdi-format-list-bulleted",
                value: "desglose-cargos",
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
        name: [
            "feedback.index",
            "feedback-management.index",
            "feedback-categories.index",
            "feedback-ticket-types.index",
            "feedback-statuses.index",
            "feedback-priorities.index",
        ],
        title: "Quejas y sugerencias",
        icon: "mdi-message-alert-outline",
        value: "feedbackMenu",
        group: "Quejas y sugerencias",
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
        ],
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
