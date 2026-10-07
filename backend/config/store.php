<?php

return [
    'currency' => 'EUR',
    'shipping_country' => 'ME',
    'shipping_cents' => (int) env('STORE_SHIPPING_CENTS', 390),
    'free_shipping_threshold_cents' => (int) env('STORE_FREE_SHIPPING_THRESHOLD_CENTS', 7500),
];
