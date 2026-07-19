<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class BackupService
{
    public const TYPES = ['full', 'database'];

    private string $directory;

    public function __construct()
    {
        $this->directory = config('backup.directory', storage_path('app/private/backups'));
    }

    public function create(string $type, bool $automatic = false): array
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new RuntimeException('Jenis backup tidak valid.');
        }

        File::ensureDirectoryExists($this->directory);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $source = $automatic ? 'otomatis' : 'manual';
        $filename = "backup-{$type}-{$source}-{$timestamp}.zip";
        $path = $this->directory.DIRECTORY_SEPARATOR.$filename;
        $sqlPath = storage_path("app/private/backup-database-{$timestamp}.sql");

        $this->dumpDatabase($sqlPath);

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::delete($sqlPath);
            throw new RuntimeException('Arsip ZIP backup tidak dapat dibuat.');
        }

        try {
            $zip->addFile($sqlPath, 'database/database.sql');
            if ($type === 'full') {
                $this->addApplicationFiles($zip);
            }
        } finally {
            $zip->close();
            File::delete($sqlPath);
        }

        clearstatcache(true, $path);

        return $this->metadata($path);
    }

    public function all(): array
    {
        File::ensureDirectoryExists($this->directory);

        return collect(File::files($this->directory))
            ->filter(fn ($file) => $file->getExtension() === 'zip')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => $this->metadata($file->getPathname()))
            ->values()->all();
    }

    public function path(string $filename): string
    {
        $safe = basename($filename);
        abort_unless($safe === $filename && str_ends_with($safe, '.zip'), 404);
        $path = $this->directory.DIRECTORY_SEPARATOR.$safe;
        abort_unless(File::isFile($path), 404);

        return $path;
    }

    public function delete(string $filename): void
    {
        File::delete($this->path($filename));
    }

    public function prune(int $keep): void
    {
        collect($this->all())->where('automatic', true)->values()->slice(max(1, $keep))->each(
            fn (array $backup) => File::delete($this->directory.DIRECTORY_SEPARATOR.$backup['filename'])
        );
    }

    private function dumpDatabase(string $path): void
    {
        $driver = DB::connection()->getDriverName();
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('File dump database tidak dapat dibuat.');
        }

        try {
            fwrite($handle, "-- Backup database Beliin\n-- Dibuat: ".now()->toDateTimeString()."\n\n");
            if ($driver === 'mysql') {
                fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");
                $tables = collect(DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']))
                    ->map(fn ($row) => array_values((array) $row)[0]);
                foreach ($tables as $table) {
                    $create = array_values((array) DB::selectOne('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`'))[1];
                    fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");
                    DB::table($table)->orderBy(DB::raw('1'))->chunk(500, function ($rows) use ($handle, $table): void {
                        foreach ($rows as $row) {
                            $values = collect((array) $row)->map(fn ($value) => $value === null ? 'NULL' : DB::getPdo()->quote((string) $value));
                            $columns = collect(array_keys((array) $row))->map(fn ($column) => '`'.str_replace('`', '``', $column).'`');
                            fwrite($handle, "INSERT INTO `{$table}` (".$columns->implode(', ').') VALUES ('.$values->implode(', ').");\n");
                        }
                    });
                    fwrite($handle, "\n");
                }
                fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");

                return;
            }

            if ($driver === 'sqlite') {
                foreach (DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $table) {
                    fwrite($handle, "DROP TABLE IF EXISTS \"{$table->name}\";\n{$table->sql};\n");
                    foreach (DB::table($table->name)->get() as $row) {
                        $values = collect((array) $row)->map(fn ($value) => $value === null ? 'NULL' : DB::getPdo()->quote((string) $value));
                        $columns = collect(array_keys((array) $row))->map(fn ($column) => '"'.str_replace('"', '""', $column).'"');
                        fwrite($handle, 'INSERT INTO "'.$table->name.'" ('.$columns->implode(', ').') VALUES ('.$values->implode(', ').");\n");
                    }
                    fwrite($handle, "\n");
                }

                return;
            }

            throw new RuntimeException("Driver database {$driver} belum didukung untuk backup.");
        } finally {
            fclose($handle);
        }
    }

    private function addApplicationFiles(ZipArchive $zip): void
    {
        $base = base_path();
        $excluded = [
            '.git', 'node_modules', 'vendor', 'storage/app/private/backups',
            'storage/framework/cache', 'storage/framework/sessions',
            'storage/framework/views', 'storage/logs',
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                function ($current) use ($base, $excluded): bool {
                    $relative = str_replace('\\', '/', substr($current->getPathname(), strlen($base) + 1));

                    return ! collect($excluded)->contains(fn ($path) => $relative === $path || str_starts_with($relative, $path.'/'));
                }
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                $zip->addFile($file->getPathname(), 'application/'.$relative);
            }
        }
    }

    private function metadata(string $path): array
    {
        $filename = basename($path);

        return [
            'filename' => $filename,
            'type' => str_contains($filename, '-full-') ? 'full' : 'database',
            'automatic' => str_contains($filename, '-otomatis-'),
            'size' => File::size($path),
            'created_at' => date('c', File::lastModified($path)),
        ];
    }
}
