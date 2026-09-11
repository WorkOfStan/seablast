<?php

declare(strict_types=1);

namespace Seablast\Seablast\Apis;

use Seablast\Seablast\Apis\GenericRestApiJsonModel;
use Seablast\Seablast\ClientErrorRateLimiter;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastRequestContext;
use stdClass;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Log errors reported by Ajax saved to the app error log with these information:
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
          'model' => '\Seablast\Seablast\Apis\ApiErrorModel',
      ]
  )
 *
 * JavaScript client
    let errorCount = 0;
    function errorLog(message, severity = 'error') {
        const stringifiedData = JSON.stringify({
            csrfToken: csrfToken,
            message: message,
            severity: severity,
            order: ++errorCount,
            page: window.location.href
        });
        console.error(message);
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

    protected const JSON_INPUT_MAX_BYTES = 16384;

    protected function beforeInput(): bool
    {
        if (!$this->configuration->flag->status(SeablastConstant::FLAG_CLIENT_ERROR_LOGGING)) {
            $this->rejectInput(403, 'Client error logging is disabled.');
            return false;
        }
        if (($this->superglobals->server['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            $this->rejectInput(405, 'Method not allowed.');
            return false;
        }
        $context = new SeablastRequestContext($this->configuration, $this->superglobals->server);
        $limit = (new ClientErrorRateLimiter($this->configuration))->consume($context->getClientIp());
        if ($limit['status'] !== 200) {
            if ($limit['retryAfter'] > 0) {
                header('Retry-After: ' . $limit['retryAfter']);
            }
            $this->rejectInput($limit['status'], $limit['status'] === 429
                ? 'Too many client error reports.' : 'Client error logging is temporarily unavailable.');
            return false;
        }
        return true;
    }

    protected function inputDiagnosticsEnabled(): bool
    {
        return false;
    }

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
        return parent::knowledge();
    }

    /**
     * Log the input.
     *
     * @return void
     * @throws \Exception
     */
    private function executeBusinessLogic(): void
    {
        // Mapping of text severity -> Tracy\ILogger constant
        $severityMap = [
            'DEBUG' => ILogger::DEBUG,
            'INFO' => ILogger::INFO,
            'WARNING' => ILogger::WARNING,
            'ERROR' => ILogger::ERROR,
            'EXCEPTION' => ILogger::ERROR,
            'CRITICAL' => ILogger::ERROR,
        ];

        if (!isset($this->data->message) || !is_string($this->data->message) || $this->data->message === '') {
            $this->rejectInput(400, 'A nonempty message string is required.');
            return;
        }
        $page = property_exists($this->data, 'page') ? $this->data->page : 'unknown-page';
        $severity = property_exists($this->data, 'severity') ? $this->data->severity : 'ERROR';
        if (!is_string($page) || !is_string($severity)) {
            $this->rejectInput(400, 'Page and severity must be strings.');
            return;
        }
        if (strlen($this->data->message) > 4096 || strlen($page) > 2048) {
            $this->rejectInput(413, 'Client error field exceeds the maximum allowed size.');
            return;
        }
        $severity = strtoupper($severity);
        if (!isset($severityMap[$severity])) {
            $this->rejectInput(400, 'Unknown client error severity.');
            return;
        }
        $record = ['page' => $page, 'severity' => $severity, 'message' => $this->data->message];
        if (property_exists($this->data, 'order')) {
            $order = $this->data->order;
            if (!is_int($order) || $order < 1 || $order > 2147483647) {
                $this->rejectInput(400, 'Order must be a positive 32-bit integer.');
                return;
            }
            $record['order'] = $order;
        }
        // Default JSON escaping also neutralizes Unicode line separators, controls, and delimiter text.
        $encoded = json_encode($record);
        if (!is_string($encoded)) {
            $this->rejectInput(400, 'Invalid client error text.');
            return;
        }
        $message = 'client_error ' . $encoded;
        if (strlen($message) > 8192) {
            $this->rejectInput(413, 'Client error record exceeds the maximum allowed size.');
            return;
        }
        Debugger::log($message, $severityMap[$severity]);
        $this->message = 'Error logged.';
    }
}
