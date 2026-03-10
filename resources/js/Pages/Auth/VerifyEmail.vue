<script setup>
import { computed } from "vue"
import { Head, Link, useForm } from "@inertiajs/vue3"

const props = defineProps({
  status: String,
})

const form = useForm({})

const reenviarVerificacion = () => {
  form.post(route("verification.send"))
}

const enlaceEnviado = computed(
  () => props.status === "verification-link-sent"
)
</script>

<template>
  <Head title="Verificación de correo" />

  <v-container class="fill-height d-flex align-center justify-center">
    <v-card width="500" class="pa-6">

      <v-card-title class="text-h6 text-center">
        Verificación de correo electrónico
      </v-card-title>

      <v-card-text>

        <v-alert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          Antes de continuar, verifica tu correo electrónico
          haciendo clic en el enlace que te enviamos.
          Si no lo recibiste, puedes solicitar uno nuevo.
        </v-alert>

        <v-alert
          v-if="enlaceEnviado"
          type="success"
          variant="tonal"
          class="mb-4"
        >
          Se ha enviado un nuevo enlace de verificación
          a tu correo electrónico.
        </v-alert>

        <v-form @submit.prevent="reenviarVerificacion">

          <v-btn
            block
            color="primary"
            :loading="form.processing"
            :disabled="form.processing"
            type="submit"
            class="mb-4"
          >
            Reenviar correo de verificación
          </v-btn>

          <div class="d-flex justify-space-between">

            <Link
              :href="route('profile.show')"
              class="text-decoration-none"
            >
              <v-btn variant="text">
                Editar perfil
              </v-btn>
            </Link>

            <Link
              :href="route('logout')"
              method="post"
              as="button"
            >
              <v-btn variant="text" color="error">
                Cerrar sesión
              </v-btn>
            </Link>

          </div>

        </v-form>

      </v-card-text>
    </v-card>
  </v-container>
</template>