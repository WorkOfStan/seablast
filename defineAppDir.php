<?php

// Keep this in a separate file in order to avoid declaring new symbols in index.php
if (file_exists(__DIR__ . '/../../autoload.php')) {
    // called as library
    define('APP_DIR', __DIR__ . '/../../..');
} else {
    // direct usage (TODO: consider whether necessary at all)
    define('APP_DIR', __DIR__);
}
