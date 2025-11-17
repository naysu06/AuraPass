<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\CheckIn;
use Illuminate\Support\Facades\DB;

class CheckInsChart extends ChartWidget
{
    protected static ?string $heading = 'Check-ins Over Time'; // Generic heading
    protected static ?int $sort = 1;

    /**
     * 1. Add the filter dropdown options
     */
    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 Days',
            '14' => 'Last 14 Days',
            '30' => 'Last 30 Days',
        ];
    }

    protected function getData(): array
    {
        // 2. Get the active filter (default to 7 days if none selected)
        $activeFilter = $this->filter ?? '7';
        $daysToLookBack = (int) $activeFilter;

        // 3. Query data based on the selected timeframe
        $data = CheckIn::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays($daysToLookBack))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Check-ins',
                    'data' => $data->pluck('count'),
                    'backgroundColor' => '#36A2EB',
                    'borderColor' => '#9BD0F5',
                    'borderWidth' => 1,
                ],
            ],
            // Format the dates nicely (e.g., "Nov 12")
            'labels' => $data->pluck('date')->map(fn ($date) => date('M d', strtotime($date))),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}