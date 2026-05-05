<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { Head, router } from "@inertiajs/vue3";
import { computed } from "vue";

interface OptionCount { label: string; count: number; }
interface RatingData   { distribution: OptionCount[]; average: number | null; }
interface QuestionResult {
    id: number;
    question_text: string;
    type: string;
    config: Record<string, any> | null;
    options: any[];
    answers_count: number;
    total_responses: number;
    chart_data: OptionCount[] | RatingData | string[] | null;
}

interface Props {
    survey: { id: number; title: string; description: string | null; status: string };
    totalResponses: number;
    questions: QuestionResult[];
}

const props = defineProps<Props>();

const typeLabel: Record<string, string> = {
    single_choice:   "Opción múltiple",
    multiple_choice: "Casillas",
    open_text:       "Texto abierto",
    rating:          "Valoración",
};
const typeColor: Record<string, string> = {
    single_choice:   "blue",
    multiple_choice: "indigo",
    open_text:       "teal",
    rating:          "amber",
};

const responseRate = computed(() =>
    props.totalResponses > 0
        ? Math.round((props.totalResponses / props.totalResponses) * 100)
        : 0
);

const maxCount = (items: OptionCount[]) =>
    items.length ? Math.max(...items.map((i) => i.count), 1) : 1;

const pct = (count: number, total: number) =>
    total === 0 ? 0 : Math.round((count / total) * 100);

const isChoiceData   = (q: QuestionResult): q is QuestionResult & { chart_data: OptionCount[] } =>
    q.type === "single_choice" || q.type === "multiple_choice";

const isRatingData   = (q: QuestionResult): q is QuestionResult & { chart_data: RatingData } =>
    q.type === "rating";

const isOpenTextData = (q: QuestionResult): q is QuestionResult & { chart_data: string[] } =>
    q.type === "open_text";
</script>

