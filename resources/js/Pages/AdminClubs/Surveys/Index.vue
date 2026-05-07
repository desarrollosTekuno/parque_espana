<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import { debounce } from "lodash";

const page = usePage();
const can = page.props.auth?.permissions ?? [];

interface Survey {
    id: number;
    title: string;
    description: string | null;
    status: "draft" | "active";
    slug: string;
    questions_count: number;
    responses_count: number;
    created_at: string;
}

interface Props {
    surveys?: any;
    messageError?: string;
}

const props = withDefaults(defineProps<Props>(), {
    surveys: null,
});

const search = ref("");
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [] as any[] });
const loading = ref(false);

const statusColor: Record<string, string> = {
    draft: "grey",
    active: "green",
};
const statusLabel: Record<string, string> = {
    draft: "Borrador",
    active: "Activa",
};

const headers = [
    { title: "Título", key: "title", sortable: true },
    { title: "Estado", key: "status", sortable: true, width: "120px" },
    { title: "Preguntas", key: "questions_count", sortable: false, align: "center" as const, width: "110px" },
    { title: "Respuestas", key: "responses_count", sortable: false, align: "center" as const, width: "110px" },
    { title: "Creada", key: "created_at", sortable: true, width: "140px" },
    { title: "Acciones", key: "actions", sortable: false, align: "end" as const, width: "150px" },
];

const loadData = () => {
    loading.value = true;
    const sort   = options.value.sortBy[0];
    router.get(
        route("surveys.index"),
        {
            surveys_search:   search.value || undefined,
            surveys_page:     options.value.page,
            surveys_per_page: options.value.itemsPerPage,
            surveys_sort:     sort?.key || "id",
            surveys_order:    sort?.order || "desc",
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => { loading.value = false; },
        }
    );
};

watch(search, debounce(() => { options.value.page = 1; loadData(); }, 400));
watch(options, loadData, { deep: true });

const create = () => router.visit(route("surveys.create"));
const edit   = (item: Survey) => router.visit(route("surveys.edit", item.id));
const results = (item: Survey) => router.visit(route("surveys.results", item.id));

const destroy = async (item: Survey) => {
    const result = await customConfirmSwal({
        title: "¿Eliminar encuesta?",
        text: `Se eliminará "${item.title}" y todas sus preguntas. Esta acción no se puede deshacer.`,
        icon: "warning",
    });
    if (!result.isConfirmed) return;
    router.delete(route("surveys.destroy", item.id), {
        preserveScroll: true,
        onSuccess: () => customToastSwal({ title: "Encuesta eliminada", icon: "success" }),
        onError: (err: any) => customToastSwal({ title: err?.messageError || "Error al eliminar", icon: "error" }),
    });
};

const copyLink = (item: Survey) => {
    if (item.status !== "active") {
        customToastSwal({ title: "La encuesta debe estar activa para compartir el enlace", icon: "warning" });
        return;
    }
    const url = `${window.location.origin}/encuesta/${item.slug}`;
    navigator.clipboard.writeText(url).then(() => {
        customToastSwal({ title: "URL copiada al portapapeles", icon: "success" });
    });
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return "—";
    return new Date(dateStr).toLocaleDateString("es-MX", { day: "2-digit", month: "short", year: "numeric" });
};
</script>

<template>
    <AppLayout title="Encuestas">
        <Head title="Encuestas" />

        <div class="pa-4">
            <v-row align="center" class="mb-4">
                <v-col>
                    <h2 class="text-h5 font-weight-bold">Encuestas</h2>
                    <div class="text-body-2 text-medium-emphasis">
                        Crea y administra encuestas para recopilar opiniones de los usuarios
                    </div>
                </v-col>
                <v-col cols="auto">
                    <BaseButton
                        text="Nueva encuesta"
                        icon="mdi-plus"
                        action="save"
                        variant="flat"
                        :icon-only="false"
                        @click="create"
                        v-if="can.includes('surveys.create')"
                    />
                </v-col>
            </v-row>

            <v-alert v-if="props.messageError" type="error" variant="tonal" class="mb-4">
                {{ props.messageError }}
            </v-alert>

            <v-card elevation="1">
                <v-data-table-server
                    :headers="headers"
                    :items="props.surveys?.data ?? []"
                    :items-length="props.surveys?.total ?? 0"
                    :loading="loading"
                    v-model:options="options"
                    loading-text="Cargando encuestas..."
                    no-data-text="No hay encuestas registradas"
                    :items-per-page-options="[10, 25, 50]"
                    items-per-page-text="Mostrar"
                >
                    <template #top>
                        <v-text-field
                            v-model="search"
                            label="Buscar encuesta..."
                            prepend-inner-icon="mdi-magnify"
                            variant="outlined"
                            density="comfortable"
                            class="mx-4 mt-4"
                            clearable
                        />
                    </template>

                    <template #item.status="{ item }">
                        <v-chip
                            size="small"
                            variant="tonal"
                            :color="statusColor[item.status]"
                        >
                            {{ statusLabel[item.status] }}
                        </v-chip>
                    </template>

                    <template #item.questions_count="{ item }">
                        <v-chip size="small" variant="tonal" color="blue">
                            {{ item.questions_count }}
                        </v-chip>
                    </template>

                    <template #item.responses_count="{ item }">
                        <v-chip size="small" variant="tonal" color="purple">
                            {{ item.responses_count }}
                        </v-chip>
                    </template>

                    <template #item.created_at="{ item }">
                        {{ formatDate(item.created_at) }}
                    </template>

                    <template #item.actions="{ item }">
                        <v-tooltip text="Ver resultados">
                            <template #activator="{ props: tp }">
                                <BaseButton v-bind="tp" icon="mdi-chart-bar" color="purple" @click="results(item)" v-if="can.includes('surveys.results')" />
                            </template>
                        </v-tooltip>
                        <!-- <v-tooltip text="Copiar URL pública">
                            <template #activator="{ props: tp }">
                                <BaseButton v-bind="tp" icon="mdi-link-variant" color="teal" @click="copyLink(item)" />
                            </template>
                        </v-tooltip> -->
                        <BaseButton action="edit" @click="edit(item)" v-if="can.includes('surveys.edit')" />
                        <BaseButton action="delete" @click="destroy(item)" v-if="can.includes('surveys.destroy')" />
                    </template>
                </v-data-table-server>
            </v-card>
        </div>
    </AppLayout>
</template>
