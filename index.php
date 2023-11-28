<?php

// The Composer auto-loader (official way to load Composer contents) to load external stuff automatically
require_once __DIR__ . '/vendor/autoload.php';

use Seablast\Seablast\SeablastController;
use Seablast\Seablast\SeablastModel;
use Seablast\Seablast\SeablastView;
use Tracy\Debugger;

//Tracy is able to show Debug bar and Bluescreens for AJAX and redirected requests.
//You just have to start session before Tracy
session_start() || error_log('session_start failed');
//$developmentEnvironment = (
//    in_array($_SERVER['REMOTE_ADDR'], ['::1', '127.0.0.1']) || in_array($_SERVER['REMOTE_ADDR'], $debug-IP-Array)
//);
//
//force debug mode //TODO parametrizovat
Debugger::enable(false, __DIR__ . '/log'); // TODO ale log aplikace!
//Debugger::enable($developmentEnvironment ? Debugger::DEVELOPMENT : Debugger::PRODUCTION, __DIR__ . '/log');
//Debugger::$email = email of admin;

$controller = new SeablastController();
$model = new SeablastModel($controller);
$view = new SeablastView($model);
