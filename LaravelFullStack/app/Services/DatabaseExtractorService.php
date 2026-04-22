<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DatabaseExtractorService
{
    /**
     * Step 1: Extract ONLY the tables and columns.
     * This returns a raw PHP array to be used by the Filament UI.
     */
    public function extractAllTables(string $filePath): array
    {
        $this->connectSqlite($filePath);
        $db = DB::connection('sqlite');

        $tablesData = [];
        $tables = $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        foreach ($tables as $table) {
            $tableName = $table->name;

            $foreignKeys = $db->select("PRAGMA foreign_key_list('{$tableName}')");
            $fkColumnNames = array_map(fn($fk) => $fk->from, $foreignKeys);

            $columns = $db->select("PRAGMA table_info('{$tableName}')");
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

        DB::purge('sqlite');

        return $tablesData;
    }

    /**
     * Step 2: Build the final JSON schema for Rust.
     * This calculates the strict 0-indexed positions for ONLY the selected tables.
     */
    public function buildDiagramSchema(string $filePath, array $selectedTableNames): string
    {
        $this->connectSqlite($filePath);
        $db = DB::connection('sqlite');

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
            $foreignKeys = $db->select("PRAGMA foreign_key_list('{$tableName}')");
            $fkColumnNames = array_map(fn($fk) => $fk->from, $foreignKeys);

            $columns = $db->select("PRAGMA table_info('{$tableName}')");

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
            // Safety check: skip if the table failed to load in Pass 1
            if (!isset($tableIndices[$tableName])) continue;

            $foreignKeys = $db->select("PRAGMA foreign_key_list('{$tableName}')");

            foreach ($foreignKeys as $fk) {
                $fromTableIdx = $tableIndices[$tableName] ?? null;
                $toTableIdx   = $tableIndices[$fk->table] ?? null;

                $fromColIdx = $columnIndices[$tableName][$fk->from] ?? null;
                $toColIdx   = $columnIndices[$fk->table][$fk->to] ?? null;

                // STRICT CHECK: We only add the relation if BOTH tables exist in the user's selection!
                if (isset($fromTableIdx, $toTableIdx, $fromColIdx, $toColIdx)) {
                    $schema['relations'][] = [
                        'name' => "{$tableName}_{$fk->table}",
                        'relation_segments' => [],
                        'tables' => [$fromTableIdx, $toTableIdx],   // Rust: pub tables: [usize; 2]
                        'columns' => [$fromColIdx, $toColIdx],      // Rust: pub columns: [usize; 2]
                        'description' => "FK: {$tableName}.{$fk->from} -> {$fk->table}.{$fk->to}"
                    ];
                }
            }
        }

        DB::purge('sqlite');

        return json_encode($schema);
    }

    /**
     * Helper method to keep the connection logic DRY
     */
    private function connectSqlite(string $filePath): void
    {
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $filePath,
            'foreign_key_constraints' => true,
        ]);
    }
}
