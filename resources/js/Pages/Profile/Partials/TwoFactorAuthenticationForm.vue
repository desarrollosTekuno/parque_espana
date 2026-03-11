<script setup>
import { ref, computed } from "vue"
import { router, useForm, usePage } from "@inertiajs/vue3"

const page = usePage()

const habilitando = ref(false)
const confirmando = ref(false)
const qrCode = ref(null)
const claveSecreta = ref(null)
const codigosRecuperacion = ref([])

const form = useForm({
  code: "",
})

const dosFactoresActivo = computed(() =>
  page.props.auth.user?.two_factor_enabled
)

const activarDosFactores = () => {
  habilitando.value = true

  router.post(route("two-factor.enable"), {}, {
    onSuccess: async () => {
      await obtenerQr()
      await obtenerClave()
      await obtenerCodigos()
      confirmando.value = true
    },
    onFinish: () => habilitando.value = false,
  })
}

const obtenerQr = async () => {
  const response = await axios.get(route("two-factor.qr-code"))
  qrCode.value = response.data.svg
}

const obtenerClave = async () => {
  const response = await axios.get(route("two-factor.secret-key"))
  claveSecreta.value = response.data.secretKey
}

const obtenerCodigos = async () => {
  const response = await axios.get(route("two-factor.recovery-codes"))
  codigosRecuperacion.value = response.data
}

const confirmarCodigo = () => {
  form.post(route("two-factor.confirm"), {
    onSuccess: () => {
      confirmando.value = false
      qrCode.value = null
      claveSecreta.value = null
      router.reload({ only: ["auth"] })
    },
  })
}

const desactivarDosFactores = () => {
  router.delete(route("two-factor.disable"), {
    onSuccess: () => router.reload({ only: ["auth"] }),
  })
}
</script>

<template>
  <v-card class="pa-6">
    <v-card-title>
      Autenticación en Dos Factores
    </v-card-title>

    <v-card-subtitle class="mb-4">
      Agrega una capa adicional de seguridad a tu cuenta.
    </v-card-subtitle>

    <v-card-text>

      <div v-if="!dosFactoresActivo">
        <v-btn
          color="primary"
          :loading="habilitando"
          @click="activarDosFactores"
        >
          Activar autenticación en dos factores
        </v-btn>
      </div>

      <div v-else>
        <v-alert
          type="success"
          variant="tonal"
          class="mb-4"
        >
          La autenticación en dos factores está activa.
        </v-alert>

        <v-btn
          color="error"
          @click="desactivarDosFactores"
        >
          Desactivar
        </v-btn>
      </div>

      <div v-if="qrCode" class="mt-6">
        <v-alert type="info" variant="tonal" class="mb-4">
          Escanea el código QR con tu aplicación autenticadora.
        </v-alert>

        <div v-html="qrCode" class="mb-4"></div>

        <v-text-field
          v-model="form.code"
          label="Código de verificación"
          prepend-inner-icon="mdi-shield-key"
          class="mb-4"
        />

        <v-btn
          color="primary"
          @click="confirmarCodigo"
        >
          Confirmar código
        </v-btn>
      </div>

      <div v-if="codigosRecuperacion.length" class="mt-6">
        <v-alert type="warning" variant="tonal" class="mb-4">
          Guarda estos códigos de recuperación en un lugar seguro.
        </v-alert>

        <v-list>
          <v-list-item
            v-for="codigo in codigosRecuperacion"
            :key="codigo"
          >
            {{ codigo }}
          </v-list-item>
        </v-list>
      </div>

    </v-card-text>
  </v-card>
</template>