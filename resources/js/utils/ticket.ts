import axios from "axios";

export interface TicketConcept {
    charge_id: number | null;
    codigo: string | null;
    concepto: string | null;
    descripcion: string | null;
    cantidad: number;
    importe_unitario: number;
    total: number;
    descuento: number | null;
    monto: number;
}

export interface TicketPaymentMethod {
    payment_id: number;
    folio: string | null;
    ticket_serie: string | null;
    ticket_folio: string | null;
    nombre: string | null;
    codigo: string | null;
    codigo_ticket: string | null;
    monto: number;
    referencia: string | null;
    banco: string | null;
    numero_cheque: string | null;
    es_este_ticket: boolean;
    status: string | null;
}

export interface TicketData {
    payment_id: number;
    payment_group_key: string;
    folio: string | null;
    ticket_serie: string | null;
    ticket_folio: string | null;
    identificacion_archivo: string | null;
    fecha: string | null;
    estatus: string | null;
    club_id: number;
    club_codigo: string | null;
    club_nombre: string | null;
    club_nombre_institucion: string;
    club_razon_social: string | null;
    club_direccion_lineas: string[];
    club_rfc: string | null;
    club_url_facturacion: string | null;
    club_logo_url: string | null;
    cajero_nombre: string | null;
    cajero_codigo: string | null;
    cuenta_numero: string | null;
    cuenta_interna: string | null;
    titular: string | null;
    receptor_nombre: string | null;
    receptor_rfc: string | null;
    receptor_uso_cfdi: string | null;
    receptor_regimen_fiscal: string | null;
    receptor_codigo_postal: string | null;
    casilleros: string[];
    conceptos: TicketConcept[];
    forma_pago: string | null;
    forma_pago_codigo: string | null;
    forma_pago_ticket_codigo: string | null;
    pago_identificacion: string | null;
    referencia: string | null;
    banco: string | null;
    numero_cheque: string | null;
    es_pago_dividido: boolean;
    formas_de_pago: TicketPaymentMethod[];
    notas: string | null;
    leyenda_institucion: string;
    leyenda_no_fiscal: string;
    subtotal: number | null;
    iva: number | null;
    iva_porcentaje: number | null;
    total: number;
}

export interface TicketBundle {
    payment_group_key: string;
    tickets: TicketData[];
}

const printedGroups = new Set<string>();

export const escapeHtml = (value: unknown): string => {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
};

const money = (value: number | null): string => {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(value ?? 0));
};

const ticketDate = (value: string | null): string => {
    if (!value) return "";

    return new Date(value).toLocaleDateString("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "2-digit",
    });
};

