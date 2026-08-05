<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";

// ─────────────────────────────────────────
//  Props
// ─────────────────────────────────────────
interface QuestionOption {
    id?: number;
    option_text: string;
    order?: number;
}
interface Question {
    id?: number;
    question_text: string;
    type: string;
    order: number;
    is_required: boolean;
    config: Record<string, any> | null;
    options: QuestionOption[];
}
interface SurveyProp {
    id?: number;
    title: string;
    description: string | null;
    status: "draft" | "active";
    slug?: string;
    questions: Question[];
}
interface Props {
    survey?: SurveyProp | null;
    messageError?: string;
}

const props = withDefaults(defineProps<Props>(), {
    survey: null,
});
const can = usePage().props.auth.permissions;

// ─────────────────────────────────────────
//  Survey form
// ─────────────────────────────────────────
const surveyFormRef = ref<any>(null);
const form = useForm({
    title: props.survey?.title ?? "",
    description: props.survey?.description ?? "",
    status: props.survey?.status ?? "draft",
});

const saveSurvey = () => {
    surveyFormRef.value?.validate().then(({ valid }: { valid: boolean }) => {
        if (!valid) return;
        if (props.survey?.id) {
            form.put(route("surveys.update", props.survey.id), {
                preserveScroll: true,
                onSuccess: () =>
                    customToastSwal({
                        title: "Encuesta actualizada",
                        icon: "success",
                    }),
                onError: (err: any) =>
                    customToastSwal({
                        title: err?.messageError || "Error al guardar",
                        icon: "error",
                    }),
            });
        } else {
            form.post(route("surveys.store"), {
                onError: (err: any) =>
                    customToastSwal({
                        title: err?.messageError || "Error al guardar",
                        icon: "error",
                    }),
            });
        }
    });
};

// ─────────────────────────────────────────
//  Questions list
// ─────────────────────────────────────────
const questions = ref<Question[]>(
    (props.survey?.questions ?? []).map((q) => ({
        ...q,
        options: q.options ?? [],
    })),
);

// ─────────────────────────────────────────
//  Question modal
// ─────────────────────────────────────────
const showModal = ref(false);
const questionFormRef = ref<any>(null);
const editingQuestion = ref<Question | null>(null);

const questionTypes = [
    {
        title: "Opción múltiple (una respuesta)",
        value: "single_choice",
        icon: "mdi-radiobox-marked",
    },
    {
        title: "Casillas (varias respuestas)",
        value: "multiple_choice",
        icon: "mdi-checkbox-marked",
    },
    { title: "Texto abierto", value: "open_text", icon: "mdi-text" },
    { title: "Escala de valoración", value: "rating", icon: "mdi-star" },
];

const qForm = useForm({
    question_text: "",
    type: "open_text",
    is_required: true,
    config: {
        min: 1,
        max: 5,
        label_min: "Muy malo",
        label_max: "Excelente",
    } as any,
    options: [] as QuestionOption[],
});

const needsOptions = computed(() =>
    ["single_choice", "multiple_choice"].includes(qForm.type),
);
const isRating = computed(() => qForm.type === "rating");

const openCreateQuestion = () => {
    editingQuestion.value = null;
    qForm.reset();
    qForm.type = "open_text";
    qForm.is_required = true;
    qForm.config = {
        min: 1,
        max: 5,
        label_min: "Muy malo",
        label_max: "Excelente",
    };
    qForm.options = [];
    showModal.value = true;
};

const openEditQuestion = (q: Question) => {
    editingQuestion.value = q;
    qForm.question_text = q.question_text;
    qForm.type = q.type;
    qForm.is_required = q.is_required;
    qForm.config = q.config
        ? { ...q.config }
        : { min: 1, max: 5, label_min: "Muy malo", label_max: "Excelente" };
    qForm.options = (q.options ?? []).map((o) => ({ ...o }));
    showModal.value = true;
};

const addOption = () => {
    qForm.options.push({ option_text: "" });
};
const removeOption = (idx: number) => {
    qForm.options.splice(idx, 1);
};

