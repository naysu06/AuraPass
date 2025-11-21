<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
//IMPORT YOUR CUSTOM WIDGETS HERE
use App\Filament\Widgets\RecentAccessLog; 
use App\Filament\Widgets\ExpiringMembers;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\PeakHoursChart;
use App\Filament\Widgets\DailyVisitsChart;
use App\Filament\Widgets\LatestCheckIns;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandName('AuraPass')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                RecentAccessLog::class, // <--- Uses the imported class above

                // Row 2: The Stats Row
                StatsOverview::class,

                // Row 3: Charts
                PeakHoursChart::class,
                DailyVisitsChart::class,

                // Row 4: Task List
                ExpiringMembers::class,
                //Widgets\FilamentInfoWidget::class, // Disabled to reduce dashboard clutter
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Enable the bell icon and make it check every 2 seconds
            ->databaseNotifications()
            ->databaseNotificationsPolling('2s');
    }
}
