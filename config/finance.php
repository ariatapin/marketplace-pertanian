<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Finance Demo Mode
    |--------------------------------------------------------------------------
    |
    | Sistem keuangan wallet/withdraw di project ini saat ini diposisikan
    | sebagai modul demo/simulasi. Set false untuk mematikan mutasi demo
    | (topup saldo demo, pembayaran saldo procurement, mark paid withdraw).
    |
    */
    'demo_mode' => (bool) env('FINANCE_DEMO_MODE', true),
];
