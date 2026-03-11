<script setup lang="ts">
import { ref, computed } from "vue";

const props = defineProps({
  modelValue: File || null,
  label: {
    type: String,
    default: "Icono de la amenidad",
  },
  maxSizeKB: {
    type: Number,
    default: 0.5,
  },
  allowedExtensions: {
    type: Array as () => string[],
    default: () => ["jpg", "jpeg", "png", "webp", "svg"],
  },
});

const emit = defineEmits(["update:modelValue"]);

const errorMessage = ref("");

const isValid = computed(() => {
  return !!props.modelValue && !errorMessage.value;
});

const handleFile = (file: File | null) => {

  // limpiar archivo
  if (!file) {
    emit("update:modelValue", null);
    errorMessage.value = "";
    return;
  }

  const fileExt = file.name.split(".").pop()?.toLowerCase();

  if (!fileExt || !props.allowedExtensions.includes(fileExt)) {
    errorMessage.value = `Solo se permiten: ${props.allowedExtensions.join(", ")}`;
    emit("update:modelValue", null);
    return;
  }

  const maxBytes = props.maxSizeKB * 1024 * 1024;

  if (file.size > maxBytes) {
    errorMessage.value = `El archivo no puede superar ${props.maxSizeKB} MB`;
    emit("update:modelValue", null);
    return;
  }

  errorMessage.value = "";
  emit("update:modelValue", file);
};

defineExpose({ isValid });
</script>

<template>

<v-file-input
  :model-value="modelValue"
  @update:modelValue="handleFile"
  accept="image/*"
  :label="label"
  prepend-inner-icon="mdi-image-area"
  prepend-icon=""
  :error="!!errorMessage"
  :error-messages="errorMessage"
  clearable
/>

</template>