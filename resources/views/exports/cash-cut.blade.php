<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    @if(!$cashCut)

<table>

    <tr>
        <td style="font-size:16px;font-weight:bold;text-align:center;">
            No existe un corte de caja para este club.
        </td>
    </tr>

</table>

@php
    return;
@endphp

@endif
<table>
    <tr>
        <td colspan="8"
            style="
                font-size:18pt;
                font-weight:bold;
                text-align:center;
                color:#1F4E78;
                border:none;
                padding-bottom:8px;
            ">
            {{ $club->name }} 
        </td>
    </tr>
    <tr>
        <td colspan="8"
            style="
                font-size:13pt;
                text-align:center;
                border:none;
                color:#555;
                padding-bottom:4px;
                font-weight:bold;
            ">
            Cierre de caja del {{ $cashCut->date->format('d/m/Y') }}
        </td>
    </tr>
    <tr>
        <td colspan="8"
            style="
                font-size:11pt;
                text-align:center;
                border:none;
                padding-bottom:12px;
            ">
            Cobranza del {{ $club->name }} de <strong>{{ $cashCut->cashier->name }}</strong>
        </td>
    </tr>
    <tr>
        <td colspan="8" style="border:none;height:15px;"></td>
    </tr>
    
    @php
    $denominations = $denominations->values();
    $paymentSummary = $paymentSummary->values();

    $rows = max(
        $denominations->count(),
        $paymentSummary->count()
    );
@endphp

<tr>

    <th style="background:#D9EAD3;border:1px solid #808080;text-align:center;">
        Denominación
    </th>

    <th style="background:#D9EAD3;border:1px solid #808080;text-align:center;">
        Cantidad
    </th>

    <th style="background:#D9EAD3;border:1px solid #808080;text-align:center;">
        Total
    </th>

    <th style="border:none;width:4%;"></th>

    <th style="background:#D9EAD3;border:1px solid #808080;text-align:center;">
        Método
    </th>

    <th style="background:#D9EAD3;border:1px solid #808080;text-align:center;">
        Cantidad registrada
    </th>

    <th style="background:#D9EAD3;border:1px solid #808080;text-align:center;">
        Suma registrada
    </th>

</tr>

@for($i = 0; $i < $rows; $i++)

@php

    $d = $denominations->get($i);

    $p = $paymentSummary->get($i);

@endphp

<tr>

    {{-- Denominaciones --}}

    <td style="border:1px solid #C9C9C9;text-align:right;">

        {{ $d ? '$'.number_format($d->denomination,2) : '' }}

    </td>

    <td style="border:1px solid #C9C9C9;text-align:center;">

        {{ $d?->quantity }}

    </td>

    <td style="border:1px solid #C9C9C9;text-align:right;">

        {{ $d ? '$'.number_format($d->subtotal,2) : '' }}

    </td>

    {{-- Separador --}}

    <td style="border:none;"></td>

    {{-- Métodos --}}

    <td style="border:1px solid #C9C9C9;">

        {{ $p['method'] ?? '' }}

    </td>

    <td style="border:1px solid #C9C9C9;text-align:center;">

        {{ $p['quantity'] ?? '' }}

    </td>

    <td style="border:1px solid #C9C9C9;text-align:right;">

        {{ isset($p['total']) ? '$'.number_format($p['total'],2) : '' }}

    </td>

</tr>

@endfor

<tr>

    <td colspan="2"
        style="
            background:#F2F2F2;
            border-top:2px solid black;
            font-weight:bold;
            text-align:right;
        ">

        Total

    </td>

    <td
        style="
            background:#F2F2F2;
            border-top:2px solid black;
            font-weight:bold;
            text-align:right;
        ">

        ${{ number_format($cashCut->cash_counted,2) }}

    </td>

    <td style="border:none;"></td>

    <td colspan="2"
        style="
            background:#F2F2F2;
            border-top:2px solid black;
            font-weight:bold;
            text-align:right;
        ">

        Total

    </td>

    <td
        style="
            background:#F2F2F2;
            border-top:2px solid black;
            font-weight:bold;
            text-align:right;
        ">

        ${{ number_format($payments->sum('amount'),2) }}

    </td>

</tr>





    <tr>
        <td colspan="8" style="border:none;height:15px;"></td>
    </tr>
    <tr>
        <td colspan="6"
            style="
                background:#D9EAD3;
                font-weight:bold;
                text-align:right;
            ">
            TOTAL
        </td>
        <td colspan="2"
            style="
                background:#D9EAD3;
                font-weight:bold;
                text-align:right;
            ">
            ${{ number_format($payments->sum('amount'), 2) }}
        </td>
    </tr>
    <tr>
        <td colspan="6"
            style="font-weight:bold;text-align:right;color:{{ $cashCut->cash_difference == 0 ? '#008000' : '#C00000' }};">
            Diferencia:
        </td>
        <td colspan="2"
            style="font-weight:bold;text-align:right;color:{{ $cashCut->cash_difference == 0 ? '#008000' : '#C00000' }};">
            ${{ number_format($cashCut->cash_difference, 2) }}
        </td>
    </tr>
    <tr>
        <td colspan="8" style="height:40px;border:none;"></td>
    </tr>
    <tr>
        <td colspan="3"
            style="
                border-top:1px solid black;
                text-align:center;
                padding-top:5px;
                border-left:none;
                border-right:none;
                border-bottom:none;
            ">
            {{ $cashCut->cashier->name }}
        </td>
        <td colspan="2" style="border:none;"></td>
        <td colspan="3"
            style="
                border-top:1px solid black;
                text-align:center;
                padding-top:5px;
                border-left:none;
                border-right:none;
                border-bottom:none;
            ">
        </td>
    </tr>
    <tr>
        <td colspan="3"
            style="
                text-align:center;
                border:none;
                font-size:10pt;
                color:#666;
            ">
            Cajero
        </td>
        <td colspan="2" style="border:none;"></td>
        <td colspan="3"
            style="
                text-align:center;
                border:none;
                font-size:10pt;
                color:#666;
            ">
            Gerente
        </td>
    </tr>
</table>
</body>
</html>
