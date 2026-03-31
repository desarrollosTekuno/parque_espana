<script setup lang="ts">
import { computed, ref } from 'vue'

type ValidationRule =
    | string
    | ((value: unknown) => boolean | string | Promise<boolean | string>)

interface Props {
    modelValue?: File[] | null
    label?: string
    hint?: string
    accept?: string
    multiple?: boolean
    disabled?: boolean
    rules?: ValidationRule[]
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    label: 'Subir archivo',
    hint: 'PDF, JPG o PNG',
    accept: '',
    multiple: false,
    disabled: false,
    rules: () => [],
})

const emit = defineEmits<{
    (e: 'update:modelValue', value: File[] | null): void
}>()

const fileInputRef = ref<HTMLInputElement | null>(null)
const isFocused = ref(false)

const internalValue = computed({
    get: () => props.modelValue,
    set: value => emit('update:modelValue', value),
})

const fileNames = computed(() => {
    const value = props.modelValue
    if (!value || !value.length) return []
    return value.map(file => file.name)
})

const hasFiles = computed(() => fileNames.value.length > 0)

const openPicker = () => {
    if (props.disabled) return
    fileInputRef.value?.click()
}

const onFilesChange = (event: Event) => {
    const target = event.target as HTMLInputElement
    const files = target.files ? Array.from(target.files) : []

    if (props.multiple) {
        internalValue.value = files
    } else {
        internalValue.value = files.length ? [files[0]] : []
    }

    target.value = ''
}

const removeFile = (index: number) => {
    if (!internalValue.value) return

    const updatedFiles = [...internalValue.value]
    updatedFiles.splice(index, 1)
    internalValue.value = updatedFiles.length ? updatedFiles : []
}

const clearFiles = () => {
    internalValue.value = []
}
</script>

<template>
    <v-input
        v-model="internalValue"
        :rules="rules"
        :disabled="disabled"
        hide-details="auto"
    >
        <template #default="{ isValid, messages }">
            <div class="w-100">
                <input
                    ref="fileInputRef"
                    type="file"
                    class="d-none"
                    :accept="accept"
                    :multiple="multiple"
                    :disabled="disabled"
                    @change="onFilesChange"
                    @focus="isFocused = true"
                    @blur="isFocused = false"
                >

                <div
                    class="upload-box"
                    :class="{
                        'upload-box--error': isValid === false,
                        'upload-box--focused': isFocused,
                        'upload-box--disabled': disabled,
                        'upload-box--success': hasFiles && isValid !== false,
                    }"
                    @click="openPicker"
                >
                    <div class="upload-box__content">
                        <v-icon
                            size="34"
                            class="mb-2"
                            :color="hasFiles ? 'success' : undefined"
                        >
                            {{ hasFiles ? 'mdi-check-circle-outline' : 'mdi-upload' }}
                        </v-icon>

                        <div v-if="label" class="upload-box__label">
                            {{ label }}
                        </div>

                        <div
                            v-if="hasFiles"
                            class="upload-box__status upload-box__status--success"
                        >
                            Cargado
                        </div>

                        <div v-else class="upload-box__hint">
                            {{ hint }}
                        </div>
                    </div>
                </div>

                <div v-if="fileNames.length" class="mt-3">
                    <div
                        v-for="(fileName, index) in fileNames"
                        :key="`${fileName}-${index}`"
                        class="file-row"
                    >
                        <span class="text-body-2">{{ fileName }}</span>

                        <v-btn
                            size="small"
                            color="error"
                            variant="text"
                            @click.stop="removeFile(index)"
                        >
                            Eliminar
                        </v-btn>
                    </div>

                    <div class="d-flex ga-2 mt-2">
                        <v-btn
                            size="small"
                            variant="outlined"
                            @click="openPicker"
                        >
                            Reemplazar
                        </v-btn>

                        <v-btn
                            size="small"
                            color="error"
                            variant="outlined"
                            @click="clearFiles"
                        >
                            Borrar todo
                        </v-btn>
                    </div>
                </div>

                <div
                    v-if="messages?.length"
                    class="text-error text-caption mt-1"
                >
                    {{ messages[0] }}
                </div>
            </div>
        </template>
    </v-input>
</template>

<style scoped>
.upload-box {
    border: 2px dashed #d1d5db;
    border-radius: 16px;
    min-height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: #f8fafc;
    transition: all 0.2s ease;
    padding: 20px;
    text-align: center;
}

.upload-box:hover {
    border-color: #94a3b8;
    background: #f1f5f9;
}

.upload-box--focused {
    border-color: #1976d2;
}

.upload-box--error {
    border-color: rgb(var(--v-theme-error));
    background: rgba(var(--v-theme-error), 0.04);
}

.upload-box--success {
    border-color: rgb(var(--v-theme-success));
    background: rgba(var(--v-theme-success), 0.06);
}

.upload-box--disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.upload-box__content {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #6b7280;
}

.upload-box__label {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
}

.upload-box__hint {
    font-size: 14px;
}

.upload-box__status {
    font-size: 14px;
    font-weight: 600;
}

.upload-box__status--success {
    color: rgb(var(--v-theme-success));
}

.file-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 6px 0;
}
</style>