<?php

declare(strict_types=1);

namespace Seablast\Seablast\Api;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;
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
    /** @var string API response message */
    protected $message = 'Input ready for processing.';
    /** @var int HTTP status to be used in response */
    protected $status = 200;
    /** @var Superglobals */
    protected $superglobals;

    /**
     *
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     * @throws \Exception
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
            'API call without REQUEST_METHOD will not work'
        );
        Debugger::barDump($this->superglobals, 'Superglobals for API'); // TODO add such barDump somewhere before this
        $this->processInput();
    }

    /**
     * If the returned status >= 400, then it doesn't make sense to process anymore,
     * but return the same status to SeablastView
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        // todo move this example to SBdist
        /*
          return (object) [
          'status' => $this->status,
          'rest' => (object) [
          'message' => 'Calculation completed successfully',
          'result' => $this->businessLogicResult
          ]
          ];
         *
         */
        // if ($this->status < 400) {$this->executeBusinessLogic();} // TODO move to SBdist
        return (object) [
                'status' => $this->status,
                'rest' => (object) [
                    'message' => $this->message,
                ]
        ];
    }

    /**
     * Validates standard PHP input
     * Doesn't output anything, even errors, because output is done by SeablastView which handles Tracy bar etc.
     * @return void
     */
    private function processInput(): void
    {
        // Read JSON from standard input
        $jsonInput = file_get_contents('php://input');
        if (!is_string($jsonInput)) {
            Debugger::barDump(["php://input doesn't contain string", $jsonInput], 'ERROR on input');
            Debugger::log("php://input doesn't contain string", ILogger::ERROR);
            $this->status = 400; // Bad Request
            $this->message = 'Invalid input';
            return;
        }
        $jsonDecoded = json_decode($jsonInput);
        Debugger::barDump($jsonDecoded, 'data json_decoded from php://input');
        // Validate JSON input
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->status = 400; // Bad Request
            $this->message = 'Invalid JSON input'; // TODO be more specific by https://www.php.net/json_last_error
            return;
        }
        if (!is_object($jsonDecoded)) { // maybe this is redundant vs json_last_error above
            Debugger::barDump("Decoded JSON doesn't translate to an object", 'ERROR on input');
            Debugger::log("Decoded JSON doesn't translate to an object", ILogger::ERROR);
            $this->status = 400; // Bad Request
            $this->message = 'Invalid JSON decoding';
            return;
        }
        $this->data = $jsonDecoded;
        Assert::object($this->data); // just to read the property, it will be used in a child class
        // Note: Access the data like this
        //$userInput = $this->data->userInput;
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
