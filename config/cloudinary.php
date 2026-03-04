<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Configuration\Configuration;

Configuration::instance([
    'cloud' => [
        'cloud_name' => trim(getenv('CLOUDINARY_CLOUD_NAME')),
        'api_key' => trim(getenv('CLOUDINARY_API_KEY')),
        'api_secret' => trim(getenv('CLOUDINARY_API_SECRET')),
    ],
    'url' => [
        'secure' => true
    ]
]);