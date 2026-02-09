<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class DailyVisitsChart extends ChartWidget
{
    protected static ?string $heading = 'Daily Visits Comparison';
    protected static ?int $sort = 2;

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
        $days = (int) $activeFilter;

        // --- 1. FETCH DATA (Using Collection Grouping for simplicity) ---
        // Fetch Current Period Data
        $currentData = CheckIn::select('created_at')
            ->where('created_at', '>=', now()->subDays($days))
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)
                    ->setTimezone('Asia/Manila')
                    ->format('Y-m-d');
            });

        // Fetch Previous Period Data (The period immediately before this one)
        $previousData = CheckIn::select('created_at')
            ->where('created_at', '>=', now()->subDays($days * 2)) 
            ->where('created_at', '<', now()->subDays($days))
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)
                    ->setTimezone('Asia/Manila')
                    ->format('Y-m-d');
            });

        // --- 2. ALIGN DATA ---
        $currentCounts = [];
        $previousCounts = [];
        $labels = [];

        // Loop backwards (e.g., 6 days ago -> Today)
        for ($i = $days - 1; $i >= 0; $i--) {
            // Calculate specific dates
            $dateCurrent = now()->subDays($i);
            $datePrevious = now()->subDays($i + $days); // Shift back 1 full cycle

            $cKey = $dateCurrent->format('Y-m-d');
            $pKey = $datePrevious->format('Y-m-d');

            // Retrieve counts or 0
            $currentCounts[] = isset($currentData[$cKey]) ? $currentData[$cKey]->count() : 0;
            $previousCounts[] = isset($previousData[$pKey]) ? $previousData[$pKey]->count() : 0;

            // Label: "Mon (Nov 20)"
            $labels[] = $dateCurrent->format('D M d'); 
        }

        // --- 3. RETURN BAR CHART CONFIG ---
        return [
            'datasets' => [
                [
                    'label' => 'Current Period',
                    'data' => $currentCounts,
                    'backgroundColor' => '#3B82F6', // Bright Blue
                    'borderColor' => '#3B82F6',
                    'barPercentage' => 0.6, // Makes bars thinner/cleaner
                    'categoryPercentage' => 0.8,
                ],
                [
                    'label' => 'Previous Period',
                    'data' => $previousCounts,
                    'backgroundColor' => '#9CA3AF', // Muted Gray (Context)
                    'borderColor' => '#9CA3AF',
                    'barPercentage' => 0.6,
                    'categoryPercentage' => 0.8,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // <--- Changed from 'line' to 'bar'
    }
    
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => true,
                        'borderDash' => [2, 2], // Subtle grid lines
                    ],
                    'ticks' => [
                        'stepSize' => 1, // Integers only
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false, // Remove vertical clutter
                    ],
                ],
            ],
        ];
    }
}