<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DatabaseExtractorService
{

    public function extractTables(?string $filePath, string $engine = 'sqlite', array $state = []): array
    {
        $this->setupConnection($filePath, $engine, $state);

        try {
            $tablesData = [];
            $tableNames = $this->fetchTables();

            foreach ($tableNames as $tableName) {
                $tablesData[] = [
                    'name' => $tableName,
                    'columns' => $this->formatColumnsForSchema($tableName),
                ];
            }

            DB::purge('dynamic_extract');
            return $tablesData;

        } catch (\Exception $e) {
            DB::purge('dynamic_extract');
            //throw new \Exception("Erro na extração: " . $e->getMessage());
            throw new \Exception(1 . $e->getMessage());
        }
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
            $formattedColumns = $this->formatColumnsForSchema($tableName);

            if (empty($formattedColumns)) continue;

            $tableIndices[$tableName] = $tIndex;

            // Map column indices to build the relations
            foreach ($formattedColumns as $cIndex => $col) {
                $columnIndices[$tableName][$col['name']] = $cIndex;
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
                $fromTableIdx = $tableIndices[$tableName];
                $toTableIdx   = $tableIndices[$fk->table] ?? null;

                $fromColIdx = $columnIndices[$tableName][$fk->from] ?? null;
                $toColIdx   = $columnIndices[$fk->table][$fk->to] ?? null;

                if (isset($fromTableIdx, $toTableIdx, $fromColIdx, $toColIdx)) {
                    $schema['relations'][] = [
                        'name' => "{$tableName}_{$fk->from}_{$fk->table}",
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

    /**
     *  Fetch table foreign keys columns and table columns, then format column according to rust structures in the wasm module
     *
     * @param string $tableName
     * @return array
     *
     */
    private function formatColumnsForSchema(string $tableName): array
    {
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
                'unique' => $column->unique,
            ];
        }
        return $formattedColumns;
    }
    public function setupConnection(?string $path, string $engine, array $state = []) : void
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
        // Clean existing connection and try to connect to the given database
        DB::purge('dynamic_extract');
        try {
            // Test the connection
            DB::connection('dynamic_extract')->getPdo();
        } catch (\Exception $e) {
            //throw new \Exception("Erro de Ligação: " . $e->getMessage());
            throw new \Exception(0 . $e->getMessage());
        }
    }

    /**
     *  Resolves the absolute path of the generated SQLite file by Filament
     *
     * @param mixed $filePathData
     * @return string
     * @throws \Exception
     *
     */
    public function resolveSqlitePath(mixed $filePathData): string
    {
        if (!$filePathData) throw new \Exception('Ficheiro SQLite não encontrado.');

        $fileItem = is_array($filePathData) ? array_values($filePathData)[0] : $filePathData;
        $absolutePath = '';

        if ($fileItem instanceof TemporaryUploadedFile) {
            $absolutePath = $fileItem->getRealPath();
        } elseif (is_string($fileItem)) {
            if (preg_match('/^([a-zA-Z]:\\\\|\\/)/', $fileItem)) {
                $absolutePath = $fileItem;
            } else {
                $absolutePath = Storage::disk('local')->path($fileItem);
            }
        }

        if (!$absolutePath || !file_exists($absolutePath)) {
            throw new \Exception("Ficheiro não encontrado: " . $absolutePath);
        }

        return $absolutePath;
    }
    private function fetchTables(): array
    {
        $tables = Schema::connection('dynamic_extract')->getTables();
        $driver = DB::connection('dynamic_extract')->getDriverName();

        return array_map(function ($table) use ($driver) {
            // No PostgreSQL, se a tabela não for do public, prefixamos com o schema (ex: auth.users)
            if ($driver === 'pgsql' && isset($table['schema']) && $table['schema'] !== 'public') {
                return $table['schema'] . '.' . $table['name'];
            }
            return $table['name'];
        }, $tables);
    }
    public function getDefaultSelectedTables(array $allTables, string $driver = 'sqlite'): array
    {
        $filteredTables = array_filter($allTables, function ($tableName) use ($driver) {
            if ($driver === 'pgsql' && str_contains($tableName, '.')) {
                $parts = explode('.', $tableName);
                if ($parts[0] !== 'public') return false;
            }

            $cleanName = str_contains($tableName, '.') ? explode('.', $tableName)[1] : $tableName;

            $laravelInternalTables = [
                'migrations', 'jobs', 'job_batches', 'failed_jobs',
                'cache', 'cache_locks', 'password_reset_tokens', 'sessions',
            ];

            if (in_array($cleanName, $laravelInternalTables)) return false;

            return true;
        });

        return array_values($filteredTables);
    }

    // https://laravel-news.com/laravel-10-37-0#content-get-the-indexes-and-foreign-keys-of-a-table
    private function fetchColumns(string $tableName): array
    {
        $normalized = [];

        $columns = Schema::connection('dynamic_extract')->getColumns($tableName);

        // Obter os indexes
        $indexes = Schema::connection('dynamic_extract')->getIndexes($tableName);
        $pkColumns = [];
        $uniqueColumns = [];
        foreach ($indexes as $index) {
            if ($index['primary']) {
                $pkColumns = $index['columns'];
            }
            if ($index['unique']) {
                $uniqueColumns = $index['columns'];
            }
        }

        foreach ($columns as $col) {
            $normalized[] = (object)[
                'name' => $col['name'],
                'type' => $col['type_name'], // e.g., 'varchar', 'integer'
                'notnull' => !$col['nullable'],
                'pk' => in_array($col['name'], $pkColumns),
                'unique' => in_array($col['name'], $uniqueColumns),
            ];
        }

        return $normalized;
    }

    //https://laravel-news.com/laravel-10-37-0#content-get-the-indexes-and-foreign-keys-of-a-table
    public function fetchForeignKeys(string $tableName): array
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
