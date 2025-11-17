<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PeakHoursChart extends ChartWidget
{
    protected static ?string $heading = 'Peak Hours';
    protected static ?int $sort = 2;

    /**
     * 1. Define the filter options.
     * This adds a dropdown to the top-right of the widget.
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
        // 2. Get the active filter value. 
        // If it's null (on first load), default to '7'.
        $activeFilter = $this->filter ?? '7';

        // Convert to an integer for the query
        $daysToLookBack = (int) $activeFilter;

        // 3. Query the data based on the filter
        $data = CheckIn::select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays($daysToLookBack))
            ->groupBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        // 4. Fill in all 24 hours (0-23)
        $hours = [];
        $counts = [];
        
        for ($i = 0; $i < 24; $i++) {
            // Format label: "8 AM", "2 PM"
            $hours[] = date('g A', mktime($i, 0, 0, 1, 1));
            
            // Get count or 0 if no data
            $counts[] = $data[$i] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Peak Hours',
                    'data' => $counts,
                    // Single, solid color (Filament Primary Blue-ish)
                    'backgroundColor' => '#3B82F6', 
                    'borderColor' => '#2563EB',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $hours,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}