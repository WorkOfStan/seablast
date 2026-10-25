<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use PHPUnit\Framework\TestCase;

/** Real bootstrap and response-header tests, run on each supported PHP version in CI. */
class RequestContextHttpTest extends TestCase
{
    /** @var string */
    private static $address;
    /** @var string */
    private static $app;
    /** @var resource|null */
    private static $process;

    /**
     * Accept the combined process-status return types across supported PHP versions.
     *
     * @param array{running: bool}|false $status
     */
    private static function isRunningStatus($status): bool
    {
        return $status !== false && $status['running'];
    }

    /**
     * @param string[] $query
     * @param string[]|null $headers
     * @return array{status: int, headers: string[], body: string}
     */
    private function request(
        array $query,
        ?array $headers = null,
        string $method = 'GET',
        string $body = '',
        string $host = 'app.example.com'
    ): array
    {
        $headers = $headers ?? ['X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.8'];
        $headers[] = 'Host: ' . $host;
        $headers[] = 'Connection: close';
        $context = stream_context_create(['http' => [
            'method' => $method,
            'content' => $body,
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 10,
            'header' => implode("\r\n", $headers),
        ]]);
        $stream = fopen('http://' . self::$address . '/?' . http_build_query($query), 'r', false, $context);
        $this->assertIsResource($stream);
        $metadata = stream_get_meta_data($stream);
        $body = stream_get_contents($stream);
        fclose($stream);
        $this->assertIsString($body);
        $responseHeaders = $metadata['wrapper_data'];
        $this->assertIsArray($responseHeaders);
        $this->assertNotEmpty($responseHeaders);
        $headers = [];
        foreach ($responseHeaders as $header) {
            $this->assertIsString($header);
            $headers[] = $header;
        }
        $parts = explode(' ', $headers[0]);
        return ['status' => (int) $parts[1], 'headers' => $headers, 'body' => $body];
    }

