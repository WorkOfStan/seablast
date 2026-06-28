<?php

declare(strict_types=1);

namespace Seablast\Seablast\Tests;

use PHPUnit\Framework\TestCase;
use Seablast\Seablast\Apis\GenericRestApiJsonModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;

class GenericRestApiJsonModelTest extends TestCase
{
    private const JSON_INPUT_MAX_BYTES = 1048576;

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('APP_DIR')) {
            define('APP_DIR', __DIR__ . '/..');
            Debugger::enable(Debugger::DEVELOPMENT, APP_DIR . '/log');
        }
    }

    public function testInjectedJsonUnderLimitReachesCsrfValidation(): void
    {
        $knowledge = $this->knowledgeForInjectedJson('{}');

        $this->assertRestResponse($knowledge, 401, 'CSRF token missing');
    }

    public function testInjectedJsonOverLimitReturnsPayloadTooLarge(): void
    {
        $knowledge = $this->knowledgeForInjectedJson(str_repeat(' ', self::JSON_INPUT_MAX_BYTES + 1));

        $this->assertRestResponse($knowledge, 413, 'JSON input exceeds the maximum allowed size.');
    }

    public function testUnsupportedContentTypeReturnsUnsupportedMediaType(): void
    {
        $knowledge = $this->knowledgeForServer([
            'CONTENT_TYPE' => 'text/plain',
        ]);

        $this->assertRestResponse($knowledge, 415, 'Unsupported content type.');
    }

    public function testMissingContentTypeReturnsUnsupportedMediaType(): void
    {
        $knowledge = $this->knowledgeForServer();

        $this->assertRestResponse($knowledge, 415, 'Unsupported content type.');
    }

    public function testStructuredJsonContentTypeWithParametersIsAccepted(): void
    {
        $knowledge = $this->knowledgeForServer([
            'CONTENT_TYPE' => 'application/problem+json; charset=utf-8',
        ]);

        $this->assertRestResponse($knowledge, 400, 'Syntax error');
    }

    public function testContentLengthOverLimitReturnsPayloadTooLarge(): void
    {
        $knowledge = $this->knowledgeForServer([
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => (string) (self::JSON_INPUT_MAX_BYTES + 1),
        ]);

        $this->assertRestResponse($knowledge, 413, 'JSON input exceeds the maximum allowed size.');
    }

    public function testMalformedJsonUnderLimitKeepsExistingParseError(): void
    {
        $knowledge = $this->knowledgeForInjectedJson('{');

        $this->assertRestResponse($knowledge, 400, 'Syntax error');
    }

    private function assertRestResponse(stdClass $knowledge, int $httpCode, string $message): void
    {
        $this->assertSame($httpCode, $knowledge->httpCode);
        $rest = $knowledge->rest;
        $this->assertInstanceOf(stdClass::class, $rest);
        $this->assertSame($message, $rest->message);
    }

    private function knowledgeForInjectedJson(string $jsonInput): stdClass
    {
        $configuration = new SeablastConfiguration();
        $configuration->setString(SeablastConstant::JSON_INPUT, $jsonInput);

        $model = new GenericRestApiJsonModel(
            $configuration,
            new Superglobals([], [], ['REQUEST_METHOD' => 'POST'])
        );

        return $model->knowledge();
    }

    /**
     * @param array<string, string> $server
     */
    private function knowledgeForServer(array $server = []): stdClass
    {
        $model = new GenericRestApiJsonModel(
            new SeablastConfiguration(),
            new Superglobals([], [], array_merge(['REQUEST_METHOD' => 'POST'], $server))
        );

        return $model->knowledge();
    }
}
