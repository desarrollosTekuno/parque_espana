<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
<table>
    <tr>
        <td colspan="9"
            style="
                font-size:18pt;
                font-weight:bold;
                text-align:center;
                color:#1F4E78;
                border:none;
                padding-bottom:8px;
            ">
            {{ $clubName ? $clubName : 'REPORTE DE COBRANZA' }}
        </td>
    </tr>
    <tr>
        <td colspan="9"
            style="
                font-size:13pt;
                text-align:center;
                border:none;
                color:#555;
                padding-bottom:4px;
                font-weight:bold;
            ">
            Reporte de cobranza
        </td>
    </tr>
    <tr>
        <td colspan="9"
            style="
                font-size:11pt;
                text-align:center;
                border:none;
                padding-bottom:12px;
            ">
            Del {{ $start->format('d/m/Y') }} al {{ $end->format('d/m/Y') }}
        </td>
    </tr>
    <tr>
        <td colspan="9" style="border:none;height:10px;"></td>
    </tr>

    {{-- Encabezado de dos niveles --}}
    <tr>
        <th colspan="3" style="background:#1F4E78;color:#FFFFFF;font-weight:bold;text-align:center;border:1px solid #808080;">
            Usuario
        </th>
        <th style="background:#1F4E78;color:#FFFFFF;font-weight:bold;text-align:center;border:1px solid #808080;">
            Cantidad
        </th>
        <th style="background:#1F4E78;color:#FFFFFF;font-weight:bold;text-align:center;border:1px solid #808080;">
            Importe
        </th>
        <th style="background:#1F4E78;color:#FFFFFF;font-weight:bold;text-align:center;border:1px solid #808080;">
            Bonificación
        </th>
        <th style="background:#1F4E78;color:#FFFFFF;font-weight:bold;text-align:center;border:1px solid #808080;">
            Descuento
        </th>
        <th style="background:#1F4E78;color:#FFFFFF;font-weight:bold;text-align:center;border:1px solid #808080;">
            Efectivo
        </th>
        <th style="background:#1F4E78;color:#FFFFFF;font-weight:bold;text-align:center;border:1px solid #808080;">
            Documento
        </th>
    </tr>
    <tr>
        <th style="background:#D9EAD3;font-weight:bold;text-align:center;border:1px solid #808080;">
            Código de usuario
        </th>
        <th style="background:#D9EAD3;font-weight:bold;text-align:center;border:1px solid #808080;">
            Tipo de membresía
        </th>
        <th style="background:#D9EAD3;font-weight:bold;text-align:center;border:1px solid #808080;">
            Fecha de pago
        </th>
        <th style="background:#D9EAD3;border:1px solid #808080;"></th>
        <th style="background:#D9EAD3;border:1px solid #808080;"></th>
        <th style="background:#D9EAD3;border:1px solid #808080;"></th>
        <th style="background:#D9EAD3;border:1px solid #808080;"></th>
        <th style="background:#D9EAD3;border:1px solid #808080;"></th>
        <th style="background:#D9EAD3;border:1px solid #808080;"></th>
    </tr>

    @forelse($groups as $group)
        {{-- Separador por tipo de concepto --}}
        <tr>
            <td colspan="9"
                style="
                    background:#FCE5CD;
                    font-weight:bold;
                    border:1px solid #808080;
                    padding:4px;
                ">
                Concepto de pago: {{ $group['concept_code'] }} - {{ $group['concept_name'] }}
            </td>
        </tr>

        @foreach($group['rows'] as $row)
            <tr>
                <td style="border:1px solid #C9C9C9;">{{ $row['user_code'] }}</td>
                <td style="border:1px solid #C9C9C9;">{{ $row['membership_type'] }}</td>
                <td style="border:1px solid #C9C9C9;text-align:center;">
                    {{ $row['paid_at'] ? $row['paid_at']->format('d/m/Y H:i') : '' }}
                </td>
                <td style="border:1px solid #C9C9C9;text-align:right;">${{ number_format($row['cantidad'], 2) }}</td>
                <td style="border:1px solid #C9C9C9;text-align:right;">${{ number_format($row['importe'], 2) }}</td>
                <td style="border:1px solid #C9C9C9;text-align:right;">${{ number_format($row['bonificacion'], 2) }}</td>
                <td style="border:1px solid #C9C9C9;text-align:right;">${{ number_format($row['descuento'], 2) }}</td>
                <td style="border:1px solid #C9C9C9;text-align:right;">${{ number_format($row['efectivo'], 2) }}</td>
                <td style="border:1px solid #C9C9C9;"></td>
            </tr>
        @endforeach

        {{-- Subtotal del concepto --}}
        <tr>
            <td colspan="3"
                style="background:#F2F2F2;font-weight:bold;text-align:right;border:1px solid #C9C9C9;">
                Subtotal {{ $group['concept_name'] }}
            </td>
            <td style="background:#F2F2F2;font-weight:bold;text-align:right;border:1px solid #C9C9C9;">
                ${{ number_format($group['totals']['cantidad'], 2) }}
            </td>
            <td style="background:#F2F2F2;font-weight:bold;text-align:right;border:1px solid #C9C9C9;">
                ${{ number_format($group['totals']['importe'], 2) }}
            </td>
            <td style="background:#F2F2F2;font-weight:bold;text-align:right;border:1px solid #C9C9C9;">
                ${{ number_format($group['totals']['bonificacion'], 2) }}
            </td>
            <td style="background:#F2F2F2;font-weight:bold;text-align:right;border:1px solid #C9C9C9;">
                ${{ number_format($group['totals']['descuento'], 2) }}
            </td>
            <td style="background:#F2F2F2;font-weight:bold;text-align:right;border:1px solid #C9C9C9;">
                ${{ number_format($group['totals']['efectivo'], 2) }}
            </td>
            <td style="background:#F2F2F2;border:1px solid #C9C9C9;"></td>
        </tr>
        <tr>
            <td colspan="9" style="border:none;height:8px;"></td>
        </tr>
    @empty
        <tr>
            <td colspan="9" style="text-align:center;border:1px solid #C9C9C9;padding:8px;">
                No se registraron cobros en el rango de fechas seleccionado.
            </td>
        </tr>
    @endforelse

    {{-- Gran total --}}
    @if($groups->isNotEmpty())
        <tr>
            <td colspan="3"
                style="background:#D9EAD3;font-weight:bold;text-align:right;border:1px solid #808080;">
                TOTAL GENERAL
            </td>
            <td style="background:#D9EAD3;font-weight:bold;text-align:right;border:1px solid #808080;">
                ${{ number_format($grandTotals['cantidad'], 2) }}
            </td>
            <td style="background:#D9EAD3;font-weight:bold;text-align:right;border:1px solid #808080;">
                ${{ number_format($grandTotals['importe'], 2) }}
            </td>
            <td style="background:#D9EAD3;font-weight:bold;text-align:right;border:1px solid #808080;">
                ${{ number_format($grandTotals['bonificacion'], 2) }}
            </td>
            <td style="background:#D9EAD3;font-weight:bold;text-align:right;border:1px solid #808080;">
                ${{ number_format($grandTotals['descuento'], 2) }}
            </td>
            <td style="background:#D9EAD3;font-weight:bold;text-align:right;border:1px solid #808080;">
                ${{ number_format($grandTotals['efectivo'], 2) }}
            </td>
            <td style="background:#D9EAD3;border:1px solid #808080;"></td>
        </tr>
    @endif
</table>
</body>
</html>
