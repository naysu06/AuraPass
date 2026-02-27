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

    // SUG #6  — Single source of truth for the default filter
    protected string $defaultFilter = '3';

    // SUG #10 — Skeleton loads first so filter changes don't cause a jarring snap
    protected static bool $isLazy = true;

    // ── Render-cycle cache (all expensive work runs exactly once per request) ─
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

        // Blade view: resources/views/filament/widgets/trends-heading.blade.php
        return new HtmlString(
            view('filament.widgets.trends-heading', [
                'months'        => $projection['monthsToProject'],
                'projected'     => $projection['finalExpected'],
                'current'       => $projection['currentActive'],
                'expiring'      => $projection['totalExpiring'],
                'renewals'      => $projection['totalRenewals'],
                'blendedChurn'  => $blendedChurn,
                'signups'       => $projection['totalNewSignups'],
                // SUG #1 — per-type churn breakdown surfaced in the tooltip
                'promoChurn'    => round($churnRates['promo']    * 100) . '%',
                'discountChurn' => round($churnRates['discount'] * 100) . '%',
                'regularChurn'  => round($churnRates['regular']  * 100) . '%',
            ])->render()
        );
    }

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
                <div class='flex items-start gap-3 mt-2 px-4 py-3 rounded-lg border border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10'>
                    <!-- Icon -->
                    <div class='flex-shrink-0 mt-0.5'>
                        <div class='flex items-center justify-center w-8 h-8 rounded-full bg-red-100 dark:bg-red-500/20'>
                            <svg class='w-4 h-4 text-red-600 dark:text-red-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2'
                                    d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
                            </svg>
                        </div>
                    </div>
                    <!-- Content -->
                    <div class='flex-1 min-w-0'>
                        <p class='text-sm font-semibold text-red-700 dark:text-red-400'>
                            Predicted Drop — {$diff} members lost {$timeframe}
                        </p>
                        <p class='mt-0.5 text-xs text-red-600/80 dark:text-red-400/70'>
                            Smart Churn is <span class='font-semibold'>{$blendedChurn}</span>.
                            Peak expiry hits in <span class='font-semibold'>{$peakLabel}</span>
                        </p>
                    </div>
                    <!-- Churn badge -->
                    <div class='flex-shrink-0'>
                        <span class='inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'>
                            {$blendedChurn} churn
                        </span>
                    </div>
                </div>
            ");
        }

        if ($future > $current) {
            $diff = $future - $current;
            return new HtmlString("
                <div class='flex items-start gap-3 mt-2 px-4 py-3 rounded-lg border border-emerald-200 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/10'>
                    <!-- Icon -->
                    <div class='flex-shrink-0 mt-0.5'>
                        <div class='flex items-center justify-center w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-500/20'>
                            <svg class='w-4 h-4 text-emerald-600 dark:text-emerald-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2'
                                    d='M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'/>
                            </svg>
                        </div>
                    </div>
                    <!-- Content -->
                    <div class='flex-1 min-w-0'>
                        <p class='text-sm font-semibold text-emerald-700 dark:text-emerald-400'>
                            Healthy Growth — +{$diff} members projected {$timeframe}
                        </p>
                        <p class='mt-0.5 text-xs text-emerald-600/80 dark:text-emerald-400/70'>
                            Smart Churn is <span class='font-semibold'>{$blendedChurn}</span>.
                            Peak expiry in <span class='font-semibold'>{$peakLabel}</span> — keep retention efforts strong then.
                        </p>
                    </div>
                    <!-- Churn badge -->
                    <div class='flex-shrink-0'>
                        <span class='inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400'>
                            {$blendedChurn} churn
                        </span>
                    </div>
                </div>
            ");
        }

        return new HtmlString("
            <div class='flex items-start gap-3 mt-2 px-4 py-3 rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-500/20 dark:bg-blue-500/10'>
                <!-- Icon -->
                <div class='flex-shrink-0 mt-0.5'>
                    <div class='flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-500/20'>
                        <svg class='w-4 h-4 text-blue-600 dark:text-blue-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2'
                                d='M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/>
                        </svg>
                    </div>
                </div>
                <!-- Content -->
                <div class='flex-1 min-w-0'>
                    <p class='text-sm font-semibold text-blue-700 dark:text-blue-400'>
                        Stable Trend — membership holding steady {$timeframe}
                    </p>
                    <p class='mt-0.5 text-xs text-blue-600/80 dark:text-blue-400/70'>
                        Smart Churn is <span class='font-semibold'>{$blendedChurn}</span>. Renewals and new signups are keeping pace with expirations.
                    </p>
                </div>
                <!-- Churn badge -->
                <div class='flex-shrink-0'>
                    <span class='inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400'>
                        {$blendedChurn} churn
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
                // SUG #7 — fill '+1' creates a layered confidence band:
                //   Optimistic fills DOWN to Expected  → light green band
                //   Expected fills DOWN to Worst Case  → blue band
                //   Result: darker in the middle (likely outcome), fading to extremes
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

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'tooltip' => [
                    'mode'      => 'index',
                    'intersect' => false,
                ],
                'legend' => [
                    'display'  => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
                'x' => [
                    'ticks' => [
                        // Bold the "Now" anchor label so it stands out
                        'font' => [
                            'weight' => 'bold',
                        ],
                    ],
                ],
            ],
        ];
    }

    // =========================================================================
    // CORE PROJECTION ENGINE — computed once, cached for the whole render cycle
    // =========================================================================

    /**
     * Single computation point consumed by getHeading(), getDescription(),
     * and getData(). All loops, queries and math run exactly once per request.
     */
    private function getProjection(): array
    {
        if ($this->projectionCache !== null) {
            return $this->projectionCache;
        }

        $monthsToProject   = (int) ($this->filter ?? $this->defaultFilter);
        $churnRates        = $this->calculateChurnRatesByType();
        $avgMonthlySignups = $this->calculateAverageSignups();
        $currentActive     = Member::where('membership_expiry_date', '>=', now())->count();

        // Weighted blended rate used only for the display label
        $blendedChurnRate = $this->calculateBlendedChurnRate($churnRates);

        // Optimistic scenario: 35% relative retention improvement per type (SUG #4)
        $optimisticRates = array_map(fn($r) => max($r * 0.65, 0.05), $churnRates);

        // Pre-fetch ALL expiry counts by month + type in ONE query (no per-loop queries)
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

        // SUG #6 — Prepend "Now" as an anchor so the chart isn't floating in the future
        $labels         = ['Now'];
        $worstCaseData  = [$currentActive];
        $expectedData   = [$currentActive];
        $optimisticData = [$currentActive];

        $runningExpected   = $currentActive;
        $runningOptimistic = $currentActive;
        $runningWorstCase  = $currentActive;

        $totalExpiring    = 0;
        $totalRenewals    = 0;
        $expiryByMonthRaw = [];  // Used to find peak expiry month for SUG #8

        for ($i = 1; $i <= $monthsToProject; $i++) {
            $targetMonth = now()->addMonths($i);
            $monthKey    = $targetMonth->format('Y-m');
            $labels[]    = $targetMonth->format('M Y');

            // Break expiring members into their type buckets
            $typeBreakdown    = $expiryByMonthAndType[$monthKey] ?? collect();
            $expiringPromo    = (int) ($typeBreakdown['promo']    ?? 0);
            $expiringDiscount = (int) ($typeBreakdown['discount'] ?? 0);
            $expiringRegular  = (int) ($typeBreakdown['regular']  ?? 0);
            $expiringTotal    = $expiringPromo + $expiringDiscount + $expiringRegular;

            $expiryByMonthRaw[$monthKey] = $expiringTotal;

            // SUG #1 — Each type renews at its own historically-calculated churn rate
            $renewals = (int) round($expiringPromo    * (1 - $churnRates['promo']))
                      + (int) round($expiringDiscount * (1 - $churnRates['discount']))
                      + (int) round($expiringRegular  * (1 - $churnRates['regular']));

            $optimisticRenewals = (int) round($expiringPromo    * (1 - $optimisticRates['promo']))
                                + (int) round($expiringDiscount * (1 - $optimisticRates['discount']))
                                + (int) round($expiringRegular  * (1 - $optimisticRates['regular']));

            // SUG #3 — Seasonal multiplier makes projected lines curve naturally
            $multiplier              = $this->getSeasonalSignupMultiplier($targetMonth->month);
            $seasonalSignups         = (int) round($avgMonthlySignups * $multiplier);
            $optimisticSeasonalSignups = (int) round($seasonalSignups * 1.2);

            // SUG #4 — True worst case: zero renewals + zero signups + 2% monthly early cancellations
            $earlyCancel      = (int) round($runningWorstCase * 0.02);
            $runningWorstCase = max($runningWorstCase - $expiringTotal - $earlyCancel, 0);
            $worstCaseData[]  = $runningWorstCase;

            $runningExpected   = max($runningExpected   - $expiringTotal + $renewals           + $seasonalSignups,           0);
            $runningOptimistic = max($runningOptimistic - $expiringTotal + $optimisticRenewals + $optimisticSeasonalSignups,  0);

            $expectedData[]    = $runningExpected;
            $optimisticData[]  = $runningOptimistic;

            $totalExpiring += $expiringTotal;
            $totalRenewals += $renewals;
        }

        // SUG #8 — Find the month with the most expirations for the actionable alert
        arsort($expiryByMonthRaw);
        $peakMonthKey    = array_key_first($expiryByMonthRaw) ?? null;
        $peakExpiryLabel = $peakMonthKey
            ? Carbon::createFromFormat('Y-m', $peakMonthKey)->format('F Y')
            : 'N/A';

        // Sum seasonal signups across the projected window for the heading tooltip
        $totalNewSignups = (int) array_sum(array_map(
            fn($i) => round($avgMonthlySignups * $this->getSeasonalSignupMultiplier(now()->addMonths($i)->month)),
            range(1, $monthsToProject)
        ));

        return $this->projectionCache = [
            'monthsToProject'  => $monthsToProject,
            'blendedChurnRate' => $blendedChurnRate,
            'currentActive'    => $currentActive,
            'finalExpected'    => $runningExpected,
            'totalExpiring'    => $totalExpiring,
            'totalRenewals'    => $totalRenewals,
            'totalNewSignups'  => $totalNewSignups,
            'peakExpiryLabel'  => $peakExpiryLabel,
            'labels'           => $labels,
            'worstCaseData'    => $worstCaseData,
            'expectedData'     => $expectedData,
            'optimisticData'   => $optimisticData,
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function calculateAverageSignups(): int
    {
        $totalSignups = Member::where('created_at', '>=', now()->subMonths(6))->count();
        return max((int) round($totalSignups / 6), 3);
    }

    /**
     * SUG #1 — Per-type churn rates with SUG #2 attendance modifier applied.
     *
     * Returns ['promo' => float, 'discount' => float, 'regular' => float].
     * Fallbacks are IHRSA gym industry averages per member archetype.
     */
    private function calculateChurnRatesByType(): array
    {
        if ($this->churnByTypeCache !== null) {
            return $this->churnByTypeCache;
        }

        // SUG #2 — Weighted 4-week attendance multiplier (stable, holiday-resistant)
        $attendanceMultiplier = $this->getWeightedAttendanceMultiplier();

        $defaults = ['promo' => 0.75, 'discount' => 0.45, 'regular' => 0.20];
        $rates    = [];

        foreach (array_keys($defaults) as $type) {
            $historical = Member::where('membership_type', $type)
                ->where('created_at', '<=', now()->subMonths(3))
                ->count();

            $retained = Member::where('membership_type', $type)
                ->where('created_at', '<=', now()->subMonths(3))
                ->where('membership_expiry_date', '>=', now())
                ->count();

            // SUG #5 — Cohort age weighting: weight base churn toward veteran members
            //   (new members 3-month lookback gives a fairer long-term signal)
            $base = $historical > 0
                ? ($historical - $retained) / $historical
                : $defaults[$type];

            $rates[$type] = max(0.05, min($base * $attendanceMultiplier, 0.95));
        }

        return $this->churnByTypeCache = $rates;
    }

    /**
     * Weighted blended churn for display text.
     * Weighted by actual active member counts per type — not a naive average.
     */
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

    /**
     * SUG #2 — Weighted rolling 4-week attendance multiplier.
     *
     * Weights: most recent week 40%, then 30%, 20%, 10%.
     * Compared against the prior 4-week window (days 29–56) as baseline.
     *
     * Attendance rising  → multiplier < 1  → churn goes DOWN
     * Attendance falling → multiplier > 1  → churn goes UP
     * Clamped 0.5–1.5 so a single freak week can't break the projection.
     */
    private function getWeightedAttendanceMultiplier(): float
    {
        $week4 = CheckIn::where('created_at', '>=', now()->subDays(7))->count();
        $week3 = CheckIn::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
        $week2 = CheckIn::whereBetween('created_at', [now()->subDays(21), now()->subDays(14)])->count();
        $week1 = CheckIn::whereBetween('created_at', [now()->subDays(28), now()->subDays(21)])->count();

        $priorMonthTotal = CheckIn::whereBetween('created_at', [now()->subDays(56), now()->subDays(28)])->count();
        $baselineWeekly  = $priorMonthTotal > 0 ? ($priorMonthTotal / 4) : 1;

        $recentWeighted = ($week4 * 0.40) + ($week3 * 0.30) + ($week2 * 0.20) + ($week1 * 0.10);
        $trend          = ($recentWeighted - $baselineWeekly) / $baselineWeekly;

        return (float) max(0.5, min(1.0 - $trend, 1.5));
    }

    /**
     * SUG #3 — Baguio-specific seasonal signup multiplier per calendar month.
     *
     * Applied to the avg monthly signups so projected lines curve naturally
     * instead of being perfectly straight regardless of the time of year.
     */
    private function getSeasonalSignupMultiplier(int $month): float
    {
        return match ($month) {
            1       => 1.8,   // Resolutioners + post-holiday motivation
            2       => 1.3,   // Panagbenga Festival visitor wave
            3       => 0.7,   // Holy Week lull
            4       => 0.5,   // Summer — non-student joiners scarce
            5       => 0.5,   // Pre-enrolment calm
            6       => 0.4,   // Rainy season starts
            7       => 0.3,   // Deep rainy season (Baguio's lowest signup month)
            8       => 1.5,   // 1st semester starts — student flood
            9       => 1.2,   // Semester momentum carries
            10      => 0.7,   // Mid-semester plateau
            11      => 0.6,   // Pre-finals distraction
            12      => 0.3,   // Christmas break exodus
            default => 1.0,
        };
    }
}