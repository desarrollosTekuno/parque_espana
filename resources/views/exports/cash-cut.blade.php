<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
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
            {{ strtoupper($cashCut->club->name) }}
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
            Cobranza del {{ $cashCut->club->name }} de <strong>{{ $cashCut->cashier->name }}</strong>
        </td>
    </tr>
    <tr>
        <td colspan="8" style="border:none;height:15px;"></td>
    </tr>
    <tr>
        <td colspan="3">
            <table>
                <thead>
                <tr>
                    <th style="background:#D9EAD3; font-weight:bold; text-align:center; border:1px solid #808080;">Denominación</th>
                    <th style="background:#D9EAD3; font-weight:bold; text-align:center; border:1px solid #808080;">Cantidad</th>
                    <th style="background:#D9EAD3; font-weight:bold; text-align:center; border:1px solid #808080;">Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach($denominations as $denomination)
                    <tr>
                        <td>${{ number_format($denomination->denomination, 2) }}</td>
                        <td>{{ $denomination->quantity }}</td>
                        <td>${{ number_format($denomination->subtotal, 2) }}</td>
                    </tr>
                @endforeach
                    <tr>
                        <td colspan="2"
                            style="
                                background:#F2F2F2;
                                font-weight:bold;
                                text-align:right;
                                border-top:2px solid black;
                            ">
                            Total
                        </td>
                        <td
                            style="
                                background:#F2F2F2;
                                font-weight:bold;
                                text-align:right;
                                border-top:2px solid black;
                            ">
                            ${{ number_format($cashCut->cash_counted, 2) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </td>
        <td></td>
        <td colspan="3">
            <table>
                <thead>
                <tr>
                    <th style="background:#D9EAD3; font-weight:bold; text-align:center; border:1px solid #808080;">Método</th>
                    <th style="background:#D9EAD3; font-weight:bold; text-align:center; border:1px solid #808080;">Cantidad registrada</th>
                    <th style="background:#D9EAD3; font-weight:bold; text-align:center; border:1px solid #808080;">Suma registrada</th>
                </tr>
                </thead>
                <tbody>
                @foreach($paymentSummary as $method => $row)
                    <tr>
                        <td>{{ $method }}</td>
                        <td>{{ $row['quantity'] }}</td>
                        <td>${{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
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