const saveQuestion = () => {
    questionFormRef.value?.validate().then(({ valid }: { valid: boolean }) => {
        if (!valid) return;
        if (!props.survey?.id) {
            customToastSwal({
                title: "Guarda la encuesta primero antes de agregar preguntas",
                icon: "warning",
            });
            return;
        }

        const payload = {
            question_text: qForm.question_text,
            type: qForm.type,
            is_required: qForm.is_required,
            config: isRating.value ? qForm.config : null,
            options: needsOptions.value ? qForm.options : [],
        };

        if (editingQuestion.value?.id) {
            qForm.put(
                route("surveys.questions.update", {
                    survey: props.survey!.id,
                    question: editingQuestion.value.id,
                }),
                {
                    data: payload,
                    preserveScroll: true,
                    onSuccess: () => {
                        showModal.value = false;
                        qForm.reset();
                        qForm.clearErrors();
                        customToastSwal({
                            title: "Pregunta actualizada",
                            icon: "success",
                        });
                    },
                    onError: (err: any) =>
                        customToastSwal({
                            title: err?.messageError || "Error",
                            icon: "error",
                        }),
                },
            );
        } else {
            qForm.post(route("surveys.questions.store", props.survey!.id), {
                data: payload,
                preserveScroll: true,
                onSuccess: () => {
                    showModal.value = false;
                    qForm.reset();
                    qForm.clearErrors();
                    customToastSwal({
                        title: "Pregunta agregada",
                        icon: "success",
                    });
                },
                onError: (err: any) =>
                    customToastSwal({
                        title: err?.messageError || "Error",
                        icon: "error",
                    }),
            });
        }
    });
};

