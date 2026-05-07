<script setup>
import { Head, useForm } from "@inertiajs/vue3"
import { ref } from "vue"
import { required, passwordRule, confirmPasswordRule } from "@/constants/validationRules"

const props = defineProps({
  email: String,
  token: String,
})

const formRef = ref(null)

const form = useForm({
  token: props.token,
  email: props.email,
  password: "",
  password_confirmation: "",
})

const restablecerContrasena = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  form.post(route("password.update"), {
    onFinish: () => form.reset("password", "password_confirmation"),
  })
}
</script>

<template>
  <Head title="Restablecer contraseña" />

  <div class="min-h-screen flex items-center justify-center auth-bg">
    <v-card width="500" class="pa-6">

      <v-card-title class="text-h6 text-center">
        Restablecer contraseña
      </v-card-title>

      <v-card-text>

        <v-alert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          Ingresa tu nueva contraseña para completar el proceso.
        </v-alert>

        <v-form ref="formRef" @submit.prevent="restablecerContrasena">

          <!-- Correo -->
          <v-text-field
            v-model="form.email"
            label="Correo electrónico"
            type="email"
            prepend-inner-icon="mdi-email-outline"
            variant="outlined"
            :error-messages="form.errors.email"
            :rules="[required]"
            required
            class="mb-4"
          />

          <!-- Nueva contraseña -->
          <v-text-field
            v-model="form.password"
            label="Nueva contraseña"
            type="password"
            prepend-inner-icon="mdi-lock-outline"
            variant="outlined"
            :error-messages="form.errors.password"
            :rules="[required, passwordRule]"
            required
            class="mb-4"
          />

          <!-- Confirmar contraseña -->
          <v-text-field
            v-model="form.password_confirmation"
            label="Confirmar nueva contraseña"
            type="password"
            prepend-inner-icon="mdi-lock-check"
            variant="outlined"
            :error-messages="form.errors.password_confirmation"
            :rules="[required, confirmPasswordRule(form.password)]"
            required
            class="mb-6"
          />

          <v-btn
            block
            color="primary"
            type="submit"
            :loading="form.processing"
            :disabled="form.processing"
          >
            Restablecer contraseña
          </v-btn>

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
