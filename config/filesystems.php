<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => 'public', // غيرناها من local إلى public لضمان استخدام إعدادات القرص المعدل

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
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
            // هنا كتبنا الرابط المباشر الذي تريده يظهر في السيستم بدلاً من الاعتماد على متغيرات البيئة
            'url' => 'https://eyana2026.khadamaat.org/storage/app/public',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => '',
            'secret' => '',
            'region' => 'us-east-1',
            'bucket' => '',
            'url' => '',
            'endpoint' => '',
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];