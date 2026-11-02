<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Encryption\Encrypter;
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

    public function download(Request $request): Response
    {
        $request->validate(['backup_password' => ['nullable', 'string', 'min:12', 'max:128', 'confirmed']]);
        $backup = DB::transaction(fn (): string => $this->snapshot($request->filled('backup_password') ? $request->string('backup_password')->toString() : null));

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
            'backup_password' => ['nullable', 'string', 'max:128'],
            'backup' => ['required', 'file', 'max:10240'],
            'confirm_restore' => ['accepted'],
        ]);

        try {
            $contents = file_get_contents($request->file('backup')->getRealPath());
            $envelope = json_decode($contents, true);
            $portable = is_array($envelope) && ($envelope['format'] ?? null) === 'propeller-portable-v1';
            if ($portable) {
                $salt = base64_decode($envelope['salt'] ?? '', true);
                if (! is_string($salt) || strlen($salt) !== 16 || ! $request->filled('backup_password')) {
                    throw new \UnexpectedValueException;
                }
                $json = $this->portableEncrypter($request->string('backup_password')->toString(), $salt)->decryptString($envelope['data'] ?? '');
            } else {
                $json = Crypt::decryptString($contents);
            }
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (($payload['format'] ?? null) !== ($portable ? 'propeller-data-v2' : 'propeller-data-v1') || array_keys($payload['tables'] ?? []) !== self::TABLES) {
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
            throw ValidationException::withMessages(['backup' => 'Unable to open this backup. Check the backup password and application version. Older backups only work with their original application key; download a new password-protected backup from the source installation to transfer it. SQL files cannot be uploaded.']);
        }

        try {
            DB::transaction(function () use ($payload, $portable): void {
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
                        if ($portable && $table === 'email_settings') {
                            $row['password'] = $row['password'] === null ? null : Crypt::encryptString($row['password']);
                            $row['application_url'] = rtrim(config('app.url'), '/');
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

    private function snapshot(?string $password = null): string
    {
        $tables = [];
        foreach (self::TABLES as $table) {
            $tables[$table] = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all();
        }

        if ($password !== null) {
            foreach ($tables['email_settings'] as &$setting) {
                $setting['password'] = $setting['password'] === null ? null : Crypt::decryptString($setting['password']);
            }
            unset($setting);
        }
        $json = json_encode(['format' => $password === null ? 'propeller-data-v1' : 'propeller-data-v2', 'created_at' => now()->toIso8601String(), 'tables' => $tables], JSON_THROW_ON_ERROR);
        if ($password === null) {
            return Crypt::encryptString($json);
        }
        $salt = random_bytes(16);

        return json_encode(['format' => 'propeller-portable-v1', 'salt' => base64_encode($salt), 'data' => $this->portableEncrypter($password, $salt)->encryptString($json)], JSON_THROW_ON_ERROR);
    }

    private function portableEncrypter(#[\SensitiveParameter] string $password, string $salt): Encrypter
    {
        return new Encrypter(hash_pbkdf2('sha256', $password, $salt, 600000, 32, true), 'aes-256-cbc');
    }
}