const row = (label: string, value: unknown): string => {
    if (value === null || value === undefined || value === "") return "";

    return `<div class="row"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
};

export const getTicketBundle = async (paymentId: number): Promise<TicketBundle> => {
    const response = await axios.get(route("tickets.data", paymentId));

    return response.data;
};

export const getTicketData = async (paymentId: number): Promise<TicketData> => {
    const bundle = await getTicketBundle(paymentId);

    return bundle.tickets[0];
};

const paymentBreakdown = (ticket: TicketData): string => {
    return `
        <div class="concept-title">Formas de pago</div>
        ${ticket.formas_de_pago.map((payment) => {
            const detail = [payment.referencia, payment.banco, payment.numero_cheque]
                .filter(Boolean)
                .map(escapeHtml)
                .join(" · ");
            const paymentFolio = [payment.ticket_serie, payment.ticket_folio].filter(Boolean).join(" ");

            return `
                <div class="payment-method">
                    <div class="row">
                        <span>${escapeHtml(payment.codigo_ticket ?? payment.nombre)}</span>
                        <strong>${escapeHtml(money(payment.monto))}</strong>
                    </div>
                    ${paymentFolio ? `<div class="muted">Folio: ${escapeHtml(paymentFolio)}</div>` : ""}
                    ${detail ? `<div class="muted">${detail}</div>` : ""}
                </div>
            `;
        }).join("")}
    `;
};

const ticketSection = (ticket: TicketData, duplicate: boolean, copyLabel: string): string => {
    const ticketNumber = [ticket.ticket_serie, ticket.ticket_folio].filter(Boolean).join(" ");
    const title = ticketNumber ? `Ticket: ${ticketNumber}` : `Pago #${ticket.payment_id}`;
    const concepts = ticket.conceptos.map((concept) => `
        <div class="concept-item">
            <div class="concept-description">
                ${concept.codigo ? `<strong>${escapeHtml(concept.codigo)}</strong>` : ""}
                <span>${escapeHtml(concept.descripcion ?? concept.concepto)}</span>
            </div>
            <div class="concept-values">
                <span>${escapeHtml(concept.cantidad)}</span>
                <span>${escapeHtml(money(concept.importe_unitario))}</span>
                <strong>${escapeHtml(money(concept.total))}</strong>
            </div>
            ${concept.descuento ? `<div class="concept-discount">Descuento: ${escapeHtml(money(concept.descuento))}</div>` : ""}
        </div>
    `).join("");
    const address = ticket.club_direccion_lineas.map((line) => `<div>${escapeHtml(line)}</div>`).join("");
    const receiverFiscalLine = [
        ticket.receptor_uso_cfdi,
        ticket.receptor_regimen_fiscal,
        ticket.receptor_codigo_postal,
    ].filter(Boolean).join(" ");

    return `
    <section class="ticket-copy">
        <div class="copy-label">${escapeHtml(copyLabel)}${duplicate ? " · DUPLICADO" : ""}</div>
        <div class="ticket-brand">
            ${ticket.club_logo_url ? `<img class="logo" src="${escapeHtml(ticket.club_logo_url)}" alt="Logo de la institución">` : ""}
            <div class="institution-name">${escapeHtml(ticket.club_nombre_institucion)}</div>
        </div>
        <div class="issuer-data">
            ${ticket.club_razon_social ? `<div>${escapeHtml(ticket.club_razon_social)}</div>` : ""}
            ${ticket.club_rfc ? `<div>${escapeHtml(ticket.club_rfc)}</div>` : ""}
            ${address}
        </div>
        <div class="ticket-identification">
            <span>${escapeHtml(title)}</span>
            <span>${escapeHtml(ticketDate(ticket.fecha))}</span>
        </div>
        <div class="ticket-operator"><span></span><span>${escapeHtml(ticket.cajero_codigo)}</span></div>
        <div class="center">
            ${ticket.folio ? "" : '<div class="warning">PRUEBA SIN FOLIO</div>'}
            ${ticket.estatus === "cancelled" ? '<div class="warning">CANCELADO</div>' : ""}
        </div>
        <div class="divider"></div>
        ${row("Cuenta:", ticket.cuenta_numero ?? ticket.cuenta_interna)}
        ${ticket.titular ? `<div class="account-name">${escapeHtml(ticket.titular)}</div>` : ""}
        <div class="receiver-tax-data">
            ${ticket.receptor_nombre ? `<div>${escapeHtml(ticket.receptor_nombre)}</div>` : ""}
            ${ticket.receptor_rfc ? `<div>${escapeHtml(ticket.receptor_rfc)}</div>` : ""}
            ${receiverFiscalLine ? `<div>${escapeHtml(receiverFiscalLine)}</div>` : ""}
        </div>
        ${ticket.casilleros.length ? `<div class="locker-data"><div>Casilleros:</div><div>${escapeHtml(ticket.casilleros.join(" "))}</div></div>` : ""}
        <div class="divider"></div>
        <div class="concept-title">Concepto</div>
        <div class="concept-header"><span>Can</span><span>Importe U.</span><span>Total</span></div>
        ${concepts || '<div class="center muted">Sin desglose de conceptos</div>'}
        <div class="divider"></div>
        ${ticket.subtotal !== null ? row("Subtotal", money(ticket.subtotal)) : ""}
        ${ticket.iva !== null ? row(`IVA ${ticket.iva_porcentaje ?? 16}%`, money(ticket.iva)) : ""}
        <div class="row total"><span>Total</span><strong>${escapeHtml(money(ticket.total))}</strong></div>
        <div class="payment-detail">${paymentBreakdown(ticket)}</div>
        <div class="institution-legend">${escapeHtml(ticket.leyenda_institucion)}</div>
        <div class="footer">${escapeHtml(ticket.leyenda_no_fiscal)}</div>
        ${ticket.identificacion_archivo ? `<div class="file-identification"><div>Identificación de Archivo:</div><div class="file-identifier">${escapeHtml(ticket.identificacion_archivo)}</div></div>` : ""}
        ${ticket.club_url_facturacion ? `<div class="useful-information"><div>Puede descargar sus comprobantes fiscales en la siguiente dirección:</div><div>${escapeHtml(ticket.club_url_facturacion)}</div></div>` : ""}
    </section>`;
};

