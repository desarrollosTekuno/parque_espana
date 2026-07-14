<script setup lang="ts">
interface Props {
    datos: any;
}

const props = defineProps<Props>();

function formatearFecha(fechaTexto) {
    if (!fechaTexto) {
        return "";
    }

    const fecha = new Date(fechaTexto);

    const dia = String(fecha.getDate()).padStart(2, "0");
    const mes = String(fecha.getMonth() + 1).padStart(2, "0");
    const anio = String(fecha.getFullYear()).slice(-2);

    return dia + "/" + mes + "/" + anio;
}

function formatearMonto(monto) {
    if (monto === null || monto === undefined) {
        return "";
    }

    return "$" + Number(monto).toFixed(2);
}
</script>

<template>
    <div class="ticket-preview-fondo" v-if="datos">
        <div class="ticket">
            <div v-if="datos.club_razon_social_lineas && datos.club_razon_social_lineas.length > 0">
                <div
                    v-for="(linea, index) in datos.club_razon_social_lineas"
                    :key="'razon-' + index"
                    class="center"
                    :class="{ bold: index === 0 }"
                >
                    {{ linea }}
                </div>
            </div>
            <div v-else class="center bold">{{ datos.club_nombre }}</div>

            <div class="encabezado" v-if="datos.club_logo_url">
                <img class="logo" :src="datos.club_logo_url" />
                <div class="encabezado-texto">
                    <div v-if="datos.club_rfc" class="center">{{ datos.club_rfc }}</div>

                    <div
                        v-for="(linea, index) in datos.club_direccion_lineas"
                        :key="'direccion-' + index"
                        class="center"
                    >
                        {{ linea }}
                    </div>
                </div>
            </div>

            <div v-else>
                <div v-if="datos.club_rfc" class="center">{{ datos.club_rfc }}</div>

                <div
                    v-for="(linea, index) in datos.club_direccion_lineas"
                    :key="'direccion-' + index"
                    class="center"
                >
                    {{ linea }}
                </div>
            </div>

            <div class="sep"></div>

            <div class="row">
                <span>{{ datos.folio_corto }}</span>
                <span>{{ formatearFecha(datos.fecha) }}</span>
            </div>
            <div>{{ datos.cajero_codigo }}</div>

            <div class="sep"></div>

            <div v-if="datos.cuenta_numero">Cuenta: {{ datos.cuenta_numero }}</div>
            <div v-if="datos.titular">{{ datos.titular.toUpperCase() }}</div>

            <div class="sep"></div>

            <div class="row bold">
                <span>Concepto</span>
                <span>Total</span>
            </div>

            <div v-for="(concepto, index) in datos.conceptos" :key="index" class="row">
                <span>{{ (concepto.descripcion || "").toUpperCase() }}</span>
                <span>{{ formatearMonto(concepto.monto) }}</span>
            </div>

            <div class="sep"></div>

            <div v-if="datos.subtotal !== null && datos.iva !== null">
                <div class="row">
                    <span>Subtotal</span>
                    <span>{{ formatearMonto(datos.subtotal) }}</span>
                </div>
                <div class="row">
                    <span>IVA 16%</span>
                    <span>{{ formatearMonto(datos.iva) }}</span>
                </div>
            </div>

            <div class="row bold">
                <span>Total</span>
                <span>{{ formatearMonto(datos.total) }}</span>
            </div>

            <div class="sep"></div>

            <div>
                {{ datos.forma_pago }}
                <span v-if="datos.referencia"> {{ datos.referencia }}</span>
            </div>

            <div class="sep"></div>

            <div class="center">DOS MESES SIN APORTACIÓN GENERAN SUSPENSIÓN</div>
            <div class="center">Este comprobante no tiene validez fiscal.</div>

            <div class="sep"></div>

            <div>Identificación de Archivo:</div>
            <div>XXX0000</div>

            <div v-if="datos.club_url_facturacion">
                <div class="sep"></div>
                <div class="center">Puede descargar sus comprobantes fiscales en:</div>
                <div class="center">{{ datos.club_url_facturacion }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ticket-preview-fondo {
    background: #ddd;
    padding: 12px;
    display: flex;
    justify-content: center;
}

.ticket {
    width: 72mm;
    box-sizing: border-box;
    padding: 2mm;
    font-family: monospace;
    font-size: 3mm;
    line-height: 1.35;
    color: #000;
    background: #fff;
}

.center {
    text-align: center;
}

.row {
    display: flex;
    justify-content: space-between;
}

.bold {
    font-weight: bold;
}

.sep {
    border-top: 1px dashed #000;
    margin: 1.5mm 0;
}

.encabezado {
    display: flex;
    align-items: center;
    gap: 2mm;
}

.encabezado .logo {
    width: 14mm;
    height: 14mm;
    object-fit: contain;
    flex-shrink: 0;
}

.encabezado .encabezado-texto {
    flex: 1;
}
</style>
