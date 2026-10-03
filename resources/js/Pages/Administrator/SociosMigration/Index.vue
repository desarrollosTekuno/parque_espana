<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { customToastSwal } from "@/utils/swal";
import { Head, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { computed, ref } from "vue";

/* ====================== Variables ====================== */
const can = usePage().props.auth.permissions;
const selectedFile = ref<File | null>(null);
const sinPersonal = ref(false);
const reviewing = ref(false);
const importing = ref(false);
const showReport = ref(false);
const errorText = ref("");
const token = ref("");
const report = ref<{
    counts: Record<string, number>;
    errors: Array<{ sheet: string; row: number | null; field: string; message: string; origin: string }>;
} | null>(null);

/* ====================== Computed ====================== */
const canImport = computed(() => !!token.value && report.value?.errors.length === 0 && can.includes("socios-migration.import"));

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
    form.append("sin_personal", sinPersonal.value ? "1" : "0");
    try {
        const response = await axios.post(route("socios-migration.preview"), form);
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
        const response = await axios.post(route("socios-migration.import"), { token: token.value });
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
    <Head title="Carga de socios" />
    <AppLayout>
        <template #header>Carga temporal de socios</template>

        <v-container fluid class="pa-6">
            <v-card max-width="760" class="mx-auto">
                <v-card-title>Primera fase: socios y membresías</v-card-title>
                <v-card-text>
                    <p class="mb-2">Suba la plantilla para revisar Personal, Usuarios, Membresias e Integrantes. El archivo no se carga a la base hasta que confirme el reporte.</p>
                    <p class="mb-4">En cada membresía, GENERA COBRO requiere SI o NO. Si indica SI, CUOTA MENSUAL debe ser mayor que cero.</p>
                    <v-file-input
                        label="Plantilla Excel (.xlsx)"
                        accept=".xlsx"
                        prepend-icon="mdi-file-excel-outline"
                        :disabled="reviewing || importing"
                        @update:model-value="onFileChange"
                    />
                    <v-checkbox v-model="sinPersonal" label="Omitir la pestaña Personal" hide-details class="mb-3" />
                    <v-alert v-if="errorText" type="error" class="mb-4">{{ errorText }}</v-alert>
                    <v-btn
                        v-if="can.includes('socios-migration.preview')"
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
                        {{ report.errors.length }} bloqueo(s). Revise la causa indicada; algunos requieren completar el Excel y otros ajustar la carga.
                    </v-alert>
                    <v-alert v-else type="success" class="mb-4">La simulación de la primera fase terminó sin errores bloqueantes.</v-alert>

                    <h3 v-if="report.errors.length" class="mb-2">Errores</h3>
                    <v-table v-if="report.errors.length" density="compact" class="mb-5">
                        <thead><tr><th>Pestaña</th><th>Fila</th><th>Campo</th><th>Origen</th><th>Qué revisar</th></tr></thead>
                        <tbody>
                            <tr v-for="(issue, index) in report.errors" :key="`error-${index}`">
                                <td>{{ issue.sheet }}</td><td>{{ issue.row ?? '—' }}</td><td>{{ issue.field }}</td><td>{{ issue.origin ?? 'Validación técnica' }}</td><td>{{ issue.message }}</td>
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
