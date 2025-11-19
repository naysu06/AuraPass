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
        $activeFilter = $this->filter ?? '7';
        $daysToLookBack = (int) $activeFilter;

        // 1. Get all raw records from the database
        // We don't group by SQL anymore to avoid the CONVERT_TZ issue.
        $records = CheckIn::where('created_at', '>=', now()->subDays($daysToLookBack))
            ->get();

        // 2. Create an empty bucket for 24 hours (0 to 23)
        $hours = array_fill(0, 24, 0);

        // 3. Loop through records and sort them into hour buckets
        foreach ($records as $record) {
            // This converts the UTC database time to your local time automatically
            // assuming your config/app.php timezone is set to 'Asia/Manila'
            // If not, use: $record->created_at->setTimezone('Asia/Manila')->hour
            $hour = $record->created_at->setTimezone('Asia/Manila')->hour;
            
            $hours[$hour]++;
        }

        // 4. Prepare the chart data
        $averages = [];
        foreach ($hours as $totalCount) {
            // FIX FOR ROUNDING TRAP:
            // If we have data (totalCount > 0), we use ceil() to ensure we show at least 1.
            // Otherwise, 1 check-in divided by 7 days would be 0.
            if ($totalCount > 0) {
                // Example: 1 check-in / 7 days = 0.14 -> Becomes 1
                $averages[] = ceil($totalCount / $daysToLookBack); 
            } else {
                $averages[] = 0;
            }
        }
        
        // 5. Generate Labels (12 AM - 11 PM)
        $labels = [];
        for ($i = 0; $i < 24; $i++) {
            $labels[] = date('g A', mktime($i, 0, 0, 1, 1));
        }

        return [
            'datasets' => [
                [
                    'label' => 'Avg. Crowd Size',
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