<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\Exceptions\SeablastConfigurationException;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastSecurityHeaders;

class SecurityHeadersTest extends TestCase
{
    public function testDefaultsAndRequestNonceLifetime(): void
    {
        $configuration = new SeablastConfiguration();
        $policy = $configuration->securityHeaders();
        $this->assertSame($policy, $configuration->securityHeaders());
        $this->assertNotSame($policy->getNonce(), (new SeablastConfiguration())->securityHeaders()->getNonce());
        $this->assertSame(24, strlen((string) base64_decode($policy->getNonce(), true)));
        $headers = $policy->getHeaders();
        $this->assertSame('no-store', $headers['Cache-Control']);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
        $this->assertSame("frame-ancestors 'self'", $headers['Content-Security-Policy']);
        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
        $report = $headers['Content-Security-Policy-Report-Only'];
        $this->assertStringContainsString("'nonce-" . $policy->getNonce() . "'", $report);
        $this->assertStringContainsString("object-src 'none'", $report);
        $this->assertStringNotContainsString('unsafe-inline', $report);
        $this->assertStringNotContainsString('unsafe-eval', $report);
    }

    public function testInvalidConfigurationFailsBeforeHeadersAreApplied(): void
    {
        $cases = [
            [SeablastConstant::SB_CSP_MODE, 'invalid'],
            [SeablastConstant::SB_CACHE_CONTROL, "no-store\r\nX-Injected: yes"],
            [SeablastConstant::SB_CACHE_CONTROL, ''],
            [SeablastConstant::SB_REFERRER_POLICY, "no-referrer\0"],
            [SeablastConstant::SB_CSP_REPORT_URI, '//attacker.example/report'],
            [SeablastConstant::SB_CSP_REPORT_URI, '/report; script-src *'],
        ];
        foreach ($cases as $case) {
            $configuration = new SeablastConfiguration();
            $configuration->setString($case[0], $case[1]);
            try {
                new SeablastSecurityHeaders($configuration);
                $this->fail('Invalid setting accepted: ' . $case[0]);
            } catch (SeablastConfigurationException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        foreach ([[], ["'none'", "'self'"], ['https://example.com;'], ["https://example.com\r\n"]] as $frames) {
            $configuration = new SeablastConfiguration();
            $configuration->setArrayString(SeablastConstant::SB_FRAME_ANCESTORS, $frames);
            try {
                new SeablastSecurityHeaders($configuration);
                $this->fail('Invalid framing accepted.');
            } catch (SeablastConfigurationException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        foreach (
            [
            'frame-ancestors' => ["'self'"],
            'script-src;' => ["'self'"],
            'script-src' => ["'self';", "'nonce-fixed'"],
            'style-src' => ["'none'", "'self'"],
            'img-src' => ["https://example.com\t"],
            ] as $name => $values
        ) {
            $configuration = new SeablastConfiguration();
            $configuration->setArrayArrayString(SeablastConstant::SB_CSP_DIRECTIVES, $name, $values);
            try {
                new SeablastSecurityHeaders($configuration);
                $this->fail('Invalid CSP accepted.');
            } catch (SeablastConfigurationException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testModesAndExplicitOverrides(): void
    {
        foreach (['report-only', 'enforce', 'off'] as $mode) {
            $configuration = new SeablastConfiguration();
            $configuration->setString(SeablastConstant::SB_CSP_MODE, $mode);
            $configuration->setString(SeablastConstant::SB_CACHE_CONTROL, 'private, max-age=0');
            $configuration->setString(SeablastConstant::SB_REFERRER_POLICY, 'no-referrer');
            $configuration->setArrayString(SeablastConstant::SB_FRAME_ANCESTORS, ["'none'"]);
            $configuration->setString(SeablastConstant::SB_CSP_REPORT_URI, '/csp-report');
            $configuration->setArrayArrayString(SeablastConstant::SB_CSP_DIRECTIVES, 'connect-src', [
                "'self'", 'https://api.example.com',
            ]);
            $headers = $configuration->securityHeaders()->getHeaders();
            $this->assertSame('DENY', $headers['X-Frame-Options']);
            $this->assertSame('no-referrer', $headers['Referrer-Policy']);
            $this->assertSame('private, max-age=0', $headers['Cache-Control']);
            $this->assertStringContainsString("frame-ancestors 'none'", $headers['Content-Security-Policy']);
            $this->assertSame($mode === 'report-only', isset($headers['Content-Security-Policy-Report-Only']));
            if ($mode !== 'off') {
                $key = $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
                $this->assertStringContainsString('report-uri /csp-report', $headers[$key]);
                $this->assertStringContainsString("connect-src 'self' https://api.example.com", $headers[$key]);
            }
        }
        $configuration = new SeablastConfiguration();
        $configuration->setArrayString(SeablastConstant::SB_FRAME_ANCESTORS, ['https://embed.example.com:8443']);
        $headers = $configuration->securityHeaders()->getHeaders();
        $this->assertArrayNotHasKey('X-Frame-Options', $headers);
        $this->assertSame('frame-ancestors https://embed.example.com:8443', $headers['Content-Security-Policy']);
    }
}
