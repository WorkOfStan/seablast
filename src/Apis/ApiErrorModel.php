<?php

declare(strict_types=1);

namespace Seablast\Seablast\Apis;

use Seablast\Seablast\Apis\GenericRestApiJsonModel;
use stdClass;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Log errors reported by Ajax saved to the app error log with these informations:
 * - page name that invoked the call
 * - order of ajax call from one script
 * - severity accepted values (case insensitive): DEBUG, INFO, WARNING, ERROR, EXCEPTION, CRITICAL (default=ERROR)
 * - message
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
     * Return the knowledge calculated in this model.
     *
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        $result = parent::knowledge();
        if ($result->httpCode >= 400) {
            // Error state means that further processing is not desired
            return $result;
        }
        $this->executeBusinessLogic();
        $result->rest->message = $this->message;
        return $result;
    }

    /**
     * Log the input.
     *
     * @return void
     * @throws \Exception
     */
    private function executeBusinessLogic(): void
    {
        if ($this->superglobals->server['REQUEST_METHOD'] !== 'POST') {
            throw new \Exception('Unexpected HTTP method');
        }

    // Mapping of text severity -> Tracy\ILogger constant
        $severityMap = [
        'DEBUG'     => ILogger::DEBUG,
        'INFO'      => ILogger::INFO,
        'WARNING'   => ILogger::WARNING,
        'ERROR'     => ILogger::ERROR,
        'EXCEPTION' => ILogger::EXCEPTION,
        'CRITICAL'  => ILogger::CRITICAL,
        ];

        $inputSeverity = strtoupper((string) ($this->data->severity ?? 'ERROR'));
        $severity = $severityMap[$inputSeverity] ?? ILogger::ERROR;

        $message = sprintf(
            '%s %s %s %s',
            $this->data->page ?? 'unknown-page',
            $this->data->order ?? '-',
            $inputSeverity,
            $this->data->message ?? '(missing message)'
        );

        Debugger::log($message, $severity);

        $this->message = 'Error logged.';
    }
}
