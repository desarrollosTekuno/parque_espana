interface Routing {
    name: string | Array<string>;
    title: string;
    icon: string;
    value: string;
    group: string | null;
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
    {
        name: "amenities.index",
        title: "Amenidades",
        icon: "mdi-beach",
        value: "amenidades",
        group: null,
        // groupItems: null
    },
    // Reservaciones
    {
        name: "reservations.index",
        title: "Reservaciones",
        icon: "mdi-calendar-check",
        value: "reservaciones",
        group: null,
        // groupItems: null
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
