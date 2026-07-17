<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sqlitePath = database_path('database.sqlite');
if (! is_file($sqlitePath)) {
    throw new RuntimeException("Database SQLite tidak ditemukan: {$sqlitePath}");
}

config(['database.connections.sqlite_legacy' => [
    'driver' => 'sqlite',
    'database' => $sqlitePath,
    'prefix' => '',
    'foreign_key_constraints' => true,
]]);

$legacy = DB::connection('sqlite_legacy');
$mysql = DB::connection('mysql');
$tables = collect($legacy->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
    ->pluck('name')
    ->values();

$mysql->statement('SET FOREIGN_KEY_CHECKS=0');

try {
    foreach ($tables as $table) {
        if (! $mysql->getSchemaBuilder()->hasTable($table)) {
            throw new RuntimeException("Tabel tujuan tidak ditemukan di MySQL: {$table}");
        }

        $mysql->table($table)->truncate();
        $count = 0;

        $legacy->table($table)->orderBy($legacy->raw('rowid'))->chunk(250, function ($rows) use ($mysql, $table, &$count) {
            $payload = $rows->map(fn ($row) => (array) $row)->all();
            if ($payload !== []) {
                $mysql->table($table)->insert($payload);
                $count += count($payload);
            }
        });

        $targetCount = $mysql->table($table)->count();
        if ($targetCount !== $count) {
            throw new RuntimeException("Jumlah data tabel {$table} tidak cocok: SQLite {$count}, MySQL {$targetCount}");
        }

        echo str_pad($table, 40)." {$count} baris\n";
    }
} finally {
    $mysql->statement('SET FOREIGN_KEY_CHECKS=1');
}

echo "Migrasi data SQLite ke MySQL selesai.\n";
