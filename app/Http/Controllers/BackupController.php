<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $driver = DB::connection()->getDriverName();
        abort_unless(in_array($driver, ['sqlite', 'mysql']), 422, 'SQL backup supports SQLite and MySQL.');

        return response()->streamDownload(function () use ($driver) {
            DB::transaction(function () use ($driver) {
                echo "-- Propeller Logistics SQL backup\n-- Database dialect: $driver\n-- Contains confidential account and shipment data.\n";
                echo $driver === 'mysql' ? "SET FOREIGN_KEY_CHECKS=0;\n" : "PRAGMA foreign_keys=OFF;\n";
                $tables = ['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'customers', 'vendors', 'shipments', 'airway_bills', 'tracking_updates', 'company_settings', 'template_settings', 'email_settings', 'booking_options', 'migrations'];
                foreach ($tables as $table) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }
                    $quoted = '`'.$table.'`';
                    $ddl = $driver === 'mysql' ? (array) DB::selectOne('SHOW CREATE TABLE '.$quoted) : null;
                    $schema = $driver === 'mysql' ? array_values($ddl)[1] : DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table])->sql;
                    echo "DROP TABLE IF EXISTS $quoted;\n$schema;\n";
                    foreach (DB::table($table)->orderBy(array_key_first((array) (DB::table($table)->first() ?? (object) ['id' => 0])))->cursor() as $row) {
                        $values = array_map(fn ($v) => $v === null ? 'NULL' : DB::getPdo()->quote((string) $v), (array) $row);
                        $columns = implode(',', array_map(fn ($c) => '`'.$c.'`', array_keys((array) $row)));
                        echo "INSERT INTO $quoted ($columns) VALUES (".implode(',', $values).");\n";
                    }
                }
                if ($driver === 'sqlite') {
                    foreach (DB::select("SELECT sql FROM sqlite_master WHERE type IN ('index','trigger') AND sql IS NOT NULL ORDER BY type,name") as $object) {
                        echo $object->sql.";\n";
                    }
                }
                echo $driver === 'mysql' ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n";
            });
        }, 'logistics-'.now()->format('Y-m-d-His').'.sql', ['Content-Type' => 'application/sql', 'Cache-Control' => 'no-store, private']);
    }
}
