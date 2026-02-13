<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BackupRestoreController extends Controller
{
    private const DISK = 'local';
    private const DIRECTORY = 'backups';

    private const TABLES = [
        'users',
        'role_permissions',
        'delegation_settings',
        'residents',
        'certificates',
        'blotters',
        'payments',
        'documents',
        'audit_logs',
    ];

    public function index(): Response
    {
        $files = collect(Storage::disk(self::DISK)->files(self::DIRECTORY))
            ->filter(fn ($file) => str_ends_with($file, '.json'))
            ->map(function ($file) {
                return [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => Storage::disk(self::DISK)->size($file),
                    'last_modified' => Storage::disk(self::DISK)->lastModified($file),
                ];
            })
            ->sortByDesc('last_modified')
            ->values();

        return Inertia::render('Admin/BackupRestore', [
            'files' => $files,
        ]);
    }

    public function create(Request $request)
    {
        $snapshot = [
            'meta' => [
                'created_at' => now()->toIso8601String(),
                'app_url' => config('app.url'),
                'generated_by' => $request->user()?->name,
                'tables' => self::TABLES,
            ],
            'tables' => [],
        ];

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $snapshot['tables'][$table] = [];
                continue;
            }

            $snapshot['tables'][$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        $filename = 'backup-'.now()->format('Ymd-His').'.json';
        $path = self::DIRECTORY.'/'.$filename;

        Storage::disk(self::DISK)->put(
            $path,
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        AuditLogger::log(
            $request,
            'backup.create',
            self::class,
            0,
            null,
            ['file' => $filename, 'tables' => self::TABLES]
        );

        return redirect()->back()->with('success', 'Backup created successfully.');
    }

    public function download(string $name)
    {
        $name = basename($name);
        $path = self::DIRECTORY.'/'.$name;

        if (! Storage::disk(self::DISK)->exists($path)) {
            return redirect()->back()->with('error', 'Backup file not found.');
        }

        return Storage::disk(self::DISK)->download($path, $name);
    }

    public function destroy(Request $request, string $name)
    {
        $name = basename($name);
        $path = self::DIRECTORY.'/'.$name;

        if (! Storage::disk(self::DISK)->exists($path)) {
            return redirect()->back()->with('error', 'Backup file not found.');
        }

        Storage::disk(self::DISK)->delete($path);

        AuditLogger::log(
            $request,
            'backup.delete',
            self::class,
            0,
            ['file' => $name],
            null
        );

        return redirect()->back()->with('success', 'Backup deleted.');
    }

    public function restore(Request $request, string $name)
    {
        if (! $request->user()->hasPermission('backup.restore')) {
            abort(403, 'Insufficient permission.');
        }

        $name = basename($name);
        $path = self::DIRECTORY.'/'.$name;

        if (! Storage::disk(self::DISK)->exists($path)) {
            return redirect()->back()->with('error', 'Backup file not found.');
        }

        $payload = json_decode(Storage::disk(self::DISK)->get($path), true);
        if (! is_array($payload) || ! isset($payload['tables']) || ! is_array($payload['tables'])) {
            return redirect()->back()->with('error', 'Invalid backup format.');
        }

        $driver = DB::connection()->getDriverName();

        DB::beginTransaction();
        try {
            $this->disableForeignKeys($driver);

            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                if (! array_key_exists($table, $payload['tables']) || ! is_array($payload['tables'][$table])) {
                    continue;
                }

                DB::table($table)->truncate();

                $rows = $payload['tables'][$table];
                if (! empty($rows)) {
                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            $this->enableForeignKeys($driver);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->enableForeignKeys($driver);

            return redirect()->back()->with('error', 'Restore failed: '.$e->getMessage());
        }

        AuditLogger::log(
            $request,
            'backup.restore',
            self::class,
            0,
            null,
            ['file' => $name]
        );

        return redirect()->back()->with('success', 'Backup restored successfully.');
    }

    private function disableForeignKeys(string $driver): void
    {
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
    }

    private function enableForeignKeys(string $driver): void
    {
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
}
