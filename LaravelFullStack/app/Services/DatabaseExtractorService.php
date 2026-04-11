<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DatabaseExtractorService
{
    public function extractSqlite(string $filePath): string
    {
        // Alterar a configuração do sqlite
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $filePath,
            'foreign_key_constraints' => true,
        ]);

        // Conectar o laravel à base de dados
        $db = DB::connection('sqlite');

        $schema = [
            'tables' => [],
            'relations' => []
        ];

        $tableIndices = [];
        $columnIndices = [];

        // Obter as tabelas
        // https://stackoverflow.com/questions/37931929/laravel-php-artisan-tinker-how-to-view-all-tables-in-sqlite-database
        // $db->select("PRAGMA table_list")
        $tables = $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        // https://www.sqlite.org/pragma.html#pragma_table_info
        foreach ($tables as $tIndex => $table) {
            $tableName = $table->name;
            $tableIndices[$tableName] = $tIndex;

            $foreignKeys = $db->select("PRAGMA foreign_key_list('{$tableName}')");
            $fkColumnNames = array_map(fn($fk) => $fk->from, $foreignKeys);

            // Obter e formatar colunas
            $columns = $db->select("PRAGMA table_info('{$tableName}')");
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

            // Adicionar tabela e colunas à lista de tabelas
            $schema['tables'][] = [
                'name' => $tableName,
                'columns' => $formattedColumns,
            ];

            // Obter chaves estrangeiras para as relações
            foreach ($tables as $table) {
                $tableName = $table->name;
                $foreignKeys = $db->select("PRAGMA foreign_key_list('{$tableName}')");

                foreach ($foreignKeys as $fk) {
                    $fromTableIdx = $tableIndices[$tableName] ?? null;
                    $toTableIdx   = $tableIndices[$fk->table] ?? null;

                    $fromColIdx = $columnIndices[$tableName][$fk->from] ?? null;
                    $toColIdx   = $columnIndices[$fk->table][$fk->to] ?? null;

                    if (isset($fromTableIdx, $toTableIdx, $fromColIdx, $toColIdx)) {
                        $schema['relations'][] = [
                            'name' => "{$tableName}_{$fk->table}",
                            'connection_segments' => [],
                            'tables' => [$fromTableIdx, $toTableIdx],   // Rust: pub tables: [usize; 2]
                            'columns' => [$fromColIdx, $toColIdx],      // Rust: pub columns: [usize; 2]
                            'description' => "FK: {$tableName}.{$fk->from} -> {$fk->table}.{$fk->to}"
                        ];
                    }
                }
            }
        }

        DB::purge('sqlite');

        return json_encode($schema);
    }
}
