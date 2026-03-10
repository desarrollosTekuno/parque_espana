<template>
  <v-textarea
    v-model="internalValue"
    :label="label"
    :rules="rules"
    :error="hasError"
    :error-messages="errorMessage"
    :disabled="disabled"
    :counter="maxLength"
    :rows="rows"
    :auto-grow="autoGrow"
    prepend-inner-icon="mdi-text-box-outline"
    clearable
    @blur="validate"
  />
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue"
import { required, minLength, maxLength } from "@/constants/validationRules"

interface Props {
  modelValue: string
  label?: string
  disabled?: boolean
  required?: boolean
  minLength?: number
  maxLength?: number
  rows?: number
  autoGrow?: boolean
  showErrorsOnSubmit?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: "Descripción",
  disabled: false,
  required: true,
  minLength: 10,
  maxLength: 300,
  rows: 3,
  autoGrow: true,
  showErrorsOnSubmit: false,
})

const emit = defineEmits(["update:modelValue"])

const internalValue = ref(props.modelValue || "")
const errorMessage = ref("")
const hasError = ref(false)

const rules = computed(() => {
  const r: any[] = []

  // required solo si se solicita
  if (props.required) {
    r.push(required)
  }

  // minLength solo si hay texto
  if (props.minLength) {
    r.push((v: string) => {
      if (!v) return true
      return v.length >= props.minLength || `Debe tener al menos ${props.minLength} caracteres`
    })
  }

  // maxLength solo si hay texto
  if (props.maxLength) {
    r.push((v: string) => {
      if (!v) return true
      return v.length <= props.maxLength || `Debe tener máximo ${props.maxLength} caracteres`
    })
  }

  return r
})

watch(
  () => props.modelValue,
  (val) => {
    internalValue.value = val
    if (props.showErrorsOnSubmit) validate()
  }
)

watch(internalValue, (val) => {
  emit("update:modelValue", val)
  if (!props.showErrorsOnSubmit) validate()
})

function validate() {
  for (const rule of rules.value) {
    const result = rule(internalValue.value)
    if (result !== true) {
      hasError.value = true
      errorMessage.value = result
      return false
    }
  }

  hasError.value = false
  errorMessage.value = ""
  return true
}

defineExpose({ validate, hasError })
</script>