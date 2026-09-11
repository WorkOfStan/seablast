<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Seablast\Exceptions\InvalidRequestContextException;
use Seablast\Seablast\Exceptions\SeablastConfigurationException;

/** Immutable security decisions from one request snapshot; Host is deliberately not trusted here. */
final class SeablastRequestContext
{
    /** @var bool */
    private $https;
    /** @var string|null */
    private $clientIp;
    /** @var bool */
    private $debugAllowed;

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
        $this->debugAllowed = $this->clientIp !== null && in_array(
            $this->clientIp,
            array_merge(['127.0.0.1', '::1'], $debugIps),
            true
        );
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

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function isDebugAllowed(): bool
    {
        return $this->debugAllowed;
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
}
