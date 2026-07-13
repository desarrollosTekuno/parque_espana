<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import { customToastSwal } from "@/utils/swal";

function handlePrint() {
    const win = window.open("", "", "width=400,height=600");
    if (!win) {
        customToastSwal({
            icon: "error",
            title: "No se pudo abrir la ventana de impresión. Revisa si el navegador bloqueó el popup.",
        });
        return;
    }

    const estilos = `
        <style>
            @page { size: 76mm 3276mm; margin: 0; }
            html, body { margin: 0; padding: 0; }
            .ticket {
                width: 72mm;
                box-sizing: border-box;
                padding: 2mm;
                font-family: monospace;
                font-size: 3mm;
                line-height: 1.35;
                color: #000;
            }
            .center { text-align: center; }
            .row { display: flex; justify-content: space-between; }
            .bold { font-weight: bold; }
            .sep { border-top: 1px dashed #000; margin: 1.5mm 0; }
        </style>
    `;

    win.document.open();
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8" /><title>Ticket</title>${estilos}</head>
        <body>
            <div class="ticket">
                <div class="center bold">FUNDACION DEPORTIVO PARQUE ESPAÑA II</div>
                <div class="center">FUNDACION DEPORTIVO PARQUE ESPAÑA</div>
                <div class="center">FDP990423J51</div>
                <div class="center">Carril San Martinito Km. 1.5 s/n</div>
                <div class="center">Col. Ampliación Emiliano Zapata CP 72810</div>
                <div class="center">San Andrés Cholula Puebla México</div>

                <div class="sep"></div>

                <div class="row"><span>Ticket: A 30214</span><span>21/05/26</span></div>
                <div>CNF</div>

                <div class="sep"></div>

                <div>Cuenta: 00046</div>
                <div>OTERO SAN MARTIN LUIS MANUEL</div>

                <div class="sep"></div>

                <div>XAXX010101000</div>
                <div>Casilleros:</div>
                <div>NA00129 CA00494 DA00370 CA00948</div>

                <div class="sep"></div>

                <div class="row bold"><span>Concepto</span><span>Importe U.</span><span>Total</span></div>
                <div>CUOTA PASE DIARIO 1-0 2026</div>
                <div class="row"><span>1</span><span>$344.83</span><span>$344.83</span></div>

                <div class="sep"></div>

                <div class="row"><span>Subtotal</span><span>$344.83</span></div>
                <div class="row"><span>IVA 16%</span><span>$55.17</span></div>
                <div class="row bold"><span>Total</span><span>$400.00</span></div>

                <div class="sep"></div>

                <div>TD 400.00 1215121412 VISA</div>

                <div class="sep"></div>

                <div class="center">DOS MESES SIN APORTACIÓN GENERAN SUSPENSIÓN</div>
                <div class="center">Este comprobante no tiene validez fiscal.</div>

                <div class="sep"></div>

                <div>Identificación de Archivo:</div>
                <div>0000000046DPAA302147GZ0P8NS9</div>

                <div class="sep"></div>

                <div class="center">Puede descargar sus comprobantes fiscales en:</div>
                <div class="center">http://www.parqueespana2.com.mx</div>
            </div>
        </body>
        </html>
    `);
    win.document.close();

    win.focus();
    win.print();
    setTimeout(() => {
        try {
            win.close();
        } catch {}
    }, 200);
}
</script>

<template>
    <Head title="Tickets" />

    <AppLayout>
        <template #header>Tickets</template>

        <v-card>
            <v-card-text>
                <v-btn color="primary" @click="handlePrint">
                    Imprimir prueba
                </v-btn>
            </v-card-text>
        </v-card>
    </AppLayout>
</template>
