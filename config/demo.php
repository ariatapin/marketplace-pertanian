<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy Demo Users
    |--------------------------------------------------------------------------
    |
    | Jika true, sistem tetap menyiapkan akun demo lama
    | (mitra@demo.test, petani.*@demo.test). Default false agar
    | komposisi AutomationLoadUsersSeeder tetap tepat 100 non-admin.
    |
    */
    'seed_legacy_users' => (bool) env('DEMO_SEED_LEGACY_USERS', false),
];

