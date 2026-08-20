<script setup lang="ts">
// Cinta diagonal en la esquina, igual que el banner "DEBUG" de Flutter: se
// muestra en cualquier ambiente que no sea "production" (APP_ENV en .env,
// compartido vía HandleInertiaRequests), y desaparece por completo en
// producción — nadie tiene que acordarse de quitarla a mano.
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const page = usePage<any>();

const appEnv = computed(() => String(page.props.appEnv ?? "production").toLowerCase());
const isProduction = computed(() => appEnv.value === "production");
const label = computed(() => appEnv.value.toUpperCase());

const colorsByEnv: Record<string, { bg: string; text: string }> = {
    local: { bg: "#F4B403", text: "#0A2540" },
    sandbox: { bg: "#F4B403", text: "#0A2540" },
    staging: { bg: "#FF6B35", text: "#FFFFFF" },
    testing: { bg: "#FF6B35", text: "#FFFFFF" },
};

const colors = computed(() => colorsByEnv[appEnv.value] ?? { bg: "#D32F2F", text: "#FFFFFF" });
</script>

<template>
    <div
        v-if="!isProduction"
        class="env-badge-ribbon"
        :style="{ backgroundColor: colors.bg, color: colors.text }"
    >
        {{ label }}
    </div>
</template>

<style scoped>
.env-badge-ribbon {
    position: fixed;
    top: 14px;
    right: -46px;
    width: 180px;
    transform: rotate(45deg);
    text-align: center;
    font-weight: 700;
    font-size: 12px;
    letter-spacing: 1.5px;
    padding: 4px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
    z-index: 9999;
    pointer-events: none;
    user-select: none;
}
</style>