const destroyQuestion = async (q: Question) => {
    const res = await customConfirmSwal({
        title: "¿Eliminar pregunta?",
        icon: "warning",
    });
    if (!res.isConfirmed) return;
    router.delete(
        route("surveys.questions.destroy", {
            survey: props.survey!.id,
            question: q.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () =>
                customToastSwal({
                    title: "Pregunta eliminada",
                    icon: "success",
                }),
            onError: (err: any) =>
                customToastSwal({
                    title: err?.messageError || "Error",
                    icon: "error",
                }),
        },
    );
};

// Drag & drop reorder
const draggingIdx = ref<number | null>(null);

const onDragStart = (idx: number) => {
    draggingIdx.value = idx;
};
const onDrop = (targetIdx: number) => {
    if (draggingIdx.value === null || draggingIdx.value === targetIdx) return;
    const list = [...questions.value];
    const [item] = list.splice(draggingIdx.value, 1);
    list.splice(targetIdx, 0, item);
    questions.value = list;
    draggingIdx.value = null;

    router.post(
        route("surveys.questions.reorder", props.survey!.id),
        { questions: list.map((q) => q.id!) },
        { preserveScroll: true },
    );
};

// sync cuando cambia la prop (tras guardar)
watch(
    () => props.survey?.questions,
    (val) => {
        questions.value = (val ?? []).map((q) => ({
            ...q,
            options: q.options ?? [],
        }));
    },
    { deep: true },
);

const typeLabel: Record<string, string> = {
    single_choice: "Opción múltiple",
    multiple_choice: "Casillas",
    open_text: "Texto abierto",
    rating: "Valoración",
};
const typeIcon: Record<string, string> = {
    single_choice: "mdi-radiobox-marked",
    multiple_choice: "mdi-checkbox-marked",
    open_text: "mdi-text",
    rating: "mdi-star",
};
const typeColor: Record<string, string> = {
    single_choice: "blue",
    multiple_choice: "indigo",
    open_text: "teal",
    rating: "amber",
};

const required = (v: any) => !!v || "Campo requerido";
const minOptions = (v: QuestionOption[]) =>
    needsOptions.value && v.length < 2 ? "Agrega al menos 2 opciones" : true;
</script>

<template>
    <AppLayout :title="props.survey?.id ? 'Editar encuesta' : 'Nueva encuesta'">
        <Head
            :title="props.survey?.id ? 'Editar encuesta' : 'Nueva encuesta'"
        />

        <div class="pa-4">
            <!-- Breadcrumb -->
            <div class="d-flex align-center mb-4 gap-2">
                <BaseButton
                    icon="mdi-arrow-left"
                    variant="text"
                    @click="router.visit(route('surveys.index'))"
                    :icon-only="true"
                />
                <h2 class="text-h5 font-weight-bold">
                    {{
                        props.survey?.id ? "Editar encuesta" : "Nueva encuesta"
                    }}
                </h2>
            </div>

            <v-row>
                <!-- ── Columna izquierda: datos de la encuesta ── -->
                <v-col cols="12" md="4">
                    <v-card elevation="1" class="pa-4">
                        <div class="text-subtitle-1 font-weight-bold mb-3">
                            <v-icon start>mdi-clipboard-text</v-icon>
                            Datos de la encuesta
                        </div>

                        <v-form
                            ref="surveyFormRef"
                            @submit.prevent="saveSurvey"
                        >
                            <v-text-field
                                v-model="form.title"
                                label="Título"
                                variant="outlined"
                                density="comfortable"
                                :rules="[required]"
                                prepend-inner-icon="mdi-format-title"
                                class="mb-3"
                            />

                            <v-textarea
                                v-model="form.description"
                                label="Descripción"
                                variant="outlined"
                                density="comfortable"
                                rows="3"
                                prepend-inner-icon="mdi-text"
                                class="mb-3"
                            />

                            <v-select
                                v-model="form.status"
                                label="Estado"
                                variant="outlined"
                                density="comfortable"
                                :items="[
                                    { title: 'Borrador', value: 'draft' },
                                    { title: 'Activa', value: 'active' },
                                ]"
                                item-title="title"
                                item-value="value"
                                :rules="[required]"
                                prepend-inner-icon="mdi-toggle-switch"
                                class="mb-3"
                            />

                            <v-btn
                                type="submit"
                                color="primary"
                                variant="flat"
                                block
                                :loading="form.processing"
                                prepend-icon="mdi-content-save"
                            >
                                {{
                                    props.survey?.id
                                        ? "Actualizar"
                                        : "Crear encuesta"
                                }}
                            </v-btn>
                        </v-form>

                        <v-alert
                            v-if="!props.survey?.id"
                            type="info"
                            variant="tonal"
                            density="compact"
                            class="mt-4"
                            icon="mdi-information-outline"
                        >
                            Crea la encuesta primero para luego agregar
                            preguntas.
                        </v-alert>
                    </v-card>
                </v-col>

                <!-- ── Columna derecha: preguntas ── -->
                <v-col cols="12" md="8">
                    <v-card elevation="1">
                        <v-card-title
                            class="d-flex align-center justify-space-between pa-4"
                        >
                            <span>
                                <v-icon start>mdi-help-circle</v-icon>
                                Preguntas
                                <v-chip
                                    size="small"
                                    class="ml-2"
                                    color="blue"
                                    variant="tonal"
                                >
                                    {{ questions.length }}
                                </v-chip>
                            </span>
                            <BaseButton
                                v-if="
                                    props.survey?.id &&
                                    can.includes('surveys.questions.store')
                                "
                                text="Agregar pregunta"
                                icon="mdi-plus"
                                action="save"
                                variant="flat"
                                :icon-only="false"
                                @click="openCreateQuestion"
                            />
                        </v-card-title>

                        <v-divider />

                        <div
                            v-if="questions.length === 0"
                            class="pa-8 text-center text-medium-emphasis"
                        >
                            <v-icon size="48" color="grey-lighten-1"
                                >mdi-help-circle-outline</v-icon
                            >
                            <div class="mt-2">
                                Aún no hay preguntas. Agrega la primera.
                            </div>
                        </div>

                        <!-- Lista de preguntas (drag & drop) -->
                        <v-list v-else lines="two" class="pa-2">
                            <v-list-item
                                v-for="(q, idx) in questions"
                                :key="q.id ?? idx"
                                class="mb-2 rounded"
                                :class="{
                                    'bg-blue-lighten-5': draggingIdx === idx,
                                }"
                                :draggable="can.includes('surveys.questions.reorder')"
                                @dragstart="onDragStart(idx)"
                                @dragover.prevent
                                @drop="onDrop(idx)"
                                style="
                                    border: 1px solid rgba(0, 0, 0, 0.08);
                                    cursor: grab;
                                "
                            >
                                <template #prepend>
                                    <div class="d-flex align-center gap-2">
                                        <v-icon color="grey" size="18"
                                            >mdi-drag</v-icon
                                        >
                                        <v-chip
                                            size="x-small"
                                            variant="flat"
                                            :color="typeColor[q.type]"
                                        >
                                            <v-icon start size="12">{{
                                                typeIcon[q.type]
                                            }}</v-icon>
                                            {{ typeLabel[q.type] }}
                                        </v-chip>
                                    </div>
                                </template>

                                <v-list-item-title class="font-weight-medium">
                                    {{ idx + 1 }}. {{ q.question_text }}
                                    <v-chip
                                        v-if="q.is_required"
                                        size="x-small"
                                        color="red"
                                        variant="tonal"
                                        class="ml-2"
                                    >
                                        Requerida
                                    </v-chip>
                                </v-list-item-title>

                                <v-list-item-subtitle v-if="q.options?.length">
                                    {{
                                        q.options
                                            .map((o) => o.option_text)
                                            .join(" · ")
                                    }}
                                </v-list-item-subtitle>
                                <v-list-item-subtitle
                                    v-else-if="q.type === 'rating' && q.config"
                                >
                                    Escala {{ q.config.min }} –
                                    {{ q.config.max }}
                                    <template v-if="q.config.label_min">
                                        ({{ q.config.label_min }} →
                                        {{ q.config.label_max }})
                                    </template>
                                </v-list-item-subtitle>

                                <template #append>
                                    <BaseButton
                                        action="edit"
                                        @click="openEditQuestion(q)"
                                        v-if="
                                            can.includes(
                                                'surveys.questions.update',
                                            )
                                        "
                                    />
                                    <BaseButton
                                        action="delete"
                                        @click="destroyQuestion(q)"
                                        v-if="
                                            can.includes(
                                                'surveys.questions.destroy',
                                            )
                                        "
                                    />
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-card>
                </v-col>
            </v-row>
        </div>

        <!-- ── Modal de pregunta ── -->
        <v-dialog v-model="showModal" max-width="600" persistent>
            <v-form ref="questionFormRef" @submit.prevent="saveQuestion">
                <v-card
                    :title="
                        editingQuestion ? 'Editar pregunta' : 'Nueva pregunta'
                    "
                >
                    <v-card-text style="max-height: 70vh; overflow: auto">
                        <v-row>
                            <v-col cols="12">
                                <v-select
                                    v-model="qForm.type"
                                    label="Tipo de pregunta"
                                    variant="outlined"
                                    density="comfortable"
                                    :items="questionTypes"
                                    item-title="title"
                                    item-value="value"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-textarea
                                    v-model="qForm.question_text"
                                    label="Texto de la pregunta"
                                    variant="outlined"
                                    density="comfortable"
                                    rows="3"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-switch
                                    v-model="qForm.is_required"
                                    label="Respuesta obligatoria"
                                    color="primary"
                                    inset
                                />
                            </v-col>

                            <!-- Opciones para single_choice / multiple_choice -->
                            <v-col v-if="needsOptions" cols="12">
                                <div
                                    class="text-subtitle-2 mb-2 font-weight-bold"
                                >
                                    Opciones de respuesta
                                </div>
                                <v-row
                                    v-for="(opt, idx) in qForm.options"
                                    :key="idx"
                                    align="center"
                                    class="mb-1"
                                >
                                    <v-col>
                                        <v-text-field
                                            v-model="opt.option_text"
                                            :label="`Opción ${idx + 1}`"
                                            variant="outlined"
                                            density="compact"
                                            :rules="[required]"
                                        />
                                    </v-col>
                                    <v-col cols="auto">
                                        <v-btn
                                            icon="mdi-close"
                                            size="small"
                                            variant="text"
                                            color="error"
                                            @click="removeOption(idx)"
                                        />
                                    </v-col>
                                </v-row>
                                <v-btn
                                    variant="tonal"
                                    color="primary"
                                    size="small"
                                    prepend-icon="mdi-plus"
                                    @click="addOption"
                                    class="mt-1"
                                >
                                    Agregar opción
                                </v-btn>
                                <div
                                    v-if="qForm.options.length < 2"
                                    class="text-caption text-error mt-1"
                                >
                                    Agrega al menos 2 opciones
                                </div>
                            </v-col>

                            <!-- Config para rating -->
                            <template v-if="isRating">
                                <v-col cols="6">
                                    <v-text-field
                                        v-model.number="qForm.config.min"
                                        label="Valor mínimo"
                                        type="number"
                                        variant="outlined"
                                        density="comfortable"
                                        :rules="[required]"
                                    />
                                </v-col>
                                <v-col cols="6">
                                    <v-text-field
                                        v-model.number="qForm.config.max"
                                        label="Valor máximo"
                                        type="number"
                                        variant="outlined"
                                        density="comfortable"
                                        :rules="[required]"
                                    />
                                </v-col>
                                <v-col cols="6">
                                    <v-text-field
                                        v-model="qForm.config.label_min"
                                        label="Etiqueta mínimo"
                                        variant="outlined"
                                        density="comfortable"
                                        placeholder="Ej. Muy malo"
                                    />
                                </v-col>
                                <v-col cols="6">
                                    <v-text-field
                                        v-model="qForm.config.label_max"
                                        label="Etiqueta máximo"
                                        variant="outlined"
                                        density="comfortable"
                                        placeholder="Ej. Excelente"
                                    />
                                </v-col>
                            </template>
                        </v-row>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        <BaseButton
                            text="Cancelar"
                            action="cancel"
                            variant="tonal"
                            :icon-only="false"
                            @click="showModal = false"
                        />
                        <BaseButton
                            :text="editingQuestion ? 'Actualizar' : 'Guardar'"
                            action="save"
                            variant="flat"
                            type="submit"
                            :icon-only="false"
                            :disabled="needsOptions && qForm.options.length < 2"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>
