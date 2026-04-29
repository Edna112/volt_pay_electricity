<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VoltPay fees
    |--------------------------------------------------------------------------
    |
    | Fixed fee kept by VoltPay on every payment (XAF).
    |
    */
    'platform_fee_xaf' => (int) env('VOLTPAY_PLATFORM_FEE_XAF', 200),
];

