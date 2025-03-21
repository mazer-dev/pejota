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

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'tasks' => [
            'driver' => 'local',
            'root' => storage_path('app/tasks'),
            'url' => env('APP_URL').'/attachments/tasks',
            'visibility' => 'private',
            'throw' => true,
        ],

        'projects' => [
            'driver' => 'local',
            'root' => storage_path('app/projects'),
            'url' => env('APP_URL').'/attachments/projects',
            'visibility' => 'private',
            'throw' => true,
        ],

        'work_sessions' => [
            'driver' => 'local',
            'root' => storage_path('app/work_sessions'),
            'url' => env('APP_URL').'/attachments/work_sessions',
            'visibility' => 'private',
            'throw' => true,
        ],

        'companies' => [
            'driver' => 'local',
            'root' => storage_path('app/companies'),
            'url' => env('APP_URL').'/attachments/companies',
            'visibility' => 'private',
            'throw' => true,
        ],

        'companies-logo' => [
            'driver' => 'local',
            'root' => storage_path('app/companies/logo'),
            'url' => env('APP_URL').'/attachments/companies-logo',
            'visibility' => 'private',
            'throw' => true,
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
