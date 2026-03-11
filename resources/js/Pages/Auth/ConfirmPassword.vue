<script setup>
import { ref } from "vue"
import { Head, useForm } from "@inertiajs/vue3"

const formRef = ref(null)
const passwordInput = ref(null)

const form = useForm({
  password: "",
})

const confirmar = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  form.post(route("password.confirm"), {
    onFinish: () => {
      form.reset()
      passwordInput.value?.focus()
    },
  })
}
</script>

<template>
  <Head title="Área segura" />

  <v-container class="fill-height d-flex align-center justify-center">
    <v-card width="450" class="pa-6">

      <v-card-title class="text-h6 text-center">
        Confirmación de seguridad
      </v-card-title>

      <v-card-text>

        <v-alert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          Esta es un área segura de la aplicación.
          Por favor confirma tu contraseña antes de continuar.
        </v-alert>

        <v-form ref="formRef" @submit.prevent="confirmar">

          <v-text-field
            ref="passwordInput"
            v-model="form.password"
            type="password"
            label="Contraseña"
            prepend-inner-icon="mdi-lock"
            variant="outlined"
            :error-messages="form.errors.password"
            required
            autofocus
            class="mb-4"
          />

          <v-btn
            block
            color="primary"
            type="submit"
            :loading="form.processing"
            :disabled="form.processing"
          >
            Confirmar
          </v-btn>

        </v-form>

      </v-card-text>
    </v-card>
  </v-container>
</template>