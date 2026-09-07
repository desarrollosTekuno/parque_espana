<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
@php
    $columns = 4 + $methods->count();
    $methodTotals = $tickets->reduce(function ($totals, $ticket) use ($methods) {
        foreach ($methods as $method) {
            $totals[$method] = ($totals[$method] ?? 0) + $ticket['methods'][$method];
        }

        return $totals;
    }, []);
@endphp

<table>
    <tr><td colspan="{{ $columns }}" style="font-size:18pt;font-weight:bold;text-align:center;border:none;">{{ $clubName }}</td></tr>
    <tr><td colspan="{{ $columns }}" style="font-size:14pt;font-weight:bold;text-align:center;border:none;">Reporte Global Diario de Caja del {{ \Carbon\Carbon::parse($date)->format('d/m/y') }}</td></tr>
    <tr><td colspan="{{ $columns }}" style="font-size:12pt;font-weight:bold;text-align:center;border:none;">Cobranza {{ $clubName }} de {{ $cashierName }}</td></tr>
    <tr><td colspan="{{ $columns }}" style="height:15px;border:none;"></td></tr>
    <tr>
        <th>Num. Ticket</th>
        <th>Num. Usuario</th>
        <th>Nombre</th>
        <th>Total</th>
        @foreach($methods as $method)<th>{{ $method }}</th>@endforeach
    </tr>
    @foreach($tickets as $ticket)
        <tr>
            <td>{{ $ticket['folio'] }}</td>
            <td>{{ $ticket['membership_number'] }}</td>
            <td>{{ $ticket['holder_name'] }}</td>
            <td>${{ number_format($ticket['total'], 2) }}</td>
            @foreach($methods as $method)<td>${{ number_format($ticket['methods'][$method], 2) }}</td>@endforeach
        </tr>
    @endforeach
    <tr>
        <td colspan="3" style="font-weight:bold;text-align:right;">TOTAL</td>
        <td style="font-weight:bold;">${{ number_format($tickets->sum('total'), 2) }}</td>
        @foreach($methods as $method)<td style="font-weight:bold;">${{ number_format($methodTotals[$method] ?? 0, 2) }}</td>@endforeach
    </tr>
    <tr><td colspan="{{ $columns }}" style="height:20px;border:none;"></td></tr>
    <tr><td colspan="{{ $columns }}" style="font-size:12pt;font-weight:bold;border:none;">Tickets Cancelados</td></tr>
    <tr>
        <th>Num. Ticket</th>
        <th>Num. Usuario</th>
        <th>Nombre</th>
        <th>Total</th>
        @foreach($methods as $method)<th>{{ $method }}</th>@endforeach
    </tr>
    @forelse($cancelledTickets as $ticket)
        <tr>
            <td>{{ $ticket['folio'] }}</td>
            <td>{{ $ticket['membership_number'] }}</td>
            <td>{{ $ticket['holder_name'] }}</td>
            <td>${{ number_format($ticket['total'], 2) }}</td>
            @foreach($methods as $method)<td>${{ number_format($ticket['methods'][$method], 2) }}</td>@endforeach
        </tr>
    @empty
        <tr><td colspan="{{ $columns }}" style="text-align:center;">Sin tickets cancelados</td></tr>
    @endforelse
    <tr><td colspan="{{ $columns }}" style="height:45px;border:none;"></td></tr>
    <tr>
        <td colspan="2" style="border-top:1px solid black;text-align:center;">{{ $cashierName }}</td>
        <td colspan="2" style="border:none;"></td>
        <td colspan="{{ max(1, $columns - 4) }}" style="border-top:1px solid black;text-align:center;"></td>
    </tr>
    <tr>
        <td colspan="2" style="text-align:center;border:none;">Cajero</td>
        <td colspan="2" style="border:none;"></td>
        <td colspan="{{ max(1, $columns - 4) }}" style="text-align:center;border:none;">Gtte At. Usuarios</td>
    </tr>
</table>
</body>
</html>
