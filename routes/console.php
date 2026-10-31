<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('logistics:admin {email} {--name=Administrator}', function () {
    $password = $this->secret('Choose a password (at least 12 characters, letters and numbers)');
    if (! is_string($password) || strlen($password) < 12 || ! preg_match('/[a-zA-Z]/', $password) || ! preg_match('/[0-9]/', $password)) {
        $this->error('Use at least 12 characters with letters and numbers.');

        return 1;
    }
    if (! filter_var($this->argument('email'), FILTER_VALIDATE_EMAIL) || User::where('email', $this->argument('email'))->exists()) {
        $this->error('Provide a valid, unused email address.');

        return 1;
    }
    $user = new User(['name' => $this->option('name'), 'email' => $this->argument('email'), 'password' => Hash::make($password)]);
    $user->role = 'admin';
    $user->save();
    $this->info('Administrator created.');

    return 0;
})->purpose('Securely create the first logistics administrator');
