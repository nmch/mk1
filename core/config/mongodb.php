<?php

return [
    'active' => 'default',
    'default' => [
        'connection' => [
            'host' => getenv('MONGO_ENDPOINT'),
            'user' => getenv('MONGO_USERNAME'),
            'password' => getenv('MONGO_PASSWORD'),
            'dbname' => getenv('MONGO_DATABASE'),
        ],
    ],
];
