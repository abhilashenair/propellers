<?php

use App\Http\Controllers\AirwayBillController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BookingOptionController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailSettingController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WebsiteIntegrationController;
use Illuminate\Support\Facades\Route;

Route::get('/embed/tracking', [WebsiteIntegrationController::class, 'embed'])->middleware('throttle:30,1')->name('tracking.embed');
Route::get('/embed/company-logo', [CompanySettingController::class, 'logo'])->middleware('throttle:60,1')->name('tracking.embed-logo');
Route::get('/', [TrackingController::class, 'index'])->middleware('throttle:30,1')->name('tracking');
Route::get('/track', [TrackingController::class, 'index'])->middleware('throttle:30,1')->name('tracking.search');
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
    Route::get('/booking-options/search', [BookingOptionController::class, 'search'])->name('booking-options.search');
    Route::get('/company/logo', [CompanySettingController::class, 'logo'])->name('company.logo');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('customers', CustomerController::class)->except(['show', 'destroy']);
    Route::resource('vendors', VendorController::class)->except(['show', 'destroy']);
    Route::resource('bills', AirwayBillController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('shipments', ShipmentController::class)->only(['index', 'store', 'show']);
    Route::post('/shipments/{shipment:number}/updates', [TrackingController::class, 'store'])->name('updates.store');
    Route::middleware('can:admin')->group(function () {
        Route::get('/settings/website-integration', [WebsiteIntegrationController::class, 'index'])->name('website-integration.index');
        Route::get('/settings/email', [EmailSettingController::class, 'edit'])->name('email-settings.edit');
        Route::put('/settings/email', [EmailSettingController::class, 'update'])->name('email-settings.update');
        Route::resource('booking-options', BookingOptionController::class)->only(['index', 'store', 'update']);
        Route::get('/settings', [CompanySettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [CompanySettingController::class, 'update'])->name('settings.update');
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::post('/backup', BackupController::class)->middleware('throttle:2,1')->name('backup');
    });
});
