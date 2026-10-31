<?php

namespace App\Providers;

use App\Models\CompanySetting;
use App\Models\EmailSetting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('layout', function (View $view): void {
            $view->with('branding', CompanySetting::find(1));
        });
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $base = EmailSetting::find(1)?->application_url ?? config('app.url');

            return rtrim($base, '/').'/reset-password/'.$token.'?email='.rawurlencode($user->getEmailForPasswordReset());
        });
        Gate::define('admin', fn (User $user): bool => $user->role === 'admin');
    }
}
