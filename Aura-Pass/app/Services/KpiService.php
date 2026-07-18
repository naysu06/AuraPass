<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Member;
use App\Models\CheckIn;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KpiService 
{
    /**
     * Generates a KPI report PDF, archives it on the server, and returns the file path.
     *
     * @param string $type ('daily', 'weekly', 'monthly', 'custom')
     * @param string|null $startDateStr (e.g. '2026-07-01')
     * @param string|null $endDateStr (e.g. '2026-07-15')
     * @return string|null The local storage path to the generated PDF
     */
    public function generateReport(string $type, ?string $startDateStr = null, ?string $endDateStr = null): ?string
    {
        try {
            $now = Carbon::now();

            // If admin selected a custom range but chose the same past date for start and end,
            // treat it as a daily report for data selection purposes, but remember the chosen
            // date so we can label the report (and file) as a Custom Report for that specific date.
            $customSingleDate = null;
            if ($type === 'custom' && !empty($startDateStr) && !empty($endDateStr)) {
                $startDate = Carbon::parse($startDateStr)->toDateString();
                $endDate = Carbon::parse($endDateStr)->toDateString();
                if ($startDate === $endDate) {
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
                    $reportDate = $customSingleDate ? Carbon::parse($customSingleDate) : $now->copy();

                    $reportTitle = $customSingleDate
                        ? 'Custom Report (' . $reportDate->format('F j, Y') . ')'
                        : 'Daily Report (' . $reportDate->format('F j, Y') . ')';

                    $liveCount = $customSingleDate
                        ? null
                        : CheckIn::whereNull('check_out_at')->where('created_at', '>=', $now->copy()->subHours(12))->count();

                    $todayCheckIns = CheckIn::whereDate('created_at', $reportDate->toDateString())->count();

                    $failedScans = AuditLog::whereDate('created_at', $reportDate->toDateString())
                        ->where('activity', 'like', 'member.scan_failed')
                        ->count();
                    
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

                    $activeMembers = Member::where('membership_expiry_date', '>=', $now)->count();
                    
                    $expiringNext7 = Member::whereBetween('membership_expiry_date', [$now->copy()->startOfDay(), $now->copy()->addDays(7)->endOfDay()])->get();

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

                    $activeMembers = Member::where('membership_expiry_date', '>=', $now)->count();
                    $newSignups = Member::whereBetween('created_at', [$startOfMonth, $now])->count();
                    $expirations = Member::whereBetween('membership_expiry_date', [$startOfMonth, $now])->count();
                    $netGrowth = $newSignups - $expirations;
                    
                    $getProj = function($months) {
                        $trendWidget = new \App\Filament\Widgets\FutureTrendsChart();
                        $reflection = new \ReflectionClass($trendWidget);
                        $method = $reflection->getMethod('getProjection');
                        $method->setAccessible(true);
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

                    $sections['Churn Rate by Type'] = [
                        'Promo' => round($churnRates['promo'] * 100) . '%',
                        'Discount' => round($churnRates['discount'] * 100) . '%',
                        'Regular' => round($churnRates['regular'] * 100) . '%',
                    ];
                    break;

                case 'custom':
                    $startDate = Carbon::parse($startDateStr)->startOfDay();
                    $endDate = Carbon::parse($endDateStr)->endOfDay();
                    $daysInPeriod = max(1, $startDate->diffInDays($endDate) + 1);
                    
                    $reportTitle = 'Custom Report: ' . $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y');

                    $newSignupCohort = Member::withTrashed()->whereBetween('created_at', [$startDate, $endDate]);
                    $newSignups = (clone $newSignupCohort)->count();
                    $newSignupIds = (clone $newSignupCohort)->pluck('id');

                    $expirations = Member::whereBetween('membership_expiry_date', [$startDate, $endDate])->count();
                    $netGrowth = $newSignups - $expirations;
                    $netGrowthString = $netGrowth >= 0 ? "+{$netGrowth} Members" : "{$netGrowth} Members";

                    $activatedCount = Member::withTrashed()
                        ->whereIn('id', $newSignupIds)
                        ->whereHas('checkIns')
                        ->count();
                    $activationRate = $newSignups > 0 ? round(($activatedCount / $newSignups) * 100, 1) : 0.0;

                    $stillActiveCount = Member::whereIn('id', $newSignupIds)->active()->count();
                    $retentionRate = $newSignups > 0 ? round(($stillActiveCount / $newSignups) * 100, 1) : 0.0;

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
            // 2. COMPILE TO PDF & SERVER ARCHIVE
            // =========================================================
            $pdf = Pdf::loadView('pdf.kpi-report', [
                'reportTitle' => $reportTitle,
                'date' => $now->setTimezone('Asia/Manila')->format('F j, Y - g:i A'),
                'sections' => $sections, 
            ]);

            $pdfContent = $pdf->output();

            $fileLabel = $customSingleDate ? 'Custom' : ucfirst($type);
            
            // Generate dynamic folder structure: private/kpi_archives/daily (or weekly/monthly)
            $folderPath = 'kpi_archives/' . strtolower($fileLabel);

            if (!Storage::disk('local')->exists($folderPath)) {
                Storage::disk('local')->makeDirectory($folderPath);
            }

            $fileName = 'AuraPass_' . $fileLabel . '_Report_' . $now->format('Ymd_Hi') . '.pdf';
            $archivePath = $folderPath . '/' . $fileName;

            // Save directly to the server folder
            Storage::disk('local')->put($archivePath, $pdfContent);

            return $archivePath;

        } catch (\Throwable $e) {
            Log::error('AuraPass PDF Service Export Failed: ' . $e->getMessage());
            return null;
        }
    }

    // =========================================================
    // 3. PREDICTIVE MATH HELPERS 
    // =========================================================
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
        
        $avgRisk = $members->avg(fn($m) => $m->churn_risk_score);
        return round($avgRisk - 0.5, 4); 
    }

    private function calculateChurnRatesByType(): array
    {
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