<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\Apis\ApiErrorModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\Superglobals;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Tracy\Debugger;
use Tracy\ILogger;

class ApiErrorModelTest extends TestCase
{
    /** @var SeablastConfiguration */
    private $configuration;
    /** @var string */
    private $path;
    /** @var string */
    private $token;
    /** @var ILogger */
    private $previousLogger;
    /** @var array<int, array{message: string, severity: string}> */
    private $entries = [];

    protected function setUp(): void
    {
        if (!defined('APP_DIR')) {
            define('APP_DIR', dirname(__DIR__));
            Debugger::enable(Debugger::DEVELOPMENT, APP_DIR . '/log');
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $this->token = (new CsrfTokenManager())->getToken('sb_json')->getValue();
        $this->path = sys_get_temp_dir() . '/seablast-error-test-' . bin2hex(random_bytes(8));
        $this->configuration = new SeablastConfiguration();
        $this->configuration->flag->activate(SeablastConstant::FLAG_CLIENT_ERROR_LOGGING);
        $this->configuration->setString(SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE, $this->path);
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_CLIENT_PER_MINUTE, 1000);
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_APP_PER_MINUTE, 1000);
        $this->previousLogger = Debugger::getLogger();
        $logger = $this->createMock(ILogger::class);
        $logger->method('log')->willReturnCallback(function ($message, $severity): void {
            $this->assertIsString($message);
            $this->assertIsString($severity);
            $this->entries[] = ['message' => $message, 'severity' => $severity];
        });
        Debugger::setLogger($logger);
    }

    protected function tearDown(): void
    {
        Debugger::setLogger($this->previousLogger);
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testNormalReportsAndEverySeverity(): void
    {
        foreach (['debug', 'info', 'warning', 'error', 'exception', 'critical'] as $severity) {
            $result = $this->report(['message' => 'Browser failed', 'page' => '/home', 'order' => 1,
                'severity' => $severity, 'ignored' => 'SECRET']);
            $this->assertSame(200, $result->httpCode);
            $this->assertInstanceOf(\stdClass::class, $result->rest);
            $this->assertSame('Error logged.', $result->rest->message);
            $entry = end($this->entries);
            $this->assertIsArray($entry);
            $expected = in_array($severity, ['exception', 'critical'], true) ? ILogger::ERROR : $severity;
            $this->assertSame($expected, $entry['severity']);
            $this->assertStringNotContainsString('SECRET', $entry['message']);
            $this->assertStringNotContainsString($this->token, $entry['message']);
            $record = json_decode(substr($entry['message'], strlen('client_error ')), true);
            $this->assertIsArray($record);
            $this->assertSame(strtoupper($severity), $record['severity']);
            $this->assertSame(1, $record['order']);
        }
        $this->report(['message' => 'Defaults']);
        $record = json_decode(substr($this->entries[6]['message'], strlen('client_error ')), true);
        $this->assertSame(['page' => 'unknown-page', 'severity' => 'ERROR', 'message' => 'Defaults'], $record);
    }

    public function testInvalidFieldsNeverLog(): void
    {
        foreach (
            [
            [], ['message' => ''], ['message' => null], ['message' => []], ['message' => 1],
            ['page' => []], ['page' => null], ['severity' => []], ['severity' => null],
            ['severity' => 'fatal'], ['order' => 0], ['order' => -1], ['order' => '1'],
            ['order' => 1.5], ['order' => 2147483648], ['order' => null], ['order' => []],
            ] as $fields
        ) {
            if ($fields !== [] && !array_key_exists('message', $fields)) {
                $fields['message'] = 'Report';
            }
            $label = json_encode($fields);
            $this->assertIsString($label);
            $this->assertSame(400, $this->report($fields)->httpCode, $label);
        }
        $this->assertSame([], $this->entries);
    }

    public function testAuthenticatedReportsStillCannotSelectCriticalSeverity(): void
    {
        $this->configuration->flag->activate(SeablastConstant::FLAG_USER_IS_AUTHENTICATED);
        $this->assertSame(200, $this->report(['message' => 'Report', 'severity' => 'CRITICAL'])->httpCode);
        $this->assertSame(ILogger::ERROR, $this->entries[0]['severity']);
    }

    public function testUnavailableStorageRejectsWithoutParsingOrLogging(): void
    {
        $this->configuration->setString(SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE, $this->path . '/missing');
        $this->assertSame(503, $this->raw('{')->httpCode);
        $this->assertSame([], $this->entries);
    }

    public function testEscapingAndExactFieldLimits(): void
    {
        $text = "first\r\n[CRITICAL] forged\t\0\x1B\"\\\u{2028}\u{2029}";
        $this->assertSame(200, $this->report(['message' => $text, 'page' => $text])->httpCode);
        $entry = $this->entries[0]['message'];
        $this->assertSame(0, preg_match('/[\x00-\x1F\x7F]/', $entry));
        $this->assertStringContainsString('\\u2028', $entry);
        $record = json_decode(substr($entry, 13), true);
        $this->assertIsArray($record);
        $this->assertSame($text, $record['message']);
        $this->assertSame(200, $this->report(['message' => str_repeat('a', 4096),
            'page' => str_repeat('p', 2048), 'order' => 2147483647])->httpCode);
        $count = count($this->entries);
        foreach (
            [['message' => str_repeat('a', 4097)],
            ['message' => 'x', 'page' => str_repeat('p', 2049)]] as $fields
        ) {
            $this->assertSame(413, $this->report($fields)->httpCode);
        }
        $this->assertCount($count, $this->entries);
    }

    public function testExactSerializedLimitAndUtf8Expansion(): void
    {
        // Account for the default record's metadata and fixed prefix before filling the message.
        $base = strlen('client_error ' . json_encode(['page' => 'unknown-page',
            'severity' => 'ERROR', 'message' => '']));
        $expanded = 8192 - $base;
        $text = str_repeat("\u{0080}", intdiv($expanded, 6)) . str_repeat('a', $expanded % 6);
        $this->assertSame(200, $this->report(['message' => $text])->httpCode);
        $this->assertSame(8192, strlen($this->entries[0]['message']));
        $this->assertSame(413, $this->report(['message' => $text . 'a'])->httpCode);
        $this->assertCount(1, $this->entries);
    }

    public function testBodyLimitsAndInvalidAuthenticationAreQuiet(): void
    {
        $json = json_encode(['csrfToken' => $this->token, 'message' => 'ok']);
        $this->assertIsString($json);
        $this->assertSame(200, $this->raw($json . str_repeat(' ', 16384 - strlen($json)))->httpCode);
        $this->entries = [];
        $this->assertSame(413, $this->raw(str_repeat(' ', 16385))->httpCode);
        foreach (['{', '[]', 'null', "{\"message\":\"\xFF\"}"] as $input) {
            $this->assertSame(400, $this->raw($input)->httpCode);
        }
        foreach (['{}', '{"csrfToken":[]}', '{"csrfToken":{}}', '{"csrfToken":"bad"}'] as $input) {
            $this->assertSame(401, $this->raw($input)->httpCode);
        }
        $this->assertSame([], $this->entries);
    }

    public function testEarlyRejectionsDoNotReadOrCreateState(): void
    {
        foreach (['GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $this->assertSame(405, $this->raw('{', ['REQUEST_METHOD' => $method])->httpCode);
        }
        $this->configuration->flag->deactivate(SeablastConstant::FLAG_CLIENT_ERROR_LOGGING);
        $this->assertSame(403, $this->raw('{')->httpCode);
        $this->assertFalse(file_exists($this->path));
        $this->assertSame([], $this->entries);
    }

    public function testInvalidPostsConsumeAllowanceAcrossSessionSnapshots(): void
    {
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_CLIENT_PER_MINUTE, 1);
        $this->assertSame(400, $this->raw('{')->httpCode);
        $previousSession = session_id();
        session_regenerate_id(true);
        $this->assertNotSame($previousSession, session_id());
        $this->assertSame(429, $this->report(['message' => 'ok'])->httpCode);
        $this->assertSame([], $this->entries);
    }

    public function testVerifiedProxyClientsAndSpoofedHeaders(): void
    {
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_CLIENT_PER_MINUTE, 1);
        $this->configuration->setArrayString(SeablastConstant::SB_TRUSTED_PROXIES, ['192.0.2.1']);
        $this->assertSame(400, $this->raw('{', ['HTTP_X_FORWARDED_FOR' => '198.51.100.1'])->httpCode);
        $this->assertSame(429, $this->raw('{', ['HTTP_X_FORWARDED_FOR' => '198.51.100.2'])->httpCode);
        $proxy = ['REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_PROTO' => 'https'];
        $this->assertSame(400, $this->raw('{', $proxy + ['HTTP_X_FORWARDED_FOR' => '198.51.100.1'])->httpCode);
        $this->assertSame(400, $this->raw('{', $proxy + ['HTTP_X_FORWARDED_FOR' => '198.51.100.2'])->httpCode);
        $this->assertSame(429, $this->raw('{', $proxy + ['HTTP_X_FORWARDED_FOR' => '198.51.100.1'])->httpCode);
        $this->assertSame([], $this->entries);
    }

    /** @param array<string, mixed> $fields */
    private function report(array $fields): \stdClass
    {
        $json = json_encode(['csrfToken' => $this->token] + $fields);
        $this->assertIsString($json);
        return $this->raw($json);
    }

    /** @param array<string, string> $server */
    private function raw(string $json, array $server = []): \stdClass
    {
        $this->configuration->setString(SeablastConstant::JSON_INPUT, $json);
        return (new ApiErrorModel($this->configuration, new Superglobals(
            [],
            [],
            $server + ['REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => '198.51.100.8']
        )))->knowledge();
    }
}
