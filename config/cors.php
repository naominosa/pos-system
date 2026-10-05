<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
    'http://localhost:5173',
    'https://pos-frontend-nine-delta.vercel.app',
    'https://pos-frontend-git-main-naomis-projects-10628417.vercel.app',
    'https://pos-frontend-mfbmv04e7-naomis-projects-10628417.vercel.app',
],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];