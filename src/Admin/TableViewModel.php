<?php

declare(strict_types=1);

namespace Seablast\Seablast\Admin;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;
use Webmozart\Assert\Assert;

/**
 * Retrieve items from database.
 *
 * // GET:
 * // SELECT * ... explicitně uvést * v konfiguraci kvůli security; také vyjmenovat editable fields
 * // FROM ___ ... conf table
 * // WHERE xy ... conf filter
 * // ORDER BY id/timestamp DESC ... defaultně id nebo conf
 * // LIMIT offset ... zacit 1-20
 * // todo podporovat číselníky, tedy vazby mezi tabulkama
 * //
 * // POST a DELETE přes API (todo CSRF) TableUpdateApi /api/table
 * // UPDATE jen pokud editable field
 */
class TableViewModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

    /** @var AdminHelper */
    private $adminHelper;
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
        $this->adminHelper = new AdminHelper($this->configuration, $this->superglobals);
        $this->adminHelper->populateSelectedTable();
        // TODO be able to change Limit (i.e. paging)
        // TODO Count the possible rows
    }

    /**
     * If there's a pipe, split the string by it.
     *
     * @param string $string
     * @return ?array<string>
     */
    private function splitStringByFirstPipe($string): ?array
    {
        $position = strpos($string, '|');
        if ($position === false) {
            return null;
            // If no pipe character is found, return the original string and an empty string
            // return array($string, "");
        }
        return array(substr($string, 0, $position), substr($string, $position + 1));
    }

    /// compare to             $columnTypes = $this->adminHelper->columnTypes(
    //                $this->configuration->getString(SeablastConstant::APP_SELECTED_TABLE)
    //          );

    /**
     * Get boolean-like columns of a table.
     *
     * @param string $tableName
     * @return array<string, bool>
     */
    private function booleanLikeColumns(string $tableName): array
    {
        $columns = $this->columnTypes($tableName);
        Debugger::barDump($columns, 'column Types new');
        $result = [];

        foreach ($columns as $column) {
            Assert::scalar($column['COLUMN_NAME']);
            $columnName = (string) $column['COLUMN_NAME'];
            $result[$columnName] = !empty($column['IS_BOOLEAN_LIKE']);
        }

        return $result;
    }

    /**
     * Get information about table columns including their SQL type.
     *
     * @param string $tableName
     * @return array<int, array<string, mixed>>
     */
    private function columnTypes(string $tableName): array
    {
        $fullTableName = $this->configuration->dbmsTablePrefix() . $tableName;

        $query = "SELECT
        COLUMN_NAME,
        DATA_TYPE,
        COLUMN_TYPE,
        IS_NULLABLE,
        COLUMN_DEFAULT,
        COLUMN_KEY,
        EXTRA
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = '" . $this->configuration->mysqli()->real_escape_string($fullTableName) . "'
    ORDER BY ORDINAL_POSITION;";

        $result = $this->configuration->mysqli()->query($query);
        $columnTypes = [];

        if ($result && is_object($result)) {
            while ($row = $result->fetch_assoc()) {
                $columnType = isset($row['COLUMN_TYPE']) ? strtolower((string) $row['COLUMN_TYPE']) : '';
                $dataType = isset($row['DATA_TYPE']) ? strtolower((string) $row['DATA_TYPE']) : '';

                $row['IS_BOOLEAN_LIKE'] = (
                    $columnType === 'tinyint(1)' || $columnType === 'tinyint(1) unsigned' || $columnType === 'bit(1)' //
                    || $dataType === 'boolean' || $dataType === 'bool'
                    ) ? 1 : 0;

                $columnTypes[] = $row;
            }

            $result->free();
        }

        return $columnTypes;
    }

    /**
     * Get the foreign keys information.
     *
     * @param string $tableName
     * @return array<array<string, float|int|string|null>>
     */
    private function foreignKeys(string $tableName): array
    {
        //Query to List Foreign Keys in a Specific Table
        $query = "SELECT 
    TABLE_NAME, 
    COLUMN_NAME, 
    CONSTRAINT_NAME, 
    REFERENCED_TABLE_NAME, 
    REFERENCED_COLUMN_NAME
FROM 
    information_schema.KEY_COLUMN_USAGE
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = '" . $this->configuration->dbmsTablePrefix() . $tableName . "';";
        $result = $this->configuration->mysqli()->query($query);
        $columnTypes = [];

        if ($result && is_object($result)) {
            while ($row = $result->fetch_assoc()) {
                $columnTypes[] = $row;
            }
            $result->free();
        }
        return $columnTypes;
    }

    /**
     * Collection of items to be displayed.
     *
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        $cols = $this->adminHelper->getAllowedColumns();
        Debugger::barDump($cols, 'cols');
        $columns = array_merge($cols['view'] ?? [], $cols['edit'] ?? []);
        Debugger::barDump($columns, 'columns');

        // dev
        $foreignKeys = $this->foreignKeys($this->configuration->getString(SeablastConstant::APP_SELECTED_TABLE));
        Debugger::barDump($foreignKeys, 'foreignKeys');
        Debugger::barDump(
            $this->booleanLikeColumns($this->configuration->getString(SeablastConstant::APP_SELECTED_TABLE)),
            'boolean like'
        );
        // Get order and conditions from GET parameters
        $order = isset($this->superglobals->get['order']) ? $this->superglobals->get['order'] : '';
        $conditions = [];
        $conditionDetails = []; // todo move to AdminModel and provide here by configuration
        $sql = '';
        if (isset($this->superglobals->get['condition']) && is_array($this->superglobals->get['condition'])) {
            $conditions = $this->superglobals->get['condition'];
            $columnTypes = $this->adminHelper->columnTypes(
                $this->configuration->getString(SeablastConstant::APP_SELECTED_TABLE)
            );
            Debugger::barDump($columnTypes, 'column Types Existing');
            // TODO equals operator by column type
            // Add conditions and order clauses (this logic can be expanded as needed)
            // Add WHERE clauses based on conditions (this is simplified for the example)
            $whereClauses = [];
            for ($i = 0; $i < count($conditions); $i++) {
                if (!empty($conditions[$i])) {
                    Assert::string($conditions[$i]);
                    $condDetails = $this->splitStringByFirstPipe($conditions[$i]);
                    if (!is_null($condDetails)) {
                        $conditionDetails[] = $condDetails;
                        $column = $columns[$condDetails[0]];
                        // todo maybe SQL statement would be safer?
                        if (
                            in_array(
                                $columnTypes[$column],
                                [
                                    'char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext',
                                    'timestamp', // comparing timestamp as pattern is not optimal //todo improve
                                ]
                            )
                        ) {
                            $whereClauses[] = "`{$column}` "
                                . "LIKE"
                                . " '" . $this->configuration->mysqli()->real_escape_string($condDetails[1]) . "'";
                        } elseif (in_array($columnTypes[$column], ['int', 'bigint', 'smallint', 'tinyint'])) {
                            $whereClauses[] = "`{$column}` "
                                . "= "
                                . (int) $condDetails[1];
                        } elseif (in_array($columnTypes[$column], ['float', 'double', 'decimal'])) {
                            $whereClauses[] = "`{$column}` "
                                . '= '
                                . (float) $condDetails[1];
                        } else {
                            // todo timestamp compare like number but check as string
                            // ignore condition
                        }
                    }
                    // todo the above for text fields; for int fields allow for comparing operations ><=number
                }
            }

            if (!empty($whereClauses)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
            }
        }
        // Add ORDER BY clause
        if (!empty($order) && is_string($order)) {
            $orderParts = explode(',', $order);
            $orderClauses = [];
            foreach ($orderParts as $part) {
                $direction = substr($part, 0, 1) == 'a' ? 'ASC' : 'DESC';
                $columnIndex = substr($part, 1);
                if (isset($columns[$columnIndex])) {
                    $orderClauses[] = ' `' . $columns[$columnIndex] . "` " . $direction;
                }
            }
            if (!empty($orderClauses)) {
                $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
            }
        }

        $fields = implode('`,`', $columns);
        $mysqliResult = $this->configuration->mysqli()->query(
            'SELECT `' . $fields . '` '
            . 'FROM `' . $this->configuration->dbmsTablePrefix()
            . $this->configuration->getString(SeablastConstant::APP_SELECTED_TABLE) . '` '
            . $sql
            // todo allow paging
            . ' LIMIT 0,200'
        );
        $data = [];
        // Fetch each row and add it to the $data array
        if (is_object($mysqliResult)) {
            while ($row = $mysqliResult->fetch_assoc()) {
                Assert::true(is_string($row['id']) || is_int($row['id']));
                $data[$row['id']] = $row;
            }
        }
        return (object) [
                // meta-data
                'columns' => $columns,
                'editable' => $cols['edit'] ?? [],
                'conditionDetails' => $conditionDetails,
                // data
                'table' => $data,
        ];
    }
}
