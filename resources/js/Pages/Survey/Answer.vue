<script setup lang="ts">
import { useForm, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { Head } from "@inertiajs/vue3";

interface Option    { id: number; option_text: string; order: number; }
interface Question  { id: number; question_text: string; type: string; is_required: boolean; config: Record<string, any> | null; options: Option[]; }
interface Survey    { id: number; title: string; description: string | null; questions: Question[]; slug: string; }

interface Props {
    survey?: Survey | null;
    tokenStr?: string;
    error?: string | null;
    already_answered?: boolean;
    submitted?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    survey:           null,
    tokenStr:         "",
    error:            null,
    already_answered: false,
    submitted:        false,
});

// Construir estado de respuestas
const answerMap = ref<Record<number, { answer_text: string | null; answer_options: number[] }>>({});

if (props.survey) {
    for (const q of props.survey.questions) {
        answerMap.value[q.id] = { answer_text: null, answer_options: [] };
    }
}

const formRef = ref<any>(null);
const submitting = ref(false);
const form = useForm({ token: props.tokenStr, answers: [] as any[] });

const ratingRange = (q: Question): number[] => {
    const min = q.config?.min ?? 1;
    const max = q.config?.max ?? 5;
    return Array.from({ length: max - min + 1 }, (_, i) => min + i);
};

const toggleOption = (questionId: number, optionId: number) => {
    const ans = answerMap.value[questionId];
    const idx = ans.answer_options.indexOf(optionId);
    if (idx === -1) {
        ans.answer_options.push(optionId);
    } else {
        ans.answer_options.splice(idx, 1);
    }
};

const setSingleOption = (questionId: number, optionId: number) => {
    answerMap.value[questionId].answer_options = [optionId];
};

const setRating = (questionId: number, val: number) => {
    answerMap.value[questionId].answer_text = String(val);
};

const requiredRule = (q: Question) => {
    if (!q.is_required) return true;
    const ans = answerMap.value[q.id];
    if (q.type === "open_text")  return !!ans.answer_text?.trim() || "Este campo es obligatorio";
    if (q.type === "rating")     return !!ans.answer_text || "Selecciona una valoración";
    return (ans.answer_options.length > 0) || "Selecciona al menos una opción";
};

const validate = (): boolean => {
    for (const q of props.survey?.questions ?? []) {
        if (!q.is_required) continue;
        const ans = answerMap.value[q.id];
        if (q.type === "open_text" && !ans.answer_text?.trim()) return false;
        if (q.type === "rating"    && !ans.answer_text)          return false;
        if (["single_choice","multiple_choice"].includes(q.type) && ans.answer_options.length === 0) return false;
    }
    return true;
};

const submitForm = () => {
    if (!validate()) {
        showErrors.value = true;
        return;
    }

    const answers = (props.survey?.questions ?? []).map((q) => ({
        question_id:    q.id,
        answer_text:    answerMap.value[q.id].answer_text,
        answer_options: answerMap.value[q.id].answer_options,
    }));

    form.answers = answers;
    form.post(route("survey.submit", props.survey!.slug), {
        onStart: () => { submitting.value = true; },
        onFinish: () => { submitting.value = false; },
    });
};

const showErrors = ref(false);

const hasError = (q: Question): boolean => {
    if (!showErrors.value || !q.is_required) return false;
    const ans = answerMap.value[q.id];
    if (q.type === "open_text")  return !ans.answer_text?.trim();
    if (q.type === "rating")     return !ans.answer_text;
    return ans.answer_options.length === 0;
};
</script>

