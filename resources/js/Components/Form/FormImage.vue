<script setup lang="ts">
import { ref, watch, computed } from "vue";

const props = defineProps({
  modelValue: File || null,
  label: {
    type: String,
    default: "Imagen de fondo",
  },
  maxSizeMB: {
    type: Number,
    default: 2, // tamaño máximo 2MB
  },
  allowedExtensions: {
    type: Array as () => string[],
    default: () => ["jpg", "jpeg", "png", "webp"], // extensiones permitidas
  },
});

const emit = defineEmits(["update:modelValue"]);

const preview = ref<string | null>(null);
const errorMessage = ref<string>("");

// --- Validación estricta para v-form ---
const validate = (): boolean => {
  return !!props.modelValue && !errorMessage.value;
};

// --- Computed que indica si el input es válido ---
const isValid = computed(() => validate());

const onFileChange = (event: any) => {
  const file = event.target.files[0];
  if (!file) return;

  // --- Validación de extensión ---
  const fileExt = file.name.split(".").pop()?.toLowerCase();
  if (!fileExt || !props.allowedExtensions.includes(fileExt)) {
    errorMessage.value = `Solo se permiten: ${props.allowedExtensions.join(", ")}`;
    preview.value = null;
    emit("update:modelValue", null);
    return;
  }

  // --- Validación de tamaño ---
  const maxBytes = props.maxSizeMB * 1024 * 1024;
  if (file.size > maxBytes) {
    errorMessage.value = `El archivo no puede superar ${props.maxSizeMB} MB`;
    preview.value = null;
    emit("update:modelValue", null);
    return;
  }

  // --- Archivo válido ---
  errorMessage.value = "";
  emit("update:modelValue", file);

  const reader = new FileReader();
  reader.onload = (e) => {
    preview.value = e.target?.result as string;
  };
  reader.readAsDataURL(file);
};

watch(
  () => props.modelValue,
  (file: any) => {
    if (!file) {
      preview.value = null;
      errorMessage.value = "";
    }
  }
);

defineExpose({ validate, isValid });
</script>

<template>
  <v-file-input
    accept="image/*"
    :label="label"
    prepend-inner-icon="mdi-image-area"
    prepend-icon=""
    :error="!!errorMessage"
    :error-messages="errorMessage"
    @change="onFileChange"
    clearable
  />

  <div v-if="preview" class="mt-3">x
    <v-img
      :src="preview"
      height="150"
      cover
      class="rounded"
    />
  </div>
</template>