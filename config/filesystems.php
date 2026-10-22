<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'private'),

    // Upload workflows select these independently from Laravel's default disk.
    'public_upload_disk' => env('PUBLIC_UPLOAD_DISK', 'public'),
    'private_upload_disk' => env('PRIVATE_UPLOAD_DISK', 'private'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Optional Cloudflare R2 (or other S3-compatible provider) disks.
        // They are never selected unless their names are explicitly configured.
        'r2-public' => [
            'driver' => 's3',
            'key' => env('OBJECT_STORAGE_ACCESS_KEY_ID'),
            'secret' => env('OBJECT_STORAGE_SECRET_ACCESS_KEY'),
            'region' => env('OBJECT_STORAGE_REGION', 'auto'),
            'bucket' => env('OBJECT_STORAGE_PUBLIC_BUCKET'),
            'url' => env('OBJECT_STORAGE_PUBLIC_URL'),
            'endpoint' => env('OBJECT_STORAGE_ENDPOINT'),
            'use_path_style_endpoint' => env('OBJECT_STORAGE_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'public',
            'throw' => true,
            'report' => false,
        ],

        'r2-private' => [
            'driver' => 's3',
            'key' => env('OBJECT_STORAGE_ACCESS_KEY_ID'),
            'secret' => env('OBJECT_STORAGE_SECRET_ACCESS_KEY'),
            'region' => env('OBJECT_STORAGE_REGION', 'auto'),
            'bucket' => env('OBJECT_STORAGE_PRIVATE_BUCKET'),
            // Intentionally no URL: private objects are streamed by Laravel.
            'endpoint' => env('OBJECT_STORAGE_ENDPOINT'),
            'use_path_style_endpoint' => env('OBJECT_STORAGE_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],


        'b2-private' => [
            'driver' => 's3',
            'key' => env('B2_ACCESS_KEY_ID'),
            'secret' => env('B2_SECRET_ACCESS_KEY'),
            'region' => env('B2_REGION', 'us-east-005'),
            'bucket' => env('B2_PRIVATE_BUCKET'),
            'endpoint' => env('B2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
        ],


    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
