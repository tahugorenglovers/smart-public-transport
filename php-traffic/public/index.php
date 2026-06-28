<?php

declare(strict_types=1);

// Load autoloader composer
require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\App;

// Bootstrap aplikasi - Load .env - Set header JSON & CORS
App::init();

// Load router
require_once __DIR__ . '/../routes/api.php';