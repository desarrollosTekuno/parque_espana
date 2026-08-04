<script setup lang="ts">
import type { TicketData } from "@/utils/ticket";

/* ====================== Props ====================== */
const props = defineProps<{
    ticket: TicketData;
}>();

/* ====================== Funciones ====================== */
const money = (value: number | null) => {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(value ?? 0));
};

const ticketDate = (value: string | null) => {
    if (!value) {
        return "-";
    }

    return new Date(value).toLocaleDateString("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "2-digit",
    });
};

const paymentDetail = (ticket: TicketData) => {
    return [
        ticket.forma_pago_ticket_codigo,
        Number(ticket.total).toFixed(2),
        ticket.pago_identificacion,
        ticket.banco,
    ].filter(Boolean).join(" ");
};
</script>

<template>
    <div class="ticket-preview">
        <div class="ticket-brand">
            <img
                v-if="props.ticket.club_logo_url"
                :src="props.ticket.club_logo_url"
                class="ticket-logo"
                alt="Logo de la institución"
            />
            <div class="institution-name">
                {{ props.ticket.club_nombre_institucion }}
            </div>
        </div>

        <div class="issuer-data">
            <div v-if="props.ticket.club_razon_social">{{ props.ticket.club_razon_social }}</div>
            <div v-if="props.ticket.club_rfc">{{ props.ticket.club_rfc }}</div>
            <div v-for="line in props.ticket.club_direccion_lineas" :key="line">
                {{ line }}
            </div>
        </div>

        <div class="ticket-identification">
            <span>
                <template v-if="props.ticket.ticket_folio">
                    Ticket: {{ [props.ticket.ticket_serie, props.ticket.ticket_folio].filter(Boolean).join(" ") }}
                </template>
                <template v-else>Pago #{{ props.ticket.payment_id }}</template>
            </span>
            <span>{{ ticketDate(props.ticket.fecha) }}</span>
        </div>

        <div v-if="props.ticket.cajero_codigo" class="cashier-code">
            {{ props.ticket.cajero_codigo }}
        </div>

        <div class="text-center">
            <strong v-if="!props.ticket.folio" class="ticket-warning">PRUEBA SIN FOLIO</strong>
            <strong v-if="props.ticket.estatus === 'cancelled'" class="ticket-warning">CANCELADO</strong>
        </div>

        <div class="ticket-divider"></div>
        <div class="ticket-row"><span>Cuenta:</span><strong>{{ props.ticket.cuenta_numero || props.ticket.cuenta_interna || "-" }}</strong></div>
        <div class="account-name">{{ props.ticket.titular || "-" }}</div>
        <div class="receiver-tax-data">
            <div v-if="props.ticket.receptor_nombre">{{ props.ticket.receptor_nombre }}</div>
            <div v-if="props.ticket.receptor_rfc">{{ props.ticket.receptor_rfc }}</div>
            <div v-if="props.ticket.receptor_uso_cfdi || props.ticket.receptor_regimen_fiscal || props.ticket.receptor_codigo_postal">
                {{ [props.ticket.receptor_uso_cfdi, props.ticket.receptor_regimen_fiscal, props.ticket.receptor_codigo_postal].filter(Boolean).join(" ") }}
            </div>
        </div>
        <div v-if="props.ticket.casilleros.length" class="locker-data">
            <div>Casilleros:</div>
            <div>{{ props.ticket.casilleros.join(" ") }}</div>
        </div>

        <div class="ticket-divider"></div>
        <div class="concept-title">Concepto</div>
        <div class="concept-header">
            <span>Can</span>
            <span>Importe U.</span>
            <span>Total</span>
        </div>
        <div v-if="props.ticket.conceptos.length">
            <div v-for="concept in props.ticket.conceptos" :key="`${concept.charge_id}-${concept.monto}`" class="concept-item">
                <div class="concept-description">
                    <strong v-if="concept.codigo">{{ concept.codigo }}</strong>
                    {{ concept.descripcion || concept.concepto || `Cargo #${concept.charge_id}` }}
                </div>
                <div class="concept-values">
                    <span>{{ concept.cantidad }}</span>
                    <span>{{ money(concept.importe_unitario) }}</span>
                    <strong>{{ money(concept.total) }}</strong>
                </div>
                <div v-if="concept.descuento" class="concept-discount">Descuento: {{ money(concept.descuento) }}</div>
            </div>
        </div>
        <div v-else class="text-center text-caption">Sin desglose de conceptos</div>

        <div class="ticket-divider"></div>
        <div v-if="props.ticket.subtotal !== null" class="ticket-row"><span>Subtotal</span><strong>{{ money(props.ticket.subtotal) }}</strong></div>
        <div v-if="props.ticket.iva !== null" class="ticket-row"><span>IVA {{ props.ticket.iva_porcentaje || 16 }}%</span><strong>{{ money(props.ticket.iva) }}</strong></div>
        <div class="ticket-row ticket-total"><span>Total</span><strong>{{ money(props.ticket.total) }}</strong></div>

        <div class="payment-detail">{{ paymentDetail(props.ticket) }}</div>
        <div class="institution-legend">{{ props.ticket.leyenda_institucion }}</div>
        <div v-if="props.ticket.notas" class="mt-2"><strong>Notas:</strong> {{ props.ticket.notas }}</div>

        <div class="ticket-footer">
            <div>{{ props.ticket.leyenda_no_fiscal }}</div>
            <div v-if="props.ticket.club_url_facturacion">Facturación: {{ props.ticket.club_url_facturacion }}</div>
        </div>
    </div>
