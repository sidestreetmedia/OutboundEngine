<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProofController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WinsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Positive replies + push-to-HubSpot toggle.
Route::get('/wins', [WinsController::class, 'index'])->name('wins.index');
Route::post('/wins/{lead}/hubspot', [WinsController::class, 'push'])->name('wins.push');

Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

// Public, per-prospect proof page linked from cold emails.
Route::get('/p/{token}', [ProofController::class, 'show'])->name('proof.show');
