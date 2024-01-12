<?php

declare(strict_types=1);

namespace Seablast\Seablast\Api;

use Seablast\Seablast\Api\GenericRestApiJsonModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;
use stdClass;

/**
 * Log errors reported by Ajax saved to the standard error log
 * - message
 * - severity (default=error)
 * - order of ajax call from one script
 * - page name that invoked the call
 *
 * Usage:
 * conf/app.conf.php
  ->setArrayArrayString(
      SeablastConstant::APP_MAPPING,
      '/api/error',
      [
          'model' => '\Seablast\Seablast\Api\ApiErrorModel',
      ]
  )
 *
 * JavaScript client
    let errorCount = 0;
    function errorLog(message, severity = 'error') {
        const stringifiedData = JSON.stringify({
            message: message,
            severity: severity,
            order: ++errorCount,
            page: window.location.href
        });
        console.log(stringifiedData);
        $.ajax({
            url: './api/error',
            type: 'POST',
            contentType: 'application/json',
            data: stringifiedData,
            dataType: 'json', // Expecting JSON response
            success: function(response) {
                console.log('Error sent successfully to be logged');
                console.log(response);
            },
            error: function(xhr, status, error) {
                console.error('Error sending data of error: ' + errorCount, error);
                addBanner('Error sending data ' + error, 'warning');
            }
        });
    }
 */
class ApiErrorModel extends GenericRestApiJsonModel
{
    use \Nette\SmartObject;

    /**
     *
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     * @throws \Exception
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        parent::__construct($configuration, $superglobals);
    }

    /**
     * Return the knowledge calculated in this model.
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        $result = parent::knowledge();
        if ($result->status >= 400) {
            // error state means that further execution isn't reasonable
            return $result;
        }
        $this->executeBusinessLogic();
        $result->rest->message = $this->message;
        return $result;
    }

    /**
     * Log the input
     * @return void
     * @throws \Exception
     */
    private function executeBusinessLogic(): void
    {
        if ($this->superglobals->server['REQUEST_METHOD'] === 'POST') {
            // todo log better - into log folder
            error_log(($this->data->page ?? 'unknown-page') . ' ' . ($this->data->order ?? '-') . ' '
                . ($this->data->severity ?? 'error') . ' ' . ($this->data->message ?? '(missing message)'));
            $this->message = 'Error logged.';
            return;
        }
        throw new \Exception('Unexpected HTTP method');
    }
}
