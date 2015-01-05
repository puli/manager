<?php

use Rhumsaa\Uuid\Uuid;

require_once __DIR__.'/vendor/autoload.php';

$uuid = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'php.net');

echo $uuid->toString()."\n";

$uuid = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'php.net');

echo $uuid->toString()."\n";
