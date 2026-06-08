<?php

return [
    /*
    |--------------------------------------------------------------------------
    | S3 Configuration
    |--------------------------------------------------------------------------
    |
    | These settings configure S3 access for file uploads. The "url" is used
    | for browser-facing pre-signed URLs (e.g., LocalStack on localhost:4566).
    | The "endpoint" is used for server-to-server communication within the
    | Docker network (e.g., http://localstack:4566).
    |
    */

    'url' => env('AWS_URL', 'http://localhost:4566'),
    'endpoint' => env('AWS_ENDPOINT', 'http://localstack:4566'),
    'bucket' => env('S3_BUCKET', 'bcra-files'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
];
