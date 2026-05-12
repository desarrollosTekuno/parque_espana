@extends('emails.email_template')

@section('title', $subjectText)

@section('header', $recipientType === 'client'
    ? ($event === 'cancelled' ? 'Confirmacion de cancelacion' : 'Confirmacion de recepcion')
    : ($event === 'cancelled' ? 'Ticket cancelado' : 'Nuevo ticket registrado'))

@section('content')
    <p style="margin-bottom: 18px;">
        @if ($recipientType === 'client')
            {{ $event === 'cancelled' ? 'Tu ticket fue cancelado correctamente.' : 'Tu queja/sugerencia fue recibida correctamente.' }}
        @else
            Se {{ $event === 'cancelled' ? 'cancelo' : 'registro' }} un ticket de feedback en el sistema.
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 18px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
        <tr>
            <td style="background-color: #f8fafc; padding: 10px 12px; font-weight: 700; color: #0f172a;" colspan="2">
                Resumen del ticket
            </td>
        </tr>
        <tr>
            <td width="35%" style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Folio</td>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->ticket_number }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Fecha</td>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ optional($ticket->created_at)->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Estado</td>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->status->name ?? 'N/A' }}</td>
        </tr>
        @if ($recipientType === 'admin')
            <tr>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Tipo</td>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->type->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Categoria</td>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->category->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Prioridad</td>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->priority->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Reportado por</td>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->is_anonymous ? 'Anonimo' : ($ticket->reportedBy->name ?? 'N/A') }}</td>
            </tr>
        @endif
        @if ($event === 'cancelled')
            <tr>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Fecha de cancelacion</td>
                <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ optional($ticket->closed_at)->format('d/m/Y H:i') }}</td>
            </tr>
        @endif
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 18px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
        <tr>
            <td style="background-color: #f8fafc; padding: 10px 12px; font-weight: 700; color: #0f172a;">
                Detalle del mensaje
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
        <tr>
            <td style="padding: 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">
                Descripcion
            </td>
        </tr>
        <tr>
            <td style="padding: 0 12px 14px; color: #0f172a; line-height: 1.65;">
                {!! nl2br(e($ticket->description)) !!}
            </td>
        </tr>
    </table>

    @if ($recipientType === 'admin' && !empty($attachmentLinks))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 18px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
            <tr>
                <td style="background-color: #f8fafc; padding: 10px 12px; font-weight: 700; color: #0f172a;">
                    Archivos adjuntos
                </td>
            </tr>
            <tr>
                <td style="padding: 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">
                    @foreach ($attachmentLinks as $attachment)
                        <div style="margin-bottom: 8px;">
                            <a href="{{ $attachment['url'] }}" target="_blank" style="color: #004aad; text-decoration: underline;">
                                {{ $attachment['name'] }}
                            </a>
                        </div>
                    @endforeach
                </td>
            </tr>
        </table>
    @endif

    @if ($recipientType === 'admin' && !empty($reviewUrl))
        <p style="margin: 0;">
            <a class="btn" href="{{ $reviewUrl }}" target="_blank">Revisar ticket en el sistema</a>
        </p>
    @endif
@endsection
