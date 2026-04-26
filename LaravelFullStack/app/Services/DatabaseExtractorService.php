<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class DatabaseExtractorService
{
    /**
     * Step 1: Extract ONLY the tables and columns.
     */
    public function extractAllTables(?string $filePath, string $engine = 'sqlite', array $state = []): array
    {
        $db = $this->setupConnection($filePath, $engine, $state);
        $dbName = $state['mysql_database'] ?? null;

        $tablesData = [];
        $tableNames = $this->fetchTables($db, $engine, $dbName);

        foreach ($tableNames as $tableName) {
            $foreignKeys = $this->fetchForeignKeys($db, $engine, $tableName, $dbName);
            $fkColumnNames = array_column($foreignKeys, 'from');

            $columns = $this->fetchColumns($db, $engine, $tableName);
            $formattedColumns = [];

            foreach ($columns as $column) {
                $columnName = $column->name;

                $keyType = '';
                if ($column->pk) {
                    $keyType = 'PK';
                } elseif (in_array($columnName, $fkColumnNames)) {
                    $keyType = 'FK';
                }

                $formattedColumns[] = [
                    'name' => $columnName,
                    'column_type' => $column->type,
                    'nullable' => !$column->notnull,
                    'description' => '',
                    'key_type' => $keyType,
                ];
            }

            $tablesData[] = [
                'name' => $tableName,
                'columns' => $formattedColumns,
            ];
        }

        DB::purge('dynamic_extract');

        return $tablesData;
    }

    /**
     * Step 2: Build the final JSON schema for Rust.
     */
    public function buildDiagramSchema(?string $filePath, array $selectedTableNames, string $engine = 'sqlite', array $state = []): string
    {
        $db = $this->setupConnection($filePath, $engine, $state);
        $dbName = $state['mysql_database'] ?? null;

        $schema = [
            'tables' => [],
            'relations' => []
        ];

        $tableIndices = [];
        $columnIndices = [];

        // ---------------------------------------------------------
        // PASS 1: Build the Tables Array and calculate the NEW indices
        // ---------------------------------------------------------
        $tIndex = 0;
        foreach ($selectedTableNames as $tableName) {
            $foreignKeys = $this->fetchForeignKeys($db, $engine, $tableName, $dbName);
            $fkColumnNames = array_column($foreignKeys, 'from');

            $columns = $this->fetchColumns($db, $engine, $tableName);

            if (empty($columns)) continue;

            $tableIndices[$tableName] = $tIndex;
            $formattedColumns = [];

            foreach ($columns as $cIndex => $column) {
                $columnName = $column->name;
                $columnIndices[$tableName][$columnName] = $cIndex;

                $keyType = '';
                if ($column->pk) {
                    $keyType = 'PK';
                } elseif (in_array($columnName, $fkColumnNames)) {
                    $keyType = 'FK';
                }

                $formattedColumns[] = [
                    'name' => $columnName,
                    'column_type' => $column->type,
                    'nullable' => !$column->notnull,
                    'description' => '',
                    'key_type' => $keyType,
                ];
            }

            $schema['tables'][] = [
                'name' => $tableName,
                'columns' => $formattedColumns,
            ];

            $tIndex++;
        }

        // ---------------------------------------------------------
        // PASS 2: Build the Relations ONLY if both tables were selected
        // ---------------------------------------------------------
        foreach ($selectedTableNames as $tableName) {
            if (!isset($tableIndices[$tableName])) continue;

            $foreignKeys = $this->fetchForeignKeys($db, $engine, $tableName, $dbName);

            foreach ($foreignKeys as $fk) {
                $fromTableIdx = $tableIndices[$tableName] ?? null;
                $toTableIdx   = $tableIndices[$fk->table] ?? null;

                $fromColIdx = $columnIndices[$tableName][$fk->from] ?? null;
                $toColIdx   = $columnIndices[$fk->table][$fk->to] ?? null;

                if (isset($fromTableIdx, $toTableIdx, $fromColIdx, $toColIdx)) {
                    $schema['relations'][] = [
                        'name' => "{$tableName}_{$fk->table}",
                        'relation_segments' => [],
                        'tables' => [$fromTableIdx, $toTableIdx],
                        'columns' => [$fromColIdx, $toColIdx],
                        'description' => "FK: {$tableName}.{$fk->from} -> {$fk->table}.{$fk->to}"
                    ];
                }
            }
        }

        DB::purge('dynamic_extract');

        return json_encode($schema);
    }

    /**
     * ==========================================
     * DATABASE ABSTRACTION HELPERS
     * ==========================================
     */

    private function setupConnection(?string $path, string $engine, array $state = [])
    {
        if ($engine === 'mysql') {
            Config::set('database.connections.dynamic_extract', [
                'driver'    => 'mysql',
                'host'      => $state['mysql_host'] ?? '127.0.0.1',
                'port'      => $state['mysql_port'] ?? '3306',
                'database'  => $state['mysql_database'] ?? '',
                'username'  => $state['mysql_username'] ?? 'root',
                'password'  => $state['mysql_password'] ?? '',
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'strict'    => true,
            ]);
        } else {
            Config::set('database.connections.dynamic_extract', [
                'driver' => 'sqlite',
                'database' => $path,
                'foreign_key_constraints' => true,
            ]);
        }

        // Clear old cached instances
        DB::purge('dynamic_extract');
        return DB::connection('dynamic_extract');
    }

    private function fetchTables($db, string $engine, ?string $dbName): array
    {
        if ($engine === 'mysql') {
            $results = $db->select("SELECT TABLE_NAME as name FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
        } else {
            $results = $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        }
        return array_column($results, 'name');
    }

    private function fetchColumns($db, string $engine, string $tableName): array
    {
        $normalized = [];

        if ($engine === 'mysql') {
            $cols = $db->select("SHOW COLUMNS FROM `{$tableName}`");
            foreach ($cols as $col) {
                $normalized[] = (object)[
                    'name' => $col->Field,
                    'type' => $col->Type,
                    'notnull' => $col->Null === 'NO',
                    'pk' => $col->Key === 'PRI',
                ];
            }
        } else {
            $cols = $db->select("PRAGMA table_info('{$tableName}')");
            foreach ($cols as $col) {
                $normalized[] = (object)[
                    'name' => $col->name,
                    'type' => $col->type,
                    'notnull' => (bool)$col->notnull,
                    'pk' => (bool)$col->pk,
                ];
            }
        }
        return $normalized;
    }

    private function fetchForeignKeys($db, string $engine, string $tableName, ?string $dbName): array
    {
        $normalized = [];

        if ($engine === 'mysql') {
            $fks = $db->select("
                SELECT COLUMN_NAME as 'from', REFERENCED_TABLE_NAME as 'table', REFERENCED_COLUMN_NAME as 'to'
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$dbName, $tableName]);

            foreach ($fks as $fk) {
                $normalized[] = (object)[
                    'from' => $fk->from,
                    'table' => $fk->table,
                    'to' => $fk->to,
                ];
            }
        } else {
            $fks = $db->select("PRAGMA foreign_key_list('{$tableName}')");

            foreach ($fks as $fk) {
                $normalized[] = (object)[
                    'from' => $fk->from,
                    'table' => $fk->table,
                    'to' => $fk->to,
                ];
            }
        }
        return $normalized;
    }
}
