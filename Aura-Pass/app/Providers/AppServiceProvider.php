<?php

namespace App\Providers;

use App\Models\Member;
use App\Observers\MemberObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use App\Services\AuditLogService;
use App\Models\User;
use App\Observers\UserObserver;

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
        // Keep your existing Member Observer
        Member::observe(MemberObserver::class);
        User::observe(UserObserver::class);

        // Listen for any user logging out of the application
        Event::listen(function (Logout $event) {
            if ($event->user) {
                $username = $event->user->username ?? 'Unknown';
                
                // Pass parameters strictly by position to avoid named argument conflicts:
                // 1. Activity String
                // 2. Model (?Model = null)
                // 3. Details Array
                // 4. Forced User ID
                app(AuditLogService::class)->logActivity(
                    'admin.logged_out',
                    null,
                    ['username' => $username],
                    $event->user->id
                );
            }
        });
    }
}