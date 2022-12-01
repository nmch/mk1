<?php

return [
    'default_headers' => [
        'X-FRAME-OPTIONS' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'no-referrer-when-downgrade',
    ],
];
