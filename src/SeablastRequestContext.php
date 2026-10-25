<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Seablast\Exceptions\InvalidRequestContextException;
use Seablast\Seablast\Exceptions\SeablastConfigurationException;

/** Immutable security decisions from one request snapshot; Host is validated before use. */
final class SeablastRequestContext
{
    /** @var string|null */
    private $clientIp;
    /** @var bool */
    private $debugAllowed;
    /** @var string */
    private $host;
    /** @var bool */
    private $hostAllowListConfigured;
    /** @var string */
    private $hostname;
    /** @var bool */
    private $https;

    /**
     * Supports Apache and Nginx HTTPS detection, including TLS-terminating reverse proxies.
     * Explicit trusted proxy IPs prevent spoofed headers from controlling the request context.
     * Only X-Forwarded-For and a single X-Forwarded-Proto value are supported.
     *
     * @param SeablastConfiguration $configuration
     * @param mixed[] $server The $_SERVER snapshot or a custom equivalent for testing.
     */
    public function __construct(SeablastConfiguration $configuration, array $server)
    {
        $trusted = self::configuredIps($configuration, SeablastConstant::SB_TRUSTED_PROXIES);
        $debugIps = self::configuredIps($configuration, SeablastConstant::DEBUG_IP_LIST);
        $peer = self::normalizeIp($server['REMOTE_ADDR'] ?? null);
        $this->clientIp = $peer;
        $this->https =
            (isset($server['HTTPS']) && is_string($server['HTTPS']) && strtolower($server['HTTPS']) === 'on') ||
            (isset($server['REQUEST_SCHEME']) && is_string($server['REQUEST_SCHEME'])
                && strtolower($server['REQUEST_SCHEME']) === 'https') ||
            (($server['SERVER_PORT'] ?? null) === '443' || ($server['SERVER_PORT'] ?? null) === 443);

        if ($peer !== null && in_array($peer, $trusted, true)) {
            $proto = $server['HTTP_X_FORWARDED_PROTO'] ?? null;
            $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? null;
            if (!is_string($proto) || !is_string($forwarded)) {
                throw new InvalidRequestContextException();
            }
            // Bound parsing work and reject control characters instead of trimming them away.
            if (
                strlen($proto) > 32 || strlen($forwarded) > 8192 ||
                preg_match('/[\x00-\x1F\x7F]/', $proto . $forwarded)
            ) {
                throw new InvalidRequestContextException();
            }
            $proto = strtolower(trim($proto, ' '));
            if (!in_array($proto, ['http', 'https'], true)) {
                throw new InvalidRequestContextException();
            }
            $chain = [];
            foreach (explode(',', $forwarded) as $value) {
                $ip = self::normalizeIp(trim($value, ' '));
                if ($ip === null) {
                    throw new InvalidRequestContextException();
                }
                $chain[] = $ip;
            }
            $this->https = $proto === 'https'; // External scheme overrides the backend connection.
            $this->clientIp = null; // A shared proxy is never a fallback operational identity.
            foreach (array_reverse($chain) as $ip) {
                if (!in_array($ip, $trusted, true)) {
                    $this->clientIp = $ip;
                    break;
                }
            }
        }
        $this->configureHost($configuration, $server);
        $this->debugAllowed = $this->clientIp !== null && in_array(
            $this->clientIp,
            $debugIps,
            true
        );
    }

    /** @return string[] */
    private static function configuredIps(SeablastConfiguration $configuration, string $key): array
    {
        $result = [];
        foreach ($configuration->exists($key) ? $configuration->getArrayString($key) : [] as $value) {
            $ip = self::normalizeIp($value);
            if ($ip === null) {
                throw new SeablastConfigurationException($key . ' requires exact IP addresses.');
            }
            $result[] = $ip;
        }
        return $result;
    }