</template>

<style scoped>
.ticket-preview {
    width: 76mm;
    max-width: 100%;
    margin: 0 auto;
    padding: 12px;
    color: #111;
    background: #fff;
    font-family: Arial, sans-serif;
    font-size: 12px;
}

.ticket-brand {
    width: 100%;
    text-align: center;
}

.ticket-logo {
    display: block;
    width: 16mm;
    height: 18mm;
    object-fit: contain;
    margin: 0 0 2mm 0;
    filter: grayscale(1) contrast(1.35);
}

.institution-name {
    font-size: 12px;
    font-weight: 700;
    line-height: 1.25;
    text-align: center;
    text-transform: uppercase;
}

.issuer-data {
    margin-top: 4mm;
    line-height: 1.3;
    text-align: left;
}

.ticket-identification {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin: 8px 0;
    font-size: 12px;
}

.cashier-code {
    margin: 3px 0;
    text-align: right;
}

.ticket-warning {
    display: block;
    margin: 5px 0;
}

.ticket-divider {
    margin: 8px 0;
    border-top: 1px dashed #111;
}

.ticket-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin: 3px 0;
}

.ticket-row strong {
    text-align: right;
}

.concept-title {
    margin-top: 7px;
    font-weight: bold;
}

.concept-header,
.concept-values {
    display: grid;
    grid-template-columns: 12mm 28mm 1fr;
    gap: 2mm;
}

.concept-header {
    margin: 3px 0;
    font-weight: bold;
}

.concept-header span:nth-child(n + 2),
.concept-values span:nth-child(n + 2),
.concept-values strong {
    text-align: right;
}

.concept-item {
    margin: 5px 0;
}

.concept-description {
    margin-bottom: 2px;
}

.concept-discount {
    text-align: right;
}

.account-name {
    margin: 3px 0;
    text-transform: uppercase;
}

.receiver-tax-data {
    margin: 3px 0;
    text-transform: uppercase;
}

.locker-data {
    margin: 6px 0;
}

.ticket-total {
    font-size: 15px;
}

.payment-detail {
    margin: 6px 0;
}

.institution-legend {
    margin: 6px 0;
    text-transform: uppercase;
}

.ticket-footer {
    margin-top: 12px;
    text-align: center;
    font-size: 10px;
}
</style>
