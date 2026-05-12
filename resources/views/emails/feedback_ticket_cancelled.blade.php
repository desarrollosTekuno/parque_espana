@extends('emails.email_template')

@section('title', $subjectText)

@section('header', $recipientType === 'client' ? 'Ticket cancelado' : 'Cancelacion de ticket')

@section('content')
    <p style="margin-bottom: 18px;">
        @if ($recipientType === 'client')
            Confirmamos que tu ticket fue cancelado correctamente.
        @else
            Se cancelo un ticket de feedback en el sistema.
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 18px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
        <tr>
            <td style="background-color: #f8fafc; padding: 10px 12px; font-weight: 700; color: #0f172a;" colspan="2">
                Resumen de cancelacion
            </td>
        </tr>
        <tr>
            <td width="35%" style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Folio</td>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->ticket_number }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Estado</td>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->status->name ?? 'CANCELADO' }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Fecha de cancelacion</td>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ optional($ticket->closed_at)->format('d/m/Y H:i') }}</td>
        </tr>
        @if ($recipientType === 'admin')
            <tr>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Reportado por</td>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->is_anonymous ? 'Anonimo' : ($ticket->reportedBy->name ?? 'N/A') }}</td>
            </tr>
        @endif
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 18px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
        <tr>
            <td style="background-color: #f8fafc; padding: 10px 12px; font-weight: 700; color: #0f172a;">
                Ticket
            </td>
        </tr>
        <tr>
            <td style="padding: 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">
                Titulo
            </td>
        </tr>
        <tr>
            <td style="padding: 0 12px 12px; color: #0f172a;">
                {{ $ticket->title }}
            </td>
        </tr>
    </table>

    @if ($recipientType === 'admin' && !empty($reviewUrl))
        <p style="margin: 0;">
            <a href="{{ $reviewUrl }}" target="_blank" style="color: #004aad; text-decoration: underline; font-weight: 600;">
                Revisar ticket en el sistema
            </a>
        </p>
    @endif
@endsection
