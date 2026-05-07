<template>
  <v-container class="px-0 py-0">
    <v-row justify="space-around">
      <v-col cols="12">

        <v-text-field
          :model-value="modelValue"
          variant="outlined"
          density="compact"
          hide-details="auto"
          :label="labelMenu"
          v-bind="showIcon ? { 'prepend-icon': 'mdi-clock-time-four-outline' } : {}"
          :error="error"
          :error-messages="errorMessages"
          readonly
          @click="openMenu"
          :rules="rules"
        >

          <v-menu
            v-model="showMenu"
            :close-on-content-click="false"
            activator="parent"
            min-width="0"
          >

            <v-card>

              <v-time-picker
                v-model="internalValue"
              />

              <v-card-actions>
                <v-spacer/>

                <v-btn
                  variant="text"
                  @click="cancel"
                >
                  Cancelar
                </v-btn>

                <v-btn
                  color="primary"
                  variant="flat"
                  @click="confirm"
                >
                  Confirmar
                </v-btn>

              </v-card-actions>

            </v-card>

          </v-menu>

        </v-text-field>

      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, watch } from 'vue'

  const props = defineProps({
    modelValue: String,
    labelMenu: {
      type: String,
      default: 'Seleccionar hora',
    },
    showIcon: {
      type: Boolean,
      default: false,
    },
    rules: {
      type: Array,
      default: () => [],
    },
    error: {
      type: Boolean,
      default: false
    },
    errorMessages: {
      type: [String, Array],
      default: ''
    }
    })

  const emit = defineEmits(['update:modelValue'])

  const internalValue = ref(props.modelValue)
  const showMenu = ref(false)

  watch(
    () => props.modelValue,
    (val) => {
      internalValue.value = val
    }
  )

  function openMenu() {
    internalValue.value = props.modelValue
    showMenu.value = true
  }

  function confirm() {
    emit('update:modelValue', internalValue.value)
    showMenu.value = false
  }

  function cancel() {
    internalValue.value = props.modelValue
    showMenu.value = false
  }
</script>
