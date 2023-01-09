<?php

return [
    'enable' => (strlen(getenv('SENTRY_DSN')) > 0),

    // JS用DSN
    'dsn_front' => getenv('SENTRY_DSN_FRONT'),

    'config' => [
        'dsn' => getenv('SENTRY_DSN'),

        'environment' => Mk::env(),
        //'traces_sample_rate' => 0.0,

        'default_integrations' => false,
        'integrations' => [
            new \Sentry\Integration\RequestIntegration(),
            new \Sentry\Integration\TransactionIntegration(),
            new \Sentry\Integration\FrameContextifierIntegration(),
            new \Sentry\Integration\EnvironmentIntegration(),
        ],
    ],
];
