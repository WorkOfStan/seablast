<?php

declare(strict_types=1);

namespace Seablast\Seablast\Admin;

use Seablast\Seablast\Apis\GenericRestApiJsonModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;

/**
 * Update editable fields in database
 *
 * (TODO Move to Seablast)
 * // todo DELETE via this API
 * todo use route: /api/table
 */
class ApiTableUpdateModel extends GenericRestApiJsonModel
{
    use \Nette\SmartObject;

    /** @var AdminHelper */
    private $adminHelper;

    /**
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        parent::__construct($configuration, $superglobals);

        $this->adminHelper = new AdminHelper($this->configuration, $this->superglobals);
        $this->adminHelper->populateSelectedTable();
    }

    /**
     * Collection of items to be displayed.
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

        $cols = $this->adminHelper->getAllowedColumns();
        Debugger::barDump($cols, 'cols');
        $columns = array_merge($cols['view'] ?? [], $cols['edit'] ?? []);
        Debugger::barDump($columns, 'columns');
        // Validate input
        if (
            empty($cols['edit'] ?? []) || !in_array($this->superglobals->get['key'], $cols['edit']) //
            || !isset($this->superglobals->get['id']) || !is_scalar($this->superglobals->get['id']) //
            || (int) $this->superglobals->get['id'] === 0 || !isset($this->data->val)
        ) {
            return self::response(403, 'Nothing to edit.');
        }
        $this->data->val = trim($this->data->val); // no reason to store empty lines or other whitespace around content
        $column = $this->configuration->mysqli()->real_escape_string($this->superglobals->get['key']);
        $columnTypes = $this->adminHelper->columnTypes($this->configuration->getString('App:selected-table'));
        if (
            (
                in_array(
                    $columnTypes[$column],
                    ['int', 'bigint', 'smallint', 'tinyint']
                ) && (string) (int) $this->data->val !== $this->data->val
            ) || (
                in_array(
                    $columnTypes[$column],
                    ['float', 'double', 'decimal']
                ) && !is_numeric($this->data->val)
            ) || !in_array(
                $columnTypes[$column],
                [
                    // all accepted types
                    'int', 'bigint', 'smallint', 'tinyint',
                    'float', 'double', 'decimal',
                    'char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext',
                    'timestamp', // comparing timestamp as pattern is not optimal //todo improve
                ] // $this->data->val is always a string
            )
        ) {
            return self::response(400, 'Input is of different type than expected.');
        }

        $id = (int) $this->superglobals->get['id']; // it's non-zero as checked above
        // Update value
        //UPDATE `app_items` SET `active` = '0' WHERE `app_items`.`id` = 2;
        // todo SQL statement would be safer
        $mysqliResult = $this->configuration->mysqli()->query(
            'UPDATE `' . $this->configuration->dbmsTablePrefix() . $this->configuration->getString('App:selected-table')
            . '` SET `' . $column . "` = '"
            . $this->configuration->mysqli()->real_escape_string($this->data->val) . "' WHERE `"
            . $this->configuration->dbmsTablePrefix() . $this->configuration->getString('App:selected-table')
            . '`.`id` = ' . $id
        );
        if ($mysqliResult && $this->configuration->mysqli()->warning_count) {
            $mysqliResult = false;
            $warnings = $this->configuration->mysqli()->get_warnings();
            if ($warnings !== false) {
                do {
                    Debugger::barDump("Warning: {$warnings->errno} {$warnings->message}", "mysqli warning");
                } while ($warnings->next());
            }
        }
        return ($mysqliResult === false) ? self::response(500, 'UPDATE failed.') : self::response(200, 'UPDATE ok.');
    }
}
