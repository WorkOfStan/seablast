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
            __DIR__ . '/../conf/default.conf.php', // default Seablast configuration
            APP_DIR . '/vendor/seablast/auth/conf/app.conf.php', // Seablast/Auth extension configuration
            APP_DIR . '/conf/app.conf.php', // default application configuration
            APP_DIR . '/conf/app.conf.local.php', // configuration specific for the environment
        ];
        foreach ($fileConfigurationPriority as $confFilename) {
            $this->updateConfiguration($confFilename);
        }
    }

    /**
     * Getter.
     *
     * @return SeablastConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Process a configuration file, if it exists.
     *
     * @param string $configurationFilename
     * @return void
     */
    private function updateConfiguration(string $configurationFilename): void
    {
        if (file_exists($configurationFilename)) {
            $configurationClosure = require $configurationFilename;
            $configurationClosure($this->configuration);
        }
    }
}
