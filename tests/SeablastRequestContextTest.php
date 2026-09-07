<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\Exceptions\InvalidRequestContextException;
use Seablast\Seablast\Exceptions\SeablastConfigurationException;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastRequestContext;

class SeablastRequestContextTest extends TestCase
{
    public function testDirectConnectionsIgnoreAllForwardingHeaders(): void
    {
        foreach ([[], ['HTTPS' => 'on'], ['REQUEST_SCHEME' => 'https'], ['SERVER_PORT' => '443']] as $tls) {
            $context = new SeablastRequestContext(new SeablastConfiguration(), $tls + [
                'REMOTE_ADDR' => '198.51.100.8',
                'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_SSL' => 'on',
                'HTTP_FORWARDED' => 'for=127.0.0.1;proto=https',
            ]);
            $this->assertSame($tls !== [], $context->isHttps());
            $this->assertSame('198.51.100.8', $context->getClientIp());
            $this->assertFalse($context->isDebugAllowed());
        }
        foreach ([[], ['REMOTE_ADDR' => []], ['REMOTE_ADDR' => 'invalid']] as $server) {
            $context = new SeablastRequestContext(new SeablastConfiguration(), $server);
            $this->assertNull($context->getClientIp());
            $this->assertFalse($context->isDebugAllowed());
        }
    }

    public function testProxyChainUsesFirstUntrustedHopAndExternalScheme(): void
    {
        $configuration = $this->configuration();
        $configuration->setArrayString(SeablastConstant::DEBUG_IP_LIST, ['198.51.100.7', '127.0.0.1']);
        $cases = [
            ['198.51.100.7', '198.51.100.7', true],
            ['127.0.0.1, 198.51.100.8, 2001:db8::1', '198.51.100.8', false],
            ['198.51.100.7, 2001:0db8:0:0:0:0:0:1', '198.51.100.7', true],
            ['127.0.0.1, 2001:db8::1', null, false],
        ];
        foreach ($cases as $case) {
            $context = new SeablastRequestContext($configuration, [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTPS' => 'on',
                'HTTP_X_FORWARDED_PROTO' => 'http',
                'HTTP_X_FORWARDED_FOR' => $case[0],
            ]);
            $this->assertFalse($context->isHttps());
            $this->assertSame($case[1], $context->getClientIp());
            $this->assertSame($case[2], $context->isDebugAllowed());
        }
        $context = new SeablastRequestContext($configuration, [
            'REMOTE_ADDR' => '2001:0db8:0:0:0:0:0:1',
            'HTTP_X_FORWARDED_PROTO' => ' HTTPS ',
            'HTTP_X_FORWARDED_FOR' => '2001:db8::2',
        ]);
        $this->assertTrue($context->isHttps());
        $this->assertSame('2001:db8::2', $context->getClientIp());
        $configuration->setArrayString(SeablastConstant::DEBUG_IP_LIST, ['2001:db8::2']);
        $this->assertFalse($context->isDebugAllowed(), 'The resolved snapshot must remain immutable.');
    }

    public function testMalformedTrustedProxyMetadataFailsClosed(): void
    {
        $cases = [
            [],
            ['HTTP_X_FORWARDED_PROTO' => 'https'],
            ['HTTP_X_FORWARDED_PROTO' => ['https']],
            ['HTTP_X_FORWARDED_PROTO' => 'https,http'],
            ['HTTP_X_FORWARDED_PROTO' => "https\r\n"],
            ['HTTP_X_FORWARDED_PROTO' => 'ftp'],
            ['HTTP_X_FORWARDED_FOR' => ''],
            ['HTTP_X_FORWARDED_FOR' => []],
            ['HTTP_X_FORWARDED_FOR' => '198.51.100.8,'],
            ['HTTP_X_FORWARDED_FOR' => 'unknown'],
            ['HTTP_X_FORWARDED_FOR' => '198.51.100.8:1234'],
            ['HTTP_X_FORWARDED_FOR' => '[2001:db8::2]'],
            ['HTTP_X_FORWARDED_FOR' => "198.51.100.8\n"],
            ['HTTP_X_FORWARDED_FOR' => str_repeat('1', 8193)],
        ];
        foreach ($cases as $case) {
            $server = $case === [] ? [] : $case + [
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.8',
            ];
            // A missing client chain is tested separately from a missing entire header pair.
            if ($case === ['HTTP_X_FORWARDED_PROTO' => 'https']) {
                unset($server['HTTP_X_FORWARDED_FOR']);
            }
            try {
                new SeablastRequestContext($this->configuration(), $server + ['REMOTE_ADDR' => '127.0.0.1']);
                $this->fail('Invalid forwarding metadata accepted.');
            } catch (InvalidRequestContextException $e) {
                $this->assertSame(400, $e->getCode());
                $this->assertSame('Bad Request', $e->getMessage());
            }
        }
    }

    public function testExactIpConfigurationAndLoopbackDefaults(): void
    {
        foreach (['127.0.0.1', '0:0:0:0:0:0:0:1'] as $ip) {
            $context = new SeablastRequestContext(new SeablastConfiguration(), ['REMOTE_ADDR' => $ip]);
            $this->assertTrue($context->isDebugAllowed());
        }
        foreach (['*', '127.0.0.0/8', 'localhost', '198.51.100.8:80', ''] as $ip) {
            $configuration = new SeablastConfiguration();
            $configuration->setArrayString(SeablastConstant::SB_TRUSTED_PROXIES, [$ip]);
            try {
                new SeablastRequestContext($configuration, []);
                $this->fail('Invalid proxy configuration accepted.');
            } catch (SeablastConfigurationException $e) {
                $this->assertStringContainsString('exact IP addresses', $e->getMessage());
            }
        }
    }

    private function configuration(): SeablastConfiguration
    {
        $configuration = new SeablastConfiguration();
        $configuration->setArrayString(SeablastConstant::SB_TRUSTED_PROXIES, ['127.0.0.1', '2001:db8::1']);
        return $configuration;
    }
}
