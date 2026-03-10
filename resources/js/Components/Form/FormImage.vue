<script setup lang="ts">
import { ref, watch } from "vue";

const props = defineProps({
    modelValue: File || null,
    label: {
        type: String,
        default: "Imagen de fondo"
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
<v-col cols="12">
    <v-file-input
        accept="image/*"
        :label="label"
        prepend-icon="mdi-image-area"
        @change="onFileChange"
    />

    <div v-if="preview" class="mt-3">
        <v-img
            :src="preview"
            height="150"
            cover
            class="rounded"
        />
    </div>
</v-col>
</template>