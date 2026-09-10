<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { computed } from "vue";

interface Props {
    status: number;
}

const props = defineProps<Props>();

interface ErrorContent {
    title: string;
    description: string;
}

const errorContent = computed<ErrorContent>(() => {
    const messages: Record<number, ErrorContent> = {
        403: {
            title: "No tienes permiso para acceder",
            description: "Tu cuenta o rol no puede realizar esta acción.",
        },
        404: {
            title: "No pudimos encontrar lo que buscas",
            description: "La página o recurso no existe o fue movido",
        },
        408: {
            title: "La solicitud tardó demasiado",
            description: "El servidor no recibió respuesta a tiempo",
        },
        419: {
            title: "Tu sesión ha expirado",
            description: "Vuelve a iniciar sesión para continuar",
        },
        429: {
            title: "Demasiadas solicitudes",
            description: "Espera un momento antes de intentarlo de nuevo",
        },
        500: {
            title: "Ocurrió un error en el servidor",
            description: "Algo salió mal, estamos trabajando en ello",
        },
        503: {
            title: "Servicio temporalmente no disponible",
            description: "Intenta de nuevo más tarde",
        },
    };

    return messages[props.status] ?? {
        title: "Error inesperado",
        description: "Ocurrió un problema al procesar tu solicitud.",
    };
});

const imagePath = computed(() => `/assets/images/errors/${props.status}.png`);

const goHome = () => {
    router.visit("/");
};


</script>

<template>
    <Head :title="`Error ${status}`" />

    <div class="relative min-h-screen w-full overflow-hidden bg-[#1e1e1e] flex items-center justify-center">

        <!-- Decoración esquina inferior izquierda -->
        <img
            src="/assets/images/errors/Ondas.png"
            alt=""
            class="absolute bottom-0 left-0 w-72 pointer-events-none select-none"
        />

        <!-- Logos esquina inferior derecha -->
        <img
            src="/assets/images/errors/logos.png"
            alt="Logos"
            class="absolute bottom-6 right-6 h-14 pointer-events-none select-none"
        />

        <!-- Contenido principal: 2 columnas -->
        <div class="relative z-10 w-full max-w-5xl px-8 grid grid-cols-1 md:grid-cols-2 gap-8 items-center">

            <!-- Columna izquierda: texto y botón -->
            <div class="text-center md:text-left">
                <h1 class="text-5xl md:text-[85px] font-extrabold text-white mb-6">
                    Error {{ status }}
                </h1>

                <h2 class="text-xl md:text-[43px] font-semibold text-[#87addb] mb-4">
                    {{ errorContent.title }}
                </h2>

                <p class="text-[27px] text-white font-semibold mb-10">
                    {{ errorContent.description }}
                </p>

                <button
                    @click="goHome"
                    class="px-10 py-3 rounded-lg bg-cyan-600 text-white text-lg font-medium hover:bg-cyan-700 transition"
                >
                    Inicio
                </button>
            </div>

            <!-- Columna derecha: imagen del error -->
            <div class="flex justify-center md:justify-end">
                <img
                    :src="imagePath"
                    :alt="`Error ${status}`"
                    class="w-72 h-72 md:w-96 md:h-96 object-contain"
                />
            </div>

        </div>
    </div>
</template>
