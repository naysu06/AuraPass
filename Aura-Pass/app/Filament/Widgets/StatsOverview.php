<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Member;
use App\Models\CheckIn;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Members', Member::where('membership_expiry_date', '>', now())->count()),
            Stat::make('Total Members', Member::count()),
            Stat::make('Check-ins Today', CheckIn::whereDate('created_at', today())->count()),
        ];
    }
}
