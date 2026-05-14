<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Correo Electronico')</title>
    <style>
        body {
            margin: 0;
            padding: 24px 12px;
            font-family: Arial, sans-serif;
            background-color: #f3f7fb;
            background-image: radial-gradient(circle at 10% 10%, rgba(0, 151, 178, 0.14), transparent 38%), radial-gradient(circle at 90% 90%, rgba(212, 23, 42, 0.1), transparent 36%);
        }
        .email-container {
            max-width: 640px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 18px;
            border: 1px solid #e5edf5;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.14);
            overflow: hidden;
        }
        .email-topbar {
            height: 6px;
            background: linear-gradient(90deg, #0097b2 0%, #004aad 60%, #d4172a 100%);
        }
        .email-header {
            padding: 26px 28px 16px;
            color: #0f172a;
            background: linear-gradient(180deg, rgba(0, 151, 178, 0.08), rgba(255, 255, 255, 0));
        }
        .email-brand {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #004aad;
            background-color: rgba(0, 74, 173, 0.1);
            border: 1px solid rgba(0, 74, 173, 0.18);
            border-radius: 999px;
            padding: 5px 10px;
            margin-bottom: 12px;
        }
        .email-header h1 {
            margin: 0;
            font-size: 26px;
            line-height: 1.3;
        }
        .email-body {
            padding: 8px 28px 26px;
            color: #1f2937;
            font-size: 15px;
        }
        .email-body p {
            line-height: 1.7;
            margin: 0 0 16px;
        }
        .email-footer {
            background-color: #f7fafc;
            border-top: 1px solid #e2e8f0;
            padding: 16px 24px 20px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }
        .email-footer p {
            margin: 0;
        }
        .btn {
            display: inline-block;
            margin-top: 6px;
            padding: 11px 18px;
            color: #ffffff !important;
            background: linear-gradient(135deg, #0097b2, #004aad);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-topbar"></div>
        <div class="email-header">
            <span class="email-brand">{{ config('app.name') }}</span>
            <h1>@yield('header', 'Correo Electronico')</h1>
        </div>
        <div class="email-body">
            @yield('content')
        </div>
        <div class="email-footer">
            <p>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
