<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProofController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

// Public, per-prospect proof page linked from cold emails.
Route::get('/p/{token}', [ProofController::class, 'show'])->name('proof.show');
