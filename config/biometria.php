<?php

return [
    'url' => env('BIOMETRIA_URL', 'http://127.0.0.1:8765'),
    'terminal' => env('BIOMETRIA_TERMINAL', ''),
    'connect_timeout' => env('BIOMETRIA_CONNECT_TIMEOUT', 2),
    'timeout' => env('BIOMETRIA_TIMEOUT', 45),
    'enroll_timeout' => env('BIOMETRIA_ENROLL_TIMEOUT', 200),
];
