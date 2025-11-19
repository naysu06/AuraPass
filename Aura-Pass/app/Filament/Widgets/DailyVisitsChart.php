<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class DailyVisitsChart extends ChartWidget
{
    protected static ?string $heading = 'Daily Visits Comparison';
    protected static ?int $sort = 3;

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 Days',
            '30' => 'Last 30 Days',
            '90' => 'Last 90 Days',
        ];
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter ?? '7';
        $days = (int) $activeFilter;

        // --- QUERY 1: CURRENT PERIOD (e.g., Nov 1 to Nov 30) ---
        $currentData = CheckIn::select('created_at')
            ->where('created_at', '>=', now()->subDays($days))
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)
                    ->setTimezone('Asia/Manila')
                    ->format('Y-m-d');
            });

        // --- QUERY 2: PREVIOUS PERIOD (e.g., Oct 1 to Oct 30) ---
        $previousData = CheckIn::select('created_at')
            ->where('created_at', '>=', now()->subDays($days * 2)) // Look back 60 days total
            ->where('created_at', '<', now()->subDays($days))    // Stop before the current period starts
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)
                    ->setTimezone('Asia/Manila')
                    ->format('Y-m-d');
            });

        // --- PREPARE ARRAYS ---
        $currentCounts = [];
        $previousCounts = [];
        $labels = [];

        // Loop backwards (Day 29 ... Day 0)
        for ($i = $days - 1; $i >= 0; $i--) {
            // 1. Define the Two Dates we are comparing
            $currentDate = now()->subDays($i);
            $previousDate = now()->subDays($i + $days); // Shift back by one full period

            // 2. Generate Keys for Lookup
            $cKey = $currentDate->format('Y-m-d');
            $pKey = $previousDate->format('Y-m-d');

            // 3. Get Counts
            $currentCounts[] = isset($currentData[$cKey]) ? $currentData[$cKey]->count() : 0;
            $previousCounts[] = isset($previousData[$pKey]) ? $previousData[$pKey]->count() : 0;

            // 4. Label (Use the Current Date)
            $labels[] = $currentDate->format('M d'); 
        }

        return [
            'datasets' => [
                [
                    'label' => 'Current Period',
                    'data' => $currentCounts,
                    'borderColor' => '#10b981', // Green (Success)
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)', 
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Previous Period',
                    'data' => $previousCounts,
                    'borderColor' => '#9ca3af', // Gray (Neutral)
                    'borderDash' => [5, 5],     // <-- MAKES IT DOTTED LINE
                    'backgroundColor' => 'rgba(0,0,0,0)', // No fill
                    'fill' => false,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}