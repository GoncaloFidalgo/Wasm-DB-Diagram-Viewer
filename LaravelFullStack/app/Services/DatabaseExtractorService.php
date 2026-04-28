<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class DatabaseExtractorService
{

    public function extractTables(?string $filePath, string $engine = 'sqlite', array $state = []): array
    {
        $this->setupConnection($filePath, $engine, $state);

        $tablesData = [];
        $tableNames = $this->fetchTables();

        // Para cada tabela
        // Obter as chaves estrangeiras e respetivas colunas
        // Obter as colunas da tabela
        // Adicionar as colunas formatadas para a estrutura "Column" do módulo wasm à lista de colunas da tabela
        // Adicionar um registo à lista de tabelas com o nome da tabela e a sua lista de colunas
        foreach ($tableNames as $tableName) {
            $foreignKeys = $this->fetchForeignKeys($tableName);
            $fkColumnNames = array_column($foreignKeys, 'from');

            $columns = $this->fetchColumns($tableName);
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


    public function buildDiagramSchema(?string $filePath, array $selectedTableNames, string $engine = 'sqlite', array $state = []): string
    {
       $this->setupConnection($filePath, $engine, $state);

        $schema = [
            'tables' => [],
            'relations' => []
        ];

        $tableIndices = [];
        $columnIndices = [];

        $tIndex = 0;
        foreach ($selectedTableNames as $tableName) {
            $foreignKeys = $this->fetchForeignKeys($tableName);
            $fkColumnNames = array_column($foreignKeys, 'from');

            $columns = $this->fetchColumns($tableName);

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


        foreach ($selectedTableNames as $tableName) {
            if (!isset($tableIndices[$tableName])) continue;

            $foreignKeys = $this->fetchForeignKeys($tableName);

            foreach ($foreignKeys as $fk) {
                $fromTableIdx = $tableIndices[$tableName] ?? null;
                $toTableIdx   = $tableIndices[$fk->table] ?? null;

                $fromColIdx = $columnIndices[$tableName][$fk->from] ?? null;
                $toColIdx   = $columnIndices[$fk->table][$fk->to] ?? null;

                if (isset($fromTableIdx, $toTableIdx, $fromColIdx, $toColIdx)) {
                    $schema['relations'][] = [
                        'name' => "{$tableName}_$fk->table",
                        'relation_segments' => [],
                        'tables' => [$fromTableIdx, $toTableIdx],
                        'columns' => [$fromColIdx, $toColIdx],
                        'description' => "FK: $tableName.$fk->from -> $fk->table.$fk->to"
                    ];
                }
            }
        }

        DB::purge('dynamic_extract');

        return json_encode($schema);
    }

    /**
     * ==========================================
     * HELPERS
     * ==========================================
     */

    private function setupConnection(?string $path, string $engine, array $state = []) : void
    {
        if ($engine === 'mysql') {
            Config::set('database.connections.dynamic_extract', [
                'driver'    => 'mysql',
                'host'      => $state['mysql_host'],
                'port'      => $state['mysql_port'],
                'database'  => $state['mysql_database'],
                'username'  => $state['mysql_username'] ?? 'root',
                'password'  => $state['mysql_password'],
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
        // Limpar conexões existentes, cache e conectar novamente com os dados fornecidos
        DB::purge('dynamic_extract');
        DB::connection('dynamic_extract');
    }

    private function fetchTables(): array
    {
        //$schemas = Schema::connection('dynamic_extract')->getSchemas();
        $tables = Schema::connection('dynamic_extract')->getTables();
        $driver = DB::connection('dynamic_extract')->getDriverName();

        //$schemas = Schema::getSchemas();
        //$tables = Schema::getTables();
        //$driver = DB::getDriverName();

        $filteredTables = array_filter($tables, function ($table) use ($driver) {
            // PostgreSQL separa as tabelas em schemas (auth, storage, public).
            // Usar apenas o public
            if ($driver === 'pgsql' && isset($table['schema'])) {
                if ($table['schema'] !== 'public') {
                    return false;
                }
            }

            // Tabelas criadas pelo laravel
            $laravelInternalTables = [
                'migrations',
                'jobs',
                'job_batches',
                'failed_jobs',
                'cache',
                'cache_locks',
                'password_reset_tokens',
                'sessions',
            ];

            // Ignorar tabelas do Laravel
            if (in_array($table['name'], $laravelInternalTables)) {
                return false;
            }

            return true;
        });

        // Devolve apenas os nomes das tabelas
        return array_column($filteredTables, 'name');
    }

    // https://laravel-news.com/laravel-10-37-0#content-get-the-indexes-and-foreign-keys-of-a-table
    private function fetchColumns(string $tableName): array
    {
        $normalized = [];

        $columns = Schema::connection('dynamic_extract')->getColumns($tableName);

        // Obter os indexes
        $indexes = Schema::connection('dynamic_extract')->getIndexes($tableName);
        $pkColumns = [];

        foreach ($indexes as $index) {
            if ($index['primary']) {
                $pkColumns = $index['columns']; // Usually an array with one column name
                break;
            }
        }

        // 3. Format them for our Rust canvas
        foreach ($columns as $col) {
            $normalized[] = (object)[
                'name' => $col['name'],
                'type' => $col['type_name'], // e.g., 'varchar', 'integer'
                'notnull' => !$col['nullable'], // Invert nullable to get 'notnull'
                'pk' => in_array($col['name'], $pkColumns),
            ];
        }

        return $normalized;
    }

    //https://laravel-news.com/laravel-10-37-0#content-get-the-indexes-and-foreign-keys-of-a-table
    private function fetchForeignKeys(string $tableName): array
    {
        $normalized = [];
        $fks = Schema::connection('dynamic_extract')->getForeignKeys($tableName);

        // O metodo getForeignKeys devolve um array para as colunas da tabela principal e outro para as colunas da tabela estrangeira
        // Percorrer as colunas de cada chave e criar o objeto com a associacao
        // (from - coluna na tabela a ser analisada, table - tabela da coluna onde liga a chave estrangeira, to - coluna onde liga a chave estrangeira)
        foreach ($fks as $fk) {
            foreach ($fk['columns'] as $index => $localColumn) {
                $normalized[] = (object)[
                    'from' => $localColumn,
                    'table' => $fk['foreign_table'],
                    'to' => $fk['foreign_columns'][$index], // Obter coluna na tabela estrangeira correspondente à coluna na tabela principal
                ];
            }
        }
        return $normalized;
    }
}
