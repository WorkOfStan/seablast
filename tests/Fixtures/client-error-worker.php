<?php

declare(strict_types=1);

use Seablast\Seablast\ClientErrorRateLimiter;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
    exit(1);
}
define('APP_DIR', dirname(__DIR__, 2));
require APP_DIR . '/vendor/autoload.php';
$configuration = new SeablastConfiguration();
$configuration->setString(SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE, $argv[1]);
$configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_CLIENT_PER_MINUTE, 2);
echo (new ClientErrorRateLimiter($configuration))->consume(null, 3600)['status'];
