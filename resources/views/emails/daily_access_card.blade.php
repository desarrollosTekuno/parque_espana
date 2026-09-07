@extends('emails.email_template')

@section('title', 'Tu acceso al club')
@section('header', 'Acceso al club')

@section('content')
    <p style="font-size: 15px; color: #0f172a; margin: 0 0 6px;">
        Hola,
    </p>
    <p style="font-size: 14px; color: #475569; margin: 0 0 24px;">
        Gracias por tu compra. Te compartimos el código de acceso para tu visita a
        <strong>{{ $club->name }}</strong>, válido del
        <strong>{{ $validFromFormatted }}</strong> al <strong>{{ $validUntilFormatted }}</strong>.
        Muestra el código QR correspondiente en el punto de acceso del parque.
    </p>

    @foreach ($cardCodes as $cardCode)
        <div style="margin-bottom: 28px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="vertical-align: top; padding-right: 20px;">
                        <p style="margin: 0 0 4px; font-size: 13px; color: #64748b;">
                            Código de acceso
                        </p>
                        <p style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; font-family: monospace;">
                            {{ $cardCode }}
                        </p>
                    </td>
                    <td style="vertical-align: top; text-align: center; width: 140px;">
                        <img
                            src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data={{ urlencode($cardCode) }}"
                            alt="QR {{ $cardCode }}"
                            width="130"
                            height="130"
                            style="display: block; border-radius: 8px;"
                        />
                    </td>
                </tr>
            </table>
        </div>
    @endforeach

    <div style="margin-top: 16px; padding: 14px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
        <p style="margin: 0; font-size: 13px; color: #14532d;">
            Este acceso es válido únicamente durante el periodo indicado. Conserva este correo para presentarlo en el club.
        </p>
    </div>
@endsection