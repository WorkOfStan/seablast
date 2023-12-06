<?php

/**
 * SeablastConfiguration structure accepts all values, however only the expected ones are processed.
 * The usage of constants defined in the SeablastConstant class is encouraged for the sake of hinting within IDE.
 */
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

return static function (SeablastConfiguration $SBConfig): void {
    $SBConfig->flag
        ->activate(SeablastConstant::FLAG_WEB_RUNNING) // debug
        ->activate('as') // debug
        ->deactivate('mon'); // debug
    $SBConfig
        // Debug
        ->setInt('a', 23)
        ->setInt('b', 45)
        ->setString('test-string', 'default-value') // debug
        ->setArrayString('test-array-string', ['a', 'y', 'omega'])
        // Environment
        ->setInt(SeablastConstant::SB_ERROR_REPORTING, E_ALL & ~E_NOTICE)
        ->setInt(SeablastConstant::SB_INI_SET_SESSION_COOKIE_LIFETIME, 60 * 60 * 24 * 2) // 2 days
        ->setInt(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_LIFETIME, 60 * 60 * 3) // 3 hours
        ->setString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH, '/')
        ->setInt(SeablastConstant::SB_SETLOCALE_CATEGORY, LC_CTYPE)
        ->setString(SeablastConstant::SB_SETLOCALE_LOCALES, 'cs_CZ.UTF-8')
        ->setString(SeablastConstant::SB_ENCODING, 'UTF-8')
        // Latte templates
        ->setString(SeablastConstant::LATTE_TEMPLATE, 'templates')
        ->setString(SeablastConstant::LATTE_CACHE, 'cache')
    ;
};
