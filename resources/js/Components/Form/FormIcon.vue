<script setup lang="ts">
import { ref, watch } from "vue";

const props = defineProps({
    modelValue: File || null,
    label: {
        type: String,
        default: "Icono"
    }
});

const emit = defineEmits(["update:modelValue"]);

const preview = ref<string | null>(null);

const onFileChange = (event: any) => {
    const file = event.target.files[0];

    if (!file) return;

    emit("update:modelValue", file);

    const reader = new FileReader();
    reader.onload = e => {
        preview.value = e.target?.result as string;
    };

    reader.readAsDataURL(file);
};

watch(
    () => props.modelValue,
    (file: any) => {
        if (!file) preview.value = null;
    }
);
</script>

<template>
    <v-file-input
        accept="image/*"
        :label="label"
        prepend-inner-icon="mdi-image"
        prepend-icon=""
        @change="onFileChange"
    />

    <div v-if="preview" class="mt-2">
        <v-img
            :src="preview"
            max-width="80"
            max-height="80"
            cover
        />
    </div>
</template>