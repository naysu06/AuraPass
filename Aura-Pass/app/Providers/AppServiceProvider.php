<?php

namespace App\Providers;

use App\Models\Member;
use App\Observers\MemberObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use App\Services\AuditLogService;

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

        // Listen for any user logging out of the application
        Event::listen(function (Logout $event) {
            if ($event->user) {
                // Safely grab the username attribute from your User model
                $username = $event->user->username ?? 'Unknown';
                // Pass the user ID manually and append the username into the details array
                app(AuditLogService::class)->logActivity(
                    activity: 'admin.logged_out',
                    details: ['user_id' => $event->user->id]
                );
            }
        });
    }
}