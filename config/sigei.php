<?php

return [
    'display_timezone' => env('SIGEI_DISPLAY_TIMEZONE', 'America/Cancun'),
    'public_reports_per_session' => (int) env('SIGEI_REPORTS_PER_SESSION_MINUTE', 5),
    'public_reports_per_ip' => (int) env('SIGEI_REPORTS_PER_IP_MINUTE', 60),
    'push' => [
        'enabled' => (bool) env('SIGEI_PUSH_ENABLED', false),
        'subject' => env('SIGEI_VAPID_SUBJECT', ''),
        'ttl' => max(60, (int) env('SIGEI_PUSH_TTL', 3600)),
        'key_file' => storage_path('app/private/sigei-vapid.json'),
        'openssl_config' => env('SIGEI_OPENSSL_CONFIG'),
        'endpoint_hosts' => ['fcm.googleapis.com', 'updates.push.services.mozilla.com', '*.push.apple.com'],
    ],
];
