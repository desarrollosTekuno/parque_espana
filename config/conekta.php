<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Conekta API Keys
    |--------------------------------------------------------------------------
    | Puedes obtener tus llaves en https://dashboard.conekta.com/
    | Usa las llaves de prueba (key_test_xxx) en desarrollo y
    | las de producción (key_live_xxx) en producción.
    */

    'secret_key'     => env('CONEKTA_SECRET_KEY', ''),
    'public_key'     => env('CONEKTA_PUBLIC_KEY', ''),
    'api_version'    => env('CONEKTA_API_VERSION', '2.1.0'),
];
