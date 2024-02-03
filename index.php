<?php

// Load Composer contents for the app if this library is called from within the app
require_once __DIR__ . '/defineAppDir.php';
require_once APP_DIR . '/vendor/autoload.php';

use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastController;
use Seablast\Seablast\SeablastModel;
use Seablast\Seablast\SeablastSetup;
use Seablast\Seablast\SeablastView;
use Seablast\Seablast\Superglobals;
use Tracy\Debugger;

//Tracy is able to show Debug bar and Bluescreens for Ajax and redirected requests.
//You just have to start session before Tracy
Debugger::setSessionStorage(new Tracy\NativeSession());
$setup = new SeablastSetup(); // combine configuration files into a valid configuration
// $setup contains the info for Debugger setup
$developmentEnvironment = (
    in_array($_SERVER['REMOTE_ADDR'], ['::1', '127.0.0.1']) ||
    in_array($_SERVER['REMOTE_ADDR'], $setup->getConfiguration()->getArrayString(SeablastConstant::DEBUG_IP_LIST))
);
Debugger::enable($developmentEnvironment ? Debugger::DEVELOPMENT : Debugger::PRODUCTION, APP_DIR . '/log');

// Wrap _GET, _POST, _SESSION and _SERVER for sanitizing and testing
$superglobals = new Superglobals($_GET, $_POST, $_SERVER); // $_SESSION hasn't started, yet
$controller = new SeablastController($setup->getConfiguration(), $superglobals);
$superglobals->setSession($_SESSION); // as only now the session started
$model = new SeablastModel($controller, $superglobals);
$view = new SeablastView($model);
