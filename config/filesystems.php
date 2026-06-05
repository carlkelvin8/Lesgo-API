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

    /*
    |--------------------------------------------------------------------------
    | Media uploads (menu images, profile photos, proof of delivery)
    |--------------------------------------------------------------------------
    */
    'media_disk' => env(
        'MEDIA_DISK',
        (env('AWS_ACCESS_KEY_ID') && env('AWS_SECRET_ACCESS_KEY')) ? 's3' : 'public'
    ),

    'default' => env(
        'FILESYSTEM_DISK',
        (env('AWS_ACCESS_KEY_ID') && env('AWS_SECRET_ACCESS_KEY')) ? 's3' : 'local'
    ),

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
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET', 'fls-a1f3a0d3-8dcf-4aa4-a31e-a5a91cc91c92'),
            'url' => env('AWS_URL', 'https://fls-a1f3a0d3-8dcf-4aa4-a31e-a5a91cc91c92.laravel.cloud'),
            'endpoint' => env(
                'AWS_ENDPOINT',
                'https://367be3a2035528943240074d0096e0cd.r2.cloudflarestorage.com'
            ),
            'use_path_style_endpoint' => filter_var(
                env('AWS_USE_PATH_STYLE_ENDPOINT', false),
                FILTER_VALIDATE_BOOL
            ),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
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