<template>
    <Head :title="props.survey?.title ?? 'Encuesta'" />

    <v-app>
        <v-main style="background: #f4f6f8;">
            <v-container max-width="700" class="py-8">

                <!-- Logo / cabecera -->
                <div class="text-center mb-6">
                    <v-icon size="48" color="primary">mdi-clipboard-list</v-icon>
                </div>

                <!-- ── Error de acceso ── -->
                <v-card v-if="props.error" elevation="2" class="pa-8 text-center">
                    <v-icon
                        size="56"
                        :color="props.already_answered ? 'green' : 'error'"
                        class="mb-3"
                    >
                        {{ props.already_answered ? "mdi-check-circle" : "mdi-alert-circle" }}
                    </v-icon>
                    <div class="text-h6 font-weight-bold mb-2">
                        {{ props.already_answered ? "Encuesta ya completada" : "Acceso no válido" }}
                    </div>
                    <div class="text-body-1 text-medium-emphasis">{{ props.error }}</div>
                </v-card>

                <!-- ── Encuesta enviada exitosamente ── -->
                <v-card v-else-if="props.submitted" elevation="2" class="pa-8 text-center">
                    <v-icon size="56" color="green" class="mb-3">mdi-check-circle</v-icon>
                    <div class="text-h6 font-weight-bold mb-2">¡Gracias por tu respuesta!</div>
                    <div class="text-body-1 text-medium-emphasis">
                        Tus respuestas para <strong>{{ props.survey?.title }}</strong> han sido registradas correctamente.
                    </div>
                </v-card>

                <!-- ── Encuesta ── -->
                <template v-else-if="props.survey">
                    <!-- Encabezado de encuesta -->
                    <v-card elevation="2" class="pa-6 mb-4">
                        <div class="text-h5 font-weight-bold mb-2">{{ props.survey.title }}</div>
                        <div v-if="props.survey.description" class="text-body-1 text-medium-emphasis">
                            {{ props.survey.description }}
                        </div>
                    </v-card>

                    <!-- Preguntas -->
                    <v-card
                        v-for="(q, idx) in props.survey.questions"
                        :key="q.id"
                        elevation="1"
                        class="pa-5 mb-4"
                        :class="{ 'border-error': hasError(q) }"
                        :style="hasError(q) ? 'border: 1px solid #f44336;' : ''"
                    >
                        <div class="text-subtitle-1 font-weight-bold mb-1">
                            {{ idx + 1 }}. {{ q.question_text }}
                            <span v-if="q.is_required" class="text-error ml-1">*</span>
                        </div>
                        <div v-if="hasError(q)" class="text-caption text-error mb-2">
                            Este campo es obligatorio.
                        </div>

                        <!-- ── Opción múltiple (una respuesta) ── -->
                        <template v-if="q.type === 'single_choice'">
                            <v-radio-group
                                :model-value="answerMap[q.id].answer_options[0]"
                                @update:model-value="(val: number) => setSingleOption(q.id, val)"
                                hide-details
                            >
                                <v-radio
                                    v-for="opt in q.options"
                                    :key="opt.id"
                                    :label="opt.option_text"
                                    :value="opt.id"
                                />
                            </v-radio-group>
                        </template>

                        <!-- ── Casillas (múltiple respuesta) ── -->
                        <template v-else-if="q.type === 'multiple_choice'">
                            <v-checkbox
                                v-for="opt in q.options"
                                :key="opt.id"
                                :label="opt.option_text"
                                :model-value="answerMap[q.id].answer_options.includes(opt.id)"
                                @update:model-value="() => toggleOption(q.id, opt.id)"
                                hide-details
                                density="comfortable"
                            />
                        </template>

                        <!-- ── Texto abierto ── -->
                        <template v-else-if="q.type === 'open_text'">
                            <v-textarea
                                v-model="answerMap[q.id].answer_text"
                                variant="outlined"
                                density="comfortable"
                                rows="3"
                                placeholder="Escribe tu respuesta aquí..."
                                hide-details
                            />
                        </template>

                        <!-- ── Escala de valoración ── -->
                        <template v-else-if="q.type === 'rating'">
                            <div class="d-flex align-center gap-1 flex-wrap mt-2">
                                <span v-if="q.config?.label_min" class="text-body-2 text-medium-emphasis mr-2">
                                    {{ q.config.label_min }}
                                </span>
                                <v-btn
                                    v-for="val in ratingRange(q)"
                                    :key="val"
                                    :variant="answerMap[q.id].answer_text === String(val) ? 'flat' : 'outlined'"
                                    :color="answerMap[q.id].answer_text === String(val) ? 'amber-darken-2' : 'grey'"
                                    size="40"
                                    style="min-width:40px; width:40px; height:40px;"
                                    @click="setRating(q.id, val)"
                                >
                                    {{ val }}
                                </v-btn>
                                <span v-if="q.config?.label_max" class="text-body-2 text-medium-emphasis ml-2">
                                    {{ q.config.label_max }}
                                </span>
                            </div>
                        </template>
                    </v-card>

                    <!-- Botón enviar -->
                    <v-card elevation="0" class="pa-4">
                        <v-alert
                            v-if="form.errors.error"
                            type="error"
                            variant="tonal"
                            class="mb-4"
                        >
                            {{ form.errors.error }}
                        </v-alert>

                        <v-btn
                            color="primary"
                            variant="flat"
                            size="large"
                            block
                            :loading="submitting || form.processing"
                            prepend-icon="mdi-send"
                            @click="submitForm"
                        >
                            Enviar respuestas
                        </v-btn>

                        <div class="text-caption text-center text-medium-emphasis mt-3">
                            Los campos marcados con <strong>*</strong> son obligatorios.
                        </div>
                    </v-card>
                </template>
            </v-container>
        </v-main>
    </v-app>
</template>
