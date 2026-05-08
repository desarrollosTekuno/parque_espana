<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Correo Electronico')</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f8fb;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .email-header {
            background: linear-gradient(90deg, #0097b2, #004aad);
            color: #ffffff;
            text-align: center;
            padding: 15px;
        }
        .email-header h1 {
            margin: 0;
            font-size: 22px;
        }
        .email-body {
            padding: 20px;
            color: #333333;
        }
        .email-body p {
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .email-footer {
            background-color: #eef2f7;
            border-top: 3px solid #d4172a;
            padding: 15px;
            text-align: center;
            font-size: 14px;
            color: #666666;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            color: #ffffff !important;
            background-color: #0097b2;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
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
