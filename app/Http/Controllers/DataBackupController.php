<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DataBackupController extends Controller
{
    private const TABLES = ['users', 'customers', 'vendors', 'shipments', 'airway_bills', 'tracking_updates', 'company_settings', 'template_settings', 'email_settings', 'booking_options'];

    public function index(): View
    {
        return view('backups');
    }

    public function download(): Response
    {
        $backup = DB::transaction(fn (): string => $this->snapshot());

        return response($backup, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="propeller-'.now()->format('Y-m-d-His').'.backup"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
            'backup' => ['required', 'file', 'max:10240'],
            'confirm_restore' => ['accepted'],
        ]);

        try {
            $payload = json_decode(Crypt::decryptString(file_get_contents($request->file('backup')->getRealPath())), true, 512, JSON_THROW_ON_ERROR);
            if (($payload['format'] ?? null) !== 'propeller-data-v1' || array_keys($payload['tables'] ?? []) !== self::TABLES) {
                throw new \UnexpectedValueException;
            }
            foreach (self::TABLES as $table) {
                $columns = Schema::getColumnListing($table);
                if (! is_array($payload['tables'][$table]) || ! array_is_list($payload['tables'][$table])) {
                    throw new \UnexpectedValueException;
                }
                foreach ($payload['tables'][$table] as $row) {
                    if (! is_array($row) || count($row) !== count($columns) || array_diff($columns, array_keys($row))) {
                        throw new \UnexpectedValueException;
                    }
                    foreach ($row as $value) {
                        if (! is_scalar($value) && $value !== null) {
                            throw new \UnexpectedValueException;
                        }
                    }
                }
            }
            if (! collect($payload['tables']['users'])->contains(fn (array $user): bool => ($user['role'] ?? null) === 'admin')) {
                throw new \UnexpectedValueException;
            }
        } catch (Throwable) {
            throw ValidationException::withMessages(['backup' => 'This backup is invalid, incompatible, or was created with a different application encryption key. Upload a data backup downloaded from this installation. SQL files cannot be uploaded.']);
        }

        try {
            DB::transaction(function () use ($payload): void {
                $path = 'backups/before-restore-'.now()->format('Y-m-d-His').'-'.Str::uuid().'.backup';
                if (! Storage::disk('local')->put($path, $this->snapshot())) {
                    throw new \RuntimeException('Unable to save the recovery backup.');
                }
                foreach (array_reverse(self::TABLES) as $table) {
                    DB::table($table)->delete();
                }
                foreach (self::TABLES as $table) {
                    foreach ($payload['tables'][$table] as $row) {
                        if ($table === 'users') {
                            $row['remember_token'] = Str::random(60);
                        }
                        DB::table($table)->insert($row);
                    }
                }
                DB::table('sessions')->delete();
                DB::table('password_reset_tokens')->delete();
            });
        } catch (Throwable) {
            throw ValidationException::withMessages(['backup' => 'Restore failed. Your previous records have been kept. Check that the backup matches this application version and that storage is writable.']);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Backup restored. Sign in with an account and password from the restored backup.');
    }

    private function snapshot(): string
    {
        $tables = [];
        foreach (self::TABLES as $table) {
            $tables[$table] = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all();
        }

        return Crypt::encryptString(json_encode(['format' => 'propeller-data-v1', 'created_at' => now()->toIso8601String(), 'tables' => $tables], JSON_THROW_ON_ERROR));
    }
}
