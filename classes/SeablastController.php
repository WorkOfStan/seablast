<?php

namespace Seablast\Seablast;

//use Webmozart\Assert\Assert;

class SeablastController
{
    use \Nette\SmartObject;

    /** @var SeablastConfiguration */
    private $configuration;

    public function __construct()
    {
        // Create configuration of the app by applying configuration files in order from generic to specific
        $this->configuration = new SeablastConfiguration();
        foreach ([
            __DIR__ . '/../conf/default.conf.php',
            __DIR__ . '/../../../conf/app.conf.php',
            __DIR__ . '/../../../conf/app.conf.local.php',
        ] as $confFilename) {
            $this->updateConfiguration($confFilename);
        }
        $this->applyConfiguration();
        //$this->devenv = xyz;
        $this->route();
    }

    /**
     * Apply the current configuration to the Seablast environment
     * The settings not used here can still be used in Models
     */
    private function applyConfiguration(): void
    {
        // identify UNDER CONSTRUCTION
        if (!$this->configuration->flag->status(SeablastConstant::WEB_RUNNING)
            // && not in_array($_SERVER['REMOTE_ADDR'], $debug-IP-array) .. ale ne SERVER napřímo
        ) {
            //TODO include from app, pokud tam je
            include __DIR__ . '/../under-construction.html';
            exit;
        }
        //$arrayOfSettings = [];
        //foreach ($arrayOfSettings as $setting => $value) {
        //    case
        //}
        
    }

    private function makeSureUrlIsParametric()
    {
        /*
    // Redirector -> friendly url / parametric url
    if !flag redirector_off
        If Select  * where url
            mSUIP //rekurze

    // Friendly url -> parametric url
    If !flag frienflyURL_off
        If Select * where url
        mSUIP
    return parametric;
    */
    }


    private function route(): void
    {
        $this->makeSureUrlIsParametric();
        //F(request type = verb/accepted type, url, url params, auth, language) --> model & params & view type (html, json)
    }

    /**
     * process a configuration file
     */
    private function updateConfiguration(string $configurationFilename): void
    {
        if (!file_exists($configurationFilename)) {
            return;
        }
        $configurationClosure = require $configurationFilename;
        $configurationClosure($this->configuration);
    }
}
