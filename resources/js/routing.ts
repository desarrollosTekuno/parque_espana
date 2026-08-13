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
        name: ["club-settings.edit"],
        title: "Mi Club",
        icon: "mdi-office-building-cog",
        value: "mi-club",
        group: null,
    },
    {
        name: ["website-content.index", "website-contacts.index"],
        title: "Página web",
        icon: "mdi-web",
        value: "pagina-web",
        group: "Página web",
        groupItems: [
            {
                name: "website-content.index",
                title: "Contenido",
                icon: "mdi-image-multiple-outline",
                value: "contenido-pagina-web",
            },
            {
                name: "website-contacts.index",
                title: "Mensajes de contacto",
                icon: "mdi-email-outline",
                value: "mensajes-contacto-pagina-web",
            },
        ],
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
                title: "Paquetes intermedios",
                icon: "mdi-swap-horizontal",
                value: "paquetes-intermedios",
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
            "fee-schedules.index"
        ],
        title: "Membresías",
        icon: "mdi-account-group-outline",
        value: "membresiasMenu",
        group: "Membresías",
        groupItems: [
            {
                name: "fee-schedules.index",
                title: "Cuotas por año",
                icon: "mdi-calendar-multiple",
                value: "cuotas-por-anio",
            },
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
            
        ],
    },
    {
        name: [
            "member-access.index",
        ],
        title: "App móvil",
        icon: "mdi-cellphone-key",
        value: "appMovilMenu",
        group: "App móvil",
        groupItems: [
            {
                name: "member-access.index",
                title: "Usuarios App Móvil",
                icon: "mdi-cellphone-key",
                value: "accesos-app",
            },
            // Configuración de variables de la app móvil
            // {
            //     name: "app-variables.index",
            //     title: "Variables de la App",
            //     icon: "mdi-cog-outline",
            //     value: "variables-app",
            // }
        ],
    },

    {
        name: [
            "collections.index",
            "billing.index",
            "billing.charges.index",
            "billing-concepts.index",
            "payment-methods.index",
            "cash-cuts.index",
            "cash-cuts.show",
            "global-cash-cuts.index",
            "global-cash-cuts.show",
            "tickets.index",
        ],
        title: "Cobranza",
        icon: "mdi-cash-multiple",
        value: "cobranzaMenu",
        group: "Cobranza",
        groupItems: [
            {
                name: "collections.index",
                title: "Registro de cobros",
                icon: "mdi-cash-register",
                value: "cobro-directo",
            },
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
                title: "Cortes de caja",
                icon: "mdi-cash-register",
                value: "cortes-caja",
            },
            {
                name: "global-cash-cuts.index",
                title: "Cortes globales",
                icon: "mdi-calculator-variant-outline",
                value: "cortes-globales",
            },
            {
                name: "tickets.index",
                title: "Tickets de pago",
                icon: "mdi-printer-outline",
                value: "tickets-pago",
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
    // Credenciales Conekta por parque
    {
        name: ["conekta-credentials.index"],
        title: "Credenciales Conekta",
        icon: "mdi-credit-card-lock-outline",
        value: "conekta-credentials",
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
    {
        name: ["email-configs.index", "notifications.index"],
        title: "Notificaciones",
        icon: "mdi-bell-outline",
        value: "notificaciones",
        group: "Notificaciones",
        groupItems: [
            {
                name: "email-configs.index",
                title: "Configuración SMTP",
                icon: "mdi mdi-email-fast-outline",
                value: "configuracion-correo",
            },
            {
                name: "notifications.index",
                title: "Gestión de notificaciones",
                icon: "mdi-bell-outline",
                value: "gestion-notificaciones",
            },
        ],
    },
    // Clases
    {
        name: ["specialties.index", "coaches.index", "classSchedules.index"],
        title: "Clases",
        icon: "mdi-whistle-outline",
        value: "clasesMenu",
        group: "Clases",
        groupItems: [
            {
                name: "specialties.index",
                title: "Especialidades",
                icon: "mdi-tag-multiple-outline",
                value: "especialidades",
            },
            {
                name: "coaches.index",
                title: "Entrenadores",
                icon: "mdi-account-star-outline",
                value: "entrenadores",
            },
            {
                name: "classSchedules.index",
                title: "Horarios de clases",
                icon: "mdi-calendar-clock-outline",
                value: "horarios-clases",
            },
        ],
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
                name: "system-variables.index",
                title: "Variables del Sistema",
                icon: "mdi-cog",
                value: "variables-sistema",
            },
            {
                name: "app-variables.index",
                title: "Variables de App Móvil",
                icon: "mdi-cellphone-cog",
                value: "variables-app-movil",
            }
        ]
    },
    // Listas de invitados
    {
        name: ["guest-lists.index", "day-passes.index", "cafeteria-visits.index"],
        title: "Listas de invitados",
        icon: "mdi-account-group-outline",
        value: "listas-invitadosMenu",
        group: "Listas de invitados",
        groupItems: [
            {
                name: "guest-lists.index",
                title: "Listas de invitados",
                icon: "mdi-account-group-outline",
                value: "listas-invitados",
            },
            {
                name: "guest-list-variables.index",
                title: "Configuración",
                icon: "mdi-cog",
                value: "configuracion",
            },
            {
                name: "guest-list-payments.index",
                title: "Pagos",
                icon: "mdi-cash",
                value: "pagos",
            },
            {
                name: "day-passes.index",
                title: "Pase por Día",
                icon: "mdi-ticket-account",
                value: "pase-por-dia",
            },
            {
                name: "cafeteria-visits.index",
                title: "Ingresos Cafetería",
                icon: "mdi-coffee",
                value: "cafeteria-visitas",
            },
            {
                name: "day-passes.incidents.index",
                title: "Incidencias",
                icon: "mdi-alert-circle-outline",
                value: "incidencias-visitantes",
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
        name: ["business-ads.index", "business-categories.index", "physical-ad-sizes.index"],
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
            {
                name: "physical-ad-sizes.index",
                title: "Tamaños de anuncios físicos",
                icon: "mdi-ruler",
                value: "tamanos-anuncios-fisicos",
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
    // Documentos
    {
        name: ["files.index"],
        title: "Formatos",
        icon: "mdi-file-outline",
        value: "archivos",
        group: "Archivos",
        showBadge: true,
        groupItems: [
            {
                name: "files.index",
                title: "Formatos",
                icon: "mdi-file-outline",
                value: "archivos",
            }
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
