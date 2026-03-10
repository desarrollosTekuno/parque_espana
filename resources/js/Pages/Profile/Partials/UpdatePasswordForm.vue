<script setup>
import { ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import { required, passwordRule, confirmPasswordRule } from "@/constants/validationRules"

const formRef = ref(null)

const form = useForm({
  current_password: "",
  password: "",
  password_confirmation: "",
})

const actualizarContrasena = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  form.put(route("user-password.update"), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
    onError: () => {
      if (form.errors.password) {
        form.reset("password", "password_confirmation")
      }
      if (form.errors.current_password) {
        form.reset("current_password")
      }
    },
  })
}
</script>

<template>
  <v-card class="pa-6">
    <v-card-title>
      Actualizar Contraseña
    </v-card-title>

    <v-card-subtitle class="mb-4">
      Asegúrate de utilizar una contraseña segura y difícil de adivinar.
    </v-card-subtitle>

    <v-form ref="formRef" @submit.prevent="actualizarContrasena">
      
      <v-text-field
        v-model="form.current_password"
        :rules="[required]"
        type="password"
        label="Contraseña actual"
        prepend-inner-icon="mdi-lock"
        :error-messages="form.errors.current_password"
        class="mb-4"
      />

      <v-text-field
        v-model="form.password"
        :rules="[required, passwordRule]"
        type="password"
        label="Nueva contraseña"
        prepend-inner-icon="mdi-lock-outline"
        :error-messages="form.errors.password"
        class="mb-4"
      />

      <v-text-field
        v-model="form.password_confirmation"
        :rules="[required, confirmPasswordRule(form.password)]"
        type="password"
        label="Confirmar nueva contraseña"
        prepend-inner-icon="mdi-lock-check"
        :error-messages="form.errors.password_confirmation"
        class="mb-6"
      />

      <div class="d-flex align-center">
        <v-btn
          color="primary"
          type="submit"
          :loading="form.processing"
          :disabled="form.processing"
        >
          Guardar cambios
        </v-btn>

        <v-alert
          v-if="form.recentlySuccessful"
          type="success"
          variant="tonal"
          class="ms-4"
          density="compact"
        >
          Contraseña actualizada correctamente
        </v-alert>
      </div>

    </v-form>
  </v-card>
</template>