<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Latte\Engine;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Seablast\Seablast\Admin\AdminColor;
use Seablast\Seablast\Admin\AdminHelper;
use Seablast\Seablast\Admin\ApiTableUpdateModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastMysqli;
use Seablast\Seablast\Superglobals;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Tracy\Debugger;
use Webmozart\Assert\Assert;

class AdminColorSecurityTest extends TestCase
{
    /** @return array<mixed> */
    private function invalidColors(): array
    {
        return [
            '', null, false, 123, [], (object) [], 'red', '#abc', '#12345678',
            ' #abcdef', '#abcdef ', "#abcdef\n", "#abc\0ef", '#gggggg',
            '#ffffff;position:fixed', '\" onmouseover=\"alert(1)',
            '</td><script>alert(1)</script>',
        ];
    }

    public function testApiRejectsInvalidColorsBeforeDatabaseAccess(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $configuration = $this->getMockBuilder(SeablastConfiguration::class)
            ->onlyMethods(['mysqli'])->getMock();
        $configuration->expects($this->never())->method('mysqli');
        $configuration->setInt(SeablastConstant::USER_ROLE_ID, 2);
        $configuration->setInt(SeablastConstant::SB_LOGGING_LEVEL, 0);
        $configuration->setArrayString(SeablastConstant::ADMIN_COLOR_FIELDS, ['color']);
        $token = (new CsrfTokenManager())->getToken('sb_json')->getValue();
        foreach ($this->invalidColors() as $value) {
            $json = json_encode(['csrfToken' => $token, 'val' => $value]);
            $this->assertIsString($json);
            $configuration->setString(SeablastConstant::JSON_INPUT, $json);
            $model = new ApiTableUpdateModel($configuration, new Superglobals(
                ['key' => 'color', 'id' => '1'],
                [],
                ['REQUEST_METHOD' => 'POST']
            ));
            $helper = $this->getMockBuilder(AdminHelper::class)->disableOriginalConstructor()->getMock();
            $helper->method('getAllowedColumns')->willReturn(['edit' => ['color']]);
            $property = new ReflectionProperty(ApiTableUpdateModel::class, 'adminHelper');
            $property->setAccessible(true);
            $property->setValue($model, $helper);
            $this->assertSame(400, $model->knowledge()->httpCode);
        }
    }

    public function testApiWritesNormalizedColorsAndPreservesTextTrimming(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $values = ['#000000' => '#000000', '#ffffff' => '#ffffff', '#AbCdEf' => '#abcdef', ' text ' => 'text'];
        foreach ($values as $raw => $expected) {
            $isColor = $raw !== ' text ';
            $configuration = $this->getMockBuilder(SeablastConfiguration::class)
                ->onlyMethods(['dbmsTablePrefix', 'mysqli'])->getMock();
            $database = $this->getMockBuilder(SeablastMysqli::class)
                ->disableOriginalConstructor()->onlyMethods(['query', 'real_escape_string'])->getMock();
            $database->method('real_escape_string')->willReturnArgument(0);
            // A failed query avoids reading native mysqli state on this disconnected test double.
            $database->expects($this->once())->method('query')
                ->with("UPDATE `items` SET `color` = '" . $expected . "' WHERE `items`.`id` = 1")
                ->willReturn(false);
            $configuration->method('mysqli')->willReturn($database);
            $configuration->method('dbmsTablePrefix')->willReturn('');
            $configuration->setString(SeablastConstant::APP_SELECTED_TABLE, 'items');
            $configuration->setInt(SeablastConstant::USER_ROLE_ID, 2);
            $configuration->setInt(SeablastConstant::SB_LOGGING_LEVEL, 0);
            $configuration->setArrayString(SeablastConstant::ADMIN_COLOR_FIELDS, $isColor ? ['color'] : []);
            $json = json_encode([
                'csrfToken' => (new CsrfTokenManager())->getToken('sb_json')->getValue(), 'val' => $raw,
            ]);
            $this->assertIsString($json);
            $configuration->setString(SeablastConstant::JSON_INPUT, $json);
            $model = new ApiTableUpdateModel($configuration, new Superglobals(
                ['key' => 'color', 'id' => '1'],
                [],
                ['REQUEST_METHOD' => 'POST']
            ));
            $helper = $this->getMockBuilder(AdminHelper::class)->disableOriginalConstructor()->getMock();
            $helper->method('getAllowedColumns')->willReturn(['edit' => ['color']]);
            $helper->method('columnTypes')->willReturn(['color' => 'varchar']);
            $property = new ReflectionProperty(ApiTableUpdateModel::class, 'adminHelper');
            $property->setAccessible(true);
            $property->setValue($model, $helper);
            $this->assertSame(500, $model->knowledge()->httpCode);
        }
    }

