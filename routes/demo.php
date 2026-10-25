<?php

// Demonstration mode routes for ORWeMS Monday showcase
// These routes are isolated from production RBAC/auth
// Demo credentials are file-based; data is synthetic
// All screens bear the "DEMONSTRATION MODE" banner

use App\Http\Controllers\DemoAuthController;
use App\Http\Controllers\DemoDashboardController;
use App\Http\Controllers\DemoBeneficiaryController;
use App\Http\Controllers\DemoCareController;
use App\Http\Controllers\DemoEducationController;
use App\Http\Controllers\DemoHealthController;

Route::middleware(['web', 'demo.session'])->prefix('demo')
    ->name('demo.')
    ->group(function () {

        // Demo login page (guest allowed).
        Route::get('login', [DemoAuthController::class, 'loginView'])->name('login');
        Route::post('login', [DemoAuthController::class, 'login'])->name('login.post')->middleware('throttle:5,1');
        Route::post('logout', [DemoAuthController::class, 'logout'])->name('logout');

        // Dashboard.
        Route::get('/', [DemoDashboardController::class, 'index'])->name('dashboard');

        // Beneficiary profile.
        Route::get('beneficiaries/{beneficiary_ref}', [DemoBeneficiaryController::class, 'show'])->name('beneficiary');

        // Care / placement history.
        Route::get('care/{beneficiary_ref}', [DemoCareController::class, 'index'])->name('care.index');

        // Education history.
        Route::get('education/{beneficiary_ref}', [DemoEducationController::class, 'index'])->name('education.index');

        // Health record listing – synthetic only.
        Route::get('health/{beneficiary_ref}', [DemoHealthController::class, 'index'])->name('health.index');

        // Health record create – simulation only.
        Route::get('health/{beneficiary_ref}/create', [DemoHealthController::class, 'create'])->name('health.create');
        Route::post('health/{beneficiary_ref}', [DemoHealthController::class, 'store'])->name('health.store');

        // Relationship management – synthetic.
        Route::get('relationships/{beneficiary_ref}', [DemoCareController::class, 'relationships'])->name('relationships.index');
        Route::post('relationships/{beneficiary_ref}', [DemoCareController::class, 'storeRelationship'])->name('relationships.store');

        // Close workflow – synthetic simulation.
        Route::get('close/{beneficiary_ref}', [DemoHealthController::class, 'close'])->name('close');
        Route::post('close/{beneficiary_ref}', [DemoHealthController::class, 'confirmClose'])->name('close.confirm');
    });