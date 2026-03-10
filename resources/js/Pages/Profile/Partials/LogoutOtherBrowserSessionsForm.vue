<script setup>
import { ref } from "vue"
import { useForm } from "@inertiajs/vue3"

defineProps({
  sessions: Array,
})

const mostrandoConfirmacion = ref(false)
const formRef = ref(null)

const form = useForm({
  password: "",
})

const abrirConfirmacion = () => {
  mostrandoConfirmacion.value = true
}

const cerrarConfirmacion = () => {
  mostrandoConfirmacion.value = false
  form.reset()
}

const cerrarOtrasSesiones = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  form.delete(route("other-browser-sessions.destroy"), {
    preserveScroll: true,
    onSuccess: () => {
      cerrarConfirmacion()
    },
    onFinish: () => form.reset(),
  })
}
</script>

<template>
  <v-card class="pa-6">
    <v-card-title>
      Sesiones Activas
    </v-card-title>

    <v-card-subtitle class="mb-4">
      Administra y cierra sesión en otros dispositivos donde tu cuenta esté activa.
    </v-card-subtitle>

    <v-card-text>

      <v-alert
        type="info"
        variant="tonal"
        class="mb-6"
      >
        Si crees que tu cuenta ha sido comprometida, te recomendamos cambiar tu contraseña.
      </v-alert>

      <!-- Lista de sesiones -->
      <v-list v-if="sessions.length">
        <v-list-item
          v-for="(session, i) in sessions"
          :key="i"
        >
          <template #prepend>
            <v-icon size="32">
              {{ session.agent.is_desktop ? 'mdi-monitor' : 'mdi-cellphone' }}
            </v-icon>
          </template>

          <v-list-item-title>
            {{ session.agent.platform || 'Desconocido' }} -
            {{ session.agent.browser || 'Desconocido' }}
          </v-list-item-title>

          <v-list-item-subtitle>
            {{ session.ip_address }}
            <span
              v-if="session.is_current_device"
              class="text-success font-weight-bold"
            >
              • Este dispositivo
            </span>
            <span v-else>
              • Última actividad: {{ session.last_active }}
            </span>
          </v-list-item-subtitle>
        </v-list-item>
      </v-list>

      <!-- Botón acción -->
      <div class="mt-6 d-flex align-center">
        <v-btn
          color="error"
          @click="abrirConfirmacion"
        >
          Cerrar otras sesiones
        </v-btn>

        <v-alert
          v-if="form.recentlySuccessful"
          type="success"
          variant="tonal"
          class="ms-4"
          density="compact"
        >
          Sesiones cerradas correctamente
        </v-alert>
      </div>

    </v-card-text>
  </v-card>

  <!-- Modal de confirmación -->
  <v-dialog v-model="mostrandoConfirmacion" max-width="500">
    <v-card>
      <v-card-title>
        Confirmar cierre de sesiones
      </v-card-title>

      <v-card-text>
        Para cerrar las sesiones activas en otros dispositivos,
        ingresa tu contraseña.

        <v-form ref="formRef" class="mt-4">
          <v-text-field
            v-model="form.password"
            type="password"
            label="Contraseña"
            prepend-inner-icon="mdi-lock"
            :error-messages="form.errors.password"
            required
          />
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer />

        <v-btn
          variant="text"
          @click="cerrarConfirmacion"
        >
          Cancelar
        </v-btn>

        <v-btn
          color="error"
          :loading="form.processing"
          :disabled="form.processing"
          @click="cerrarOtrasSesiones"
        >
          Confirmar
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>