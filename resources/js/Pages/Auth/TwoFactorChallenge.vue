<script setup>
import { ref, nextTick } from "vue"
import { Head, useForm } from "@inertiajs/vue3"

const usandoCodigoRecuperacion = ref(false)

const formRef = ref(null)
const codigoInput = ref(null)
const codigoRecuperacionInput = ref(null)

const form = useForm({
  code: "",
  recovery_code: "",
})

const alternarModo = async () => {
  usandoCodigoRecuperacion.value = !usandoCodigoRecuperacion.value

  await nextTick()

  if (usandoCodigoRecuperacion.value) {
    form.code = ""
    codigoRecuperacionInput.value?.focus()
  } else {
    form.recovery_code = ""
    codigoInput.value?.focus()
  }
}

const enviar = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  form.post(route("two-factor.login"))
}
</script>

<template>
  <Head title="Confirmación en dos factores" />

  <div class="min-h-screen flex items-center justify-center auth-bg">
    <v-card width="500" class="pa-6">

      <v-card-title class="text-h6 text-center">
        Confirmación en dos factores
      </v-card-title>

      <v-card-text>

        <v-alert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          <template v-if="!usandoCodigoRecuperacion">
            Ingresa el código generado por tu aplicación autenticadora.
          </template>
          <template v-else>
            Ingresa uno de tus códigos de recuperación de emergencia.
          </template>
        </v-alert>

        <v-form ref="formRef" @submit.prevent="enviar">

          <!-- Código normal -->
          <v-text-field
            v-if="!usandoCodigoRecuperacion"
            ref="codigoInput"
            v-model="form.code"
            label="Código de autenticación"
            prepend-inner-icon="mdi-shield-key"
            inputmode="numeric"
            variant="outlined"
            :error-messages="form.errors.code"
            required
            autofocus
            class="mb-4"
          />

          <!-- Código recuperación -->
          <v-text-field
            v-else
            ref="codigoRecuperacionInput"
            v-model="form.recovery_code"
            label="Código de recuperación"
            prepend-inner-icon="mdi-key"
            variant="outlined"
            :error-messages="form.errors.recovery_code"
            required
            class="mb-4"
          />

          <div class="d-flex justify-space-between align-center">

            <v-btn
              variant="text"
              @click="alternarModo"
            >
              <template v-if="!usandoCodigoRecuperacion">
                Usar código de recuperación
              </template>
              <template v-else>
                Usar código de autenticación
              </template>
            </v-btn>

            <v-btn
              color="primary"
              type="submit"
              :loading="form.processing"
              :disabled="form.processing"
            >
              Iniciar sesión
            </v-btn>

          </div>

        </v-form>

      </v-card-text>
    </v-card>
  </div>
</template>
<style scoped>
.auth-bg {
    background: linear-gradient(180deg, #0B5A8C 0%, #031826 100%) !important;
}
</style>