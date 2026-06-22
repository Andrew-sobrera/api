<?php

return [
    'reservation_ttl_minutes' => (int) env('CHECKOUT_RESERVATION_TTL_MINUTES', 15),
    'cart_ttl_minutes' => (int) env('CHECKOUT_CART_TTL_MINUTES', 10),
    'seat_hold_ttl_minutes' => (int) env('CHECKOUT_SEAT_HOLD_TTL_MINUTES', 10),
];
