<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PeakHoursChart extends ChartWidget
{
    protected static ?string $heading = 'Peak Hours';

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
        $activeFilter = $this->filter ?? '7';
        $daysToLookBack = (int) $activeFilter;

        // 1. Get all raw records (No changes here)
        $records = CheckIn::where('created_at', '>=', now()->subDays($daysToLookBack))->get();

        // 2. Fill buckets for 0-23 (Keep this as is, it's easier for mapping)
        $hours = array_fill(0, 24, 0);

        // 3. Sort into buckets (No changes here)
        foreach ($records as $record) {
            $hour = $record->created_at->setTimezone('Asia/Manila')->hour;
            $hours[$hour]++;
        }

        // 4. PREPARE DISPLAY DATA
        $averages = [];
        $labels = [];

        // <--- CHANGED: Loop from 6 (6 AM) to 21 (9 PM) only --->
        for ($i = 6; $i <= 21; $i++) {
            $totalCount = $hours[$i];

            // Average Logic
            if ($totalCount > 0) {
                $averages[] = ceil($totalCount / $daysToLookBack); 
            } else {
                $averages[] = 0;
            }

            // Labels
            $labels[] = date('g A', mktime($i, 0, 0, 1, 1));
        }

        return [
            'datasets' => [
                [
                    'label' => 'Avg. Check-Ins',
                    'data' => $averages,
                    'backgroundColor' => '#3B82F6', 
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'grid' => [
                        'display' => false, // Hides horizontal grid lines
                    ],
                    'ticks' => [
                        'stepSize' => 5, // Integers only, no decimals for people
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false, // Hides vertical grid lines
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}