<?php

// only for PHPUnit tests
return
[
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'production' => [
            'adapter' => 'mysql',
            'host' => 'localhost',
            'name' => 'production_db',
            'user' => 'root',
            'pass' => '',
            'port' => '3306',
            'charset' => 'utf8',
        ],
        'development' => [
            'adapter' => 'mysql',
            'host' => 'localhost',
            'name' => 'seablast_dist',
            'user' => 'root',
            'pass' => '',
            'port' => '3306',
            'charset' => 'utf8',
            'table_prefix' => 'seablast_dist_',
        ],
        'testing' => [
            'adapter' => 'mysql',
            'host' => 'localhost',
            'name' => 'testing_db',
            'user' => 'root',
            'pass' => 'root', // so that it works in GitHub automation
            'port' => '3306',
            'charset' => 'utf8',
            'table_prefix' => 'seablast_dist_testing_',
        ]
    ],
    'version_order' => 'creation'
];
