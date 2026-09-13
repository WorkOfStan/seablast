<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Seablast\Exceptions\SeablastConfigurationException;
use SplFileObject;

/**
 * Bounded, per-host ingestion counters. All workers must use the same local file.
 *
 * @internal
 * @phpstan-type RateState array{minute: int, hour: int, minuteCount: int, hourCount: int, clients: array<string, int>}
 */
final class ClientErrorRateLimiter
{
    private const MAX_BYTES = 65536;

    /** @var int */
    private $clientLimit;
    /** @var int */
    private $hourLimit;
    /** @var int */
    private $minuteLimit;
    /** @var string */
    private $path;

    public function __construct(SeablastConfiguration $configuration)
    {
        $this->clientLimit = $this->limit($configuration, SeablastConstant::SB_CLIENT_ERROR_CLIENT_PER_MINUTE, 20);
        $this->minuteLimit = $this->limit($configuration, SeablastConstant::SB_CLIENT_ERROR_APP_PER_MINUTE, 100);
        $this->hourLimit = $this->limit($configuration, SeablastConstant::SB_CLIENT_ERROR_APP_PER_HOUR, 1000);
        $app = realpath(APP_DIR);
        if ($app === false) {
            throw new SeablastConfigurationException('Client error limiter requires an existing app directory.');
        }
        $this->path = $configuration->exists(SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE)
            ? $configuration->getString(SeablastConstant::SB_CLIENT_ERROR_RATE_LIMIT_FILE)
            : sys_get_temp_dir() . '/seablast-client-errors-' . hash('sha256', $app) . '.json';
        // Only absolute local paths are supported; reject stream wrappers and network shares.
        if (
            preg_match('/[\x00-\x1F\x7F]/', $this->path) || strpos($this->path, '://') !== false
            || substr($this->path, 0, 2) === '\\\\' || substr($this->path, 0, 2) === '//'
            || !preg_match('~^(?:/|[A-Za-z]:[/\\\\])~', $this->path)
        ) {
            throw new SeablastConfigurationException('Client error rate limit file must be an absolute local path.');
        }
    }