    /** @param mixed[] $server */
    private function configureHost(SeablastConfiguration $configuration, array $server): void
    {
        // todo once SB_ALLOWED_HOSTS is required (in v0.3.0) this should be reduced to comparison
        $allowed = [];
        $key = SeablastConstant::SB_ALLOWED_HOSTS;
        foreach ($configuration->exists($key) ? $configuration->getArrayString($key) : [] as $value) {
            $host = self::normalizeHost($value);
            if ($host === null) {
                throw new SeablastConfigurationException($key . ' requires exact hosts with optional ports.');
            }
            $allowed[] = $host['authority'];
        }
        $this->hostAllowListConfigured = $allowed !== [];
        $host = self::normalizeHost($server['HTTP_HOST'] ?? null);
        if ($host === null || ($this->hostAllowListConfigured && !in_array($host['authority'], $allowed, true))) {
            throw new InvalidRequestContextException();
        }
        $this->host = $host['authority'];
        $this->hostname = $host['hostname'];
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    /** Normalized authority for absolute URLs, including brackets and any explicit port. */
    public function getHost(): string
    {
        return $this->host;
    }

    /** Normalized hostname without port or IPv6 brackets, for cookie-domain validation. */
    public function getHostname(): string
    {
        return $this->hostname;
    }

    /** Returns whether a nonempty allowed-host list is configured. */
    public function hasAllowedHosts(): bool
    {
        return $this->hostAllowListConfigured;
    }

    /**
     * Returns whether the resolved client IP is explicitly listed in DEBUG_IP_LIST.
     * Controls Tracy development mode and maintenance bypass; an unresolved client or empty list denies both.
     */
    public function isDebugAllowed(): bool
    {
        return $this->debugAllowed;
    }

    /**
     * Checks whether the current request was made using HTTPS.
     *
     * This function supports detection of HTTPS in both Apache and Nginx environments,
     * including setups behind reverse proxies or load balancers (e.g., Nginx, Cloudflare),
     * by inspecting common server variables and headers.
     *
     * For maximum security when behind a proxy, you can pass a list of trusted proxy IPs
     * to avoid spoofed headers like X-Forwarded-Proto.
     *
     * Historical helper signature (before the shared context):
     * isHttps(array $server, array $trustedProxies = []).
     * The server snapshot and configured proxies now enter through the constructor.
     * $server was the $_SERVER array or a custom equivalent.
     * $trustedProxies was an optional array of trusted proxy IP addresses.
     *                               When specified, proxy-related headers are trusted
     *                               only if the request comes from one of these IPs.
     *
     * @return bool True if the request was made via HTTPS, false otherwise.
     *
     * Historical examples (now configure SB_TRUSTED_PROXIES and construct the context):
     * isHttps($_SERVER); // Basic usage
     * isHttps($_SERVER, ['192.168.1.1']); // Usage with trusted proxies
     */
    public function isHttps(): bool
    {
        return $this->https;
    }

    /**
     * @param mixed $value
     * @return array{authority: string, hostname: string}|null
     */
    private static function normalizeHost($value): ?array
    {
        if (!is_string($value) || strlen($value) > 260) {
            return null;
        }
        $pattern = '/\A(?:\[([0-9a-fA-F:.]+)\]|([A-Za-z0-9.-]+))(?::([1-9][0-9]{0,4}))?\z/';
        if (preg_match($pattern, $value, $parts) !== 1) {
            return null;
        }
        $port = $parts[3] ?? '';
        if ($port !== '' && (int) $port > 65535) {
            return null;
        }
        if (($parts[1] ?? '') !== '') {
            if (filter_var($parts[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                return null;
            }
            $hostname = self::normalizeIp($parts[1]);
            if ($hostname === null) {
                return null;
            }
            $authority = '[' . $hostname . ']';
        } else {
            $hostname = strtolower($parts[2] ?? '');
            if (
                substr($hostname, -1) === '.' ||
                filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            ) {
                return null;
            }
            // Reject alternative numeric IP spellings that browsers can interpret differently from PHP.
            if (
                filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false &&
                preg_match('/(?:\A|\.)(?:[0-9]+|0x[0-9a-f]+)\z/i', $hostname)
            ) {
                return null;
            }
            $authority = $hostname;
        }
        return ['authority' => $authority . ($port === '' ? '' : ':' . $port), 'hostname' => $hostname];
    }

    /** @param mixed $value */
    private static function normalizeIp($value): ?string
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $binary = inet_pton($value);
        return $binary === false ? null : (inet_ntop($binary) ?: null);
    }
}
