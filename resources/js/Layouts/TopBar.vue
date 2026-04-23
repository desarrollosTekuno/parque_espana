<script setup>
import { ref } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  title: { type: String, default: 'INICIO' }
})
const emit = defineEmits(['toggle-drawer'])
const display = useDisplay()
const search = ref('')
</script>

<template>
    <v-app-bar
            app
            height="56"
            elevation="0"
            class="text-white"
            :style="{
            background: '#0A2540',
            borderBottom: '3px solid #F4B403',
        }"
    >

        <v-btn
            icon
            variant="text"
            class="text-white hover:text-primary"
            @click="emit('toggle-drawer')"
        >
            <v-icon>mdi-menu</v-icon>
        </v-btn>

        <v-toolbar-title class="ml-1 font-semibold tracking-wide text-white">
            <span class="opacity-90">{{ title }}</span>
        </v-toolbar-title>

        <v-spacer />

        <slot name="actions">
        <v-divider vertical class="mx-2 opacity-20" />

        <v-menu location="bottom end" transition="fade-transition">
            <template #activator="{ props: act }">
                <v-btn v-bind="act" class="px-2 text-white hover:opacity-90" variant="text">
                    <v-avatar size="30" class="shadow-md bg-success/90">A</v-avatar>
                    <v-icon class="ml-1 opacity-80">mdi-chevron-down</v-icon>
                </v-btn>
            </template>

            <v-card class="min-w-[220px]">
                <v-list density="compact">
                    <v-list-item title="Perfil" prepend-icon="mdi-account" />
                    <v-list-item title="Ajustes" prepend-icon="mdi-cog-outline" />
                    <v-divider />
                    <v-list-item title="Salir" prepend-icon="mdi-logout" />
                </v-list>
            </v-card>
        </v-menu>
        </slot>
    </v-app-bar>
</template>