    public function testDatabaseUpdatesOnlyValidColors(): void
    {
        if (!defined('APP_DIR')) {
            define('APP_DIR', dirname(__DIR__));
            Debugger::enable(Debugger::DEVELOPMENT, APP_DIR . '/log');
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $configuration = new SeablastConfiguration();
        $defaults = require __DIR__ . '/../conf/default.conf.php';
        Assert::isCallable($defaults);
        $defaults($configuration);
        $configuration->setString(SeablastConstant::SB_PHINX_ENVIRONMENT, 'testing');
        $configuration->setInt(SeablastConstant::USER_ROLE_ID, 2);
        $configuration->setArrayString(SeablastConstant::ADMIN_COLOR_FIELDS, ['color']);
        $table = 'sec001_' . bin2hex(random_bytes(4));
        $configuration->setString(SeablastConstant::APP_SELECTED_TABLE, $table);
        $database = $configuration->mysqli();
        $physicalTable = $configuration->dbmsTablePrefix() . $table;
        $database->queryStrict('CREATE TEMPORARY TABLE `' . $physicalTable . '` (id INT, color VARCHAR(255))');
        try {
            $database->queryStrict("INSERT INTO `" . $physicalTable . "` VALUES (1, '#000000')");
            $token = (new CsrfTokenManager())->getToken('sb_json')->getValue();
            $expected = '#000000';
            foreach (array_merge(['#ffffff', '#AbCdEf'], $this->invalidColors()) as $value) {
                $json = json_encode(['csrfToken' => $token, 'val' => $value]);
                Assert::string($json);
                $configuration->setString(SeablastConstant::JSON_INPUT, $json);
                $model = new ApiTableUpdateModel($configuration, new Superglobals(
                    ['key' => 'color', 'id' => '1'],
                    [],
                    ['REQUEST_METHOD' => 'POST']
                ));
                // Temporary table metadata is not exposed through information_schema.COLUMNS.
                $helper = $this->getMockBuilder(AdminHelper::class)->disableOriginalConstructor()->getMock();
                $helper->method('getAllowedColumns')->willReturn(['edit' => ['color']]);
                $helper->method('columnTypes')->willReturn(['color' => 'varchar']);
                $property = new ReflectionProperty(ApiTableUpdateModel::class, 'adminHelper');
                $property->setAccessible(true);
                $property->setValue($model, $helper);
                $normalized = AdminColor::normalize($value);
                $this->assertSame($normalized === null ? 400 : 200, $model->knowledge()->httpCode);
                $expected = $normalized ?? $expected;
                $result = $database->queryStrict('SELECT color FROM `' . $physicalTable . '` WHERE id = 1');
                Assert::isInstanceOf($result, \mysqli_result::class);
                $this->assertSame([$expected], $result->fetch_row());
                $result->free();
            }
        } finally {
            $database->queryStrict('DROP TEMPORARY TABLE `' . $physicalTable . '`');
        }
    }

    public function testRenderingRejectsStoredMarkupAndKeepsValidColors(): void
    {
        $configuration = new SeablastConfiguration();
        $configuration->setString(SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL, 'https://example.test');
        $configuration->setInt(SeablastConstant::SB_WEB_FORCE_ASSET_VERSION, 1);
        $configuration->setString(SeablastConstant::APP_SELECTED_TABLE, 'items');
        $configuration->setArrayString(SeablastConstant::ADMIN_COLOR_FIELDS, ['color']);
        $payload = '\" onmouseover=\"alert(1)\"><script id="injected">alert(1)</script>';
        $column = '</script><script id="column-injected">alert(2)</script>';
        $html = (new Engine())->renderToString(__DIR__ . '/../views/admin.latte', [
            'configuration' => $configuration, 'csrfToken' => 'test', 'menu' => [],
            'content' => ['items'], 'columns' => ['id', 'color', $column], 'editable' => ['color'],
            'conditionDetails' => [], 'table' => [
                ['id' => 1, 'color' => $payload], ['id' => 2, 'color' => '#AbCdEf'],
            ],
        ]);
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            $dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            $injected = $xpath->query('//*[@onmouseover or @id="injected" or @id="column-injected"]');
            Assert::isInstanceOf($injected, DOMNodeList::class);
            $this->assertSame(0, $injected->length);
            $cells = $xpath->query('//td[contains(@class, "color-edit")]');
            Assert::isInstanceOf($cells, DOMNodeList::class);
            $this->assertSame(2, $cells->length);
            $invalidCell = $cells->item(0);
            $validCell = $cells->item(1);
            Assert::isInstanceOf($invalidCell, DOMElement::class);
            Assert::isInstanceOf($validCell, DOMElement::class);
            $this->assertSame($payload, $invalidCell->textContent);
            $this->assertFalse($invalidCell->hasAttribute('style'));
            $this->assertSame('background-color:#abcdef;', $validCell->getAttribute('style'));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function testStrictColorPolicy(): void
    {
        foreach ($this->invalidColors() as $value) {
            $this->assertNull(AdminColor::normalize($value));
        }
        foreach (['#000000', '#ffffff', '#AbCdEf'] as $value) {
            $this->assertSame(strtolower($value), AdminColor::normalize($value));
        }
    }
}
