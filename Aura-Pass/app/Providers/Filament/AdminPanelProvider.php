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
use App\Filament\Widgets\AccessLog; 
use App\Filament\Widgets\ExpiringMembers;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\PeakHoursChart;
use App\Filament\Widgets\DailyVisitsChart;
use App\Filament\Widgets\DataAnalyticsHeader;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use App\Filament\Widgets\FutureTrendsChart;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class) // Use custom login page
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandName('AuraPass')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                StatsOverview::class,
                AccessLog::class,
                ExpiringMembers::class,
                DataAnalyticsHeader::class, 
                DailyVisitsChart::class,
                PeakHoursChart::class,
                FutureTrendsChart::class,
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
            ->databaseNotifications()
            ->databaseNotificationsPolling('2s')
            // DIRECT TABLE CSS: This targets the Table directly instead of the Widget wrapper
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString('
                    <style>
                        .custom-fixed-table {
                            height: 450px !important; 
                            display: flex !important;
                            flex-direction: column !important;
                        }
                        .custom-fixed-table .fi-ta {
                            flex: 1 1 0% !important;
                            display: flex !important;
                            flex-direction: column !important;
                        }
                        .custom-fixed-table .fi-ta-content {
                            flex: 1 1 0% !important; /* Forces inner content to absorb remaining space */
                            display: flex !important;
                            flex-direction: column !important;
                            overflow-y: auto !important;
                        }
                        .custom-fixed-table .fi-ta-content > table {
                            margin-bottom: auto !important; /* Pushes empty space to bottom */
                        }
                        .custom-fixed-table .fi-ta-empty-state {
                            margin: auto !important; /* Centers "No members" perfectly */
                        }
                    </style>
                ')
            );
    }
}