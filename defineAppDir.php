<?php

// Keep this in a separate file in order to avoid declaring new symbols in index.php
define(
    'APP_DIR',
    file_exists(__DIR__ . '/../../autoload.php')
        // called as library
        ? __DIR__ . '/../../..'
        // direct usage (TODO: consider whether necessary at all)
        : __DIR__
);
