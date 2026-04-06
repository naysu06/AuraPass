<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use App\Models\CheckIn;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;

class FutureTrendsChart extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';
    protected static ?string $maxHeight    = '320px';

    protected string $defaultFilter = '3';
    protected static bool $isLazy   = true;

    // ── Render-cycle cache ────────────────────────────────────────────────────
    private ?array $projectionCache  = null;
    private ?array $churnByTypeCache = null;

    // =========================================================================
    // HEADING & DESCRIPTION
    // =========================================================================

    public function getHeading(): string|HtmlString|null
    {
        $projection   = $this->getProjection();
        $churnRates   = $this->calculateChurnRatesByType();
        $blendedChurn = round($projection['blendedChurnRate'] * 100) . '%';

        return new HtmlString(
            view('filament.widgets.trends-heading', [
                'months'        => $projection['monthsToProject'],
                'projected'     => $projection['finalExpected'],
                'current'       => $projection['currentActive'],
                'expiring'      => $projection['totalExpiring'],
                'renewals'      => $projection['totalRenewals'],
                'blendedChurn'  => $blendedChurn,
                'signups'       => $projection['totalNewSignups'],
                'promoChurn'    => round($churnRates['promo']    * 100) . '%',
                'discountChurn' => round($churnRates['discount'] * 100) . '%',
                'regularChurn'  => round($churnRates['regular']  * 100) . '%',
                'highRiskCount' => $projection['highRiskCount'],  
            ])->render()
        );
    }

    // NEW "COMMAND CENTER" SMART ALERT UI - WITH INLINE COLORS TO PREVENT CSS PURGING
    public function getDescription(): string|HtmlString|null
    {
        $projection   = $this->getProjection();
        $blendedChurn = round($projection['blendedChurnRate'] * 100) . '%';
        $current      = $projection['currentActive'];
        $future       = $projection['finalExpected'];
        $months       = $projection['monthsToProject'];
        $peakLabel    = $projection['peakExpiryLabel'];
        $timeframe    = $months === 1 ? 'next month' : "in {$months} months";

        if ($future < $current) {
            $diff = $current - $future;
            return new HtmlString("
                <div style='background-color: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2);' class='flex flex-col sm:flex-row sm:items-center justify-between gap-4 mt-4 px-6 py-4 rounded-xl shadow-sm'>
                    <div class='flex items-center gap-4'>
                        <div style='background-color: rgba(239, 68, 68, 0.15); color: #EF4444;' class='flex items-center justify-center w-10 h-10 rounded-full flex-shrink-0'>
                            <svg class='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
                            </svg>
                        </div>
                        <div>
                            <p class='text-sm font-bold text-gray-900 dark:text-white'>
                                Predicted Drop <span style='color: #EF4444;' class='font-medium ml-1'>— Losing {$diff} members {$timeframe}</span>
                            </p>
                            <p class='text-xs text-gray-600 dark:text-gray-400 mt-1'>
                                <strong class='text-gray-800 dark:text-gray-200'>Action:</strong> Peak expiry hits in {$peakLabel}. Run a renewal campaign ASAP.
                            </p>
                        </div>
                    </div>
                    <div class='flex-shrink-0 sm:ml-4'>
                        <span style='background-color: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #EF4444;' class='px-3 py-1.5 rounded-lg text-xs font-bold tracking-wide shadow-sm'>
                            {$blendedChurn} Churn
                        </span>
                    </div>
                </div>
            ");
        }

        if ($future > $current) {
            $diff = $future - $current;
            return new HtmlString("
                <div style='background-color: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2);' class='flex flex-col sm:flex-row sm:items-center justify-between gap-4 mt-4 px-6 py-4 rounded-xl shadow-sm'>
                    <div class='flex items-center gap-4'>
                        <div style='background-color: rgba(16, 185, 129, 0.15); color: #10B981;' class='flex items-center justify-center w-10 h-10 rounded-full flex-shrink-0'>
                            <svg class='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'/>
                            </svg>
                        </div>
                        <div>
                            <p class='text-sm font-bold text-gray-900 dark:text-white'>
                                Healthy Growth <span style='color: #10B981;' class='font-medium ml-1'>— +{$diff} members {$timeframe}</span>
                            </p>
                            <p class='text-xs text-gray-600 dark:text-gray-400 mt-1'>
                                <strong class='text-gray-800 dark:text-gray-200'>Action:</strong> Keep retention efforts strong through {$peakLabel}.
                            </p>
                        </div>
                    </div>
                    <div class='flex-shrink-0 sm:ml-4'>
                        <span style='background-color: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #10B981;' class='px-3 py-1.5 rounded-lg text-xs font-bold tracking-wide shadow-sm'>
                            {$blendedChurn} Churn
                        </span>
                    </div>
                </div>
            ");
        }

        return new HtmlString("
            <div style='background-color: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2);' class='flex flex-col sm:flex-row sm:items-center justify-between gap-4 mt-4 px-6 py-4 rounded-xl shadow-sm'>
                <div class='flex items-center gap-4'>
                    <div style='background-color: rgba(59, 130, 246, 0.15); color: #3B82F6;' class='flex items-center justify-center w-10 h-10 rounded-full flex-shrink-0'>
                        <svg class='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/>
                        </svg>
                    </div>
                    <div>
                        <p class='text-sm font-bold text-gray-900 dark:text-white'>
                            Stable Trend <span style='color: #3B82F6;' class='font-medium ml-1'>— Membership holding steady</span>
                        </p>
                        <p class='text-xs text-gray-600 dark:text-gray-400 mt-1'>
                            <strong class='text-gray-800 dark:text-gray-200'>Status:</strong> Renewals and signups are perfectly pacing expirations.
                        </p>
                    </div>
                </div>
                <div class='flex-shrink-0 sm:ml-4'>
                    <span style='background-color: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); color: #3B82F6;' class='px-3 py-1.5 rounded-lg text-xs font-bold tracking-wide shadow-sm'>
                        {$blendedChurn} Churn
                    </span>
                </div>
            </div>
        ");
    }

    // =========================================================================
    // CHART DATA
    // =========================================================================

    protected function getFilters(): ?array
    {
        return [
            '3'  => 'Next 3 Months',
            '6'  => 'Next 6 Months',
            '12' => 'Next 12 Months',
        ];
    }

    protected function getData(): array
    {
        $projection = $this->getProjection();

        return [
            'datasets' => [
                [
                    'label'           => 'Optimistic Goal',
                    'data'            => $projection['optimisticData'],
                    'borderColor'     => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.08)',
                    'fill'            => '+1',
                    'tension'         => 0.4,
                    'pointRadius'     => 3,
                ],
                [
                    'label'           => 'Expected Reality (Based on your true data)',
                    'data'            => $projection['expectedData'],
                    'borderColor'     => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'fill'            => '+1',
                    'tension'         => 0.4,
                    'pointRadius'     => 3,
                ],
                [
                    'label'       => 'Worst Case',
                    'data'        => $projection['worstCaseData'],
                    'borderColor' => '#EF4444',
                    'borderDash'  => [5, 5],
                    'fill'        => false,
                    'tension'     => 0.4,
                    'pointRadius' => 3,
                ],
            ],
            'labels' => $projection['labels'],
        ];
    }

    protected function getType(): string { return 'line'; }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'tooltip' => ['mode' => 'index', 'intersect' => false],
                'legend'  => ['display' => true, 'position' => 'bottom'],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
                'x' => ['ticks' => ['font' => ['weight' => 'bold']]],
            ],
        ];
    }

    // =========================================================================
    // CORE PROJECTION ENGINE
    // =========================================================================

    // This function generates a comprehensive projection of future membership trends based on historical data, current active members, 
    // and calculated churn rates.
    private function getProjection(): array
    {
        if ($this->projectionCache !== null) return $this->projectionCache;

        $monthsToProject   = (int) ($this->filter ?? $this->defaultFilter);
        $churnRates        = $this->calculateChurnRatesByType();
        $avgMonthlySignups = $this->calculateAverageSignups();
        $currentActive     = Member::where('membership_expiry_date', '>=', now())->count();
        $blendedChurnRate  = $this->calculateBlendedChurnRate($churnRates);
        $optimisticRates   = array_map(fn($r) => max($r * 0.65, 0.05), $churnRates);

        $highRiskCount = $this->countHighRiskMembers();

        $expiryByMonthAndType = Member::selectRaw("
                DATE_FORMAT(membership_expiry_date, '%Y-%m') AS ym,
                membership_type,
                COUNT(*) AS total
            ")
            ->where('membership_expiry_date', '>=', now()->startOfMonth())
            ->where('membership_expiry_date', '<=', now()->addMonths($monthsToProject)->endOfMonth())
            ->groupBy('ym', 'membership_type')
            ->get()
            ->groupBy('ym')
            ->map(fn($rows) => $rows->pluck('total', 'membership_type'));

        $labels         = ['Now'];
        $worstCaseData  = [$currentActive];
        $expectedData   = [$currentActive];
        $optimisticData = [$currentActive];

        $runningExpected   = $currentActive;
        $runningOptimistic = $currentActive;
        $runningWorstCase  = $currentActive;

        $totalExpiring    = 0;
        $totalRenewals    = 0;
        $expiryByMonthRaw = [];

        // Loop through each future month and calculate the projected active members based on expirations, renewals, and new signups. 
        for ($i = 1; $i <= $monthsToProject; $i++) {
            $targetMonth = now()->addMonths($i);
            $monthKey    = $targetMonth->format('Y-m');
            $labels[]    = $targetMonth->format('M Y');

            $typeBreakdown    = $expiryByMonthAndType[$monthKey] ?? collect();
            $expiringPromo    = (int) ($typeBreakdown['promo']    ?? 0);
            $expiringDiscount = (int) ($typeBreakdown['discount'] ?? 0);
            $expiringRegular  = (int) ($typeBreakdown['regular']  ?? 0);
            $expiringTotal    = $expiringPromo + $expiringDiscount + $expiringRegular;

            $expiryByMonthRaw[$monthKey] = $expiringTotal;

            $renewals = (int) round($expiringPromo    * (1 - $churnRates['promo']))
                      + (int) round($expiringDiscount * (1 - $churnRates['discount']))
                      + (int) round($expiringRegular  * (1 - $churnRates['regular']));

            $optimisticRenewals = (int) round($expiringPromo    * (1 - $optimisticRates['promo']))
                                + (int) round($expiringDiscount * (1 - $optimisticRates['discount']))
                                + (int) round($expiringRegular  * (1 - $optimisticRates['regular']));

            $multiplier              = $this->getSeasonalSignupMultiplier($targetMonth->month);
            $seasonalSignups         = (int) round($avgMonthlySignups * $multiplier);
            $optimisticSeasonalSignups = (int) round($seasonalSignups * 1.2);

            $earlyCancel      = (int) round($runningWorstCase * 0.02);
            $runningWorstCase = max($runningWorstCase - $expiringTotal - $earlyCancel, 0);
            $worstCaseData[]  = $runningWorstCase;

            $runningExpected   = max($runningExpected   - $expiringTotal + $renewals           + $seasonalSignups,          0);
            $runningOptimistic = max($runningOptimistic - $expiringTotal + $optimisticRenewals + $optimisticSeasonalSignups, 0);

            $expectedData[]   = $runningExpected;
            $optimisticData[] = $runningOptimistic;

            $totalExpiring += $expiringTotal;
            $totalRenewals += $renewals;
        }

        arsort($expiryByMonthRaw);
        $peakMonthKey    = array_key_first($expiryByMonthRaw) ?? null;
        $peakExpiryLabel = $peakMonthKey
            ? Carbon::createFromFormat('Y-m', $peakMonthKey)->format('F Y')
            : 'N/A';

        $totalNewSignups = (int) array_sum(array_map(
            fn($i) => round($avgMonthlySignups * $this->getSeasonalSignupMultiplier(now()->addMonths($i)->month)),
            range(1, $monthsToProject)
        ));

        // Cache the entire projection result for this render cycle to optimize performance.
        return $this->projectionCache = [
            'monthsToProject'  => $monthsToProject,
            'blendedChurnRate' => $blendedChurnRate,
            'currentActive'    => $currentActive,
            'finalExpected'    => $runningExpected,
            'totalExpiring'    => $totalExpiring,
            'totalRenewals'    => $totalRenewals,
            'totalNewSignups'  => $totalNewSignups,
            'peakExpiryLabel'  => $peakExpiryLabel,
            'highRiskCount'    => $highRiskCount,
            'labels'           => $labels,
            'worstCaseData'    => $worstCaseData,
            'expectedData'     => $expectedData,
            'optimisticData'   => $optimisticData,
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    // This function calculates the average monthly signups over the past 6 months, 
    private function calculateAverageSignups(): int
    {
        $monthsWithData = Member::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym")
            ->groupBy('ym')->get()->count();

        $lookback     = max(min($monthsWithData, 6), 1);
        $totalSignups = Member::where('created_at', '>=', now()->subMonths($lookback))->count();

        return max((int) round($totalSignups / $lookback), 3);
    }

    // This function calculates adjusted churn rates for each membership type, 
    // blending historical data with default benchmarks to account for small sample sizes.
    private function calculateChurnRatesByType(): array
    {
        if ($this->churnByTypeCache !== null) return $this->churnByTypeCache;
        $attendanceMultiplier = $this->getWeightedAttendanceMultiplier();
        $defaults = [
            'promo'    => 0.82,
            'discount' => 0.38,
            'regular'  => 0.29,
        ];
        $rates = [];

        foreach (array_keys($defaults) as $type) {
            $historical = Member::where('membership_type', $type)
                ->where('created_at', '<=', now()->subMonths(3))
                ->count();

            $retained = Member::where('membership_type', $type)
                ->where('created_at', '<=', now()->subMonths(3))
                ->where('membership_expiry_date', '>=', now())
                ->count();

            if ($historical >= 10) {
                $base = ($historical - $retained) / $historical;
            } elseif ($historical > 0) {
                $measured   = ($historical - $retained) / $historical;
                $confidence = $historical / 10;
                $base       = ($measured * $confidence) + ($defaults[$type] * (1 - $confidence));
            } else {
                $base = $defaults[$type];
            }

            $riskAdjustment = $this->getTypeRiskAdjustment($type);
            $base           = $base + ($riskAdjustment * 0.20);

            $rates[$type] = max(0.05, min($base * $attendanceMultiplier, 0.95));
        }

        return $this->churnByTypeCache = $rates;
    }

    // This function calculates a blended churn rate across all membership types, 
    // weighted by the current distribution of active members. 
    private function calculateBlendedChurnRate(array $churnRates): float
    {
        $typeCounts = Member::where('membership_expiry_date', '>=', now())
            ->selectRaw('membership_type, COUNT(*) as total')
            ->groupBy('membership_type')
            ->pluck('total', 'membership_type');

        $totalActive = $typeCounts->sum();
        if ($totalActive === 0) return 0.35;

        $blended = 0.0;
        foreach ($churnRates as $type => $rate) {
            $weight   = ($typeCounts[$type] ?? 0) / $totalActive;
            $blended += $rate * $weight;
        }

        return round($blended, 4);
    }

    // This function calculates a churn risk score for an individual member based on multiple factors. 
    private function getMemberChurnRiskScore(Member $member): float
    {
        $risk = 0.0;
        $lastVisit      = $member->checkIns()->latest('created_at')->value('created_at');
        $daysSinceVisit = $lastVisit ? now()->diffInDays($lastVisit) : 999;
        if ($daysSinceVisit >= 20)     $risk += 0.40;
        elseif ($daysSinceVisit >= 14) $risk += 0.25;
        elseif ($daysSinceVisit >= 7)  $risk += 0.10;

        $monthsOld = (int) $member->created_at->diffInMonths(now());
        if ($monthsOld <= 1)      $risk += 0.20;  
        elseif ($monthsOld <= 6)  $risk += 0.15;  
        elseif ($monthsOld >= 12) $risk -= 0.15;  

        $daysToExpiry = (int) now()->diffInDays($member->membership_expiry_date, false);
        if ($daysToExpiry <= 7)       $risk += 0.20;
        elseif ($daysToExpiry <= 14)  $risk += 0.10;

        $risk += match ($member->membership_type) {
            'promo'    => 0.20,
            'discount' => 0.05,
            default    => 0.0,
        };

        // Cap the final risk score between 0 and 1, and round to 4 decimal places for consistency.
        return (float) max(0.0, min($risk, 1.0));
    }

    // 
    private function countHighRiskMembers(): int
    {
        $activeMembers = Member::where('membership_expiry_date', '>=', now())
            ->with(['checkIns' => fn($q) => $q->latest('created_at')->limit(1)])
            ->get();

        return $activeMembers->filter(
            fn($m) => $this->getMemberChurnRiskScore($m) > 0.60
        )->count();
    }

    private function getTypeRiskAdjustment(string $type): float
    {
        $members = Member::where('membership_type', $type)
            ->where('membership_expiry_date', '>=', now())
            ->with(['checkIns' => fn($q) => $q->latest('created_at')->limit(1)])
            ->get();

        if ($members->isEmpty()) return 0.0;

        $avgRisk = $members->avg(fn($m) => $this->getMemberChurnRiskScore($m));

        return round($avgRisk - 0.5, 4); 
    }

    // This function calculates a multiplier based on recent attendance trends.
    private function getWeightedAttendanceMultiplier(): float
    {
        $week4 = CheckIn::where('created_at', '>=', now()->subDays(7))->count();
        $week3 = CheckIn::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
        $week2 = CheckIn::whereBetween('created_at', [now()->subDays(21), now()->subDays(14)])->count();
        $week1 = CheckIn::whereBetween('created_at', [now()->subDays(28), now()->subDays(21)])->count();
        // To establish a baseline, we look at the total check-ins from the prior month (28-56 days ago). 
        $priorMonthTotal = CheckIn::whereBetween('created_at', [now()->subDays(56), now()->subDays(28)])->count();
        $baselineWeekly  = $priorMonthTotal > 0 ? ($priorMonthTotal / 4) : 1; 
        $recentWeighted = ($week4 * 0.40) + ($week3 * 0.30) + ($week2 * 0.20) + ($week1 * 0.10);
        $trend = ($recentWeighted - $baselineWeekly) / $baselineWeekly;

        return (float) max(0.5, min(1.0 - $trend, 1.5));
    }

    // This function provides a seasonal multiplier for new signups based on the month of the year. 
    // If we have at least 12 months of historical data, it calculates the multiplier based on actual signup trends. 
    // If not, it falls back to predefined estimates.
    private function getSeasonalSignupMultiplier(int $month): float
    {
        $earliestSignup = Member::min('created_at');
        $monthsOfData   = $earliestSignup
            ? (int) Carbon::parse($earliestSignup)->diffInMonths(now())
            : 0;

        if ($monthsOfData >= 12) {
            return $this->getRealSeasonalMultiplier($month);
        }

        return match ($month) {
            1       => 1.80,  
            2       => 1.10,  
            3       => 0.70,  
            4       => 0.50,  
            5       => 0.50,  
            6       => 0.40,  
            7       => 0.30,  
            8       => 1.50,  
            9       => 1.20,  
            10      => 0.70,  
            11      => 0.45,  
            12      => 0.30,  
            default => 1.00,
        };
    }

    // This function calculates the actual seasonal multiplier for a given month based on historical signup data. 
    private function getRealSeasonalMultiplier(int $month): float
    {
        $signupsByMonth = Member::selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->where('created_at', '>=', now()->subYears(2))
            ->where('created_at', '<',  now()->subYear())  
            ->groupBy('month')
            ->pluck('total', 'month');

        if ($signupsByMonth->isEmpty()) return 1.0;

        $baseline  = $signupsByMonth->avg();
        $thisMonth = $signupsByMonth->get($month, $baseline);

        return $baseline > 0 ? round($thisMonth / $baseline, 2) : 1.0;
    }
}