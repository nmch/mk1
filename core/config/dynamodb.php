<?php

return [
    'active' => 'default',
    'default' => [
        'connection' => [
            'endpoint' => getenv('DYNAMODB_ENDPOINT'),

            'credentials' => [
                'access_key_id' => getenv('DYNAMODB_ACCESS_KEY_ID'),
                'secret_access_key' => getenv('DYNAMODB_SECRET_ACCESS_KEY'),
            ],
        ],
    ],
];
