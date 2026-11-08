<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['*'],
    // Only a page on ANOTHER site needs this: the app's own pages call it from the same site, and the mobile app uses a bearer
    // token, so neither goes through CORS. The app's own address (APP_URL) plus any separate front end in CORS_ALLOWED_ORIGINS
    // (comma separated). It used to name localhost:8000 and 127.0.0.1:8000, in production too, and never the real address.
    'allowed_origins' => \App\Support\CorsOrigins::for(env('APP_URL', 'http://localhost'), env('CORS_ALLOWED_ORIGINS')),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
