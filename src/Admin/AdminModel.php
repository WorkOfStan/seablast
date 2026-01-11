<?php

declare(strict_types=1);

namespace Seablast\Seablast\Admin;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;
use Webmozart\Assert\Assert;

/**
 * Retrieve items from database
 */
class AdminModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

    /** @var AdminHelper */
    private $adminHelper;
    /** @var SeablastConfiguration */
    private $configuration;
    /** @var Superglobals */
    private $superglobals;
    /** @var TableViewModel|null */
    private $tableContent = null;

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
        if ($this->configuration->exists(SeablastConstant::APP_SELECTED_TABLE)) {
            // In Latte the literal must be used
            Assert::eq(SeablastConstant::APP_SELECTED_TABLE, 'SB:APP_SELECTED_TABLE');
            $this->tableContent = new TableViewModel($this->configuration, $this->superglobals);
        }
    }

    /**
     * Collection of items to be displayed.
     *
     * @return stdClass
     * @throw \Exception if unimplemented HTTP method call
     */
    public function knowledge(): stdClass
    {
        if ($this->superglobals->server['REQUEST_METHOD'] === 'GET') {
            if (is_null($this->tableContent)) {
                $table = [];
                $columns = [];
                $conditionDetails = [];
                $editable = [];
            } else {
                $knowledge = (array) $this->tableContent->knowledge();
                $table =  $knowledge['table'];
                $columns = $knowledge['columns'];
                $editable = $knowledge['editable'];
                $conditionDetails = $knowledge['conditionDetails'];
            }

            return (object) [
                    'menu' => [
                        //['label' => 'Content', 'link' => 'content'],
                        [
                            'label' => 'Uživatelé - stránky',
                            'link' => $this->configuration->getString(SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL) //
                             . '/user-pages'
                        ],
                        [
                            'label' => 'User management',
                            'link' => $this->configuration->getString(SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL) //
                             . '/poseidon?t=users&condition%5B%5D=4%7C3'
                        ],
                        //['label' => 'Language'],
                        [
                            'label' => 'Logout',
                            'link' => $this->configuration->getString(SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL) //
                            . '/user/?logout'
                        ]
                    ],
                    'content' => $this->adminHelper->allowedTables,
                    'columns' => $columns,
                    'editable' => $editable,
                    'conditionDetails' => $conditionDetails,
                    'table' => $table,
            ];
        }
        throw new \Exception(
            'Wrong HTTP method request: ' . (string) print_r($this->superglobals->server['REQUEST_METHOD'], true)
        );
    }
}
