<?php

declare(strict_types=1);

namespace Seablast\Seablast\Apis;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Tracy\Debugger;
use Tracy\ILogger;
use Webmozart\Assert\Assert;

/**
 * Template API that validates JSON received from standard input php://input
 * TODO move executeBusinessLogic() and related stuff to SBdist
 */
class GenericRestApiJsonModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

    /** @ var array<mixed> Resulting knowledge. */
    //todo move to SBdist//private $businessLogicResult;

    /** @var SeablastConfiguration */
    protected $configuration;
    /** @var object input JSON transformed to the data */
    protected $data;
    /** @var int HTTP status to be used as a default response */
    protected $httpCode = 200;
    /** @var string API response message */
    protected $message = 'Input ready for processing.';
    /** @var Superglobals */
    protected $superglobals;

    /**
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->configuration = $configuration;
        $this->configuration->flag->status(SeablastConstant::FLAG_WEB_RUNNING); // so that $configuration is read
        $this->superglobals = $superglobals;
        Assert::propertyExists($this->superglobals, 'server');
        Assert::keyExists(
            $this->superglobals->server,
            'REQUEST_METHOD',
            'API call to ' . get_called_class() . ' without REQUEST_METHOD will not work'
        );
        Debugger::barDump($this->superglobals, 'Superglobals for API'); // TODO add such barDump somewhere before this
        $this->processInput();
    }

    /**
     * If the returned status >= 400, then it doesn't make sense to process anymore,
     * but return the same status to SeablastView.
     *
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        // todo move this example to SBdist
        /*
          return (object) [
          'httpCode' => $this->httpCode,
          'rest' => (object) [
          'message' => 'Calculation completed successfully',
          'result' => $this->businessLogicResult
          ]
          ];
         *
         */
        // if ($this->status < 400) {$this->executeBusinessLogic();} // TODO move to SBdist
        return self::response($this->httpCode, $this->message);
    }

    /**
     * Validates standard PHP input.
     *
     * Doesn't output anything, even errors, because output is done by SeablastView which handles Tracy bar etc.
     *
     * @return void
     */
    private function processInput(): void
    {
        // Read JSON from standard input if not pre-prepared
        $jsonInput = $this->configuration->exists(SeablastConstant::JSON_INPUT)
            ? $this->configuration->getString(SeablastConstant::JSON_INPUT)
            : file_get_contents('php://input');
        if (!is_string($jsonInput)) {
            Debugger::barDump(["Either JSON_INPUT or php://input isn't string", $jsonInput], 'ERROR on input');
            Debugger::log("Either JSON_INPUT or php://input isn't string", ILogger::ERROR);
            $this->httpCode = 400; // Bad Request
            $this->message = 'Invalid input';
            return;
        }
        $jsonDecoded = json_decode($jsonInput);
        Debugger::barDump($jsonDecoded, 'data json_decoded from php://input');
        // Validate JSON input
        if (json_last_error() !== JSON_ERROR_NONE) {
            // According to https://www.php.net/json_last_error
            switch (json_last_error()) {
                case JSON_ERROR_CTRL_CHAR: $err = 'Unexpected control character';
                    break;
                case JSON_ERROR_DEPTH: $err = 'Maximum stack depth exceeded';
                    break;
                case JSON_ERROR_INF_OR_NAN: $err = 'One or more NAN or INF values in the value to be encoded';
                    break;
                case JSON_ERROR_INVALID_PROPERTY_NAME: $err = 'A property name that cannot be encoded was given';
                    break;
                case JSON_ERROR_RECURSION: $err = 'One or more recursive references in the value to be encoded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:$err = 'Underflow or mismatch';
                    break;
                case JSON_ERROR_SYNTAX: $err = 'Syntax error';
                    break;
                case JSON_ERROR_UNSUPPORTED_TYPE: $err = 'A value of a type that cannot be encoded was given';
                    break;
                case JSON_ERROR_UTF16: $err = 'Malformed UTF-16 characters, possibly incorrectly encoded';
                    break;
                case JSON_ERROR_UTF8: $err = 'Malformed UTF-8';
                    break;
                default: $err = 'Unknown JSON error (int)' . json_last_error();
                    break;
            }
            $this->httpCode = 400; // Bad Request
            $this->message = $err;
            return;
        } elseif (!is_object($jsonDecoded)) { // maybe this is redundant vs json_last_error above
            Debugger::barDump("Decoded JSON doesn't translate to an object", 'ERROR on input');
            Debugger::log("Decoded JSON doesn't translate to an object", ILogger::ERROR);
            $this->httpCode = 400; // Bad Request
            $this->message = 'Invalid JSON decoding';
            return;
        }
        $this->data = $jsonDecoded;
        if (!isset($this->data->csrfToken)) {
            Debugger::barDump("CSRF token missing", 'ERROR on input');
            Debugger::log("CSRF token missing", ILogger::ERROR);
            $this->httpCode = 401; // Unauthorized
            $this->message = 'CSRF token missing';
            return;
        }
        // CSRF validation
        $csrfToken = new CsrfToken('sb_json', (string) $this->data->csrfToken);
        $csrfTokenManager = new CsrfTokenManager();
        if (!$csrfTokenManager->isTokenValid($csrfToken)) {
            Debugger::barDump("CSRF token mismatch", 'ERROR on input');
            Debugger::log("CSRF token mismatch", ILogger::ERROR);
            $this->httpCode = 401; // Unauthorized
            $this->message = 'CSRF token mismatch';
            return;
        }
    }

    /**
     * Simple API response.
     *
     * @param int $httpCode
     * @param string $message
     * @return stdClass
     */
    protected static function response(int $httpCode, string $message): stdClass
    {
        return (object) [
                'httpCode' => $httpCode,
                'rest' => (object) [
                    'message' => $message,
                ]
        ];
    }

    // todo example to be used in SBdist
    /* private function executeBusinessLogic()
      {
      if (!is_array($this->data)) {
      http_response_code(400); // Bad Request
      exit(json_encode(['status' => 400, 'rest' => ['message' => 'Input is not a valid array of numbers']]));
      }

      $sum = array_sum($this->data);
      $min = min($this->data);
      $max = max($this->data);
      $count = count($this->data);
      $average = $count > 0 ? $sum / $count : 0;

      $this->businessLogicResult = [
      'sum' => $sum,
      'min' => $min,
      'max' => $max,
      'count' => $count,
      'average' => $average
      ];
      }
     *
     */
}
