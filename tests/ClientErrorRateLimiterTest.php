<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\ClientErrorRateLimiter;
use Seablast\Seablast\Exceptions\SeablastConfigurationException;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Tracy\Debugger;

class ClientErrorRateLimiterTest extends TestCase
{
    /** @var string */
    private $path;
    /** @var SeablastConfiguration */
    private $configuration;

    protected function setUp(): void
    {
        if (!defined('APP_DIR')) {
            define('APP_DIR', dirname(__DIR__));
            Debugger::enable(Debugger::DEVELOPMENT, APP_DIR . '/log');
        }
        $this->path = sys_get_temp_dir() . '/seablast-limiter-test-' . bin2hex(random_bytes(8));
        $this->configuration = new SeablastConfiguration();
        $this->configuration->setString(SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE, $this->path);
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testClientAndGlobalWindowsAndExpiry(): void
    {
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_CLIENT_PER_MINUTE, 1);
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_APP_PER_MINUTE, 2);
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_APP_PER_HOUR, 3);
        $limiter = new ClientErrorRateLimiter($this->configuration);
        $this->assertSame(200, $limiter->consume('198.51.100.1', 3600)['status']);
        $this->assertSame(['status' => 429, 'retryAfter' => 59], $limiter->consume('198.51.100.1', 3601));
        $this->assertSame(200, $limiter->consume('198.51.100.2', 3601)['status']);
        $this->assertSame(429, $limiter->consume('198.51.100.3', 3601)['status']);
        $this->assertSame(200, $limiter->consume('198.51.100.1', 3660)['status']);
        $this->assertSame(['status' => 429, 'retryAfter' => 3539], $limiter->consume('198.51.100.2', 3661));
        $this->assertSame(200, $limiter->consume('198.51.100.2', 7200)['status']);
        $this->assertSame(429, $limiter->consume('198.51.100.2', 3600)['status']);
        $state = file_get_contents($this->path);
        $this->assertIsString($state);
        $this->assertStringNotContainsString('198.51.100', $state);
    }

    public function testUnknownClientsShareOneBucketAndInstancesShareState(): void
    {
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_CLIENT_PER_MINUTE, 1);
        $this->assertSame(200, (new ClientErrorRateLimiter($this->configuration))->consume(null, 3600)['status']);
        $this->assertSame(429, (new ClientErrorRateLimiter($this->configuration))->consume(null, 3601)['status']);
    }

    public function testCorruptEmptyAndOversizedStateFailClosed(): void
    {
        $limiter = new ClientErrorRateLimiter($this->configuration);
        $this->assertSame(200, $limiter->consume(null, 3600)['status']);
        foreach (['', '{', '{}', str_repeat(' ', 65537),
            '{"minute":3600,"hour":3600,"minuteCount":0,"hourCount":0,"clients":{"fake":1}}'] as $state) {
            file_put_contents($this->path, $state);
            $this->assertSame(503, $limiter->consume(null, 3600)['status']);
            $this->assertSame($state, file_get_contents($this->path));
        }
    }

    public function testMissingParentAndDirectoryPathsFailClosed(): void
    {
        foreach ([$this->path . '/missing.json', sys_get_temp_dir()] as $path) {
            $this->configuration->setString(SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE, $path);
            $this->assertSame(503, (new ClientErrorRateLimiter($this->configuration))->consume(null)['status']);
        }
    }

    public function testCounterStorageIsBoundedWithoutEvictingActiveClients(): void
    {
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_APP_PER_MINUTE, 10000);
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_APP_PER_HOUR, 10000);
        $limiter = new ClientErrorRateLimiter($this->configuration);
        $accepted = 0;
        for ($i = 0; $i < 1100; $i++) {
            if ($limiter->consume('client-' . $i, 3600)['status'] === 429) {
                break;
            }
            $accepted++;
        }
        $this->assertGreaterThan(0, $accepted);
        $this->assertLessThan(1100, $accepted);
        $state = file_get_contents($this->path);
        $this->assertIsString($state);
        $this->assertLessThanOrEqual(65536, strlen($state));
        $decoded = json_decode($state, true);
        $this->assertIsArray($decoded);
        $this->assertIsArray($decoded['clients']);
        $this->assertCount($accepted, $decoded['clients']);
        $this->assertSame(429, $limiter->consume('another-client', 3601)['status']);
        $this->assertSame(200, $limiter->consume('another-client', 3660)['status']);
    }

    public function testReadOnlyStateFailsClosed(): void
    {
        $limiter = new ClientErrorRateLimiter($this->configuration);
        $this->assertSame(200, $limiter->consume(null, 3600)['status']);
        chmod($this->path, 0400);
        clearstatcache(true, $this->path);
        try {
            if (is_writable($this->path)) {
                $this->markTestSkipped('The current user can override read-only permissions.');
            }
            $this->assertSame(503, $limiter->consume(null, 3600)['status']);
        } finally {
            chmod($this->path, 0600);
        }
    }

    public function testSeparateApplicationStateDoesNotShareAllowances(): void
    {
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_CLIENT_PER_MINUTE, 1);
        $first = new ClientErrorRateLimiter($this->configuration);
        $this->assertSame(200, $first->consume(null, 3600)['status']);
        $this->assertSame(429, $first->consume(null, 3601)['status']);
        $otherPath = $this->path . '-other';
        $this->configuration->setString(SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE, $otherPath);
        try {
            $this->assertSame(200, (new ClientErrorRateLimiter($this->configuration))->consume(null, 3601)['status']);
        } finally {
            if (is_file($otherPath)) {
                unlink($otherPath);
            }
        }
    }

    public function testInvalidConfiguration(): void
    {
        $this->configuration->setInt(SeablastConstant::SB_CLIENT_ERROR_APP_PER_MINUTE, 0);
        $this->expectException(SeablastConfigurationException::class);
        new ClientErrorRateLimiter($this->configuration);
    }

    public function testRelativeStatePathIsRejected(): void
    {
        $this->configuration->setString(SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE, 'relative.json');
        $this->expectException(SeablastConfigurationException::class);
        new ClientErrorRateLimiter($this->configuration);
    }

    public function testWorkersRespectLocksAndAtomicAllowances(): void
    {
        $limiter = new ClientErrorRateLimiter($this->configuration);
        $this->assertSame(200, $limiter->consume(null, 3600)['status']);
        $file = fopen($this->path, 'r+b');
        $this->assertIsResource($file);
        $this->assertTrue(flock($file, LOCK_EX));
        try {
            $worker = $this->worker();
            $this->assertSame(429, $this->finishWorker($worker));
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
        $workers = [];
        for ($i = 0; $i < 8; $i++) {
            $workers[] = $this->worker();
        }
        $statuses = [];
        foreach ($workers as $worker) {
            $statuses[] = $this->finishWorker($worker);
        }
        $this->assertSame(1, count(array_filter($statuses, static function (int $status): bool {
            return $status === 200;
        })));
        $this->assertSame(7, count(array_filter($statuses, static function (int $status): bool {
            return $status === 429;
        })));
    }

    public function testConcurrentFirstRequestsNeverResetAnotherWorkersCounters(): void
    {
        $workers = [];
        for ($i = 0; $i < 8; $i++) {
            $workers[] = $this->worker();
        }
        $accepted = 0;
        foreach ($workers as $worker) {
            $status = $this->finishWorker($worker);
            $this->assertContains($status, [200, 429, 503]);
            if ($status === 200) {
                $accepted++;
            }
        }
        $this->assertGreaterThanOrEqual(1, $accepted);
        $this->assertLessThanOrEqual(2, $accepted);
        $json = file_get_contents($this->path);
        $this->assertIsString($json);
        $state = json_decode($json, true);
        $this->assertIsArray($state);
        $this->assertSame($accepted, $state['minuteCount']);
        $this->assertSame($accepted, $state['hourCount']);
    }

    /** @return array{process: resource, output: resource, error: resource} */
    private function worker(): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/Fixtures/client-error-worker.php')
            . ' ' . escapeshellarg($this->path);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        return ['process' => $process, 'output' => $pipes[1], 'error' => $pipes[2]];
    }

    /** @param array{process: resource, output: resource, error: resource} $worker */
    private function finishWorker(array $worker): int
    {
        $output = stream_get_contents($worker['output']);
        $error = stream_get_contents($worker['error']);
        fclose($worker['output']);
        fclose($worker['error']);
        $this->assertSame(0, proc_close($worker['process']), (string) $error);
        $this->assertSame('', $error);
        return (int) $output;
    }
}
