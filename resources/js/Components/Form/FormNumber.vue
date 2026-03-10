<template>
  <v-text-field
    v-model="internalValue"
    :label="label"
    :rules="rules"
    :error="hasError"
    :error-messages="errorMessage"
    :disabled="disabled"
    :counter="maxLength"
    type="text"
    inputmode="numeric"
    prepend-inner-icon="mdi-numeric"
    clearable
    @keypress="onlyNumbers"
    @paste="handlePaste"
    @blur="validate"
  />
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue"

interface Props {
  modelValue: number | null
  label?: string
  disabled?: boolean
  required?: boolean
  min?: number
  max?: number
  maxLength?: number
}

const props = withDefaults(defineProps<Props>(), {
  label: "Número",
  disabled: false,
  required: false,
  min: 0,
  max: undefined,
  maxLength: undefined
})

const emit = defineEmits(["update:modelValue"])

const internalValue = ref(props.modelValue ?? "")
const errorMessage = ref("")
const hasError = ref(false)

const rules = computed(() => {
  const r: any[] = []

  if (props.required) {
    r.push((v: any) => (!!v || v === 0) || "Este campo es obligatorio")
  }

  if (props.min !== undefined) {
    r.push((v: any) => v >= props.min || `Debe ser mayor o igual a ${props.min}`)
  }

  if (props.max !== undefined) {
    r.push((v: any) => v <= props.max || `Debe ser menor o igual a ${props.max}`)
  }

  return r
})

watch(
  () => props.modelValue,
  (val) => {
    internalValue.value = val ?? ""
  }
)

watch(internalValue, (val) => {
  const numberValue = val === "" ? null : Number(val)
  emit("update:modelValue", numberValue)
})

function onlyNumbers(event: KeyboardEvent) {
  const char = String.fromCharCode(event.keyCode)
  if (!/[0-9]/.test(char)) {
    event.preventDefault()
  }
}

function handlePaste(event: ClipboardEvent) {
  const paste = event.clipboardData?.getData("text")
  if (!/^\d+$/.test(paste || "")) {
    event.preventDefault()
  }
}

function validate() {
  for (const rule of rules.value) {
    const result = rule(Number(internalValue.value))
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