    /**
     * @param string[] $headers
     * @return string[]
     */
    private function sessionCookies(array $headers): array
    {
        return array_values(array_filter(array_map('strtolower', $headers), static function (string $header): bool {
            return strpos($header, 'set-cookie: phpsessid=') === 0;
        }));
    }

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__);
        self::$app = sys_get_temp_dir() . '/seablast-request-context-' . bin2hex(random_bytes(6));
        foreach (['/vendor/seablast/seablast', '/cache', '/conf', '/log', '/sessions'] as $directory) {
            mkdir(self::$app . $directory, 0777, true);
        }
        foreach (['index.php', 'defineAppDir.php'] as $file) {
            copy($root . '/' . $file, self::$app . '/vendor/seablast/seablast/' . $file);
        }
        copy(__DIR__ . '/Fixtures/request-context-config.php', self::$app . '/conf/app.conf.php');
        file_put_contents(self::$app . '/under-construction.html', 'maintenance fixture');
        file_put_contents(self::$app . '/vendor/autoload.php', '<?php require '
            . var_export($root . '/vendor/autoload.php', true) . '; require_once '
            . var_export(__DIR__ . '/RequestContextHttpModel.php', true) . ';');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if (!is_resource($socket)) {
            throw new \RuntimeException('Cannot reserve a port for the HTTP fixture server.');
        }
        $address = stream_socket_get_name($socket, false);
        if (!is_string($address)) {
            fclose($socket);
            throw new \RuntimeException('Cannot determine the HTTP fixture server address.');
        }
        self::$address = $address;
        fclose($socket);
        $command = escapeshellarg(PHP_BINARY)
            . ' -d xdebug.mode=off -d session.cookie_domain=inherited.invalid'
            . ' -d session.save_path=' . escapeshellarg(self::$app . '/sessions')
            . ' -d sys_temp_dir=' . escapeshellarg(self::$app . '/sessions')
            . ' -d upload_tmp_dir=' . escapeshellarg(self::$app . '/sessions')
            . ' -S ' . self::$address . ' -t ' . escapeshellarg(self::$app)
            . ' ' . escapeshellarg(__DIR__ . '/Fixtures/request-context-router.php');
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['file', self::$app . '/server.log', 'a'],
            2 => ['file', self::$app . '/server.log', 'a'],
        ], $pipes, $root, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new \RuntimeException('Cannot start the HTTP fixture server.');
        }
        self::$process = $process;
        fclose($pipes[0]);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (!self::isRunningStatus(proc_get_status($process))) {
                throw new \RuntimeException('HTTP fixture server stopped during startup.');
            }
            // Older PHP versions buffer the startup banner when stdout is redirected to a file.
            $connection = stream_socket_client(
                'tcp://' . self::$address,
                $errorCode,
                $errorMessage,
                0,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
            );
            if (!is_resource($connection)) {
                throw new \RuntimeException('Cannot create the HTTP fixture readiness connection.');
            }
            $read = [];
            $write = [$connection];
            $except = [$connection];
            $selected = stream_select($read, $write, $except, 0, 50000);
            // stream_select replaces these arrays with the streams that are ready.
            /** @var resource[] $write */
            /** @var resource[] $except */
            $ready = $selected > 0 && $write !== [] && $except === []
                && stream_socket_get_name($connection, true) !== false;
            fclose($connection);
            if ($ready) {
                return;
            }
            usleep(50000);
        }
        throw new \RuntimeException(
            'HTTP fixture server did not start. ' . file_get_contents(self::$app . '/server.log')
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            for ($attempt = 0; $attempt < 100; $attempt++) {
                if (!self::isRunningStatus(proc_get_status(self::$process))) {
                    break;
                }
                usleep(50000);
            }
            proc_close(self::$process);
        }
        // Clean only this fixture's known files; never traverse unrelated test directories.
        foreach (['/sessions/*', '/log/*', '/cache/*'] as $pattern) {
            foreach (glob(self::$app . $pattern) ?: [] as $file) {
                unlink($file);
            }
        }
        $cases = [
            '/vendor/seablast/seablast/index.php', '/vendor/seablast/seablast/defineAppDir.php',
            '/vendor/autoload.php', '/conf/app.conf.php', '/under-construction.html', '/server.log',
        ];
        foreach ($cases as $file) {
            unlink(self::$app . $file);
        }
        $directories = [
            '/vendor/seablast/seablast', '/vendor/seablast', '/vendor', '/cache', '/conf', '/log', '/sessions', '',
        ];
        foreach ($directories as $dir) {
            rmdir(self::$app . $dir);
        }
    }

    public function testAllowedHostsProduceValidatedRootUrls(): void
    {
        $hosts = ['APP.EXAMPLE.COM', 'localhost', '192.168.1.10', 'localhost:8080', '[0:0:0:0:0:0:0:1]:8080'];
        foreach ($hosts as $host) {
            $response = $this->request([], [
                'X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.8',
                'X-Forwarded-Host: evil.example', 'Forwarded: host=evil.example', 'X-Forwarded-Port: 666',
            ], 'GET', '', $host);
            $this->assertSame(200, $response['status']);
            $body = json_decode($response['body'], true);
            $this->assertIsArray($body);
            $authority = $host === '[0:0:0:0:0:0:0:1]:8080' ? '[::1]:8080' : strtolower($host);
            $this->assertSame('https://' . $authority . '/app', $body['root']);
            foreach ($this->sessionCookies($response['headers']) as $cookie) {
                $this->assertStringNotContainsString('domain=', $cookie);
            }
        }
    }

    public function testAlreadyActiveSessionIsNotRestarted(): void
    {
        $response = $this->request(['active' => '1']);
        $this->assertSame(200, $response['status'], $response['body']);
        $body = json_decode($response['body'], true);
        $this->assertIsArray($body);
        $this->assertIsString($body['sessionId']);
        $this->assertContains('X-Initial-Session: ' . $body['sessionId'], $response['headers']);
        $this->assertCount(1, $this->sessionCookies($response['headers']));
    }

    public function testApplicationMaintenanceUsesResponseNonceForInlineStyles(): void
    {
        $file = self::$app . '/under-construction.html';
        $original = file_get_contents($file);
        $this->assertIsString($original);
        file_put_contents($file, 'maintenance fixture'
            . '<style nonce="__SEABLAST_CSP_NONCE__">body { color: black; }</style>');
        try {
            foreach (['report-only', 'enforce'] as $mode) {
                $response = $this->request(['maintenance' => '1', 'cspMode' => $mode]);
                $this->assertSame(200, $response['status']);
                $this->assertStringContainsString('maintenance fixture<style nonce="', $response['body']);
                $this->assertSame(
                    1,
                    preg_match('/<style nonce="([A-Za-z0-9+\\/=]+)"/', $response['body'], $matches)
                );
                $this->assertStringContainsString(
                    "'nonce-" . ($matches[1] ?? '') . "'",
                    implode("\n", $response['headers'])
                );
                $this->assertStringNotContainsString('__SEABLAST_CSP_NONCE__', $response['body']);
            }
        } finally {
            file_put_contents($file, $original);
        }
    }

    public function testBundledMaintenanceUsesResponseNonceForInlineStyles(): void
    {
        $file = self::$app . '/under-construction.html';
        rename($file, $file . '.saved');
        try {
            $previousNonce = '';
            foreach (['report-only', 'enforce'] as $mode) {
                $response = $this->request(['maintenance' => '1', 'cspMode' => $mode]);
                $this->assertSame(200, $response['status']);
                $this->assertContains('Cache-Control: no-store', $response['headers']);
                $this->assertSame(
                    1,
                    preg_match('/<style nonce="([A-Za-z0-9+\/=]+)"/', $response['body'], $matches)
                );
                $nonce = $matches[1] ?? '';
                $this->assertNotSame('', $nonce);
                $this->assertNotSame($previousNonce, $nonce);
                $this->assertStringContainsString("'nonce-" . $nonce . "'", implode("\n", $response['headers']));
                $this->assertStringNotContainsString('__SEABLAST_CSP_NONCE__', $response['body']);
                $this->assertStringNotContainsString('under-construction.css', $response['body']);
                $previousNonce = $nonce;
            }
        } finally {
            rename($file . '.saved', $file);
        }
    }

    public function testClientErrorHttpResponsesAndSessionBoundToken(): void
    {
        $tokenResponse = $this->request(['errorToken' => '1']);
        $tokenData = json_decode($tokenResponse['body'], true);
        $this->assertIsArray($tokenData);
        $this->assertIsString($tokenData['sessionId']);
        $headers = ['X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.8',
            'Content-Type: application/json', 'Cookie: PHPSESSID=' . $tokenData['sessionId']];
        $payload = json_encode(['csrfToken' => $tokenData['csrfToken'], 'message' => 'HTTP browser error']);
        $this->assertIsString($payload);
        $success = $this->request(['errorApi' => '1'], $headers, 'POST', $payload);
        $this->assertSame(200, $success['status'], $success['body']);
        $successData = json_decode($success['body'], true);
        $this->assertIsArray($successData);
        $this->assertSame('Error logged.', $successData['message']);
        $this->assertContains('Content-Type: application/json; charset=utf-8', $success['headers']);
        $limited = $this->request(['errorApi' => '1', 'errorLimited' => '1'], $headers, 'POST', $payload);
        $this->assertSame(429, $limited['status']);
        $this->assertSame(1, preg_match('/Retry-After: [1-9][0-9]*/', implode("\n", $limited['headers'])));
        $disabled = $this->request(['errorApi' => '1', 'errorDisabled' => '1'], $headers, 'POST', '{');
        $this->assertSame(403, $disabled['status']);
        $wrongMethod = $this->request(['errorApi' => '1']);
        $this->assertSame(405, $wrongMethod['status']);
        $this->assertContains('Allow: POST', $wrongMethod['headers']);
        $invalid = $this->request(['errorApi' => '1'], $headers, 'POST', '{"csrfToken":[]}');
        $this->assertSame(401, $invalid['status']);
        $oversize = $this->request(['errorApi' => '1'], $headers, 'POST', str_repeat(' ', 16385));
        $this->assertSame(413, $oversize['status'], $oversize['body'] . implode("\n", $oversize['headers']));
        $wrongType = $this->request(
            ['errorApi' => '1'],
            ['X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.8', 'Content-Type: text/plain'],
            'POST',
            '{}'
        );
        $this->assertSame(415, $wrongType['status']);
    }

    public function testHostRejectionsPrecedeSessionsMaintenanceAndDebug(): void
    {
        foreach (['evil.example', 'app.example.com:8080', 'app.example.com.evil', 'user@app.example.com',
            'app.example.com,evil.example', 'app.example.com.', 'app.example.com:0'] as $host) {
            foreach ([[], ['maintenance' => '1']] as $query) {
                $response = $this->request($query + ['allowDebug' => '1'], [
                    'X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.7',
                ], 'GET', '', $host);
                $this->assertSame(400, $response['status']);
                $this->assertSame('Bad Request', $response['body']);
                $this->assertContains('X-Content-Type-Options: nosniff', $response['headers']);
                $this->assertSame([], $this->sessionCookies($response['headers']));
            }
        }
        $response = $this->request(['invalidHosts' => '1', 'allowDebug' => '1'], [
            'X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.7',
        ]);
        $this->assertSame(500, $response['status']);
        $this->assertStringNotContainsString('requires exact hosts', $response['body']);
        $this->assertSame([], $this->sessionCookies($response['headers']));
    }

    public function testIndependentPdoExceptionPreservesOriginalFailure(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is required for the independent PDO regression test.');
        }
        $response = $this->request(
            ['independentPdoFailure' => '1', 'allowDebug' => '1'],
            ['X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.7']
        );

        $this->assertSame(500, $response['status']);
        $this->assertStringContainsString('no such table: independent_pdo_failure', $response['body']);
        $this->assertStringNotContainsString(
            'Provide credentials in conf/phinx.local.php to use database.',
            $response['body']
        );
    }

    public function testInvalidCookieConfigurationIncludingAlreadyActiveSession(): void
    {
        $cases = [
            ['sameSite' => 'invalid'],
            ['sameSite' => 'None', 'direct' => '1'],
            ['domain' => 'unrelated.example'],
            ['domain' => 'ample.com'],
            ['domain' => 'example.com:443'],
            ['domain' => 'example.com; Secure'],
            ['path' => '/; injected=value'],
            ['path' => "/app\r\n"],
            ['active' => '1', 'sameSite' => 'invalid'],
            ['invalidProxy' => '1'],
        ];
        foreach ($cases as $query) {
            $response = $this->request($query);
            $this->assertSame(500, $response['status']);
            $this->assertStringNotContainsString('SeablastConfigurationException', $response['body']);
            if (!isset($query['active'])) {
                $this->assertSame([], $this->sessionCookies($response['headers']));
            }
        }
    }

    public function testInvalidForwardingFailsBeforeSessionOrMaintenance(): void
    {
        $cases = [
            [],
            ['X-Forwarded-Proto: https'],
            ['X-Forwarded-Proto: https,http', 'X-Forwarded-For: 198.51.100.8'],
            ['X-Forwarded-Proto: https', 'X-Forwarded-For: unknown'],
            ['X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.8,'],
        ];
        foreach ($cases as $headers) {
            $response = $this->request(['maintenance' => '1'], $headers);
            $this->assertSame(400, $response['status']);
            $this->assertSame('Bad Request', $response['body']);
            $this->assertSame([], $this->sessionCookies($response['headers']));
        }
    }

    public function testJsonContentTypeAndMarkupPayloadInProductionAndDevelopment(): void
    {
        $value = '</script><script>alert(1)</script><img src=x onerror="alert(2)">';
        foreach ([false, true] as $development) {
            $response = $this->request(
                ['allowDebug' => '1', 'jsonValue' => $value],
                ['X-Forwarded-Proto: https', 'X-Forwarded-For: '
                    . ($development ? '198.51.100.7' : '198.51.100.8')]
            );
            $this->assertSame(200, $response['status']);
            $this->assertContains('Content-Type: application/json; charset=utf-8', $response['headers']);
            $this->assertContains('X-Content-Type-Options: nosniff', $response['headers']);
            $body = json_decode($response['body'], true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error());
            $this->assertIsArray($body);
            $this->assertSame($development, $body['debug']);
            $this->assertSame($value, $body['value']);
        }
    }

    public function testLocalhostAndUnconfiguredProxyRequireExplicitDebugPermission(): void
    {
        foreach (['ipv4', 'ipv6'] as $loopback) {
            foreach ([false, true] as $allowDebug) {
                $query = ['loopback' => $loopback, 'unconfiguredProxy' => '1'];
                if ($allowDebug) {
                    $query['allowDebug'] = '1';
                }
                foreach ([[], ['X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.7']] as $headers) {
                    $normal = $this->request($query, $headers);
                    $this->assertSame(200, $normal['status'], $normal['body']);
                    $body = json_decode($normal['body'], true);
                    $this->assertIsArray($body);
                    $this->assertSame($allowDebug, $body['debug']);
                    $maintenance = $this->request($query + ['maintenance' => '1'], $headers);
                    $this->assertSame(200, $maintenance['status'], $maintenance['body']);
                    if ($allowDebug) {
                        $body = json_decode($maintenance['body'], true);
                        $this->assertIsArray($body);
                        $this->assertTrue($body['debug']);
                    } else {
                        $this->assertSame('maintenance fixture', $maintenance['body']);
                    }
                }
            }
        }
        $headers = ['X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.7'];
        $normal = $this->request([], $headers);
        $this->assertSame(200, $normal['status'], $normal['body']);
        $body = json_decode($normal['body'], true);
        $this->assertIsArray($body);
        $this->assertFalse($body['debug'], 'Trusted proxy resolution alone must not enable development mode.');
        $maintenance = $this->request(['maintenance' => '1'], $headers);
        $this->assertSame(200, $maintenance['status'], $maintenance['body']);
        $this->assertSame('maintenance fixture', $maintenance['body']);
    }

    public function testMissingHostConfigurationOnlyLogsDebugReminder(): void
    {
        $message = 'Define SeablastConstant::SB_ALLOWED_HOSTS to restrict Host headers and protect absolute URLs.';
        foreach (['missingHosts', 'emptyHosts'] as $setting) {
            $response = $this->request([$setting => '1', 'hostDebugLog' => '1'], null, 'GET', '', 'unlisted.example');
            $this->assertSame(200, $response['status']);
            $body = json_decode($response['body'], true);
            $this->assertIsArray($body);
            $this->assertSame('https://unlisted.example/app', $body['root']);
        }
        $log = '';
        foreach (glob(self::$app . '/log/seablast*.log') ?: [] as $file) {
            $content = file_get_contents($file);
            $this->assertIsString($content);
            $log .= $content;
        }
        $this->assertSame(2, substr_count($log, $message));
        $this->assertStringContainsString('[debug]', strtolower($log));
        $this->assertStringNotContainsString('unlisted.example', $log);
        $response = $this->request(['missingHosts' => '1'], null, 'GET', '', 'user@unlisted.example');
        $this->assertSame(400, $response['status']);
        $this->assertSame([], $this->sessionCookies($response['headers']));
    }

    public function testNormalAndMaintenanceCookieHeadersAndRegeneration(): void
    {
        $cases = [
            ['direct' => '1'],
            ['direct' => '1', 'tls' => '1'],
            [],
            ['maintenance' => '1'],
            ['regenerate' => '1'],
            ['sameSite' => 'Strict'],
            ['sameSite' => 'None'],
            ['domain' => '.example.com'],
        ];
        foreach ($cases as $query) {
            $response = $this->request($query);
            $this->assertSame(200, $response['status'], $response['body']);
            $cookies = $this->sessionCookies($response['headers']);
            $this->assertNotEmpty($cookies);
            foreach ($cookies as $cookie) {
                $this->assertStringContainsString('httponly', $cookie);
                $this->assertStringContainsString('samesite=' . strtolower($query['sameSite'] ?? 'Lax'), $cookie);
                $this->assertStringContainsString('path=/app', $cookie);
                $this->assertSame(
                    !isset($query['direct']) || isset($query['tls']),
                    strpos($cookie, '; secure') !== false
                );
                if (isset($query['domain'])) {
                    $this->assertStringContainsString('domain=example.com', $cookie);
                } else {
                    $this->assertStringNotContainsString('domain=', $cookie);
                }
            }
            if (isset($query['maintenance'])) {
                $this->assertSame('maintenance fixture', $response['body']);
            } else {
                $body = json_decode($response['body'], true);
                $this->assertIsArray($body);
                $scheme = isset($query['direct']) && !isset($query['tls']) ? 'http' : 'https';
                $this->assertSame($scheme . '://app.example.com/app', $body['root']);
                $this->assertSame('/app', $body['path'], 'Legacy SameSite suffix must not leak into logical paths.');
                $this->assertFalse($body['debug']);
            }
        }
    }

    public function testProcessStatusCompatibility(): void
    {
        $this->assertFalse(self::isRunningStatus(false));
        $this->assertFalse(self::isRunningStatus(['running' => false]));
        $this->assertTrue(self::isRunningStatus(['running' => true]));
    }

    public function testSecurityHeaderModesOverridesAndNonce(): void
    {
        $previousNonce = '';
        foreach (['report-only', 'enforce', 'off'] as $mode) {
            $response = $this->request(['html' => '1', 'cspMode' => $mode]);
            $this->assertSame(200, $response['status'], $response['body']);
            $headers = implode("\n", $response['headers']);
            $this->assertStringContainsString("frame-ancestors 'self'", $headers);
            $this->assertStringContainsString('X-Frame-Options: SAMEORIGIN', $headers);
            $this->assertSame(
                $mode === 'report-only',
                strpos($headers, 'Content-Security-Policy-Report-Only:') !== false
            );
            $this->assertSame(1, preg_match('/<script nonce="([A-Za-z0-9+\/=]+)"/', $response['body'], $matches));
            $nonce = $matches[1] ?? '';
            $this->assertNotSame('', $nonce);
            $this->assertNotSame($previousNonce, $nonce);
            $this->assertStringNotContainsString('model-value-must-be-overridden', $response['body']);
            if ($mode !== 'off') {
                $this->assertStringContainsString("'nonce-" . $nonce . "'", $headers);
            }
            $previousNonce = $nonce;
        }
        $response = $this->request([
            'frameOrigin' => 'https://embed.example.com', 'cachePolicy' => 'private, max-age=0',
            'reportUri' => '/reports',
        ]);
        $headers = implode("\n", $response['headers']);
        $this->assertStringNotContainsString('X-Frame-Options:', $headers);
        $this->assertStringContainsString('frame-ancestors https://embed.example.com', $headers);
        $this->assertStringContainsString('Cache-Control: private, max-age=0', $headers);
        $this->assertStringContainsString('report-uri /reports', $headers);
        $response = $this->request(['frameOrigin' => "'none'"]);
        $this->assertContains('X-Frame-Options: DENY', $response['headers']);
        $invalid = $this->request(['cachePolicy' => "no-store\r\nX-Injected: yes"]);
        $this->assertSame(500, $invalid['status']);
        $this->assertStringNotContainsString('X-Injected:', implode("\n", $invalid['headers']));
    }

    public function testSecurityHeadersCoverDynamicResponsesAndPreserveContracts(): void
    {
        foreach (
            [[], ['html' => '1'], ['authenticated' => '1'], ['active' => '1'],
            ['maintenance' => '1'], ['redirect' => '1'], ['errorApi' => '1']] as $query
        ) {
            $response = $this->request($query);
            $expected = isset($query['redirect']) ? 303 : (isset($query['errorApi']) ? 405 : 200);
            $this->assertSame($expected, $response['status'], $response['body']);
            $this->assertContains('Cache-Control: no-store', $response['headers']);
            $this->assertContains('X-Content-Type-Options: nosniff', $response['headers']);
            $this->assertContains('Referrer-Policy: strict-origin-when-cross-origin', $response['headers']);
            $this->assertContains('X-Frame-Options: SAMEORIGIN', $response['headers']);
            $this->assertContains("Content-Security-Policy: frame-ancestors 'self'", $response['headers']);
            $this->assertNotEmpty($this->sessionCookies($response['headers']));
            if (isset($query['redirect'])) {
                $this->assertContains('Location: /target', $response['headers']);
            }
            if (!isset($query['maintenance']) && !isset($query['errorApi'])) {
                $this->assertContains('X-Fixture: preserved', $response['headers']);
            }
        }
        $invalid = $this->request([], ['X-Forwarded-Proto: invalid', 'X-Forwarded-For: 198.51.100.8']);
        $this->assertSame(400, $invalid['status']);
        $this->assertContains('Cache-Control: no-store', $invalid['headers']);
        $this->assertContains("Content-Security-Policy: frame-ancestors 'self'", $invalid['headers']);
        $this->assertSame([], $this->sessionCookies($invalid['headers']));
    }

    public function testTracyAndMaintenanceShareVerifiedClientDecision(): void
    {
        $cases = [
            ['198.51.100.7', true],
            ['127.0.0.1, 198.51.100.8', false],
            ['198.51.100.8, 2001:db8::1', false],
            ['127.0.0.1, 2001:db8::1', false],
        ];
        foreach ($cases as $case) {
            $headers = ['X-Forwarded-Proto: https', 'X-Forwarded-For: ' . $case[0]];
            $normal = $this->request(['allowDebug' => '1'], $headers);
            $this->assertSame(200, $normal['status'], $normal['body']);
            $body = json_decode($normal['body'], true);
            $this->assertIsArray($body);
            $this->assertSame($case[1], $body['debug']);
            $maintenance = $this->request(['maintenance' => '1', 'allowDebug' => '1'], $headers);
            $this->assertSame(200, $maintenance['status'], $maintenance['body']);
            $this->assertSame(!$case[1], $maintenance['body'] === 'maintenance fixture');
        }
    }
}
