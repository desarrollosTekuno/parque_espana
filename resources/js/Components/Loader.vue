<script setup lang="ts">
import { ScaleLoader } from "vue3-spinner";
import { Vue3Lottie } from "vue3-lottie";
import loaderAnimation from "@/assets/animations/loader.json";
defineProps<{
    overlay: boolean;
}>();
</script>
<template>
    <v-overlay
        opacity="80%"
        persistent
        :model-value="overlay"
        class="align-center justify-center"
    >
        <!-- <ScaleLoader
            :loading="overlay"
            color="#FFA500"
            height="80px"
            width="30px"
        /> -->

        <Vue3Lottie
            :animation-data="loaderAnimation"
            :height="200"
            :width="200"
            :loop="true"
            :auto-play="true"
        />
    </v-overlay>
</template>
