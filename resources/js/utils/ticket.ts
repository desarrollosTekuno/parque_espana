import axios from "axios";

export interface TicketConcept {
    charge_id: number | null;
    codigo: string | null;
    concepto: string | null;
    descripcion: string | null;
    monto: number;
}

export interface TicketData {
    payment_id: number;
    folio: string | null;
    ticket_serie: string | null;
    ticket_folio: string | null;
    fecha: string | null;
    estatus: string | null;
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
    conceptos: TicketConcept[];
    forma_pago: string | null;
    forma_pago_codigo: string | null;
    referencia: string | null;
    banco: string | null;
    numero_cheque: string | null;
    notas: string | null;
    subtotal: number | null;
    iva: number | null;
    iva_porcentaje: number | null;
    total: number;
}

const escapeHtml = (value: unknown): string => {
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
    if (!value) {
        return "";
    }

    return new Date(value).toLocaleDateString("es-MX", {
        day: "2-digit",
        month: "2-digit",
        year: "2-digit",
    });
};

const row = (label: string, value: unknown): string => {
    if (value === null || value === undefined || value === "") {
        return "";
    }

    return `<div class="row"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
};

export const getTicketData = async (paymentId: number): Promise<TicketData> => {
    const response = await axios.get(route("tickets.data", paymentId));

    return response.data;
};

const ticketHtml = (ticket: TicketData, duplicate: boolean): string => {
    const ticketNumber = [ticket.ticket_serie, ticket.ticket_folio].filter(Boolean).join(" ");
    const title = ticketNumber ? `Ticket: ${ticketNumber}` : `Pago #${ticket.payment_id}`;
    const concepts = ticket.conceptos.map((concept) => `
        <div class="concept">
            <div>${escapeHtml(concept.concepto ?? concept.descripcion ?? `Cargo #${concept.charge_id}`)}</div>
            <strong>${escapeHtml(money(concept.monto))}</strong>
        </div>
    `).join("");
    const address = ticket.club_direccion_lineas
        .map((line) => `<div>${escapeHtml(line)}</div>`)
        .join("");

    return `<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>${escapeHtml(title)}</title>
    <style>
        @page { size: 76mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { width: 70mm; margin: 0 auto; color: #000; font: 12px Arial, sans-serif; }
        .center { text-align: center; }
        .ticket-brand { width: 100%; text-align: center; }
        .logo { display: block; width: 16mm; height: 18mm; object-fit: contain; margin: 0 0 2mm 0; filter: grayscale(1) contrast(1.35); }
        .institution-name { margin: 0; font-size: 12px; font-weight: 700; line-height: 1.25; text-align: center; text-transform: uppercase; }
        .issuer-data { margin-top: 4mm; line-height: 1.3; text-align: left; }
        .ticket-identification { display: flex; justify-content: space-between; gap: 8px; margin: 8px 0; font-size: 12px; }
        .ticket-operator { display: flex; justify-content: space-between; gap: 8px; margin: 3px 0; }
        .muted { font-size: 10px; }
        .warning { margin: 5px 0; font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 7px 0; }
        .row, .concept { display: flex; justify-content: space-between; gap: 8px; margin: 3px 0; }
        .row strong, .concept strong { text-align: right; }
        .total { font-size: 15px; }
        .footer { margin-top: 10px; font-size: 10px; text-align: center; }
        @media print { body { width: 70mm; } }
    </style>
</head>
<body>
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
    <div class="ticket-operator">
        <span>${duplicate ? "Duplicado" : ""}</span>
        <span>${escapeHtml(ticket.cajero_codigo)}</span>
    </div>
    <div class="center">
        ${ticket.folio ? "" : '<div class="warning">PRUEBA SIN FOLIO</div>'}
        ${ticket.estatus === "cancelled" ? '<div class="warning">CANCELADO</div>' : ""}
    </div>
    <div class="divider"></div>
    ${row("Cuenta:", ticket.cuenta_numero ?? ticket.cuenta_interna)}
    ${row("Titular", ticket.titular)}
    <div class="divider"></div>
    ${concepts || '<div class="center muted">Sin desglose de conceptos</div>'}
    <div class="divider"></div>
    ${ticket.subtotal !== null ? row("Subtotal", money(ticket.subtotal)) : ""}
    ${ticket.iva !== null ? row(`IVA ${ticket.iva_porcentaje ?? 16}%`, money(ticket.iva)) : ""}
    <div class="row total"><span>Total</span><strong>${escapeHtml(money(ticket.total))}</strong></div>
    <div class="divider"></div>
    ${row("Forma de pago", ticket.forma_pago)}
    ${row("Referencia", ticket.referencia)}
    ${row("Banco", ticket.banco)}
    ${row("Cheque", ticket.numero_cheque)}
    ${ticket.notas ? `<div><strong>Notas:</strong> ${escapeHtml(ticket.notas)}</div>` : ""}
    <div class="footer">
        <div>Este comprobante no tiene validez fiscal.</div>
        ${ticket.club_url_facturacion ? `<div>Facturación: ${escapeHtml(ticket.club_url_facturacion)}</div>` : ""}
    </div>
</body>
</html>`;
};

export const printTicket = async (paymentId: number, duplicate = false): Promise<void> => {
    const printWindow = window.open("", "_blank", "width=420,height=700");

    if (!printWindow) {
        throw new Error("El navegador bloqueó la ventana de impresión.");
    }

    printWindow.document.write("<p style='font-family: Arial'>Preparando ticket...</p>");

    try {
        const ticket = await getTicketData(paymentId);
        printWindow.document.open();
        printWindow.document.write(ticketHtml(ticket, duplicate));
        printWindow.document.close();

        await new Promise<void>((resolve) => {
            if (printWindow.document.readyState === "complete") {
                resolve();
            } else {
                printWindow.onload = () => resolve();
            }
        });

        printWindow.focus();
        printWindow.print();
    } catch (error) {
        printWindow.close();
        throw error;
    }
};
