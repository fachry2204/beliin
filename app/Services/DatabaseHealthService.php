<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DatabaseHealthService
{
    public function check(): array
    {
        $migrationFiles = collect(File::files(database_path('migrations')))
            ->sortBy(fn ($file) => $file->getFilename())
            ->mapWithKeys(fn ($file) => [
                pathinfo($file->getFilename(), PATHINFO_FILENAME) => $file->getPathname(),
            ]);
        $ranMigrations = Schema::hasTable('migrations')
            ? DB::table('migrations')->orderBy('batch')->orderBy('migration')->pluck('migration')->all()
            : [];
        $pendingMigrations = $migrationFiles->keys()->diff($ranMigrations)->values()->all();
        $expectedSchema = $this->expectedSchema($migrationFiles->all());
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();
        $actualTables = $driver === 'mysql'
            ? collect(Schema::getTables())
                ->where('schema', $database)
                ->pluck('name')
                ->values()
            : collect(Schema::getTableListing())
                ->map(fn (string $table) => str_contains($table, '.')
                    ? substr($table, strrpos($table, '.') + 1)
                    : $table)
                ->values();
        $missingTables = collect(array_keys($expectedSchema))
            ->diff($actualTables)
            ->values()
            ->all();
        $missingColumns = [];

        foreach ($expectedSchema as $table => $columns) {
            if (in_array($table, $missingTables, true)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missingColumns[] = ['table' => $table, 'column' => $column];
                }
            }
        }

        $extraTables = $actualTables
            ->diff(array_keys($expectedSchema))
            ->diff([
                'migrations',
                'permissions',
                'roles',
                'model_has_permissions',
                'model_has_roles',
                'role_has_permissions',
            ])
            ->values()
            ->all();
        $status = $missingTables !== [] || $missingColumns !== []
            ? 'schema_mismatch'
            : ($pendingMigrations !== [] ? 'update_required' : 'healthy');

        return [
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'connection' => config('database.default'),
            'driver' => $driver,
            'database' => $database,
            'applied_migrations' => count($ranMigrations),
            'total_migrations' => $migrationFiles->count(),
            'pending_migrations' => $pendingMigrations,
            'missing_tables' => $missingTables,
            'missing_columns' => $missingColumns,
            'extra_tables' => $extraTables,
        ];
    }

    public function migrate(): array
    {
        $before = $this->check();
        Artisan::call('migrate', ['--force' => true]);

        return [
            'before' => $before,
            'after' => $this->check(),
            'output' => trim(Artisan::output()),
        ];
    }

    private function expectedSchema(array $migrationFiles): array
    {
        $schema = [];

        foreach ($migrationFiles as $path) {
            $up = $this->upMethod(File::get($path));
            preg_match_all(
                '/Schema::(create|table)\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*function\s*\([^)]*\)\s*\{(.*?)^\s*\}\);/ms',
                $up,
                $blocks,
                PREG_SET_ORDER,
            );

            foreach ($blocks as $block) {
                [, $operation, $table, $body] = $block;
                $schema[$table] ??= [];
                $columns = $this->columnsFromBlock($body);

                if ($operation === 'create') {
                    $schema[$table] = $columns;
                } else {
                    $schema[$table] = array_values(array_unique([...$schema[$table], ...$columns]));
                }

                preg_match_all('/\$table->dropColumn\(\s*[\'"]([^\'"]+)[\'"]/', $body, $drops);
                foreach ($drops[1] ?? [] as $column) {
                    $schema[$table] = array_values(array_diff($schema[$table], [$column]));
                }

                preg_match_all(
                    '/\$table->renameColumn\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/',
                    $body,
                    $renames,
                    PREG_SET_ORDER,
                );
                foreach ($renames as $rename) {
                    $schema[$table] = array_values(array_diff($schema[$table], [$rename[1]]));
                    $schema[$table][] = $rename[2];
                }
            }

            preg_match_all(
                '/Schema::rename\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
                $up,
                $renamedTables,
                PREG_SET_ORDER,
            );
            foreach ($renamedTables as $rename) {
                $schema[$rename[2]] = $schema[$rename[1]] ?? [];
                unset($schema[$rename[1]]);
            }

            preg_match_all('/Schema::dropIfExists\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $up, $droppedTables);
            foreach ($droppedTables[1] ?? [] as $table) {
                unset($schema[$table]);
            }
        }

        return collect($schema)
            ->map(fn (array $columns) => array_values(array_unique($columns)))
            ->all();
    }

    private function columnsFromBlock(string $body): array
    {
        preg_match_all('/\$table->\w+\(\s*[\'"]([^\'"]+)[\'"]/', $body, $matches);
        $columns = $matches[1] ?? [];

        if (preg_match('/\$table->id\(\s*\)/', $body)) {
            $columns[] = 'id';
        }
        if (str_contains($body, '$table->timestamps(')) {
            $columns = [...$columns, 'created_at', 'updated_at'];
        }
        if (str_contains($body, '$table->softDeletes(')) {
            $columns[] = 'deleted_at';
        }
        if (str_contains($body, '$table->rememberToken(')) {
            $columns[] = 'remember_token';
        }

        preg_match_all('/\$table->(?:nullable)?morphs\(\s*[\'"]([^\'"]+)[\'"]/', $body, $morphs);
        foreach ($morphs[1] ?? [] as $name) {
            $columns[] = $name.'_type';
            $columns[] = $name.'_id';
        }

        return array_values(array_unique($columns));
    }

    private function upMethod(string $migration): string
    {
        if (! preg_match(
            '/public function up\(\)(?:\s*:\s*void)?\s*\{(.*?)(?=public function down\(\))/s',
            $migration,
            $matches,
        )) {
            return $migration;
        }

        return $matches[1];
    }
}
