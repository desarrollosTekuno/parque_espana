<template>
  <div class="form-quill" :class="{ 'form-quill--disabled': disabled, 'form-quill--error': hasErrorState }">
    <label v-if="label" class="form-quill__label">
      {{ label }}
      <span v-if="required" class="form-quill__required">*</span>
    </label>

    <div class="form-quill__control">
      <QuillEditor
        v-model:content="internalValue"
        :content-type="contentType"
        :theme="theme"
        :toolbar="toolbar"
        :placeholder="placeholder"
        :read-only="disabled"
        :style="{ '--quill-min-height': `${minHeight}px` }"
        class="form-quill__editor"
        @blur="validate"
      />
    </div>

    <div v-if="hasErrorState" class="form-quill__messages">
      {{ localErrorMessage }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { QuillEditor } from "@vueup/vue-quill"
import "@vueup/vue-quill/dist/vue-quill.snow.css"

interface Props {
  modelValue: string
  label?: string
  placeholder?: string
  disabled?: boolean
  required?: boolean
  showErrorsOnSubmit?: boolean
  error?: boolean
  errorMessages?: string
  minHeight?: number
  theme?: "snow" | "bubble"
  contentType?: "delta" | "html" | "text"
  toolbar?: string | unknown[] | Record<string, unknown>
}

const props = withDefaults(defineProps<Props>(), {
  label: "Descripción",
  placeholder: "Escribe aquí...",
  disabled: false,
  required: false,
  showErrorsOnSubmit: false,
  error: false,
  errorMessages: "",
  minHeight: 180,
  theme: "snow",
  contentType: "html",
  toolbar: "essential",
})

const emit = defineEmits(["update:modelValue"])

const internalValue = ref(props.modelValue || "")
const localError = ref(false)
const localErrorMessage = ref("")

const hasErrorState = computed(() => props.error || localError.value)

watch(
  () => props.modelValue,
  (value) => {
    internalValue.value = value || ""
    if (props.showErrorsOnSubmit) validate()
  }
)

watch(internalValue, (value) => {
  emit("update:modelValue", value || "")
  if (!props.showErrorsOnSubmit) validate()
})

watch(
  () => props.errorMessages,
  (value) => {
    if (props.error && value) {
      localErrorMessage.value = value
    }
  },
  { immediate: true }
)

function plainTextFromHtml(html: string): string {
  return html
    .replace(/<(.|\n)*?>/g, "")
    .replace(/&nbsp;/g, " ")
    .trim()
}

function validate() {
  if (props.error && props.errorMessages) {
    localError.value = true
    localErrorMessage.value = props.errorMessages
    return false
  }

  if (props.required) {
    const text = plainTextFromHtml(internalValue.value || "")
    if (!text) {
      localError.value = true
      localErrorMessage.value = "Este campo es obligatorio"
      return false
    }
  }

  localError.value = false
  localErrorMessage.value = ""
  return true
}

defineExpose({ validate, hasErrorState })
</script>

<style scoped>
.form-quill {
  width: 100%;
}

.form-quill__label {
  display: inline-flex;
  align-items: center;
  margin-bottom: 6px;
  color: rgba(0, 0, 0, 0.87);
  font-size: 0.95rem;
  line-height: 1.2;
}

.form-quill__required {
  margin-left: 4px;
  color: rgb(var(--v-theme-error));
}

.form-quill__control {
  border: 1px solid rgba(0, 0, 0, 0.2);
  border-radius: 4px;
  background: rgb(var(--v-theme-surface));
  transition: border-color 0.2s ease;
}

.form-quill__control:focus-within {
  border-color: rgba(0, 0, 0, 0.35);
}

.form-quill--error .form-quill__control {
  border-color: rgb(var(--v-theme-error));
}

.form-quill--error .form-quill__control:focus-within {
  border-color: rgb(var(--v-theme-error));
}

.form-quill--disabled {
  opacity: 0.72;
}

.form-quill__messages {
  color: rgb(var(--v-theme-error));
  font-size: 0.75rem;
  line-height: 1.2;
  margin-top: 6px;
  padding-left: 10px;
}

.form-quill :deep(.ql-toolbar.ql-snow) {
  border: 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
  background: #f8f8f8;
  border-top-left-radius: 4px;
  border-top-right-radius: 4px;
}

.form-quill :deep(.ql-container.ql-snow) {
  border: 0;
  min-height: var(--quill-min-height);
  font-size: 0.95rem;
}

.form-quill :deep(.ql-editor) {
  min-height: var(--quill-min-height);
  color: rgba(0, 0, 0, 0.87);
}

.form-quill :deep(.ql-editor:focus),
.form-quill :deep(.ql-container.ql-snow:focus-within) {
  outline: none;
  box-shadow: none;
}

.form-quill :deep(.ql-editor.ql-blank::before) {
  color: rgba(0, 0, 0, 0.55);
  font-style: normal;
}
</style>
