<?php

declare(strict_types=1);

namespace Seablast\Seablast\Admin;

use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\Superglobals;
use Tracy\Debugger;
use Webmozart\Assert\Assert;

/**
 * Helper methods for AdminModel, TableViewModel and ApiTableUpdateModel
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
     * Populates SeablastConstant::APP_SELECTED_TABLE by the allowed selected table
     *
     * @return void
     */
    public function populateSelectedTable(): void
    {
        // Admin sees both Admin and Content tables; Content admin sees just the Content
        $this->allowedTables = $this->getAllowedTables(SeablastConstant::ADMIN_TABLE_VIEW);

        // if there's a requested table and the user has access to it,
        // populate string SeablastConstant::APP_SELECTED_TABLE
        if (
            is_string($this->superglobals->get['t']) && in_array($this->superglobals->get['t'], $this->allowedTables)
        ) {
            $this->configuration->setString(SeablastConstant::APP_SELECTED_TABLE, $this->superglobals->get['t']);
        }
    }

    /**
     * Returns a list of tables that the current user can view, edit, or otherwise has permissions for.
     *
     * @param string $permission E.g. SeablastConstant::ADMIN_TABLE_VIEW, SeablastConstant::ADMIN_TABLE_DELETE_ROW
     * @return array<string>
     */
    public function getAllowedTables(string $permission): array
    {
        // suffixes that are allowed for that USER_ROLE_ID
        $roleSuffixes = [
            1 => [
                SeablastConstant::USER_ROLE_EDITOR,
                SeablastConstant::USER_ROLE_ADMIN,
            ],
            2 => [
                SeablastConstant::USER_ROLE_EDITOR,
            ],
        ];

        $tables = [];

        foreach ($roleSuffixes[$this->configuration->getInt(SeablastConstant::USER_ROLE_ID)] as $suffix) {
            if ($this->configuration->exists($permission . $suffix)) {
                $tables = array_merge($tables, $this->configuration->getArrayString($permission . $suffix));
            }
        }

        return $tables;
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
            Assert::scalar($row->DATA_TYPE);
            Assert::string($row->COLUMN_NAME);
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
        // todo if SeablastConstant::APP_SELECTED_TABLE not defined, try to get it and if not possible throw \Exception
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
                if (array_key_exists($this->configuration->getString(SeablastConstant::APP_SELECTED_TABLE), $arr)) {
                    $colsContent = [
                        (($accessRightsTypes === SeablastConstant::ADMIN_TABLE_VIEW) ? 'view' : 'edit') =>
                        $arr[$this->configuration->getString(SeablastConstant::APP_SELECTED_TABLE)]
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
                if (array_key_exists($this->configuration->getString(SeablastConstant::APP_SELECTED_TABLE), $arr)) {
                    $colsAdmin = [
                        (($accessRightsTypes === SeablastConstant::ADMIN_TABLE_VIEW) ? 'view' : 'edit') =>
                        $arr[$this->configuration->getString(SeablastConstant::APP_SELECTED_TABLE)]
                    ];
                    $cols = array_merge($cols, $colsAdmin);
                }
            }
        }
        return $cols;
    }
}
