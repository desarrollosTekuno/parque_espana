@extends('emails.email_template_v2')

@section('title', 'Tu acceso al club')
@section('preheader', "Tu código de acceso para {$club->name} ya está listo.")
@section('header', "Acceso a {$club->name}")

@section('content')
    <p style="margin: 0 0 8px; font-size: 15px; line-height: 24px; font-weight: bold; color: #0f172a;">
        Hola,
    </p>
    <p style="margin: 0; font-size: 15px; line-height: 24px; color: #334155;">
        Gracias por tu compra. Muestra el código QR correspondiente en el punto de acceso del parque.
    </p>

    @foreach ($cardCodes as $cardCode)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; border: 1px solid #dbe3ec; border-radius: 12px; overflow: hidden; margin: 22px 0 0;">

            <tr>
                <td bgcolor="#f4f7fb" style="background: #f4f7fb; padding: 12px 18px; border-bottom: 1px solid #dbe3ec;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="left" style="font-family: Arial, Helvetica, sans-serif; font-size: 10px; font-weight: bold; letter-spacing: 1.6px; text-transform: uppercase; color: #1d4ed8;">
                                @if (count($cardCodes) > 1)
                                    Acceso {{ $loop->iteration }} de {{ count($cardCodes) }}
                                @else
                                    Código de acceso
                                @endif
                            </td>
                            <td align="right" style="font-family: 'Courier New', Courier, monospace; font-size: 16px; line-height: 20px; font-weight: bold; letter-spacing: 1.6px; color: #0f172a;">
                                {{ $cardCode }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td align="center" style="padding: 26px 18px 28px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto;">
                        <tr>
                            <td style="padding: 10px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px;">
                                <img
                                    src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=0&data={{ urlencode($cardCode) }}"
                                    alt="Código QR {{ $cardCode }}"
                                    width="150"
                                    height="150"
                                    style="display: block; width: 150px; height: 150px; border: 0;"
                                />
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

        </table>
    @endforeach

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 24px 0 0;">
        <tr>
            <td style="border-top: 1px solid #e2e8f0; padding-top: 18px;">
                <p style="margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 21px; color: #475569;">
                    Conserva este correo para presentarlo en el club.
                </p>
            </td>
        </tr>
    </table>
@endsection