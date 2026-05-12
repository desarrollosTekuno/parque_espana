@extends('emails.email_template')

@section('title', $subjectText)

@section('header', 'Tu ticket fue rechazado')

@section('content')
    <p style="margin-bottom: 18px;">
        Te informamos que tu queja/sugerencia fue revisada y no pudo continuar.
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
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ $ticket->status->name ?? 'RECHAZADO' }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #334155; font-weight: 700;">Fecha de rechazo</td>
            <td style="padding: 10px 12px; border-top: 1px solid #e2e8f0; color: #0f172a;">{{ optional($ticket->rejected_at)->format('d/m/Y H:i') ?: optional($ticket->updated_at)->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    @if (!empty($rejectionMessage))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
            <tr>
                <td style="background-color: #f8fafc; padding: 10px 12px; font-weight: 700; color: #0f172a;">
                    Motivo de rechazo
                </td>
            </tr>
            <tr>
                <td style="padding: 12px; border-top: 1px solid #e2e8f0; color: #0f172a; line-height: 1.65;">
                    {!! nl2br(e($rejectionMessage)) !!}
                </td>
            </tr>
        </table>
    @endif
@endsection
