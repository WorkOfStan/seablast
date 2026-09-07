<?php

declare(strict_types=1);

namespace Seablast\Seablast;

use Seablast\Seablast\Exceptions\SeablastConfigurationException;

/** Validated session policy, also constructed when an integrator has already started the session. */
final class SeablastSessionCookie
{
    /** @var array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'Strict'|'None'} */
    private $options;

    public function __construct(
        SeablastConfiguration $configuration,
        SeablastRequestContext $context,
        string $appPath,
        string $host
    ) {
        $path = $configuration->exists(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH)
            ? $configuration->getString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH) : '';
        if ($path === '') {
            // empty path defaults to the current directory, while the path to the app is required
            $path = $appPath === '' ? '/' : $appPath;
            $configuration->setString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_PATH, $path);
        }
        $sameSite = $configuration->exists(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_SAMESITE)
            ? $configuration->getString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_SAMESITE) : 'Lax';
        $domain = $configuration->exists(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_DOMAIN)
            ? $configuration->getString(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_DOMAIN) : '';
        if (preg_match('/[\x00-\x1F\x7F;]/', $path)) {
            throw new SeablastConfigurationException('Invalid session cookie path.');
        }
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true) || ($sameSite === 'None' && !$context->isHttps())) {
            throw new SeablastConfigurationException('Invalid session SameSite policy; None requires HTTPS.');
        }
        if ($domain !== '') {
            // A leading dot is accepted for explicit legacy parent-domain configuration.
            $domain = strtolower($domain[0] === '.' ? substr($domain, 1) : $domain);
            $hostname = strtolower($host);
            if (
                $domain === '' || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false ||
                filter_var($domain, FILTER_VALIDATE_IP) !== false || strpos($domain, '.') === false ||
                substr($domain, -1) === '.' ||
                ($hostname !== $domain && substr($hostname, -strlen('.' . $domain)) !== '.' . $domain)
            ) {
                throw new SeablastConfigurationException('Invalid or unrelated session cookie domain.');
            }
        }
        $this->options = [
            'lifetime' => $configuration->exists(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_LIFETIME)
                ? $configuration->getInt(SeablastConstant::SB_SESSION_SET_COOKIE_PARAMS_LIFETIME)
                : ($configuration->exists(SeablastConstant::SB_SESSION_SET_COOKIE_LIFETIME)
                    ? $configuration->getInt(SeablastConstant::SB_SESSION_SET_COOKIE_LIFETIME)
                    : (int) ini_get('session.cookie_lifetime')),
            'path' => $path,
            'domain' => $domain,
            'secure' => $context->isHttps(),
            'httponly' => true,
            'samesite' => $sameSite,
        ];
    }

    public function apply(): void
    {
        // use '1' for true and '0' for false; alternatively 'On' as true, and 'Off' as false
        //ini_set('session.cookie_httponly', '1');
        if (PHP_VERSION_ID >= 70300) {
            $applied = session_set_cookie_params($this->options);
        } else {
            // TODO PHP-7.2: Remove this compatibility branch and its legacy tests when PHP >= 7.3 is required.
            $applied = session_set_cookie_params(
                $this->options['lifetime'], // int $lifetime_or_options,
                $this->options['path'] . '; SameSite=' . $this->options['samesite'], // ?string $path = null,
                $this->options['domain'], // ?string $domain = null,
                $this->options['secure'], // ?bool $secure = null,
                $this->options['httponly'] // ?bool $httponly = null
            );
        }
        if (!$applied) {
            throw new SeablastConfigurationException('Cannot apply session cookie security settings.');
        }
        \Tracy\Debugger::barDump($this->options, 'session_set_cookie_params');
    }
}
