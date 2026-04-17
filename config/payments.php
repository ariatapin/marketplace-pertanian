<?php

return [
    'default_method' => 'bank_transfer',
    'methods' => [
        'bank_transfer' => [
            'label' => 'Transfer Bank',
            'kind' => 'bank',
            'is_active' => true,
            'account_name' => env('PAYMENT_BANK_ACCOUNT_NAME', strtoupper((string) env('APP_NAME', 'Marketplace')) . ' ESCROW'),
            'account_number' => env('PAYMENT_BANK_ACCOUNT_NUMBER', '1234567890'),
            'provider' => env('PAYMENT_BANK_PROVIDER', 'BCA'),
        ],
        'gopay' => [
            'label' => 'GoPay',
            'kind' => 'wallet',
            'is_active' => true,
            'account_name' => env('PAYMENT_GOPAY_ACCOUNT_NAME', strtoupper((string) env('APP_NAME', 'Marketplace')) . ' WALLET'),
            'account_number' => env('PAYMENT_GOPAY_ACCOUNT_NUMBER', '081234567890'),
            'provider' => 'GoPay',
        ],
        'ovo' => [
            'label' => 'OVO',
            'kind' => 'wallet',
            'is_active' => true,
            'account_name' => env('PAYMENT_OVO_ACCOUNT_NAME', strtoupper((string) env('APP_NAME', 'Marketplace')) . ' WALLET'),
            'account_number' => env('PAYMENT_OVO_ACCOUNT_NUMBER', '081234567890'),
            'provider' => 'OVO',
        ],
        'dana' => [
            'label' => 'DANA',
            'kind' => 'wallet',
            'is_active' => true,
            'account_name' => env('PAYMENT_DANA_ACCOUNT_NAME', strtoupper((string) env('APP_NAME', 'Marketplace')) . ' WALLET'),
            'account_number' => env('PAYMENT_DANA_ACCOUNT_NUMBER', '081234567890'),
            'provider' => 'DANA',
        ],
        'shopeepay' => [
            'label' => 'ShopeePay',
            'kind' => 'wallet',
            'is_active' => true,
            'account_name' => env('PAYMENT_SHOPEEPAY_ACCOUNT_NAME', strtoupper((string) env('APP_NAME', 'Marketplace')) . ' WALLET'),
            'account_number' => env('PAYMENT_SHOPEEPAY_ACCOUNT_NUMBER', '081234567890'),
            'provider' => 'ShopeePay',
        ],
    ],
];
