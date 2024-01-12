<?php

declare(strict_types=1);

namespace Seablast\Seablast\Api;

use Seablast\Seablast\Api\GenericRestApiJsonModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;
use Webmozart\Assert\Assert;

/**
 * Log errors reported by AJAX saved to the standard error log
 * - message
 * - severity (default=error)
 * - order of ajax call from one script
 * - page name that invoked the call
 *
 * Usage:
 * ->setArrayArrayString(
 *     SeablastConstant::APP_MAPPING,
 *     '/api/error',
 *     [
 *         'model' => '\Seablast\Seablast\Api\ApiErrorModel',
 *     ]
 * )
 *
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
        //Assert::propertyExists($result, 'rest');
        $result->rest->message = $this->message; // TODO nepřemaže to jen ok výsledek z parent??
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
            return;
        }
        throw new \Exception('Unexpected HTTP method');
    }
}
