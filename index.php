<?php

// The Composer auto-loader (official way to load Composer contents) to load external stuff automatically
require_once __DIR__ . '/defineAppDir.php';
require_once APP_DIR . '/vendor/autoload.php';

use Seablast\Seablast\SeablastController;
use Seablast\Seablast\SeablastModel;
use Seablast\Seablast\SeablastSetup;
use Seablast\Seablast\SeablastView;
use Seablast\Seablast\Superglobals;
use Tracy\Debugger;

//Tracy is able to show Debug bar and Bluescreens for AJAX and redirected requests.
//You just have to start session before Tracy
session_start() || error_log('session_start failed');
$setup = new SeablastSetup(); // combine configuration files into a valid configuration
// $setup contains somehow info for Debugger setup
//$developmentEnvironment = (
//    in_array($_SERVER['REMOTE_ADDR'], ['::1', '127.0.0.1']) || in_array($_SERVER['REMOTE_ADDR'], $debug-IP-Array)
//);
//
//force debug mode //TODO parametrizovat
Debugger::enable(false, APP_DIR . '/log'); // TODO zkontrolovat, že jde o log aplikace!
//Debugger::enable($developmentEnvironment ? Debugger::DEVELOPMENT : Debugger::PRODUCTION, __DIR__ . '/log');
//Debugger::$email = email of admin;

$superglobals = new Superglobals($_GET, $_POST, $_SERVER, $_SESSION);
$controller = new SeablastController($setup->getConfiguration(), $superglobals);
$model = new SeablastModel($controller);
$view = new SeablastView($model);
