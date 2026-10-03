<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { computed, ref } from "vue";

/* ====================== Variables ====================== */
const can = usePage().props.auth.permissions;
const selectedFile = ref<File | null>(null);
const reviewing = ref(false);
const importing = ref(false);
const showReport = ref(false);
const errorText = ref("");
const token = ref("");
const report = ref<{
    counts: Record<string, number>;
    errors: Array<{ sheet: string; row: number | null; field: string; message: string; origin?: string }>;
    warnings?: Array<{ sheet: string; row: number | null; field: string; message: string }>;
} | null>(null);

/* ====================== Computed ====================== */
const canImport = computed(() => !!token.value && report.value?.errors.length === 0 && can.includes("dinero-migration.import"));

/* ====================== Funciones ====================== */
const onFileChange = (value: File | File[] | null) => {
    selectedFile.value = Array.isArray(value) ? (value[0] ?? null) : value;
    report.value = null;
    token.value = "";
    errorText.value = "";
};

const review = async () => {
    if (!selectedFile.value) return;
    reviewing.value = true;
    errorText.value = "";
    const form = new FormData();
    form.append("file", selectedFile.value);
    try {
        const response = await axios.post(route("dinero-migration.preview"), form);
        token.value = response.data.token;
        report.value = response.data.report;
        showReport.value = true;
    } catch (error: any) {
        errorText.value = error.response?.data?.message ?? "No se pudo revisar la plantilla.";
    } finally {
        reviewing.value = false;
    }
};

const importFile = async () => {
    if (!canImport.value) return;
    importing.value = true;
    errorText.value = "";
    try {
        const response = await axios.post(route("dinero-migration.import"), { token: token.value });
        showReport.value = false;
        token.value = "";
        report.value = null;
        selectedFile.value = null;
        customToastSwal({ title: response.data.message, icon: "success" });
    } catch (error: any) {
        if (error.response?.data?.report) report.value = error.response.data.report;
        errorText.value = error.response?.data?.message ?? "No se pudo cargar la plantilla.";
    } finally {
        importing.value = false;
    }
};
</script>

<template>
    <Head title="Carga histórica de dinero" />
    <AppLayout>
        <template #header>Carga histórica de dinero</template>

        <v-container fluid class="pa-6">
            <v-card max-width="760" class="mx-auto">
                <v-card-title>Segunda fase: cargos y pagos</v-card-title>
                <v-card-text>
                    <p class="mb-4">Suba la plantilla para revisar las pestañas Cargos y Pagos. Las cuentas de socios deben estar cargadas. El archivo se guarda en el sistema solo después de confirmar el reporte.</p>
                    <v-file-input
                        label="Plantilla Excel (.xlsx)"
                        accept=".xlsx"
                        prepend-icon="mdi-file-excel-outline"
                        :disabled="reviewing || importing"
                        @update:model-value="onFileChange"
                    />
                    <v-alert v-if="errorText" type="error" class="mb-4">{{ errorText }}</v-alert>
                    <v-btn
                        v-if="can.includes('dinero-migration.preview')"
                        color="primary"
                        :disabled="!selectedFile || reviewing"
                        :loading="reviewing"
                        @click="review"
                    >Revisar archivo</v-btn>
                </v-card-text>
            </v-card>
        </v-container>

        <v-dialog v-model="showReport" max-width="1000" scrollable persistent>
            <v-card>
                <v-card-title>Resultado de la revisión</v-card-title>
                <v-card-text v-if="report">
                    <div class="mb-4">
                        <v-chip v-for="(count, sheet) in report.counts" :key="sheet" class="mr-2 mb-2">
                            {{ sheet }}: {{ count }}
                        </v-chip>
                    </div>
                    <v-alert v-if="report.errors.length" type="error" class="mb-4">
                        {{ report.errors.length }} error(es) impiden la carga.
                    </v-alert>
                    <v-alert v-else type="success" class="mb-4">La simulación terminó sin errores bloqueantes.</v-alert>

                    <h3 v-if="report.errors.length" class="mb-2">Errores</h3>
                    <v-table v-if="report.errors.length" density="compact" class="mb-5">
                        <thead><tr><th>Pestaña</th><th>Fila</th><th>Campo</th><th>Origen</th><th>Qué revisar</th></tr></thead>
                        <tbody>
                            <tr v-for="(issue, index) in report.errors" :key="`error-${index}`">
                                <td>{{ issue.sheet }}</td><td>{{ issue.row ?? '—' }}</td><td>{{ issue.field }}</td><td>{{ issue.origin ?? 'Validación' }}</td><td>{{ issue.message }}</td>
                            </tr>
                        </tbody>
                    </v-table>

                    <h3 v-if="report.warnings?.length" class="mb-2">Avisos (no detienen la carga)</h3>
                    <v-table v-if="report.warnings?.length" density="compact" class="mb-5">
                        <thead><tr><th>Pestaña</th><th>Fila</th><th>Campo</th><th>Aviso</th></tr></thead>
                        <tbody>
                            <tr v-for="(issue, index) in report.warnings" :key="`warning-${index}`">
                                <td>{{ issue.sheet }}</td><td>{{ issue.row ?? '—' }}</td><td>{{ issue.field }}</td><td>{{ issue.message }}</td>
                            </tr>
                        </tbody>
                    </v-table>

                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="importing" @click="showReport = false">Volver al archivo</v-btn>
                    <v-btn color="primary" :disabled="!canImport" :loading="importing" @click="importFile">Confirmar carga</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
