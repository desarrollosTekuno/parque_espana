<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            text-align: center;
            font-family: sans-serif;
            margin: 0;
            padding: 16px;
        }
        .qr {
            width: 220px;
            height: 220px;
            margin: 12px auto;
        }
        h2 { font-size: 14px; margin: 4px 0; }
        h3 { font-size: 13px; margin: 4px 0; }
        p  { font-size: 12px; margin: 4px 0; }
        small { font-size: 10px; color: #666; }
    </style>
</head>
<body>

    <h2>{{ $location->resource->amenity->club->name }}</h2>
    <h3>{{ $location->resource->amenity->name }}</h3>
    <p>{{ $location->resource->name }}</p>

    <div class="qr">
        {!! Storage::disk('public')->get($location->qr_image_path) !!}
    </div>

    <p>Escanea para registrar asistencia</p>
    <small>Generado: {{ $location->qr_generated_at?->format('d/m/Y H:i') }}</small>

</body>
</html>
