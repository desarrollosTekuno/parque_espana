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

    /*
    |--------------------------------------------------------------------------
    | SPEI
    |--------------------------------------------------------------------------
    | spei_expiration_hours: cuántas horas tiene el socio para realizar la
    |   transferencia antes de que el CLABE expire (2 – 8760).
    |
    | spei_payment_method_code: código del registro en billing.payment_methods
    |   que representa pagos SPEI/transferencia. Créalo en el panel de admin
    |   con provider = 'conekta'.
    */

    'spei_expiration_hours'       => env('CONEKTA_SPEI_EXPIRATION_HOURS', 72),
    'spei_payment_method_code'    => env('CONEKTA_SPEI_PAYMENT_METHOD_CODE', 'SPEI'),
];
