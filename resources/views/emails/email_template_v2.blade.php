<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>@yield('title', 'Notificación')</title>
</head>
<body style="margin: 0; padding: 0; width: 100%; background: #e7ecf3;">

<div style="display: none; font-size: 1px; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden;">
    @yield('preheader', '')
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #e7ecf3;">
    <tr>
        <td align="center" style="padding: 36px 12px 44px;">

            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width: 600px; max-width: 600px;">
                <tr>
                    <td style="background: #ffffff; border: 1px solid #dbe3ec; border-radius: 16px; overflow: hidden;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">

                            {{-- Cabecera --}}
                            <tr>
                                <td>
                                    @php
                                        $parkName     = $parkName     ?? 'Parque España';
                                        $parkInitials = $parkInitials ?? 'PE';
                                        $parkLogo     = $parkLogo     ?? null;
                                        $eyebrow      = $eyebrow      ?? 'Notificación';
                                        $headerBg     = '#0a2540';
                                        $headerAccent = '#7fb3d5';
                                    @endphp

                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td bgcolor="{{ $headerBg }}" style="background: {{ $headerBg }}; padding: 26px 32px 30px;">

                                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 20px;">
                                                    <tr>
                                                        <td align="left" style="vertical-align: middle;">
                                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                                <tr>
                                                                    <td style="vertical-align: middle;">
                                                                        @if ($parkLogo)
                                                                            <img src="{{ $parkLogo }}" alt="{{ $parkName }}" height="64" style="display: block; height: 64px; width: auto; border: 0;" />
                                                                        @else
                                                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                                                <tr>
                                                                                    <td width="64" height="64" style="width: 64px; height: 64px; background: #ffffff; border-radius: 10px; text-align: center; vertical-align: middle; font-family: Arial, Helvetica, sans-serif; font-size: 20px; font-weight: bold; color: {{ $headerBg }};">
                                                                                        {{ $parkInitials }}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        @endif
                                                                    </td>
                                                                    <td style="padding-left: 14px; vertical-align: middle;">
                                                                        <p style="margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff;">{{ $parkName }}</p>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                        <td align="right" style="vertical-align: middle; font-family: Arial, Helvetica, sans-serif; font-size: 11px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; color: {{ $headerAccent }}; white-space: nowrap;">
                                                            {{ $eyebrow }}
                                                        </td>
                                                    </tr>
                                                </table>

                                                <h1 style="margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 28px; line-height: 1.3; font-weight: bold; color: #ffffff;">
                                                    @yield('header', 'Notificación')
                                                </h1>

                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            {{-- Contenido específico de cada correo --}}
                            <tr>
                                <td style="padding: 30px 36px 34px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 24px; color: #334155;">
                                    @yield('content')
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>

                {{-- Pie --}}
                <tr>
                    <td>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding: 22px 24px 0; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 20px; color: #475569; text-align: center;">
                                    <p style="margin: 0 0 6px; color: #475569;">
                                        ¿Dudas? Escríbenos a
                                        <a href="mailto:{{ $supportEmail ?? 'atencion@parqueespana.mx' }}" style="color: #1d4ed8; text-decoration: underline;">{{ $supportEmail ?? 'atencion@parqueespana.mx' }}</a>
                                    </p>
                                    <p style="margin: 0; color: #475569;">
                                        {{ $parkName }} · Recibiste este correo por una compra o solicitud en tu cuenta.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>