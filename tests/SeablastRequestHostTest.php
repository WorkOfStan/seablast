<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\Exceptions\InvalidRequestContextException;
use Seablast\Seablast\Exceptions\SeablastConfigurationException;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastRequestContext;

class SeablastRequestHostTest extends TestCase
{
    public function testAllowedAuthoritiesNormalizeAndRemainImmutable(): void
    {
        $cases = [
            ['EXAMPLE.COM', 'example.com', 'example.com'],
            ['localhost', 'localhost', 'localhost'],
            ['192.168.1.10', '192.168.1.10', '192.168.1.10'],
            ['example.com:443', 'example.com:443', 'example.com'],
            ['localhost:8080', 'localhost:8080', 'localhost'],
            ['[2001:0DB8:0:0:0:0:0:1]:8080', '[2001:db8::1]:8080', '2001:db8::1'],
            ['[::1]', '[::1]', '::1'],
        ];
        foreach ($cases as [$request, $authority, $hostname]) {
            $configuration = new SeablastConfiguration();
            $configuration->setArrayString(
                SeablastConstant::SB_ALLOWED_HOSTS,
                [strtoupper($authority), 'other.example']
            );
            $context = new SeablastRequestContext($configuration, ['HTTP_HOST' => $request]);
            $configuration->setArrayString(SeablastConstant::SB_ALLOWED_HOSTS, []);
            $this->assertSame($authority, $context->getHost());
            $this->assertSame($hostname, $context->getHostname());
            $this->assertTrue($context->hasAllowedHosts());
        }
    }

    public function testInvalidConfiguredHostsAreConfigurationErrors(): void
    {
        foreach (['', '*', '*.example.com', 'https://example.com', 'example.com/path', '127.0.0.0/8',
            'example.com:0', 'example.com:65536', 'example.com:080', '[::1', '::1', 'example.com.'] as $value) {
            $configuration = new SeablastConfiguration();
            $configuration->setArrayString(SeablastConstant::SB_ALLOWED_HOSTS, ['example.com', $value]);
            try {
                new SeablastRequestContext($configuration, ['HTTP_HOST' => 'example.com']);
                $this->fail('Invalid allowed-host configuration accepted.');
            } catch (SeablastConfigurationException $e) {
                $this->assertSame('SB_ALLOWED_HOSTS requires exact hosts with optional ports.', $e->getMessage());
            }
        }
    }

    public function testMalformedHostsFailEvenWithoutAnAllowList(): void
    {
        foreach ([null, [], 123, '', ' example.com', "example.com\r\nX-Test: yes", "example.com\0", 'example.com ',
            'https://example.com', 'user@example.com', 'example.com/path', 'example.com\\path', 'example.com?x=1',
            'example.com#x', 'example.com,evil.example', 'example.com:0', 'example.com:65536', 'example.com:080',
            'example.com:', 'example.com.', 'example..com', '-example.com', 'example_.com', '127.1', '2130706433',
            '0x7f000001', '0177.0.0.1', '999.0.0.1', '[127.0.0.1]', '::1', '[fe80::1%25eth0]',
            str_repeat('a', 64) . '.com', str_repeat('a', 261)] as $value) {
            try {
                new SeablastRequestContext(new SeablastConfiguration(), ['HTTP_HOST' => $value]);
                $this->fail('Malformed Host accepted.');
            } catch (InvalidRequestContextException $e) {
                $this->assertSame(400, $e->getCode());
                $this->assertSame('Bad Request', $e->getMessage());
            }
        }
        $this->expectException(InvalidRequestContextException::class);
        new SeablastRequestContext(new SeablastConfiguration(), []);
    }

    public function testMissingAndEmptyAllowListsPermitValidHosts(): void
    {
        foreach ([false, true] as $explicitEmpty) {
            $configuration = new SeablastConfiguration();
            if ($explicitEmpty) {
                $configuration->setArrayString(SeablastConstant::SB_ALLOWED_HOSTS, []);
            }
            $context = new SeablastRequestContext($configuration, ['HTTP_HOST' => 'unlisted.example:8080']);
            $this->assertSame('unlisted.example:8080', $context->getHost());
            $this->assertFalse($context->hasAllowedHosts());
        }
    }

    public function testUnknownHostsAndPortsFailDespiteForwardedHost(): void
    {
        $configuration = new SeablastConfiguration();
        $configuration->setArrayString(SeablastConstant::SB_ALLOWED_HOSTS, ['example.com', 'localhost:8080']);
        foreach (['evil.example', 'example.com.evil', 'sub.example.com', 'example.com:443', 'localhost',
            'localhost:8081'] as $value) {
            try {
                new SeablastRequestContext($configuration, [
                    'HTTP_HOST' => $value,
                    'HTTP_X_FORWARDED_HOST' => 'example.com',
                    'HTTP_FORWARDED' => 'host=example.com',
                ]);
                $this->fail('Unlisted Host accepted.');
            } catch (InvalidRequestContextException $e) {
                $this->assertSame(400, $e->getCode());
            }
        }
    }
}
