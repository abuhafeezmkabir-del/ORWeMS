<?php

namespace App\Providers;

use App\Listeners\LogoutOtherSessionsAfterPasswordReset;
use App\Listeners\RecordLastLoginAt;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
        // Accountability hooks for Phase 3A. Both listen to framework events
        // rather than to controllers, so any future sign-in or reset path is
        // covered automatically.
        Event::listen(Authenticated::class, RecordLastLoginAt::class);
        Event::listen(PasswordReset::class, LogoutOtherSessionsAfterPasswordReset::class);
    }
}
