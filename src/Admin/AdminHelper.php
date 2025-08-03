<?php

declare(strict_types=1);

namespace WorkOfStan\Protokronika\Models;

use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\Superglobals;
use Tracy\Debugger;

/**
 * Helper methods for AdminModel, TableViewModel and ApiTableUpdateModel
 *
 * (TODO Move to Seablast)
 */
class AdminHelper
{
    use \Nette\SmartObject;

    /** @var array<string> */ // TODO since 8.1: public readonly
    public $allowedTables;
    /** @var SeablastConfiguration */
    private $configuration;
    /** @var Superglobals */
    private $superglobals;

    /**
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->configuration = $configuration;
        $this->superglobals = $superglobals;
    }

    /**
     * Populates 'App:selected-table' by the allowed selected table
     *
     * TODO replace 'App:selected-table' by SeablastConstant
     *
     * @return void
     */
    public function populateSelectedTable(): void
    {
        // Admin sees both Admin and Content tables; Content admin sees just the Content
        $this->allowedTables = $this->getAllowedTables();

        // if there's a requested table and the user has access to it, populate string 'App:selected-table'
        if (
            is_string($this->superglobals->get['t']) && in_array($this->superglobals->get['t'], $this->allowedTables)
        ) {
            $this->configuration->setString('App:selected-table', $this->superglobals->get['t']);
        }
    }

    /**
     * Returns list of tables that the particular user can see and/or edit.
     *
     * @return array<string>
     */
    private function getAllowedTables(): array
    {
        return array_merge(
            in_array(
                $this->configuration->getInt(SeablastConstant::USER_ROLE_ID),
                [1, 2]
            ) ? $this->configuration->getArrayString(
                SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_EDITOR
            ) : [],
            in_array(
                $this->configuration->getInt(SeablastConstant::USER_ROLE_ID),
                [1]
            ) ? $this->configuration->getArrayString(
                // TODO get rid of USER_ROLE_ADMIN _EDITOR etc in favor of integer id ?
                SeablastConstant::ADMIN_TABLE_VIEW . SeablastConstant::USER_ROLE_ADMIN
            ) : []
        );
    }

    /**
     * Returns array of pairs 'COLUMN_NAME' => 'DATA_TYPE'.
     *
     * @param string $tableName
     * @return array<string>
     */
    public function columnTypes(string $tableName): array
    {
        // Fetch column types
        $stmt = $this->configuration->mysqli()->prepareStrict(
            "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()"
            . " AND TABLE_NAME = ?"
        );
        $tableNameParam = $this->configuration->dbmsTablePrefix() . $tableName;
        $stmt->bind_param('s', $tableNameParam);
        $stmt->execute();

        // Retrieve the result set as a mysqli_result object.
        $result = $stmt->get_result();
        if ($result === false) {
            throw new DbmsException('Stmt get_result failed');
        }

        $columnTypes = [];
        while ($row = $result->fetch_object()) {
            $columnTypes[$row->COLUMN_NAME] = (string) $row->DATA_TYPE;
        }
        $result->free();
        $stmt->close();
        Debugger::barDump($columnTypes, 'columnTypes');
        return $columnTypes;
    }

    /**
     * Combine allowed columns for an admin according to their role.
     *
     * @return array<array<string>>
     */
    public function getAllowedColumns(): array
    {
        // todo if 'App:selected-table' not defined, try to get it and if not possible throw \Exception
        $cols = [];
        // todo refactor by recursion or smaller method
        foreach ([SeablastConstant::ADMIN_TABLE_VIEW, SeablastConstant::ADMIN_TABLE_EDIT] as $accessRightsTypes) {
            if (
                in_array(
                    $this->configuration->getInt(SeablastConstant::USER_ROLE_ID),
                    [1, 2]
                ) && $this->configuration->exists($accessRightsTypes . SeablastConstant::USER_ROLE_EDITOR)
            ) {
                $arr = $this->configuration->getArrayArrayString(
                    $accessRightsTypes . SeablastConstant::USER_ROLE_EDITOR
                );
                if (array_key_exists($this->configuration->getString('App:selected-table'), $arr)) {
                    $colsContent = [
                        (($accessRightsTypes === SeablastConstant::ADMIN_TABLE_VIEW) ? 'view' : 'edit') =>
                        $arr[$this->configuration->getString('App:selected-table')]
                    ];
                    $cols = array_merge($cols, $colsContent);
                }
            }
            if (
                in_array(
                    $this->configuration->getInt(SeablastConstant::USER_ROLE_ID),
                    [1]
                ) && $this->configuration->exists($accessRightsTypes . SeablastConstant::USER_ROLE_ADMIN)
            ) {
                $arr = $this->configuration->getArrayArrayString(
                    $accessRightsTypes . SeablastConstant::USER_ROLE_ADMIN
                );
                if (array_key_exists($this->configuration->getString('App:selected-table'), $arr)) {
                    $colsAdmin = [
                        (($accessRightsTypes === SeablastConstant::ADMIN_TABLE_VIEW) ? 'view' : 'edit') =>
                        $arr[$this->configuration->getString('App:selected-table')]
                    ];
                    $cols = array_merge($cols, $colsAdmin);
                }
            }
        }
        return $cols;
    }
}
