<?php

$environment = env('APP_ENV', 'production');

return [
    'enabled' => (bool) env('PWA_ENABLED', false),
    'worker_enabled' => (bool) env('PWA_WORKER_ENABLED', false),
    'worker_retirement' => (bool) env('PWA_WORKER_RETIREMENT', false),
    'cache_version' => (string) env('PWA_CACHE_VERSION', 'v1'),
    'cache_namespace' => 'aura-pwa-'.($environment === 'production' ? 'live' : $environment).'-',
    'name' => (string) env('PWA_NAME', env('APP_ENV') === 'production' ? 'Aura' : 'Aura DEV'),
    'short_name' => (string) env('PWA_SHORT_NAME', env('APP_ENV') === 'production' ? 'Aura' : 'Aura DEV'),
    'description' => 'Oportunități AURA, pas cu pas.',
    'lang' => 'ro',
    'start_url' => '/dashboard',
    'scope' => '/',
    'theme_color' => '#100d25',
    'background_color' => '#100d25',
];
