<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Member;
use App\Models\CheckIn;
use Illuminate\Support\Facades\DB; // Needed for the math

class StatsOverview extends BaseWidget
{
    // 1. Enable Auto-Refresh (Every 15 seconds)
    // This makes the "People Inside Now" count feel real-time.
    protected static ?string $pollingInterval = '2s';

    protected function getStats(): array
    {
        // --- CALCULATION 1: PEOPLE INSIDE NOW ---
        // Logic: Checked in within last 12h AND hasn't checked out.
        $liveCount = CheckIn::whereNull('check_out_at')
            ->where('created_at', '>=', now()->subHours(12))
            ->count();

        // --- CALCULATION 2: AVG SESSION DURATION ---
        // Logic: Average difference between In/Out for last 30 days
        $avgMinutes = CheckIn::whereNotNull('check_out_at')
            ->where('created_at', '>=', now()->subDays(30)) 
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, check_out_at)) as avg_duration'))
            ->value('avg_duration');

        // Format the duration (e.g., 85 mins -> "1h 25m")
        $formattedDuration = '0m';
        if ($avgMinutes) {
            $hours = floor($avgMinutes / 60);
            $minutes = round($avgMinutes % 60);
            $formattedDuration = $hours > 0 
                ? "{$hours}h {$minutes}m" 
                : "{$minutes}m";
        }

        // --- RETURN THE MERGED STATS ---
        return [
            // 1. The "Pulse" of the gym (Most important operational stat)
            Stat::make('People Inside Now', $liveCount)
                ->description('Current occupancy')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($liveCount > 20 ? 'danger' : 'success') // Red if crowded
                ->chart([$liveCount, $liveCount, $liveCount]), // Visual accent

            // 2. Your Traffic Volume
            Stat::make('Check-ins Today', CheckIn::whereDate('created_at', today())->count())
                ->description('Total foot traffic')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),

            // 3. Behavioral Stat
            Stat::make('Avg. Workout Time', $formattedDuration)
                ->description('Based on last 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),

            // 4. Business Health (Renamed to avoid confusion with "People Inside")
            Stat::make('Active Subscriptions', Member::where('membership_expiry_date', '>', now())->count())
                ->description('Paying members')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
        ];
    }
}