    /**
     * The explicit clock supports deterministic expiry tests without sleeping.
     *
     * @return array{status: int, retryAfter: int}
     */
    public function consume(?string $clientIp, ?int $now = null): array
    {
        $now = $now ?? time();
        clearstatcache(true, $this->path);
        $exists = file_exists($this->path);
        if (
            is_link($this->path) || !is_dir(dirname($this->path))
            || ($exists && (!is_file($this->path) || !is_readable($this->path) || !is_writable($this->path)))
            || (!$exists && !is_writable(dirname($this->path)))
        ) {
            return ['status' => 503, 'retryAfter' => 0];
        }
        // Existing POSIX state files must remain private. Windows uses the directory's inherited ACL.
        if ($exists && DIRECTORY_SEPARATOR === '/' && (fileperms($this->path) & 0077) !== 0) {
            return ['status' => 503, 'retryAfter' => 0];
        }
        $oldMask = umask(0077);
        try {
            // SplFileObject reports open failures as exceptions, without suppressing PHP warnings.
            $file = new SplFileObject($this->path, 'c+b');
        } catch (\RuntimeException $e) {
            return ['status' => 503, 'retryAfter' => 0];
        } finally {
            umask($oldMask);
        }
        $wouldBlock = 0;
        if (!$file->flock(LOCK_EX | LOCK_NB, $wouldBlock)) {
            return ['status' => $wouldBlock ? 429 : 503, 'retryAfter' => $wouldBlock ? 1 : 0];
        }
        try {
            // Read through the locked handle; pathname stat caches may predate another worker's write.
            $json = $file->fread(self::MAX_BYTES + 1);
            if (!is_string($json) || strlen($json) > self::MAX_BYTES || ($exists && $json === '')) {
                return ['status' => 503, 'retryAfter' => 0];
            }
            $state = $json === '' ? $this->emptyState($now) : $this->decodeState($json);
            if ($state === null) {
                return ['status' => 503, 'retryAfter' => 0];
            }
            // Do not reset counters backwards when the wall clock moves backwards.
            if ($now >= $state['hour'] + 3600) {
                $state['hour'] = intdiv($now, 3600) * 3600;
                $state['hourCount'] = 0;
            }
            if ($now >= $state['minute'] + 60) {
                $state['minute'] = intdiv($now, 60) * 60;
                $state['minuteCount'] = 0;
                $state['clients'] = [];
            }
            $key = hash('sha256', $clientIp ?? 'unknown');
            $count = $state['clients'][$key] ?? 0;
            $retryAfter = 0;
            if ($state['hourCount'] >= $this->hourLimit) {
                $retryAfter = $state['hour'] + 3600 - $now;
            }
            if ($state['minuteCount'] >= $this->minuteLimit || $count >= $this->clientLimit) {
                $retryAfter = max($retryAfter, $state['minute'] + 60 - $now);
            }
            if ($retryAfter > 0) {
                return ['status' => 429, 'retryAfter' => $retryAfter];
            }
            $state['hourCount']++;
            $state['minuteCount']++;
            $state['clients'][$key] = $count + 1;
            $encoded = json_encode($state);
            if (!is_string($encoded)) {
                return ['status' => 503, 'retryAfter' => 0];
            }
            if (strlen($encoded) > self::MAX_BYTES) {
                // Do not evict active clients: that would allow a rotating client to reset its allowance.
                return ['status' => 429, 'retryAfter' => $state['minute'] + 60 - $now];
            }
            $file->rewind();
            $written = $file->fwrite($encoded);
            if ($written !== strlen($encoded) || !$file->ftruncate($written) || !$file->fflush()) {
                return ['status' => 503, 'retryAfter' => 0];
            }
            return ['status' => 200, 'retryAfter' => 0];
        } catch (\RuntimeException $e) {
            return ['status' => 503, 'retryAfter' => 0];
        } finally {
            $file->flock(LOCK_UN);
        }
    }

    /** @return RateState */
    private function emptyState(int $now): array
    {
        return [
            'minute' => intdiv($now, 60) * 60,
            'hour' => intdiv($now, 3600) * 3600,
            'minuteCount' => 0,
            'hourCount' => 0,
            'clients' => [],
        ];
    }

    /** @return RateState|null */
    private function decodeState(string $json): ?array
    {
        $state = json_decode($json, true, 8);
        if (!is_array($state) || count($state) !== 5) {
            return null;
        }
        foreach (['minute', 'hour', 'minuteCount', 'hourCount'] as $key) {
            if (!isset($state[$key]) || !is_int($state[$key]) || $state[$key] < 0) {
                return null;
            }
        }
        if (
            !isset($state['clients']) || !is_array($state['clients'])
            || $state['minute'] % 60 !== 0 || $state['hour'] % 3600 !== 0
            || $state['minute'] < $state['hour'] || $state['minute'] >= $state['hour'] + 3600
            || $state['hourCount'] < $state['minuteCount']
        ) {
            return null;
        }
        $clients = [];
        foreach ($state['clients'] as $key => $count) {
            if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key) || !is_int($count) || $count < 1) {
                return null;
            }
            $clients[$key] = $count;
        }
        if (array_sum($clients) !== $state['minuteCount']) {
            return null;
        }
        return [
            'minute' => $state['minute'],
            'hour' => $state['hour'],
            'minuteCount' => $state['minuteCount'],
            'hourCount' => $state['hourCount'],
            'clients' => $clients,
        ];
    }

    private function limit(SeablastConfiguration $configuration, string $key, int $default): int
    {
        $limit = $configuration->exists($key) ? $configuration->getInt($key) : $default;
        if ($limit < 1) {
            throw new SeablastConfigurationException('Client error rate limits must be positive integers.');
        }
        return $limit;
    }
}
