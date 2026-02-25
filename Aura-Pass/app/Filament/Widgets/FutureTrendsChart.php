<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class FutureTrendsChart extends ChartWidget
{
    protected static ?string $heading = 'Future Trends & Retention';

    protected static ?string $description = 'Projected active members factoring in estimated churn rates and new signups.';

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '320px';

    protected function getFilters(): ?array
    {
        return [
            '3' => 'Next 3 Months',
            '6' => 'Next 6 Months',
            '12' => 'Next 12 Months',
        ];
    }

    protected function getData(): array
    {
        // defaults to 3 months if no filter is selected
        $monthsToProject = (int) ($this->filter ?? 3);
        
        $months = [];
        $worstCaseData = [];
        $expectedData = [];
        $optimisticData = [];

        $sixMonthsAgo = now()->subMonths(6);
        $totalSignups = Member::where('created_at', '>=', $sixMonthsAgo)->count();
        $avgMonthlySignups = max(round($totalSignups / 6), 3); 

        $expectedRenewalRate = 0.65; 
        $optimisticRenewalRate = 0.85;

        $currentActiveMembers = Member::where('membership_expiry_date', '>=', now())->count();

        $runningExpected = $currentActiveMembers;
        $runningOptimistic = $currentActiveMembers;

        for ($i = 1; $i <= $monthsToProject; $i++) {
            $targetMonth = now()->addMonths($i);
            $months[] = $targetMonth->format('M Y');

            $activeWithoutRenewals = Member::where('membership_expiry_date', '>=', $targetMonth->endOfMonth())->count();
            $worstCaseData[] = $activeWithoutRenewals;

            $expiringThisMonth = Member::whereBetween('membership_expiry_date', [
                $targetMonth->copy()->startOfMonth(), 
                $targetMonth->copy()->endOfMonth()
            ])->count();

            $projectedRenewals = round($expiringThisMonth * $expectedRenewalRate);
            $projectedOptimisticRenewals = round($expiringThisMonth * $optimisticRenewalRate);

            $runningExpected = $runningExpected - $expiringThisMonth + $projectedRenewals + $avgMonthlySignups;
            $runningOptimistic = $runningOptimistic - $expiringThisMonth + $projectedOptimisticRenewals + round($avgMonthlySignups * 1.2); 

            $expectedData[] = max($runningExpected, 0); 
            $optimisticData[] = max($runningOptimistic, 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Optimistic Goal',
                    'data' => $optimisticData,
                    'borderColor' => '#10B981', 
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)', 
                    'fill' => true,
                    'tension' => 0.4, 
                ],
                [
                    'label' => 'Expected Reality',
                    'data' => $expectedData,
                    'borderColor' => '#3B82F6', 
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)', 
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Worst Case',
                    'data' => $worstCaseData,
                    'borderColor' => '#EF4444', 
                    'borderDash' => [5, 5], 
                    'fill' => false, 
                    'tension' => 0.4,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}