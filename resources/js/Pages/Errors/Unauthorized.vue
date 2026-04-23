<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
</script>

<template>
    <Head title="Acceso no autorizado" />

    <AppLayout>
        <template #header>Acceso no autorizado</template>

        <v-card class="pa-6" rounded="lg">
            <p class="text-body-1 mb-2">
                No tienes permisos asignados para acceder a un módulo web.
            </p>
            <p class="text-medium-emphasis">
                Si necesitas acceso, solicita a un administrador que te asigne
                al menos un permiso de navegación.
            </p>
        </v-card>
    </AppLayout>
</template>
