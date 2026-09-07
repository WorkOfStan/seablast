<?php

declare(strict_types=1);

// Only the isolated PHP built-in test server may execute this fixture.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

// Simulate the origin's server metadata; forwarding headers still arrive over real HTTP.
$_SERVER['REMOTE_ADDR'] = isset($_GET['direct']) ? '198.51.100.8' : '127.0.0.1';
$_SERVER['SCRIPT_NAME'] = '/app/vendor/seablast/seablast/index.php';
$_SERVER['REQUEST_URI'] = '/app/probe';
$_SERVER['HTTPS'] = isset($_GET['tls']) ? 'on' : 'off';
$_SERVER['REQUEST_SCHEME'] = isset($_GET['tls']) ? 'https' : 'http';
$_SERVER['SERVER_PORT'] = isset($_GET['tls']) ? '443' : '80';
if (isset($_GET['active'])) {
    session_start();
    header('X-Initial-Session: ' . session_id());
}
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
if (!is_string($documentRoot)) {
    throw new RuntimeException('Missing HTTP fixture document root.');
}
require $documentRoot . '/vendor/seablast/seablast/index.php';
