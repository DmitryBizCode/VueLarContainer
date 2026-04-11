<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Manual ledger (synthetic) transactions
    |--------------------------------------------------------------------------
    |
    | When an accountant approves payment or a rental is marked paid without a
    | PSP row, one idempotent Transaction is created so finance UIs and charts
    | that read `transactions` stay consistent.
    |
    */
    'ledger' => [
        'external_id_prefix' => env('FINANCE_LEDGER_EXTERNAL_PREFIX', 'ledger:rental:'),
        'default_payment_method' => env('FINANCE_LEDGER_PAYMENT_METHOD', 'bank_transfer'),
        'default_status' => env('FINANCE_LEDGER_STATUS', 'completed'),
        'default_currency' => env('FINANCE_LEDGER_CURRENCY', 'USD'),
    ],

];