const bundleHtml = (bundle: TicketBundle, duplicate: boolean): string => {
    // Temporalmente se imprime una sola copia por parque para las pruebas.
    const sections = bundle.tickets
        .map((ticket) => ticketSection(ticket, duplicate, "COPIA SOCIO"))
        .join("");

    return `<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tickets de pago</title>
    <style>
        @page { size: 76mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { width: 70mm; margin: 0 auto; color: #000; font: 12px Arial, sans-serif; }
        .ticket-copy { width: 70mm; padding-bottom: 8mm; margin-bottom: 8mm; border-bottom: 1px dashed #000; break-after: page; }
        .ticket-copy:last-child { margin-bottom: 0; border-bottom: 0; break-after: auto; }
        .copy-label { margin-bottom: 3mm; text-align: center; font-size: 10px; font-weight: bold; }
        .center, .ticket-brand { text-align: center; }
        .logo { display: block; width: 16mm; height: 18mm; object-fit: contain; margin: 0 0 2mm; filter: grayscale(1) contrast(1.35); }
        .institution-name { font-weight: 700; line-height: 1.25; text-transform: uppercase; }
        .issuer-data { margin-top: 4mm; line-height: 1.3; }
        .ticket-identification, .ticket-operator, .row { display: flex; justify-content: space-between; gap: 8px; }
        .ticket-identification { margin: 8px 0; }
        .ticket-operator, .row { margin: 3px 0; }
        .row strong { text-align: right; }
        .muted { font-size: 10px; }
        .warning { margin: 5px 0; font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 7px 0; }
        .concept-title { margin-top: 7px; font-weight: bold; }
        .concept-header, .concept-values { display: grid; grid-template-columns: 12mm 28mm 1fr; gap: 2mm; }
        .concept-header { margin: 3px 0; font-weight: bold; }
        .concept-header span:nth-child(n+2), .concept-values span:nth-child(n+2), .concept-values strong { text-align: right; }
        .concept-item, .payment-method { margin: 5px 0; }
        .concept-description { display: flex; gap: 4px; margin-bottom: 2px; font-size: 10px; line-height: 1.15; }
        .concept-description strong { flex: 0 0 auto; }
        .concept-discount { text-align: right; }
        .account-name, .receiver-tax-data, .institution-legend { margin: 3px 0; text-transform: uppercase; }
        .locker-data, .payment-detail { margin: 6px 0; }
        .total { font-size: 15px; }
        .footer, .file-identification, .useful-information { margin-top: 10px; font-size: 10px; }
        .footer { text-align: center; }
        .file-identifier { overflow-wrap: anywhere; }
        @media print { body { width: 70mm; } }
    </style>
</head>
<body>${sections}</body>
</html>`;
};

export const printTicket = async (paymentId: number, duplicate = false): Promise<void> => {
    const printWindow = window.open("", "_blank", "width=420,height=700");

    if (!printWindow) {
        throw new Error("El navegador bloqueó la ventana de impresión.");
    }

    printWindow.document.write("<p style='font-family: Arial'>Preparando ticket...</p>");

    try {
        const bundle = await getTicketBundle(paymentId);

        if (!duplicate && printedGroups.has(bundle.payment_group_key)) {
            printWindow.close();
            return;
        }

        if (!duplicate) printedGroups.add(bundle.payment_group_key);

        printWindow.document.open();
        printWindow.document.write(bundleHtml(bundle, duplicate));
        printWindow.document.close();

        await new Promise<void>((resolve) => {
            if (printWindow.document.readyState === "complete") resolve();
            else printWindow.onload = () => resolve();
        });

        printWindow.focus();
        printWindow.print();
    } catch (error) {
        printWindow.close();
        throw error;
    }
};
