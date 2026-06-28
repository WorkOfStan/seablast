<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use PHPUnit\Framework\TestCase;

class TemplateSecurityTest extends TestCase
{
    public function testExternalScriptTagsUseSriAndAnonymousCors(): void
    {
        $templateDirectory = __DIR__ . '/../views';
        $templateFiles = glob($templateDirectory . '/*.latte');
        $this->assertNotFalse($templateFiles, 'Should be able to list bundled Latte templates.');

        $expectedIntegrityByUrl = [
            'https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js' => 'sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs',
            'https://cdn.jsdelivr.net/gh/e3rd/WebHotkeys@0.9.4/WebHotkeys.js?register' => 'sha384-VtSHOatDaywsjcaoV86liUBBl28v5GV/w+ee6ls5ZXHWpbjiI2QuMc/r+JEYDILa',
        ];
        $externalScriptTagCount = 0;

        foreach ($templateFiles as $templateFile) {
            $template = file_get_contents($templateFile);
            $this->assertNotFalse($template, sprintf('Should be able to read %s.', $templateFile));

            preg_match_all('/<script\b(?=[^>]*\bsrc="(https:\/\/[^"]+)")[^>]*>/i', $template, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                ++$externalScriptTagCount;
                $scriptTag = $match[0];
                $scriptUrl = $match[1];
                $this->assertSame(
                    1,
                    preg_match('/\bintegrity="sha384-[A-Za-z0-9+\/=]+"/', $scriptTag),
                    sprintf('External script tag in %s must use sha384 SRI: %s', basename($templateFile), $scriptTag)
                );
                $this->assertStringContainsString(
                    'crossorigin="anonymous"',
                    $scriptTag,
                    sprintf('External script tag in %s must use anonymous CORS: %s', basename($templateFile), $scriptTag)
                );
                if (array_key_exists($scriptUrl, $expectedIntegrityByUrl)) {
                    $this->assertStringContainsString(
                        sprintf('integrity="%s"', $expectedIntegrityByUrl[$scriptUrl]),
                        $scriptTag,
                        sprintf('External script tag in %s must use the expected SRI hash: %s', basename($templateFile), $scriptTag)
                    );
                }
            }
        }

        $this->assertGreaterThan(0, $externalScriptTagCount, 'Should find bundled external script tags to validate.');
    }
}
