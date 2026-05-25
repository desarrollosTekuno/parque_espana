<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de contraseña</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 480px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .header {
            background-color: #1a3c5e;
            padding: 32px 24px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 20px;
            margin: 0;
        }
        .body {
            padding: 32px 24px;
            color: #333333;
        }
        .body p {
            font-size: 15px;
            line-height: 1.6;
            margin: 0 0 16px;
        }
        .otp-box {
            text-align: center;
            margin: 28px 0;
        }
        .otp-code {
            display: inline-block;
            font-size: 42px;
            font-weight: bold;
            letter-spacing: 12px;
            color: #1a3c5e;
            background: #f0f4ff;
            border: 2px dashed #1a3c5e;
            border-radius: 10px;
            padding: 16px 28px;
        }
        .expiry {
            font-size: 13px;
            color: #888888;
            text-align: center;
            margin-top: 8px;
        }
        .warning {
            background: #fff8e1;
            border-left: 4px solid #f9a825;
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 13px;
            color: #555;
            margin-top: 24px;
        }
        .footer {
            background: #f9f9f9;
            padding: 20px 24px;
            text-align: center;
            font-size: 12px;
            color: #aaaaaa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recuperación de contraseña</h1>
        </div>
        <div class="body">
            <p>Hola, <strong>{{ $userName }}</strong>.</p>
            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta. Ingresa el siguiente código en la aplicación:</p>

            <div class="otp-box">
                <div class="otp-code">{{ $otp }}</div>
                <div class="expiry">
                    Válido por {{ $expiryMinutes >= 60 ? round($expiryMinutes / 60) . ' hora(s)' : $expiryMinutes . ' minuto(s)' }}
                </div>
            </div>

            <p>Si no solicitaste este cambio, puedes ignorar este correo. Tu contraseña no será modificada.</p>

            <div class="warning">
                ⚠️ Por seguridad, nunca compartas este código con nadie. Nuestro equipo jamás te lo solicitará.
            </div>
        </div>
        <div class="footer">
            © {{ date('Y') }} Parque España. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
