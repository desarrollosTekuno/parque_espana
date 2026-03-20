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
              :min="props.min ? minDateISO : null"
              :max="props.max ? maxDateISO : null"
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

// 🔹 Recalcula automáticamente si cambian las props
const minDateISO = computed(() => {
  const min = props.min ?? today;
  return min.toISOString().split("T")[0];
});

const maxDateISO = computed(() => {
  const max = props.max ?? today;
  return max.toISOString().split("T")[0];
});

const emit = defineEmits(["update:modelValue"]);
const internalValue = ref<Date | null>(
  props.modelValue ? new Date(props.modelValue) : null
);
const showPicker = ref(false);

/* ─────────────────────────────
 * REACTIVIDAD
 * ───────────────────────────── */
watch(
  () => props.modelValue,
  (val) => {
    internalValue.value = val ? new Date(val) : null;
  }
);

/* ─────────────────────────────
 * EVENTOS
 * ───────────────────────────── */
function updateValue(val: Date | null) {
  if (!val) return;

  const formatted = new Date(
    val.getTime() - val.getTimezoneOffset() * 60000
  )
    .toISOString()
    .split("T")[0];

  emit("update:modelValue", formatted);
  showPicker.value = false;
}

/* ─────────────────────────────
 * FORMATO DE FECHA LEGIBLE
 * ───────────────────────────── */
const formattedDate = computed(() => {
  if (!internalValue.value) return "";

  return internalValue.value.toLocaleDateString("es-MX", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
});
</script>
