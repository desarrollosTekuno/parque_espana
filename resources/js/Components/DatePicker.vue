<template>
  <v-container class="px-0 py-0">
    <v-row justify="space-around">
      <v-col cols="12">
        <v-text-field
          :prepend-inner-icon="showIcon ? icon : null"
          prepend-icon=""
          :model-value="formattedDate"
          :label="label"
          :rules="props.rules"
          readonly
          @click="showPicker = true"
        >
          <v-menu
            v-model="showPicker"
            :close-on-content-click="false"
            activator="parent"
            transition="scale-transition"
            min-width="auto"
          >
            <v-date-picker
              v-model="internalValue"
              :min="minDate"
              :max="maxDate"
              @update:model-value="updateValue"
            />
          </v-menu>
        </v-text-field>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";

/* ─────────────────────────────
 * PROPIEDADES
 * ───────────────────────────── */
interface Props {
  modelValue: string;
  label?: string;
  showIcon: boolean;
  icon?: string;
  rules?: any;
  min?: Date; // ← opcionales para mayor flexibilidad
  max?: Date;
}

const props = withDefaults(defineProps<Props>(), {
  label: "Seleccione fecha",
  showIcon: false,
  icon: "mdi-calendar-month-outline",
  rules: [],
});

const today = new Date();
today.setHours(0, 0, 0, 0);

function formatLocalDate(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function parseLocalDate(dateString: string): Date {
  const [year, month, day] = dateString.split("-").map(Number);
  return new Date(year, month - 1, day); // 👈 LOCAL, no UTC
}

// 🔹 Recalcula automáticamente si cambian las props
const minDate = computed(() => {
  const min = props.min ?? today
  return new Date(min.getFullYear(), min.getMonth(), min.getDate())
})

const maxDate = computed(() => {
  const max = props.max ?? today
  return new Date(max.getFullYear(), max.getMonth(), max.getDate())
})

const emit = defineEmits(["update:modelValue"]);
const internalValue = ref<Date | null>(null);(
  props.modelValue ? parseLocalDate(props.modelValue) : null
);
const showPicker = ref(false);

/* ─────────────────────────────
 * REACTIVIDAD
 * ───────────────────────────── */
watch(
  () => props.modelValue,
  (val) => {
    internalValue.value = val ? parseLocalDate(val) : null;
  }
);

/* ─────────────────────────────
 * EVENTOS
 * ───────────────────────────── */
function updateValue(val: Date | null) {
  if (!val) return;

  emit("update:modelValue", formatLocalDate(val));
  showPicker.value = false;
}

/* ─────────────────────────────
 * FORMATO DE FECHA LEGIBLE
 * ───────────────────────────── */
const formattedDate = computed(() => {
  if (!internalValue.value) return "";

  const d = internalValue.value;

  // Creamos fecha LOCAL (no UTC)
  const date = new Date(
    d.getFullYear(),
    d.getMonth(),
    d.getDate()
  );

  return date.toLocaleDateString("es-MX", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
});
console.log('MIN PROP:', props.min)
console.log('MAX PROP:', props.max)
console.log('MIN DATE:', minDate.value)
console.log('MAX DATE:', maxDate.value)
</script>
