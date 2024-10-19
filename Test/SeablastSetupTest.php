<?php

declare(strict_types=1);

namespace Seablast\Seablast\Test;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastSetup;
use Seablast\Seablast\SeablastConfiguration;

class SeablastSetupTest extends TestCase
{
    //private $appDirBackup;

    protected function setUp(): void
    {
        parent::setUp();
        //$this->appDirBackup = APP_DIR;

        // Mock APP_DIR constant
        if (!defined('APP_DIR')) {
            define('APP_DIR', __DIR__ . '/../..');
        }/* else {
            $this->appDirBackup = APP_DIR;
            define('APP_DIR', __DIR__ . '/../..');
        }*/
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Restore the original APP_DIR constant
        /*if (isset($this->appDirBackup)) {
            define('APP_DIR', $this->appDirBackup);
        }*/
    }

    public function testConfigurationIsInitialized(): void
    {
        $setup = new SeablastSetup();

        $this->assertInstanceOf(SeablastConfiguration::class, $setup->getConfiguration());
    }

    public function testConfigurationFilesAreProcessed(): void
    {
        //$defaultConfig = __DIR__ . '/../conf/default.conf.php';
        $appConfig = APP_DIR . '/conf/app.conf.php';
        $localConfig = APP_DIR . '/conf/app.conf.local.php';

        // Create temporary config files for testing
//        file_put_contents(
//            $defaultConfig,
//            "<?php return function (\$config) { \$config->setString('default', 'defaultValue'); };"
//        );
        file_put_contents($appConfig, "<?php return function (\$config) { \$config->setString('app', 'appValue'); };");
        file_put_contents(
            $localConfig,
            "<?php return function (\$config) { \$config->setString('local', 'localValue'); };"
        );

        $setup = new SeablastSetup();
        $config = $setup->getConfiguration();

        // SeablastConstant::SB_ENCODING, 'UTF-8'
        $this->assertEquals('UTF-8', $config->getString(SeablastConstant::SB_ENCODING)); // default value
        $this->assertEquals('appValue', $config->getString('app'));
        $this->assertEquals('localValue', $config->getString('local'));

        // Clean up temporary config files
        //unlink($defaultConfig); // this one actually exists!
        unlink($appConfig);
        unlink($localConfig);
    }

    public function testMissingConfigurationFilesAreHandledGracefully(): void
    {
        // Ensure the config files do not exist
        //@unlink(__DIR__ . '/../conf/default.conf.php'); // this one actually exists!
        @unlink(APP_DIR . '/conf/app.conf.php');
        @unlink(APP_DIR . '/conf/app.conf.local.php');

        $setup = new SeablastSetup();
        $config = $setup->getConfiguration();

        $this->assertInstanceOf(SeablastConfiguration::class, $config);
    }
}
