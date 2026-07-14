import axios from "axios";

export async function obtenerDocumentoHtmlTicket(paymentId) {
    const datos = await obtenerDatosTicket(paymentId);

    const contenido = armarHtmlTicket(datos);

    return armarDocumentoCompleto(contenido);
}

export async function imprimirTicket(paymentId) {
    const documento = await obtenerDocumentoHtmlTicket(paymentId);

    abrirVentanaEImprimir(documento);
}

export async function descargarTicket(paymentId) {
    const documento = await obtenerDocumentoHtmlTicket(paymentId);

    descargarComoArchivo(documento, "ticket-" + paymentId + ".html");
}

export async function obtenerDatosTicket(paymentId) {
    const respuesta = await axios.get(route("tickets.data", paymentId));

    return respuesta.data;
}

function formatearFecha(fechaTexto) {
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

function armarNombreParque(datos) {
    let html = "";

    if (datos.club_razon_social_lineas && datos.club_razon_social_lineas.length > 0) {
        for (let i = 0; i < datos.club_razon_social_lineas.length; i++) {
            const clase = i === 0 ? "center bold" : "center";
            html += '<div class="' + clase + '">' + datos.club_razon_social_lineas[i] + "</div>";
        }
    } else {
        html += '<div class="center bold">' + (datos.club_nombre ?? "") + "</div>";
    }

    return html;
}

function armarRfcYDireccion(datos) {
    let html = "";

    if (datos.club_rfc) {
        html += '<div class="center">' + datos.club_rfc + "</div>";
    }

    if (datos.club_direccion_lineas) {
        for (let i = 0; i < datos.club_direccion_lineas.length; i++) {
            html += '<div class="center">' + datos.club_direccion_lineas[i] + "</div>";
        }
    }

    return html;
}

function armarHtmlTicket(datos) {
    let html = "";

    html += '<div class="ticket">';

    html += armarNombreParque(datos);

    if (datos.club_logo_url) {
        html +=
            '<div class="encabezado"><img class="logo" src="' +
            datos.club_logo_url +
            '" /><div class="encabezado-texto">' +
            armarRfcYDireccion(datos) +
            "</div></div>";
    } else {
        html += armarRfcYDireccion(datos);
    }

    html += '<div class="sep"></div>';

    html +=
        '<div class="row"><span>' +
        (datos.folio_corto ?? "") +
        "</span><span>" +
        formatearFecha(datos.fecha) +
        "</span></div>";

    html += "<div>" + (datos.cajero_codigo ?? "") + "</div>";

    html += '<div class="sep"></div>';

    if (datos.cuenta_numero) {
        html += "<div>Cuenta: " + datos.cuenta_numero + "</div>";
    }

    if (datos.titular) {
        html += "<div>" + datos.titular.toUpperCase() + "</div>";
    }

    html += '<div class="sep"></div>';

    html += '<div class="row bold"><span>Concepto</span><span>Total</span></div>';

    for (let i = 0; i < datos.conceptos.length; i++) {
        const concepto = datos.conceptos[i];
        const descripcion = concepto.descripcion ?? "";

        html +=
            '<div class="row"><span>' +
            descripcion.toUpperCase() +
            "</span><span>" +
            formatearMonto(concepto.monto) +
            "</span></div>";
    }

    html += '<div class="sep"></div>';

    if (datos.subtotal !== null && datos.iva !== null) {
        html +=
            '<div class="row"><span>Subtotal</span><span>' +
            formatearMonto(datos.subtotal) +
            "</span></div>";

        html +=
            '<div class="row"><span>IVA 16%</span><span>' +
            formatearMonto(datos.iva) +
            "</span></div>";
    }

    html +=
        '<div class="row bold"><span>Total</span><span>' +
        formatearMonto(datos.total) +
        "</span></div>";

    html += '<div class="sep"></div>';

    let lineaFormaPago = datos.forma_pago ?? "";

    if (datos.referencia) {
        lineaFormaPago += " " + datos.referencia;
    }

    html += "<div>" + lineaFormaPago + "</div>";

    html += '<div class="sep"></div>';

    html += '<div class="center">DOS MESES SIN APORTACIÓN GENERAN SUSPENSIÓN</div>';
    html += '<div class="center">Este comprobante no tiene validez fiscal.</div>';

    html += '<div class="sep"></div>';

    html += "<div>Identificación de Archivo:</div>";
    html += "<div>XXX0000</div>";

    if (datos.club_url_facturacion) {
        html += '<div class="sep"></div>';
        html += '<div class="center">Puede descargar sus comprobantes fiscales en:</div>';
        html += '<div class="center">' + datos.club_url_facturacion + "</div>";
    }

    html += "</div>";

    return html;
}

function estilosDelTicket() {
    return (
        "<style>" +
        "@page { size: 76mm 3276mm; margin: 0; }" +
        "html, body { margin: 0; padding: 0; background: #ddd; }" +
        ".ticket { width: 72mm; box-sizing: border-box; padding: 2mm; font-family: monospace; font-size: 3mm; line-height: 1.35; color: #000; background: #fff; }" +
        ".center { text-align: center; }" +
        ".row { display: flex; justify-content: space-between; }" +
        ".bold { font-weight: bold; }" +
        ".sep { border-top: 1px dashed #000; margin: 1.5mm 0; }" +
        ".encabezado { display: flex; align-items: center; gap: 2mm; }" +
        ".encabezado .logo { width: 14mm; height: 14mm; object-fit: contain; flex-shrink: 0; }" +
        ".encabezado .encabezado-texto { flex: 1; }" +
        "@media print { html, body { background: #fff; } }" +
        "</style>"
    );
}

function armarDocumentoCompleto(html) {
    return (
        "<!DOCTYPE html><html><head><meta charset='utf-8' /><title>Ticket</title>" +
        estilosDelTicket() +
        "</head><body>" +
        html +
        "</body></html>"
    );
}

function abrirVentanaEImprimir(documento) {
    const ventana = window.open("", "", "width=400,height=600");

    if (!ventana) {
        return;
    }

    ventana.document.open();
    ventana.document.write(documento);
    ventana.document.close();

    ventana.focus();
    ventana.print();

    setTimeout(function () {
        try {
            ventana.close();
        } catch (e) {}
    }, 200);
}

function descargarComoArchivo(contenido, nombreArchivo) {
    const blob = new Blob([contenido], { type: "text/html" });
    const url = URL.createObjectURL(blob);

    const enlace = document.createElement("a");
    enlace.href = url;
    enlace.download = nombreArchivo;

    document.body.appendChild(enlace);
    enlace.click();
    document.body.removeChild(enlace);

    URL.revokeObjectURL(url);
}
