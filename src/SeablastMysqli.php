<?php

namespace Seablast/Seablast;

use mysqli;

class SeablastMysqli extends mysqli
{
    //nette declaration

    /** @var string */
    private $logPath = 'query_log.txt'; // TODO default is where??
    /** @var string[] For Tracy Bar Panel. */
    private $statementList;

    public function __construct($host, $username, $password, $dbname, $port = null, $socket = null) {
        parent::__construct($host, $username, $password, $dbname, $port, $socket);
        $this->set_charset("utf8"); // TODO viz configuration, ale jak to volat? Injection?
        $this->statementList = [];
    }

    public function query($query, $resultmode = MYSQLI_STORE_RESULT) {
        $trimmedQuery = trim($query);
        $this->statementList[] = $trimmedQuery;
        // todo what other keywords?
        if (stripos($trimmedQuery, 'SELECT') !== 0 && stripos($trimmedQuery, 'INFO') !== 0) {
            // Log the query if it doesn't start with SELECT or INFO
            // TODO jak NELOGOVAT hesla? Použít queryNoLog() nebo nějaká chytristika?
            $this->logQuery($query);
        }
        return parent::query($query, $resultmode);
    }

    private function logQuery($query) {
        // Implement your logging logic here
        file_put_contents($this->logPath, $query . PHP_EOL, FILE_APPEND);
    }

    public function showSqlBarPanel() {
        // todo vrátit ten panel!
        return $this->statementList;
    }
}
