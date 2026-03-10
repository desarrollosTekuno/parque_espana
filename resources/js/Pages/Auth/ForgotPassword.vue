<script setup>
import { Head, useForm } from "@inertiajs/vue3"
import { computed } from "vue"

defineProps({
  status: String,
})

const form = useForm({
  email: "",
})

const enviarEnlace = () => {
  form.post(route("password.email"))
}
</script>

<template>
  <Head title="Recuperar contraseña" />

  <v-container class="fill-height d-flex align-center justify-center">
    <v-card width="500" class="pa-6">

      <v-card-title class="text-h6 text-center">
        Recuperar contraseña
      </v-card-title>

      <v-card-text>

        <v-alert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          ¿Olvidaste tu contraseña?  
          Ingresa tu correo electrónico y te enviaremos un enlace
          para restablecerla.
        </v-alert>

        <v-alert
          v-if="status"
          type="success"
          variant="tonal"
          class="mb-4"
        >
          {{ status }}
        </v-alert>

        <v-form @submit.prevent="enviarEnlace">

          <v-text-field
            v-model="form.email"
            label="Correo electrónico"
            type="email"
            prepend-inner-icon="mdi-email-outline"
            variant="outlined"
            :error-messages="form.errors.email"
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
            Enviar enlace de recuperación
          </v-btn>

        </v-form>

      </v-card-text>
    </v-card>
  </v-container>
</template>