<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import { ref } from "vue";
import { customToastSwal } from "@/utils/swal";

// ── Reporte de cobranza (Excel) ─────────────────────────────────────────────
const reportStartDate = ref<string | null>(null);
const reportEndDate = ref<string | null>(null);

const exportChargesReport = () => {
    if (!reportStartDate.value || !reportEndDate.value) {
        customToastSwal({
            title: "Selecciona la fecha inicial y final del reporte.",
            icon: "warning",
        });
        return;
    }

    if (reportEndDate.value < reportStartDate.value) {
        customToastSwal({
            title: "La fecha final no puede ser anterior a la inicial.",
            icon: "warning",
        });
        return;
    }

    const params = new URLSearchParams({
        start_date: reportStartDate.value,
        end_date: reportEndDate.value,
    });

    window.location.href =
        route("reports.collection.export") + "?" + params.toString();
};
</script>

<template>
    <Head title="Reportes" />

    <AppLayout>
        <template #header>Reportes</template>

        <div class="d-flex flex-column ga-4">
            <v-row>
                <v-col cols="12">
                    <v-card variant="outlined">
                        <v-card-title>Reporte de cobranza</v-card-title>
                        <v-card-subtitle>
                            Genera un Excel de los cobros registrados en el
                            parque activo, agrupados por concepto de pago.
                        </v-card-subtitle>
                        <v-card-text>
                            <v-row align="center">
                                <v-col cols="12" sm="4" md="3">
                                    <v-text-field
                                        v-model="reportStartDate"
                                        label="Fecha inicial"
                                        type="date"
                                        hide-details="auto"
                                    />
                                </v-col>
                                <v-col cols="12" sm="4" md="3">
                                    <v-text-field
                                        v-model="reportEndDate"
                                        label="Fecha final"
                                        type="date"
                                        hide-details="auto"
                                    />
                                </v-col>
                                <v-col
                                    cols="12"
                                    sm="4"
                                    md="3"
                                    class="d-flex align-center"
                                >
                                    <BaseButton
                                        :icon-only="false"
                                        action="export"
                                        text="Exportar Excel"
                                        variant="tonal"
                                        :disabled="
                                            !reportStartDate || !reportEndDate
                                        "
                                        @click="exportChargesReport"
                                    />
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>
