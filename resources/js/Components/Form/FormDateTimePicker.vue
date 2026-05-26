<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';

interface Props {
  modelValue: string | null;
  label?: string;
  error?: boolean;
  errorMessages?: string | string[];
  rules?: any[];
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Fecha y hora',
  error: false,
  errorMessages: () => [],
  rules: () => []
});

const emit = defineEmits(['update:modelValue']);

const date = ref('');
const time = ref('');

const dateInputRef = ref<HTMLInputElement | null>(null);

// ✅ HOY
const today = (() => {
  const d = new Date();
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0')
  ].join('-');
})();

// ✅ HORA ACTUAL
const nowTime = computed(() => {
  const d = new Date();
  return [
    String(d.getHours()).padStart(2, '0'),
    String(d.getMinutes()).padStart(2, '0')
  ].join(':');
});

// 👉 sync inicial
watch(
  () => props.modelValue,
  (val) => {
    if (!val || !val.includes(' ')) {
      date.value = '';
      time.value = '';
      return;
    }

    const [d, t] = val.split(' ');
    date.value = d;
    time.value = t.slice(0, 5);
  },
  { immediate: true }
);

// 🔥 AUTO AJUSTE CUANDO CAMBIA FECHA
watch(date, (newDate) => {
  if (!newDate) return;

  // ❌ evitar fechas pasadas incluso si las meten a mano
  if (newDate < today) {
    date.value = today;
  }

  // 🔥 si es hoy → ajustar hora mínima
  if (newDate === today && !time.value) {
    const current = nowTime.value;

    if (!time.value || time.value < current) {
      time.value = current;
    }
  }
});

// 🔥 VALIDACIÓN
const isValid = computed(() => {
  if (!date.value || !time.value) return false;

  if (date.value === today) {
    return time.value >= nowTime.value;
  }

  return true;
});

// 🔥 EMIT AUTOMÁTICO (SIN BOTÓN)
watch([date, time], () => {
  if (!isValid.value) return;

  emit('update:modelValue', `${date.value} ${time.value}:00`);
});

// 👉 abrir calendario SIEMPRE
const openCalendar = async () => {
  await nextTick();
  dateInputRef.value?.showPicker?.(); // Chrome/Edge
  dateInputRef.value?.focus();
};

// 👉 formato bonito
const formatted = computed(() => {
  if (!date.value || !time.value) return '';

  const [y, m, d] = date.value.split('-');

  const meses = [
    'enero','febrero','marzo','abril','mayo','junio',
    'julio','agosto','septiembre','octubre','noviembre','diciembre'
  ];

  return `${d} de ${meses[Number(m)-1]} ${y} · ${time.value}`;
});

// limpiar
const clear = () => {
  date.value = '';
  time.value = '';
  emit('update:modelValue', null);
};

const onTimeInput = (e: Event) => {
  const value = (e.target as HTMLInputElement).value;

  // permite vacío
  if (!value) return;

  // valida formato HH:MM básico
  const match = value.match(/^([0-1]\d|2[0-3]):([0-5]\d)$/);

  if (!match) {
    time.value = '';
    return;
  }

  time.value = value;
};
</script>

<template>
  <!-- INPUT PRINCIPAL -->
  <!-- <v-text-field
    :label="label"
    :model-value="formatted"
    readonly
    prepend-inner-icon="mdi-calendar-clock"
    clearable
    @click="openCalendar"
    @click:clear="clear"
    :error="error"
    :error-messages="errorMessages"
    :rules="rules"
  /> -->

  <!-- INLINE PICKER -->
  <div class="d-flex ga-3 mt-2">

    <!-- 📅 FECHA -->
    <input
      ref="dateInputRef"
      v-model="date"
      type="date"
      :min="today"
      class="ios-input"
      @keydown.prevent
    />

    <!-- ⏰ HORA -->
    <input
      v-model="time"
      type="time"
      :min="date === today ? nowTime : '00:00'"
      class="ios-input"
      @input="onTimeInput"
    />

  </div>

  <!-- ERROR -->
  <v-alert
    v-if="date && time && !isValid"
    type="error"
    density="compact"
    class="mt-2"
  >
    No puedes seleccionar una hora pasada
  </v-alert>
</template>

<style scoped>
.ios-input {
  border: 1px solid #ddd;
  border-radius: 12px;
  padding: 10px 12px;
  font-size: 14px;
  outline: none;
  transition: all 0.2s ease;
}

.ios-input:focus {
  border-color: #1976d2;
  box-shadow: 0 0 0 2px rgba(25,118,210,0.15);
}
</style>