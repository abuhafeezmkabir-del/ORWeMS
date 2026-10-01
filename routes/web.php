<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Public Landing Page
Route::get('/', function () {
    return view('landing');
})->name('home');

// Application Foundation Shell / Dashboard
//
// Protected from Phase 3A onward. `auth` requires a signed-in operator;
// `account.active` refuses an operator whose account has been disabled, so
// revoking access takes effect immediately rather than at session expiry.
// No role checks yet: the dashboard stays a neutral authenticated landing
// page until Phase 3B establishes authorization.
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'account.active'])->name('dashboard');

