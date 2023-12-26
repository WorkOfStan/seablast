<?php

declare(strict_types=1);

namespace Seablast\Seablast;

class SeablastSetup
{
    use \Nette\SmartObject;

    /** @var SeablastConfiguration */
    private $configuration;

    public function __construct()
    {
        // Create configuration of the app by applying configuration files in order from generic to specific
        $this->configuration = new SeablastConfiguration();
        $fileConfigurationPriority = [
            __DIR__ . '/../conf/default.conf.php',
            APP_DIR . '/conf/app.conf.php',
            APP_DIR . '/conf/app.conf.local.php',
        ];
        foreach ($fileConfigurationPriority as $confFilename) {
            $this->updateConfiguration($confFilename);
        }
    }

    /**
     *
     * @return SeablastConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Process a configuration file
     * @param string $configurationFilename
     * @return void
     */
    private function updateConfiguration(string $configurationFilename): void
    {
        //Debugger::log('Trying config file: ' . $configurationFilename, ILogger::DEBUG);
        if (!file_exists($configurationFilename)) {
            // TODO make sure that with production settings, no INFO is written
            //Debugger::log('Not existing config file: ' . $configurationFilename, ILogger::INFO);
            return;
        }
        $configurationClosure = require $configurationFilename;
        $configurationClosure($this->configuration);
    }
}
