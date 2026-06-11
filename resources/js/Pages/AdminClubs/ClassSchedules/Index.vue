<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";

defineProps<{
    classSchedules: any;
    coaches: any;
    amenityResources: any;
}>();
</script>

<template>
    <AppLayout>
        <Head title="Clases" />
        <v-container>
            <v-row>
                <v-col cols="12">
                    <div class="d-flex align-center justify-space-between mb-4">
                        <h2 class="text-h5 font-weight-bold">Clases</h2>
                    </div>
                    <v-card>
                        <v-card-text>
                            <p class="text-medium-emphasis">Módulo en construcción.</p>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </AppLayout>
</template>
