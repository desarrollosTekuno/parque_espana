<script setup lang="ts">
import axios from "axios";
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import { ref } from "vue";
import { customToastSwal } from "@/utils/swal";

/* ====================== Props ====================== */
interface Report {
    id: number;
    name: string;
}

interface Props {
    clubId: number;
    List: Report[];
    cashiers: { id: number; name: string }[];
}

const props = defineProps<Props>();

/* ====================== Variables ====================== */
const selectedReport = ref<number | null>(null);
const reportStartDate = ref<string | null>(null);
const reportEndDate = ref<string | null>(null);
const reportType = ref("individual");
const cashierId = ref<number | null>(null);
const loading = ref(false);

/* ====================== Funciones ====================== */
const validateReport = () => {
    if (!selectedReport.value) {
        customToastSwal({
            title: "Selecciona un reporte.",
            icon: "warning",
        });
        return false;
    }

    if (!reportStartDate.value || (selectedReport.value !== 4 && !reportEndDate.value)) {
        customToastSwal({
            title: selectedReport.value === 4
                ? "Selecciona la fecha del corte."
                : "Selecciona la fecha inicial y final del reporte.",
            icon: "warning",
        });
        return false;
    }

    if (selectedReport.value !== 4 && reportEndDate.value! < reportStartDate.value) {
        customToastSwal({
            title: "La fecha final no puede ser anterior a la inicial.",
            icon: "warning",
        });
        return false;
    }

    if (selectedReport.value === 4 && reportType.value === "individual" && !cashierId.value) {
        customToastSwal({
            title: "Selecciona el cajero del corte.",
            icon: "warning",
        });
        return false;
    }

    return true;
};

const generateReport = async () => {
    if (validateReport()) {

        let reportRoute = "reports.collection.export";

        if (selectedReport.value === 2) {
            reportRoute = "billing.reports.income-combined";
        } else if (selectedReport.value === 3) {
            reportRoute = "reports.collection.income";
        } else if (selectedReport.value === 4) {
            reportRoute = "reports.cash-cuts.export";
        }

        loading.value = true;

        try {
            const params = selectedReport.value === 4
                ? {
                    date: reportStartDate.value,
                    report_type: reportType.value,
                    user_id: cashierId.value,
                }
                : {
                    start_date: reportStartDate.value,
                    end_date: reportEndDate.value,
                };

            const response = await axios.get(route(reportRoute), {
                params,
                responseType: "blob",
            });

            const fileUrl = window.URL.createObjectURL(response.data);
            const contentDisposition = response.headers["content-disposition"];
            const filename = contentDisposition?.match(/filename="?([^";]+)"?/);
            const link = document.createElement("a");
            link.href = fileUrl;
            link.download = filename?.[1] || "reporte.xlsx";
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(fileUrl);
        } catch (error) {
            console.error(error);
            customToastSwal({
                title: "No fue posible generar el reporte.",
                icon: "error",
            });
        } finally {
            loading.value = false;
        }
    }else{
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Reportes" />

    <AppLayout>
        <template #header>Reportes del parque {{ props.clubId }}</template>

        <v-card variant="outlined" class="my-2">
            <v-card-title>Generar reporte</v-card-title>
            <v-card-subtitle>
                {{ selectedReport === 4 ? "Selecciona el tipo y fecha del corte." : "Selecciona el reporte y el rango de fechas." }}
            </v-card-subtitle>

            <v-card-text>
                <v-row>
                    <v-col cols="12" md="4">
                        <v-select
                            v-model="selectedReport"
                            :items="props.List"
                            item-title="name"
                            item-value="id"
                            label="Reporte"
                            clearable
                            hide-details="auto"
                        />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="reportStartDate"
                            :label="selectedReport === 4 ? 'Fecha del corte' : 'Fecha inicial'"
                            type="date"
                            hide-details="auto"
                        />
                    </v-col>

                    <v-col v-if="selectedReport !== 4" cols="12" md="4">
                        <v-text-field
                            v-model="reportEndDate"
                            label="Fecha final"
                            type="date"
                            hide-details="auto"
                        />
                    </v-col>

                    <template v-if="selectedReport === 4">
                        <v-col cols="12" md="4">
                            <v-select
                                v-model="reportType"
                                :items="[
                                    { title: 'Individual', value: 'individual' },
                                    { title: 'Global', value: 'global' },
                                ]"
                                label="Tipo de corte"
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col v-if="reportType === 'individual'" cols="12" md="4">
                            <v-select
                                v-model="cashierId"
                                :items="props.cashiers"
                                item-title="name"
                                item-value="id"
                                label="Cajero"
                                hide-details="auto"
                            />
                        </v-col>

                    </template>
                </v-row>

                <div class="justify-end mt-4 d-flex">
                    <BaseButton
                        :icon-only="false"
                        action="export"
                        text="Exportar Excel"
                        variant="tonal"
                        :loading="loading"
                        :disabled="
                            !selectedReport ||
                            !reportStartDate ||
                            (selectedReport !== 4 && !reportEndDate) ||
                            (selectedReport === 4 && reportType === 'individual' && !cashierId)
                        "
                        @click="generateReport"
                    />
                </div>
            </v-card-text>
        </v-card>
    </AppLayout>
</template>
