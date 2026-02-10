<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString; // For coloring the description

class DailyVisitsChart extends ChartWidget
{
    protected static ?string $heading = 'Daily Visits Comparison';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 Days',
            '14' => 'Last 14 Days',
            '30' => 'Last 30 Days',
        ];
    }

    // 1. DYNAMIC DESCRIPTION: Calculates the % Change
    // FIX: Changed '?string|HtmlString' to 'string|HtmlString|null'
    public function getDescription(): string|HtmlString|null
    {
        $activeFilter = $this->filter ?? '7';
        $days = (int) $activeFilter;

        // Calculate Totals directly (Faster than looping)
        $currentCount = CheckIn::where('created_at', '>=', now()->subDays($days))->count();
        
        $previousCount = CheckIn::where('created_at', '>=', now()->subDays($days * 2))
            ->where('created_at', '<', now()->subDays($days))
            ->count();

        // Avoid division by zero
        $growth = 0;
        if ($previousCount > 0) {
            $growth = (($currentCount - $previousCount) / $previousCount) * 100;
        } elseif ($currentCount > 0) {
            $growth = 100; // 100% growth if previous was 0
        }

        // Format: +12.5% or -5.0%
        $formattedGrowth = number_format(abs($growth), 1) . '%';
        $direction = $growth >= 0 ? 'increase' : 'decrease';
        $icon = $growth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $color = $growth >= 0 ? 'text-success-500' : 'text-danger-500'; // Filament standard colors
        $word = $growth >= 0 ? 'Increase' : 'Decrease';

        // Return styled HTML
        return new HtmlString("
            <div class='flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400'>
                <span>Total: <span class='font-bold'>{$currentCount}</span> check-ins</span>
                <span class='{$color} font-bold flex items-center'>
                    ({$formattedGrowth} {$word})
                </span>
                <span class='text-xs text-gray-400'>vs previous {$days} days</span>
            </div>
        ");
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter ?? '7';
        $days = (int) $activeFilter;

        // --- FETCH DATA ---
        $currentData = CheckIn::select('created_at')
            ->where('created_at', '>=', now()->subDays($days))
            ->get()
            ->groupBy(fn ($date) => Carbon::parse($date->created_at)->setTimezone('Asia/Manila')->format('Y-m-d'));

        $previousData = CheckIn::select('created_at')
            ->where('created_at', '>=', now()->subDays($days * 2)) 
            ->where('created_at', '<', now()->subDays($days))
            ->get()
            ->groupBy(fn ($date) => Carbon::parse($date->created_at)->setTimezone('Asia/Manila')->format('Y-m-d'));

        $currentCounts = [];
        $previousCounts = [];
        $labels = [];

        // Loop backwards (e.g., 6 days ago -> Today)
        for ($i = $days - 1; $i >= 0; $i--) {
            $dateCurrent = now()->subDays($i);
            $datePrevious = now()->subDays($i + $days);

            $cKey = $dateCurrent->format('Y-m-d');
            $pKey = $datePrevious->format('Y-m-d');

            $currentCounts[] = isset($currentData[$cKey]) ? $currentData[$cKey]->count() : 0;
            $previousCounts[] = isset($previousData[$pKey]) ? $previousData[$pKey]->count() : 0;

            $labels[] = $dateCurrent->format('D M d'); 
        }

        return [
            'datasets' => [
                [
                    'label' => 'Current Period',
                    'data' => $currentCounts,
                    'backgroundColor' => '#3B82F6', // Blue
                    'borderColor' => '#3B82F6',
                    'barPercentage' => 0.7,
                ],
                [
                    // Rename label to indicate it's an offset comparison
                    'label' => "Prior {$days} Days", 
                    'data' => $previousCounts,
                    'backgroundColor' => '#9CA3AF', // Gray
                    'borderColor' => '#9CA3AF',
                    'barPercentage' => 0.7,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
    
    protected function getOptions(): array
    {
        return [
            'interaction' => [
                'intersect' => false,
                'mode' => 'index', // <--- FIX: Shows BOTH bars in one tooltip
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['display' => true, 'borderDash' => [2, 2]],
                    'ticks' => ['stepSize' => 1],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom', // Move legend to bottom for cleaner look
                ],
            ],
        ];
    }
}