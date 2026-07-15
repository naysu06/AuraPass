<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BasePage;
use Filament\Actions\Action;
use App\Models\Member;
use App\Models\CheckIn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Get;

class Dashboard extends BasePage
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportKpiSummary')
                ->label('Export KPI')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                // Add a form to the action button
                ->form([
                    Select::make('report_type')
                        ->label('Select Report Type')
                        ->options([
                            'daily' => 'Daily KPI',
                            'weekly' => 'Weekly KPI',
                            'monthly' => 'Monthly KPI',
                            'custom' => 'Custom Date Range',
                        ])
                        ->required()
                        ->live(), // Makes the form reactive
                    
                    // These only show up if 'custom' is selected!
                    DatePicker::make('start_date')
                        ->label('Start Date')
                        ->required()
                        ->hidden(fn (Get $get) => $get('report_type') !== 'custom')
                        // Only allow today and past dates
                        ->maxDate(now()->toDateString())
                        ->reactive(),

                    DatePicker::make('end_date')
                        ->label('End Date')
                        ->required()
                        ->hidden(fn (Get $get) => $get('report_type') !== 'custom')
                        // Ensure end date is not before the selected start date; default to today if start not set
                        ->minDate(fn (Get $get) => $get('start_date') ?? now()->toDateString())
                        ->maxDate(now()->toDateString())
                        ->reactive(),
                ])
                ->action(function (array $data) {
                    // Pass the user's form choices to our generator
                    return $this->generateKpiPdf($data);
                }),
        ];
    }

    private function generateKpiPdf(array $data)
    {
        try {
            $now = Carbon::now();
            $type = $data['report_type'];

            // If admin selected a custom range but chose the same past date for start and end,
            // treat it as a daily report for data selection purposes, but remember the chosen
            // date so we can label the report (and file) as a Custom Report for that specific date.
            //
            // If the collapsed date happens to be TODAY, treat it as an ordinary daily report
            // (live occupancy is still meaningful, and it's not really a "historical" pull).
            $customSingleDate = null;
            if ($type === 'custom' && !empty($data['start_date']) && !empty($data['end_date'])) {
                $startDate = Carbon::parse($data['start_date'])->toDateString();
                $endDate = Carbon::parse($data['end_date'])->toDateString();
                if ($startDate === $endDate) {
                    // switch logic to daily but preserve the selected date (unless it's today)
                    $type = 'daily';
                    $customSingleDate = $startDate === $now->toDateString() ? null : $startDate;
                }
            }
            
            $reportTitle = '';
            $sections = [];

            // =========================================================
            // 1. ROUTE THE LOGIC BASED ON REPORT TYPE
            // =========================================================
            switch ($type) {
                
                case 'daily':
                    // Anchor everything to the actual report date (today, or a collapsed
                    // custom single-date selection) instead of always using $now.
                    $reportDate = $customSingleDate ? Carbon::parse($customSingleDate) : $now->copy();

                    $reportTitle = $customSingleDate
                        ? 'Custom Report (' . $reportDate->format('F j, Y') . ')'
                        : 'Daily Report (' . $reportDate->format('F j, Y') . ')';

                    // "People Inside Now" only makes sense for a live/today report.
                    $liveCount = $customSingleDate
                        ? null
                        : CheckIn::whereNull('check_out_at')->where('created_at', '>=', $now->copy()->subHours(12))->count();

                    $todayCheckIns = CheckIn::whereDate('created_at', $reportDate->toDateString())->count();

                    $failedScans = \App\Models\AuditLog::whereDate('created_at', $reportDate->toDateString())
                        ->where('activity', 'like', 'member.scan_failed')
                        ->count();
                    
                    // Peak Hour for the report date
                    $todayRecords = CheckIn::whereDate('created_at', $reportDate->toDateString())->get();
                    $hoursBucket = array_fill(0, 24, 0);
                    foreach ($todayRecords as $record) {
                        $hour = $record->created_at->setTimezone('Asia/Manila')->hour;
                        $hoursBucket[$hour]++;
                    }
                    $peakHourRaw = 6; $maxCount = 0;
                    for ($i = 6; $i <= 21; $i++) {
                        if ($hoursBucket[$i] > $maxCount) { $maxCount = $hoursBucket[$i]; $peakHourRaw = $i; }
                    }
                    $peakHourLabel = $maxCount > 0 ? date('g A', mktime($peakHourRaw, 0, 0, 1, 1)) : 'N/A';

                    // Expiring on the report date
                    $expiringToday = Member::whereDate('membership_expiry_date', $reportDate->toDateString())->get();
                    
                    $sections['Live Operations'] = [
                        'People Inside Now (Occupancy)' => $liveCount ?? 'N/A (historical report)',
                        'Total Check-ins' => $todayCheckIns,
                        'Peak Operating Hour' => $peakHourLabel,
                    ];
                    $sections['Security & Access'] = [
                        'Total Failed QR Scans' => $failedScans,
                    ];
                    
                    $expBadge = $expiringToday->count() > 0 ? "<span class='badge badge-danger'>{$expiringToday->count()}</span>" : "0";
                    $sections['Immediate Action Items'] = [
                        'Members Expiring' => $expBadge,
                    ];
                    if ($expiringToday->count() > 0) {
                        $sections['Immediate Action Items']['-> Member IDs:'] = $expiringToday->pluck('unique_id')->implode(', ');
                    }
                    break;

                case 'weekly':
                    $reportTitle = 'Weekly Report' . ' (' . $now->copy()->subDays(6)->format('M d') . ' - ' . $now->format('M d, Y') . ')';
                    
                    $last7DaysStart = $now->copy()->subDays(6)->startOfDay();
                    $prior7DaysStart = $now->copy()->subDays(13)->startOfDay();
                    
                    $currentWeekCount = CheckIn::whereBetween('created_at', [$last7DaysStart, $now->copy()->endOfDay()])->count();
                    $priorWeekCount = CheckIn::whereBetween('created_at', [$prior7DaysStart, $last7DaysStart->copy()->subSecond()])->count();
                    
                    $growth = $priorWeekCount > 0 ? (($currentWeekCount - $priorWeekCount) / $priorWeekCount) * 100 : ($currentWeekCount > 0 ? 100 : 0);
                    $formattedGrowth = number_format(abs($growth), 1) . '% ' . ($growth >= 0 ? 'Increase' : 'Decrease');

                    $avgMinutes = CheckIn::whereNotNull('check_out_at')->where('created_at', '>=', $last7DaysStart)
                        ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, check_out_at)) as avg_duration'))->value('avg_duration');
                    $formattedDuration = $avgMinutes ? floor($avgMinutes / 60) . 'h ' . round($avgMinutes % 60) . 'm' : '0m';

                    // NOTE: standardized on ">=" for "active" to match the churn helpers below
                    // (getTypeRiskAdjustment, calculateBlendedChurnRate, getHighRiskMembers all use ">=").
                    $activeMembers = Member::where('membership_expiry_date', '>=', $now)->count();
                    
                    $expiringNext7 = Member::whereBetween('membership_expiry_date', [$now->copy()->startOfDay(), $now->copy()->addDays(7)->endOfDay()])->get();

                    // Reuses the same eager-loaded query as countHighRiskMembers() to avoid
                    // an N+1 on churn_risk_score's underlying checkIns access, and to avoid
                    // maintaining two copies of the "high risk" definition.
                    $highRisk = $this->getHighRiskMembers();
                    
                    $blendedChurn = round($this->calculateBlendedChurnRate($this->calculateChurnRatesByType()) * 100) . '%';

                    $sections['Traffic Trends'] = [
                        'Check-ins (Last 7 Days)' => $currentWeekCount,
                        'Check-ins (Prior 7 Days)' => $priorWeekCount,
                        'Week-over-Week Trend' => $formattedGrowth,
                        'Average Workout Duration' => $formattedDuration,
                    ];
                    
                    $expBadge = $expiringNext7->count() > 0 ? "<span class='badge badge-danger'>{$expiringNext7->count()}</span>" : "0";
                    $hrBadge = $highRisk->count() > 0 ? "<span class='badge badge-danger'>{$highRisk->count()}</span>" : "0";
                    
                    $sections['Retention Action Items'] = [
                        'Total Active Members' => $activeMembers,
                        'Members Expiring in Next 7 Days' => $expBadge,
                    ];
                    if ($expiringNext7->count() > 0) {
                        $sections['Retention Action Items']['-> Expiring Member IDs:'] = $expiringNext7->pluck('unique_id')->implode(', ');
                    }

                    $sections['Retention Action Items']['Members at High Risk of Churn'] = $hrBadge;
                    if ($highRisk->count() > 0) {
                        $sections['Retention Action Items']['-> High Risk Member IDs:'] = $highRisk->pluck('unique_id')->implode(', ');
                    }
                    
                    $sections['Retention Action Items']['Current Blended Churn Rate'] = $blendedChurn;
                    break;

                case 'monthly':
                    $reportTitle = 'Monthly Report (' . $now->format('F Y') . ')';
                    $startOfMonth = $now->copy()->startOfMonth();

                    // 1. Core Data
                    // NOTE: standardized on ">=" for "active" (see weekly case note above).
                    $activeMembers = Member::where('membership_expiry_date', '>=', $now)->count();
                    $newSignups = Member::whereBetween('created_at', [$startOfMonth, $now])->count();
                    $expirations = Member::whereBetween('membership_expiry_date', [$startOfMonth, $now])->count();
                    $netGrowth = $newSignups - $expirations;
                    
                    // 2. Reuse the projection engine for 3, 6, and 12 months
                    // Helper function to get projection for N months
                    $getProj = function($months) {
                        $trendWidget = new \App\Filament\Widgets\FutureTrendsChart();

                        // We use Reflection to access the private getProjection method
                        $reflection = new \ReflectionClass($trendWidget);
                        $method = $reflection->getMethod('getProjection');
                        $method->setAccessible(true);

                        // Temporarily set the filter on the widget to get the right projection
                        $trendWidget->filter = (string)$months;
                        return $method->invoke($trendWidget);
                    };

                    $proj3  = $getProj(3);
                    $proj6  = $getProj(6);
                    $proj12 = $getProj(12);

                    $sections['Macro Membership Trends'] = [
                        'Total Active Members' => $activeMembers,
                        'New Signups This Month' => $newSignups,
                        'Expirations/Cancellations' => $expirations,
                        'Net Member Growth' => ($netGrowth >= 0 ? '+' : '') . $netGrowth . ' Members',
                    ];

                    // Computed once and reused below (previously called twice per report run,
                    // which meant duplicating the full churn-rate query set unnecessarily).
                    $churnRates = $this->calculateChurnRatesByType();
                    $blendedChurn = round($this->calculateBlendedChurnRate($churnRates) * 100) . '%';

                    $sections['Monthly Traffic & Analytics'] = [
                        'Total Check-ins This Month' => CheckIn::whereBetween('created_at', [$startOfMonth, $now])->count(),
                        'Current Blended Churn Rate' => $blendedChurn,
                    ];

                    $sections['Membership Flow Forecast'] = [
                        '3-Month Projection' => ($proj3['finalExpected'] - $proj3['currentActive'] >= 0 ? '+' : '') . ($proj3['finalExpected'] - $proj3['currentActive']) . ' members',
                        '6-Month Projection' => ($proj6['finalExpected'] - $proj6['currentActive'] >= 0 ? '+' : '') . ($proj6['finalExpected'] - $proj6['currentActive']) . ' members',
                        '12-Month Projection' => ($proj12['finalExpected'] - $proj12['currentActive'] >= 0 ? '+' : '') . ($proj12['finalExpected'] - $proj12['currentActive']) . ' members',
                    ];

                    // Mirroring your dashboard structure
                    $sections['Churn Rate by Type'] = [
                        'Promo' => round($churnRates['promo'] * 100) . '%',
                        'Discount' => round($churnRates['discount'] * 100) . '%',
                        'Regular' => round($churnRates['regular'] * 100) . '%',
                    ];
                    break;

                case 'custom':
                    $startDate = Carbon::parse($data['start_date'])->startOfDay();
                    $endDate = Carbon::parse($data['end_date'])->endOfDay();
                    $daysInPeriod = max(1, $startDate->diffInDays($endDate) + 1);
                    
                    $reportTitle = 'Custom Report: ' . $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y');

                    // Use withTrashed() for the signup COHORT: a member who signed up in this
                    // window and was later soft-deleted (cancelled/removed) is still part of
                    // "who did this campaign bring in" — excluding them would silently inflate
                    // the activation/retention rates below by only counting survivors.
                    $newSignupCohort = Member::withTrashed()->whereBetween('created_at', [$startDate, $endDate]);
                    $newSignups = (clone $newSignupCohort)->count();
                    $newSignupIds = (clone $newSignupCohort)->pluck('id');

                    $expirations = Member::whereBetween('membership_expiry_date', [$startDate, $endDate])->count();
                    $netGrowth = $newSignups - $expirations;
                    $netGrowthString = $netGrowth >= 0 ? "+{$netGrowth} Members" : "{$netGrowth} Members";

                    // Activation: did the signup ever check in at all (any time up to now),
                    // regardless of whether that visit fell inside the reporting window.
                    $activatedCount = Member::withTrashed()
                        ->whereIn('id', $newSignupIds)
                        ->whereHas('checkIns')
                        ->count();
                    $activationRate = $newSignups > 0 ? round(($activatedCount / $newSignups) * 100, 1) : 0.0;

                    // Retention: still an active, non-deleted membership as of right now.
                    // Deliberately queried WITHOUT withTrashed() — a soft-deleted member is
                    // correctly excluded here, since being deleted means "not retained."
                    $stillActiveCount = Member::whereIn('id', $newSignupIds)->active()->count();
                    $retentionRate = $newSignups > 0 ? round(($stillActiveCount / $newSignups) * 100, 1) : 0.0;

                    // Signup mix by plan type — same headline signup count can mean very
                    // different things depending on whether it skewed promo vs. regular.
                    $signupsByType = (clone $newSignupCohort)
                        ->selectRaw('membership_type, COUNT(*) as total')
                        ->groupBy('membership_type')
                        ->pluck('total', 'membership_type');
                    $signupMixString = $signupsByType->isEmpty()
                        ? 'N/A'
                        : $signupsByType->map(fn ($count, $type) => ucfirst($type) . ": {$count}")->implode(', ');

                    $periodCheckIns = CheckIn::whereBetween('created_at', [$startDate, $endDate])->count();
                    $avgDailyCheckins = round($periodCheckIns / $daysInPeriod);

                    $avgMinutes = CheckIn::whereNotNull('check_out_at')->whereBetween('created_at', [$startDate, $endDate])
                        ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, check_out_at)) as avg_duration'))->value('avg_duration');
                    $formattedDuration = $avgMinutes ? floor($avgMinutes / 60) . 'h ' . round($avgMinutes % 60) . 'm' : '0m';

                    $sections['Acquisition & Churn (Campaign ROI)'] = [
                        'New Signups During Period' => $newSignups,
                        'New Signups by Plan Type' => $signupMixString,
                        'Activation Rate (Checked In At Least Once)' => $activationRate . '%',
                        'Retention Rate (Still Active Today)' => $retentionRate . '%',
                        'Expirations During Period' => $expirations,
                        'Net Growth During Period' => $netGrowthString,
                    ];
                    $sections['Traffic & Engagement'] = [
                        'Total Check-ins During Period' => $periodCheckIns,
                        'Avg. Daily Check-ins' => $avgDailyCheckins . ' / day',
                        'Average Workout Duration' => $formattedDuration,
                    ];
                    break;
            }

            // =========================================================
            // 2. COMPILE TO PDF & DOWNLOAD
            // =========================================================
            $pdf = Pdf::loadView('pdf.kpi-report', [
                'reportTitle' => $reportTitle,
                'date' => $now->setTimezone('Asia/Manila')->format('F j, Y - g:i A'),
                'sections' => $sections, // The magic happens here!
            ]);

            $pdfContent = $pdf->output();

            // File label follows the same "was this actually a custom/historical pull?"
            // logic as $reportTitle, so the archived filename always matches what's
            // printed inside the PDF (previously this used the mutated $type, which
            // always said "Daily" even when the title said "Custom Report").
            $fileLabel = $customSingleDate ? 'Custom' : ucfirst($type);
            $fileName = 'AuraPass_' . $fileLabel . '_Report_' . $now->format('Ymd_Hi') . '.pdf';
            Storage::disk('local')->put('kpi_archives/' . $fileName, $pdfContent);

            return response()->streamDownload(
                function () use ($pdfContent) { echo $pdfContent; }, 
                $fileName, 
                ['Content-Type' => 'application/pdf']
            );

        } catch (\Throwable $e) {
            // Widened from \Exception: the reflection-based projection lookup in the
            // monthly case can throw \ReflectionException / \Error / \TypeError, none
            // of which extend \Exception, and would otherwise bypass this notification
            // entirely and surface as a raw fatal error to the admin.
            Log::error('AuraPass PDF Export Failed: ' . $e->getMessage());
            Notification::make()
                ->title('Export Failed')->body('An error occurred. Check logs.')->danger()->send();
            return null;
        }
    }
    // =========================================================
    // 3. PREDICTIVE MATH HELPERS 
    // =========================================================

    /**
     * Shared "high risk" member query, optionally scoped to a membership type.
     * Eager-loads the latest check-in so the churn_risk_score accessor doesn't
     * trigger an N+1 query per member.
     */
    private function getHighRiskMembers(?string $membershipType = null)
    {
        $query = Member::where('membership_expiry_date', '>=', now())
            ->with(['checkIns' => fn($q) => $q->latest('created_at')->limit(1)]);

        if ($membershipType) {
            $query->where('membership_type', $membershipType);
        }

        return $query->get()->filter(fn($m) => $m->churn_risk_score > 0.60);
    }

    private function countHighRiskMembers(): int
    {
        return $this->getHighRiskMembers()->count();
    }

    private function getWeightedAttendanceMultiplier(): float
    {
        // NOTE: uses now() directly rather than a report-scoped date. Harmless today
        // since Weekly/Monthly always report "as of now", but if a point-in-time /
        // backdated churn report is ever added, this (and calculateChurnRatesByType,
        // calculateBlendedChurnRate, getTypeRiskAdjustment below) will need an $asOf
        // parameter threaded through so churn figures stay consistent with the rest
        // of that report's date range.
        $week4 = CheckIn::where('created_at', '>=', now()->subDays(7))->count();
        $week3 = CheckIn::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
        $week2 = CheckIn::whereBetween('created_at', [now()->subDays(21), now()->subDays(14)])->count();
        $week1 = CheckIn::whereBetween('created_at', [now()->subDays(28), now()->subDays(21)])->count();
        
        $priorMonthTotal = CheckIn::whereBetween('created_at', [now()->subDays(56), now()->subDays(28)])->count();
        $baselineWeekly  = $priorMonthTotal > 0 ? ($priorMonthTotal / 4) : 1; 
        
        $recentWeighted = ($week4 * 0.40) + ($week3 * 0.30) + ($week2 * 0.20) + ($week1 * 0.10);
        $trend = ($recentWeighted - $baselineWeekly) / $baselineWeekly;

        return (float) max(0.5, min(1.0 - $trend, 1.5));
    }

    private function getTypeRiskAdjustment(string $type): float
    {
        $members = Member::where('membership_type', $type)
            ->where('membership_expiry_date', '>=', now())
            ->with(['checkIns' => fn($q) => $q->latest('created_at')->limit(1)])
            ->get();

        if ($members->isEmpty()) return 0.0;
        
        // Using your new model accessor
        $avgRisk = $members->avg(fn($m) => $m->churn_risk_score);
        return round($avgRisk - 0.5, 4); 
    }

    private function calculateChurnRatesByType(): array
    {
        // NOTE: see getWeightedAttendanceMultiplier() above re: now() vs a report-scoped date.
        $attendanceMultiplier = $this->getWeightedAttendanceMultiplier();
        $defaults = ['promo' => 0.82, 'discount' => 0.38, 'regular' => 0.29];
        $rates = [];

        foreach (array_keys($defaults) as $type) {
            $historical = Member::where('membership_type', $type)->where('created_at', '<=', now()->subMonths(3))->count();
            $retained = Member::where('membership_type', $type)->where('created_at', '<=', now()->subMonths(3))->where('membership_expiry_date', '>=', now())->count();

            if ($historical >= 10) {
                $base = ($historical - $retained) / $historical;
            } elseif ($historical > 0) {
                $measured = ($historical - $retained) / $historical;
                $confidence = $historical / 10;
                $base = ($measured * $confidence) + ($defaults[$type] * (1 - $confidence));
            } else {
                $base = $defaults[$type];
            }

            $riskAdjustment = $this->getTypeRiskAdjustment($type);
            $base = $base + ($riskAdjustment * 0.20);
            $rates[$type] = max(0.05, min($base * $attendanceMultiplier, 0.95));
        }

        return $rates;
    }

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
            $weight = ($typeCounts[$type] ?? 0) / $totalActive;
            $blended += $rate * $weight;
        }

        return round($blended, 4);
    }
}