<?php

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
// TODO Description: use AppConstants ... A ty budou nastaveny kde? ... Slouží jako nápověda - každá hodnota je akceptovaná

return static function (SeablastConfiguration $SBConfig): void {
    $SBConfig->flag
        //        ->activate(SeablastConstant::WEB_RUNNING)
        ->activate('as')
        ->deactivate('mon');
    $SBConfig
        ->setInt('a', 23)
        ->setInt('b', 45)
        ->setInt(SeablastConstant::SB_ERROR_REPORTING, E_ALL & ~E_NOTICE)
        ->setString('test-string', 'default-value') // debug
        ->setArrayString('test-array-string', ['a', 'y', 'omega'])
        ;
};