<template>
    <AppLayout :title="`Resultados: ${props.survey.title}`">
        <Head :title="`Resultados: ${props.survey.title}`" />

        <div class="pa-4">
            <!-- Header -->
            <div class="d-flex align-center mb-4 gap-2">
                <BaseButton
                    icon="mdi-arrow-left"
                    variant="text"
                    :icon-only="true"
                    @click="router.visit(route('surveys.index'))"
                />
                <div>
                    <h2 class="text-h5 font-weight-bold">Resultados</h2>
                    <div class="text-body-2 text-medium-emphasis">{{ props.survey.title }}</div>
                </div>
            </div>

            <!-- Resumen -->
            <v-row class="mb-6">
                <v-col cols="12" sm="4">
                    <v-card elevation="1" class="pa-4 text-center">
                        <div class="text-h3 font-weight-bold text-primary">{{ props.totalResponses }}</div>
                        <div class="text-body-2 text-medium-emphasis mt-1">Respuestas recibidas</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4">
                    <v-card elevation="1" class="pa-4 text-center">
                        <div class="text-h3 font-weight-bold text-blue">{{ props.questions.length }}</div>
                        <div class="text-body-2 text-medium-emphasis mt-1">Preguntas</div>
                    </v-card>
                </v-col>
                <v-col cols="12" sm="4">
                    <v-card elevation="1" class="pa-4 text-center">
                        <v-chip
                            :color="props.survey.status === 'active' ? 'green' : 'grey'"
                            variant="tonal"
                            size="large"
                        >
                            {{ props.survey.status === "active" ? "Activa" : "Borrador" }}
                        </v-chip>
                        <div class="text-body-2 text-medium-emphasis mt-2">Estado</div>
                    </v-card>
                </v-col>
            </v-row>

            <!-- Sin respuestas -->
            <v-alert
                v-if="props.totalResponses === 0"
                type="info"
                variant="tonal"
                icon="mdi-information-outline"
                class="mb-6"
            >
                Aún no hay respuestas para esta encuesta.
            </v-alert>

            <!-- Resultados por pregunta -->
            <v-row>
                <v-col
                    v-for="(q, idx) in props.questions"
                    :key="q.id"
                    cols="12"
                >
                    <v-card elevation="1" class="pa-5">
                        <!-- Encabezado de pregunta -->
                        <div class="d-flex align-start justify-space-between mb-4">
                            <div>
                                <div class="text-body-2 text-medium-emphasis mb-1">
                                    Pregunta {{ idx + 1 }}
                                    <v-chip
                                        size="x-small"
                                        :color="typeColor[q.type]"
                                        variant="tonal"
                                        class="ml-1"
                                    >
                                        {{ typeLabel[q.type] }}
                                    </v-chip>
                                </div>
                                <div class="text-subtitle-1 font-weight-bold">{{ q.question_text }}</div>
                            </div>
                            <v-chip size="small" variant="tonal" color="purple">
                                {{ q.answers_count }} respuesta(s)
                            </v-chip>
                        </div>

                        <!-- ── Opción múltiple / Casillas ── -->
                        <template v-if="isChoiceData(q) && Array.isArray(q.chart_data)">
                            <div
                                v-for="item in (q.chart_data as OptionCount[])"
                                :key="item.label"
                                class="mb-3"
                            >
                                <div class="d-flex justify-space-between text-body-2 mb-1">
                                    <span>{{ item.label }}</span>
                                    <span class="font-weight-bold">
                                        {{ item.count }}
                                        <span class="text-medium-emphasis font-weight-regular">
                                            ({{ pct(item.count, q.answers_count) }}%)
                                        </span>
                                    </span>
                                </div>
                                <v-progress-linear
                                    :model-value="pct(item.count, q.answers_count)"
                                    height="18"
                                    rounded
                                    :color="typeColor[q.type]"
                                    bg-color="grey-lighten-3"
                                />
                            </div>
                        </template>

                        <!-- ── Escala de valoración ── -->
                        <template v-else-if="isRatingData(q) && q.chart_data">
                            <div class="mb-4">
                                <div class="text-body-2 text-medium-emphasis">Promedio</div>
                                <div class="text-h4 font-weight-bold text-amber-darken-2">
                                    {{ (q.chart_data as RatingData).average ?? "—" }}
                                    <span class="text-body-2 text-medium-emphasis font-weight-regular">
                                        / {{ q.config?.max ?? 5 }}
                                    </span>
                                </div>
                            </div>
                            <div
                                v-for="item in (q.chart_data as RatingData).distribution"
                                :key="item.label"
                                class="mb-2"
                            >
                                <div class="d-flex align-center gap-2 text-body-2 mb-1">
                                    <v-icon size="14" color="amber">mdi-star</v-icon>
                                    <span class="font-weight-medium" style="min-width:24px">{{ item.label }}</span>
                                    <v-progress-linear
                                        :model-value="pct(item.count, q.answers_count)"
                                        height="14"
                                        rounded
                                        color="amber"
                                        bg-color="grey-lighten-3"
                                        style="flex:1"
                                    />
                                    <span class="text-medium-emphasis" style="min-width:60px; text-align:right">
                                        {{ item.count }} ({{ pct(item.count, q.answers_count) }}%)
                                    </span>
                                </div>
                            </div>
                        </template>

                        <!-- ── Texto abierto ── -->
                        <template v-else-if="isOpenTextData(q) && Array.isArray(q.chart_data)">
                            <div
                                v-if="(q.chart_data as string[]).length === 0"
                                class="text-medium-emphasis text-body-2"
                            >
                                Sin respuestas aún.
                            </div>
                            <v-sheet
                                v-for="(text, tIdx) in (q.chart_data as string[])"
                                :key="tIdx"
                                rounded
                                color="grey-lighten-5"
                                class="pa-3 mb-2 text-body-2"
                            >
                                {{ text }}
                            </v-sheet>
                        </template>

                        <div v-else class="text-medium-emphasis text-body-2">Sin datos disponibles.</div>
                    </v-card>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>
