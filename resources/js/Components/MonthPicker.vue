<template>
    <v-text-field
        :model-value="displayValue"
        :label="label"
        :error-messages="errorMessages"
        prepend-inner-icon="mdi-calendar-month"
        readonly
        clearable
        @click="openPicker"
        @click:clear.stop="clear"
    >
        <v-menu
            v-model="open"
            :close-on-content-click="false"
            activator="parent"
            transition="scale-transition"
            min-width="300"
        >
            <v-card min-width="300" elevation="3">
                <!-- Navegación de año -->
                <v-card-title class="d-flex align-center justify-space-between px-2 py-1">
                    <v-btn
                        icon
                        variant="text"
                        size="small"
                        :disabled="isMinYear"
                        @click.stop="prevYear"
                    >
                        <v-icon>mdi-chevron-left</v-icon>
                    </v-btn>

                    <span class="text-body-1 font-weight-semibold">{{ currentYear }}</span>

                    <v-btn
                        icon
                        variant="text"
                        size="small"
                        @click.stop="nextYear"
                    >
                        <v-icon>mdi-chevron-right</v-icon>
                    </v-btn>
                </v-card-title>

                <v-divider />

                <!-- Grid de meses -->
                <v-card-text class="pa-2">
                    <v-row dense no-gutters>
                        <v-col
                            v-for="(month, index) in MONTHS"
                            :key="index"
                            cols="3"
                            class="pa-1"
                        >
                            <v-btn
                                :variant="isSelected(index) ? 'flat' : 'tonal'"
                                :color="isSelected(index) ? 'primary' : 'default'"
                                :disabled="isDisabled(index)"
                                size="small"
                                block
                                rounded="lg"
                                @click.stop="selectMonth(index)"
                            >
                                {{ month }}
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-menu>
    </v-text-field>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";

/* ─────────────────────────────
 * CONSTANTES
 * ───────────────────────────── */
const MONTHS = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
const MONTHS_FULL = [
    "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
    "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre",
];

/* ─────────────────────────────
 * PROPS
 * ───────────────────────────── */
interface Props {
    modelValue: string;       // Formato YYYY-MM
    label?: string;
    errorMessages?: string | string[];
    min?: string;             // Formato YYYY-MM — mes mínimo seleccionable
}

const props = withDefaults(defineProps<Props>(), {
    label: "Seleccionar mes",
    errorMessages: () => [],
    min: "",
});

const emit = defineEmits(["update:modelValue"]);

/* ─────────────────────────────
 * ESTADO
 * ───────────────────────────── */
const open = ref(false);

const currentYear = ref(
    props.modelValue
        ? parseInt(props.modelValue.split("-")[0])
        : new Date().getFullYear()
);

/* ─────────────────────────────
 * COMPUTED
 * ───────────────────────────── */
const displayValue = computed(() => {
    if (!props.modelValue) return "";
    const [year, month] = props.modelValue.split("-").map(Number);
    return `${MONTHS_FULL[month - 1]} ${year}`;
});

const minYear = computed(() => {
    if (!props.min) return null;
    return parseInt(props.min.split("-")[0]);
});

const minMonth = computed(() => {
    if (!props.min) return null;
    return parseInt(props.min.split("-")[1]);
});

const isMinYear = computed(() =>
    minYear.value !== null && currentYear.value <= minYear.value
);

/* ─────────────────────────────
 * MÉTODOS
 * ───────────────────────────── */
const isSelected = (monthIndex: number): boolean => {
    if (!props.modelValue) return false;
    const [year, month] = props.modelValue.split("-").map(Number);
    return year === currentYear.value && month === monthIndex + 1;
};

const isDisabled = (monthIndex: number): boolean => {
    if (!props.min) return false;
    if (minYear.value === null || minMonth.value === null) return false;
    return (
        currentYear.value < minYear.value ||
        (currentYear.value === minYear.value && monthIndex + 1 < minMonth.value)
    );
};

const selectMonth = (monthIndex: number) => {
    const mm = String(monthIndex + 1).padStart(2, "0");
    emit("update:modelValue", `${currentYear.value}-${mm}`);
    open.value = false;
};

const prevYear = () => {
    if (!isMinYear.value) currentYear.value--;
};

const nextYear = () => {
    currentYear.value++;
};

const openPicker = () => {
    // Al abrir, posicionarse en el año del valor actual o del mínimo
    if (props.modelValue) {
        currentYear.value = parseInt(props.modelValue.split("-")[0]);
    } else if (props.min) {
        currentYear.value = parseInt(props.min.split("-")[0]);
    } else {
        currentYear.value = new Date().getFullYear();
    }
    open.value = true;
};

const clear = () => {
    emit("update:modelValue", "");
};

/* ─────────────────────────────
 * WATCHERS
 * ───────────────────────────── */
watch(() => props.modelValue, (val) => {
    if (val) currentYear.value = parseInt(val.split("-")[0]);
});
</script>
