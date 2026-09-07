<?php

declare(strict_types=1);

use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastController;
use Seablast\Seablast\SeablastModel;
use Seablast\Seablast\SeablastRequestContext;
use Seablast\Seablast\Exceptions\InvalidRequestContextException;
use Seablast\Seablast\SeablastSetup;
use Seablast\Seablast\SeablastView;
use Seablast\Seablast\Superglobals;
use Tracy\Debugger;

// Load Composer contents for the app if this library is called from within the app
require_once __DIR__ . '/defineAppDir.php';
require_once APP_DIR . '/vendor/autoload.php';

//Tracy is able to show Debug bar and Bluescreens for Ajax and redirected requests.
//You just have to start session before Tracy
Debugger::setSessionStorage(new Tracy\NativeSession());
// Configuration failures must never expose a development error page.
Debugger::enable(Debugger::PRODUCTION);
$setup = new SeablastSetup(); // combine configuration files into a valid configuration
// $setup contains the info for Debugger setup
Debugger::$logDirectory = $setup->getConfiguration()->getString(SeablastConstant::SB_LOG_DIRECTORY);

// Wrap _GET, _POST, _SESSION and _SERVER for sanitizing and testing
$superglobals = new Superglobals($_GET, $_POST, $_SERVER); // $_SESSION hasn't started, yet
try {
    $requestContext = new SeablastRequestContext($setup->getConfiguration(), $superglobals->server);
} catch (InvalidRequestContextException $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bad Request';
    exit;
}
// development environment
Debugger::enable(
    $requestContext->isDebugAllowed() ? Debugger::DEVELOPMENT : Debugger::PRODUCTION,
    $setup->getConfiguration()->getString(SeablastConstant::SB_LOG_DIRECTORY)
);
$controller = new SeablastController($setup->getConfiguration(), $superglobals, $requestContext);
$superglobals->setSession($_SESSION); // as only now the session started
try {
    new SeablastView(new SeablastModel($controller, $superglobals));
} catch (\PDOException $e) {
    // make sure that the database Tracy BarPanel with error is displayed when PDOException is thrown
    $setup->getConfiguration()->pdo()->indicateDatabaseError();
    $setup->getConfiguration()->showSqlBarPanel();
    throw $e;
} catch (\Throwable $e) {
    // catches TypeError, Error, Exception // TODO remove try/catch in SeablastView and around $model if obsoleted
    // make sure that the database Tracy BarPanel is displayed
    $setup->getConfiguration()->showSqlBarPanel();
    throw $e;
}
