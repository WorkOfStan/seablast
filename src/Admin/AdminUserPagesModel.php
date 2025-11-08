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
 * Count active pages by user.
 * Route pt/user-pages
 *
 * TODO figure out whether part of it may be in Seablast or it is always app dependant
 */
class AdminUserPagesModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

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
     * Counts active items by user.
     *
     * It requires also the parent node to be active.
     * But if multiple levels are involved, it still can be way off. Counting result of DataRetrievalModel would be
     * accurate but much slower.
     *
     * @return stdClass
     * @throw \Exception if unimplemented HTTP method call
     */
    public function knowledge(): stdClass
    {
        if ($this->superglobals->server['REQUEST_METHOD'] === 'GET') {
            $stmt = $this->configuration->pdo()->queryStrict(
                "SELECT t.owner_id,
       u.email,
       COUNT(*) AS item_count
FROM `" . $this->configuration->dbmsTablePrefix() . "items` AS t
JOIN `" . $this->configuration->dbmsTablePrefix() . "users` AS u ON t.owner_id = u.id
LEFT JOIN `" . $this->configuration->dbmsTablePrefix() . "items` AS parent ON t.parent_id = parent.id
WHERE t.active = 1
  AND (
        (t.metadata_image IS NOT NULL AND t.metadata_image <> '')
     OR (t.metadata_text  IS NOT NULL AND t.metadata_text  <> '')
      )
  AND (t.parent_id IS NULL OR parent.active = 1)
GROUP BY t.owner_id, u.email;"
            );

            // Fetch all rows as an associative array
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $table = $results; // $knowledge['table'];
            $columns = ['owner_id', 'email', 'item_count']; //$knowledge['columns'];
            $editable = []; //$knowledge['editable'];
            $conditionDetails = []; //$knowledge['conditionDetails'];

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
                    'content' => ['test'], //$this->adminHelper->allowedTables,
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
