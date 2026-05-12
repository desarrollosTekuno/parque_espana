@extends('emails.email_template')

@section('title', $subjectText)

@section('header', 'Tu ticket fue resuelto')

@section('content')
    <p style="margin-bottom: 18px;">
        Te informamos que tu queja/sugerencia ya fue atendida y marcada como resuelta.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 18px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
        <tr>
            <td style="background-color: #f8fafc; padding: 10px 12px; font-weight: 700; color: #0f172a;" colspan="2">
                Resumen
            </td>
        </tr>
        <tr>
            <td width="35%" style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Folio</td>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->ticket_number }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Estatus</td>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->status->name ?? 'RESUELTO' }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Fecha de resolucion</td>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ optional($ticket->resolved_at)->format('d/m/Y H:i') ?: optional($ticket->updated_at)->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
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

        @if (!empty($resolutionMessage))
            <tr>
                <td style="padding: 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">
                    Comentario de resolucion
                </td>
            </tr>
            <tr>
                <td style="padding: 0 12px 14px; color: #0f172a; line-height: 1.65;">
                    {!! nl2br(e($resolutionMessage)) !!}
                </td>
            </tr>
        @endif
    </table>
@endsection
