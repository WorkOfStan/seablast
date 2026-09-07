<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use PHPUnit\Framework\TestCase;

/** Real bootstrap and response-header tests, run on each supported PHP version in CI. */
class RequestContextHttpTest extends TestCase
{
    /** @var resource|null */
    private static $process;
    /** @var string */
    private static $app;
    /** @var string */
    private static $address;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__);
        self::$app = $root . '/.tmp/request-context-' . bin2hex(random_bytes(6));
        foreach (['/vendor/seablast/seablast', '/conf', '/log', '/sessions'] as $directory) {
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
            $connection = @stream_socket_client('tcp://' . self::$address, $errno, $error, 0.1);
            if (is_resource($connection)) {
                fclose($connection);
                return;
            }
            usleep(50000);
        }
        throw new \RuntimeException('HTTP fixture server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
        // Clean only this fixture's known files; never traverse unrelated test directories.
        foreach (['/sessions/*', '/log/*'] as $pattern) {
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
        $directories = ['/vendor/seablast/seablast', '/vendor/seablast', '/vendor', '/conf', '/log', '/sessions', ''];
        foreach ($directories as $dir) {
            // Windows sync/indexing tools may briefly hold an already-empty directory open.
            for ($attempt = 0; $attempt < 10; $attempt++) {
                if (@rmdir(self::$app . $dir)) {
                    break;
                }
                usleep(50000);
            }
        }
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
            $normal = $this->request([], $headers);
            $this->assertSame(200, $normal['status'], $normal['body']);
            $body = json_decode($normal['body'], true);
            $this->assertIsArray($body);
            $this->assertSame($case[1], $body['debug']);
            $maintenance = $this->request(['maintenance' => '1'], $headers);
            $this->assertSame(200, $maintenance['status'], $maintenance['body']);
            $this->assertSame(!$case[1], $maintenance['body'] === 'maintenance fixture');
        }
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

    /**
     * @param string[] $query
     * @param string[]|null $headers
     * @return array{status: int, headers: string[], body: string}
     */
    private function request(array $query, ?array $headers = null): array
    {
        $headers = $headers ?? ['X-Forwarded-Proto: https', 'X-Forwarded-For: 198.51.100.8'];
        $headers[] = 'Host: app.example.com';
        $headers[] = 'Connection: close';
        $context = stream_context_create(['http' => [
            'ignore_errors' => true,
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
}
