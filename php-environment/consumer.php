<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Services/RabbitMQConsumer.php';

$consumer = new RabbitMQConsumer();
$consumer->listen();