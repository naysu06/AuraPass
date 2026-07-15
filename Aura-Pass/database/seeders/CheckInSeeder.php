<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Baguio City Gym — Realistic Behavioral CheckIn & Audit Seeder
 *
 * Personas & retention modeled after real Baguio demographics:
 * - Students (SLU, UB, BSU, BCU) — semester-driven, holiday exodus
 * - BPO/Office Workers         — consistent 9-5 schedule, evening crowd
 * - Resolutioners              — Jan spike, rapid decay
 * - Seniors / Retirees         — morning loyalists, steady long-term
 * - Athletes / Gym Rats        — hardcore, near-daily, multi-year
 */
class CheckInSeeder extends Seeder
{
    // ─── Tuneable Constants ───────────────────────────────────────────────────
    private const SEED_MONTHS   = 6;    // Scaled down from 18
    private const CHECKIN_DAYS  = 30;   // Scaled down from 120
    private const MAX_CHECKINS  = 100;  // NEW: Exact limit for check-ins
    private const MAX_MEMBERS   = 15;   // NEW: Exact limit for random members
    private const BATCH_SIZE    = 100;

    // Weighted hourly traffic profile (0–23). Reflects Baguio cold-morning culture.
    private const HOUR_WEIGHTS = [
        0 => 0,  1 => 0,  2 => 0,  3 => 0,  4 => 0,
        5 => 2,  6 => 18, 7 => 42, 8 => 35, 9 => 20,   // Early birds (cold weather motivation)
        10 => 12, 11 => 8,                               // Mid-morning trickle
        12 => 20, 13 => 16, 14 => 10,                   // Lunch crowd
        15 => 28, 16 => 62, 17 => 100, 18 => 88, 19 => 65, // After-work PEAK
        20 => 30, 21 => 10, 22 => 3, 23 => 0,
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        CheckIn::truncate();
        Member::truncate();
        AuditLog::truncate(); 
        Schema::enableForeignKeyConstraints();

        $this->command->info('🏋️  [1/4] Generating Baguio-realistic member cohorts (Limited to ' . self::MAX_MEMBERS . ')...');
        $members = $this->generateMembers();

        $this->command->info("✅  Created {$members->count()} members.");
        $this->command->info('📅  [2/4] Simulating exactly ' . self::MAX_CHECKINS . ' recent check-ins...');

        $totalInserted = $this->simulateCheckIns($members);

        $this->command->info("✅  Seeded {$totalInserted} check-ins and corresponding audit logs.");

        $this->command->info('🎯  [3/4] Seeding dedicated KPI Showcase Cohort (Custom Report demo data)...');
        [$showcaseStart, $showcaseEnd] = $this->generateKpiShowcaseCohort();

        $this->command->info('✅  [4/4] Analytics ready!');
        $this->command->newLine();
        $this->command->info('  ┌─────────────────────────────────────────────────────────────────┐');
        $this->command->info('  │  KPI SHOWCASE — Custom Report Demo                                 ');
        $this->command->info('  │  In the "Export KPI" action, pick Report Type: Custom Date Range   ');
        $this->command->info('  │    Start Date : ' . $showcaseStart->format('Y-m-d'));
        $this->command->info('  │    End Date   : ' . $showcaseEnd->format('Y-m-d'));
        $this->command->info('  │  Expect: 20 signups (8 promo / 7 discount / 5 regular),             ');
        $this->command->info('  │          ~65% Activation Rate, ~50% Retention Rate.                 ');
        $this->command->info('  └─────────────────────────────────────────────────────────────────┘');
    }

    // =========================================================================
    // MEMBER GENERATION
    // =========================================================================

    private function generateMembers(): \Illuminate\Support\Collection
    {
        $members = collect();
        $auditBuffer = []; 

        for ($m = self::SEED_MONTHS; $m >= 0; $m--) {
            $targetMonth = now()->subMonthsNoOverflow($m);
            $monthNum    = (int) $targetMonth->month;

            $cohort = $this->buildMonthlyCohort($monthNum, $targetMonth);
            foreach ($cohort as $memberData) {
                // APPLIED LIMIT: Stops generating once we hit the cap
                if ($members->count() >= self::MAX_MEMBERS) break 2;
                
                $newMember = Member::factory()->create($memberData);
                $members->push($newMember);

                $auditBuffer[] = $this->buildAuditLogRow('member.created', $newMember, $newMember->created_at);
            }
        }

        // ── Expiry Alert Fixtures (always present for dashboard testing) ────
        $fixtures = [
            ['name' => 'Alert — Expires Today',       'days' => 0,  'type' => 'regular'],
            ['name' => 'Alert — Expires in 1 Day',    'days' => 1,  'type' => 'regular'],
            ['name' => 'Alert — Expires in 3 Days',   'days' => 3,  'type' => 'discount'],
            ['name' => 'Alert — Expires in 7 Days',   'days' => 7,  'type' => 'promo'],
            ['name' => 'Alert — Expires in 14 Days',  'days' => 14, 'type' => 'regular'],
        ];
        
        foreach ($fixtures as $f) {
            $joinDate = Carbon::now()->subMonths(rand(2, 5));
            $newMember = Member::factory()->create([
                'name'                   => $f['name'],
                'created_at'             => $joinDate,
                'membership_expiry_date' => Carbon::now()->addDays($f['days']),
                'membership_type'        => $f['type'],
            ]);
            $members->push($newMember);
            
            $auditBuffer[] = $this->buildAuditLogRow('member.created', $newMember, $joinDate);
        }

        AuditLog::insert($auditBuffer);

        return $members;
    }

    private function buildMonthlyCohort(int $month, Carbon $targetMonth): array
    {
        $cohort = [];
        $volumeMap = [
            1  => 16, 2  => 12, 3  => 6,  4  => 4,  5  => 4,  6  => 3,
            7  => 3,  8  => 14, 9  => 11, 10 => 6,  11 => 5,  12 => 3,
        ];

        $volume = $volumeMap[$month] ?? 5;
        $volume = (int) round($volume * (0.80 + lcg_value() * 0.40));

        for ($i = 0; $i < $volume; $i++) {
            $persona      = $this->pickPersona($month);
            $joinDay      = rand(0, 27);
            $joinDate     = $targetMonth->copy()->startOfMonth()->addDays($joinDay);
            $durationMonths = $this->getPersonaDuration($persona);
            $expiryDate   = $joinDate->copy()->addMonths($durationMonths);

            $cohort[] = [
                'created_at'             => $joinDate,
                'membership_expiry_date' => $expiryDate,
                'membership_type'        => $this->getPersonaType($persona),
            ];
        }

        return $cohort;
    }

    // =========================================================================
    // KPI SHOWCASE COHORT (Custom Report demo data)
    // =========================================================================

    /**
     * Seeds a fixed, deterministic 20-member cohort inside a specific past
     * date window, purpose-built to exercise the Custom Report's activation
     * rate / retention rate / plan-mix metrics with numbers that are always
     * the same, no matter when the seeder is run — good for demos and for
     * regression-checking the report's math.
     *
     * Totals by design:
     *   - Plan mix:   8 promo / 7 discount / 5 regular      (20 total)
     *   - Retention:  10 still active / 5 naturally expired / 5 cancelled
     *                 early via soft-delete                  (20 total)
     *   - Activation: 13 checked in at least once / 7 never showed up
     *
     * The 5 "cancelled early" members are removed via Eloquent's delete()
     * (not truncate), so the app's real MemberObserver fires normally —
     * this also doubles as a live check that the PII-scrubbing /
     * event-snapshot audit fix behaves correctly on soft-delete.
     *
     * Returns [windowStart, windowEnd] so run() can print the exact dates
     * an admin should select in the Custom Report form.
     */
    private function generateKpiShowcaseCohort(): array
    {
        $windowStart = Carbon::now()->subDays(45)->startOfDay(); // older boundary
        $windowEnd   = Carbon::now()->subDays(31)->startOfDay(); // more recent boundary

        // [membership_type, retention_status, activated]
        $spec = [
            // -- Still active (10) --------------------------------------------
            ['promo',    'still_active', true],
            ['promo',    'still_active', true],
            ['promo',    'still_active', false], // "ghost" member: paying, never attended
            ['discount', 'still_active', true],
            ['discount', 'still_active', true],
            ['discount', 'still_active', false], // another ghost member
            ['regular',  'still_active', true],
            ['regular',  'still_active', true],
            ['promo',    'still_active', true],
            ['discount', 'still_active', true],
            // -- Naturally expired, not deleted (5) ----------------------------
            ['promo',    'expired_not_deleted', true],
            ['promo',    'expired_not_deleted', false],
            ['discount', 'expired_not_deleted', true],
            ['discount', 'expired_not_deleted', false],
            ['regular',  'expired_not_deleted', true],
            // -- Cancelled early / soft-deleted (5) ----------------------------
            ['promo',    'soft_deleted', true],  // classic churn: tried it, then quit
            ['promo',    'soft_deleted', false],
            ['discount', 'soft_deleted', false],
            ['regular',  'soft_deleted', true],
            ['regular',  'soft_deleted', false],
        ];

        $auditBuffer = [];

        foreach ($spec as $i => [$type, $status, $activated]) {
            // Join date somewhere inside the window (always within [windowStart, windowEnd]).
            $joinDate = $windowStart->copy()->addDays(rand(0, 13))->setTime(rand(9, 18), rand(0, 59));

            $expiryDate = match ($status) {
                'still_active'        => Carbon::now()->addDays(rand(15, 120)),
                'expired_not_deleted' => Carbon::now()->subDays(rand(1, 25)),
                'soft_deleted'        => Carbon::now()->addDays(rand(10, 60)), // cancelled while still technically valid
            };

            $member = Member::factory()->create([
                'name'                   => 'Showcase — ' . ucfirst(str_replace('_', ' ', $status)) . ' #' . ($i + 1),
                'created_at'             => $joinDate,
                'membership_expiry_date' => $expiryDate,
                'membership_type'        => $type,
            ]);

            $auditBuffer[] = $this->buildAuditLogRow('member.created', $member, $joinDate);

            if ($activated) {
                // 1–2 visits shortly after signing up — enough to count as "activated"
                // and to also feed the period's Total Check-ins / Avg Duration metrics.
                $visits = rand(1, 2);
                for ($v = 0; $v < $visits; $v++) {
                    $checkInTime = $joinDate->copy()->addDays(rand(1, 6))->setTime(rand(6, 20), rand(0, 59));
                    if ($checkInTime->isFuture()) continue;

                    $checkOutTime = $checkInTime->copy()->addMinutes(rand(35, 90));
                    if ($checkOutTime->isFuture()) $checkOutTime = null;

                    CheckIn::insert($this->buildCheckInRow($member->id, $checkInTime, $checkOutTime));
                    $auditBuffer[] = $this->buildAuditLogRow('member.checked_in', $member, $checkInTime);
                    if ($checkOutTime) {
                        $auditBuffer[] = $this->buildAuditLogRow('member.checked_out', $member, $checkOutTime);
                    }
                }
            }

            // Cancelled-early members are soft-deleted via Eloquent (not truncate) so
            // MemberObserver::deleting() fires for real — exercising the PII-scrubbing
            // / event-snapshot fix the audit trail already depends on. NOTE: if that
            // observer dispatches a queued listener (ShouldQueue) to write the
            // "member.deleted" audit row, it won't execute during `db:seed` unless
            // QUEUE_CONNECTION=sync — run with that env var set (or a queue worker
            // active) if you want to see the deletion entry in the Live Log Feed too.
            if ($status === 'soft_deleted') {
                $member->delete();
            }
        }

        AuditLog::insert($auditBuffer);

        return [$windowStart, $windowEnd];
    }

    // =========================================================================
    // CHECK-IN & AUDIT SIMULATION
    // =========================================================================

    private function simulateCheckIns(\Illuminate\Support\Collection $allMembers): int
    {
        $checkInBuffer = [];
        $auditBuffer   = [];
        $totalInserted = 0;

        // CHANGED: Loop counts UP from 0 to ensure the 100 check-ins generated are the most recent ones
        for ($d = 0; $d <= self::CHECKIN_DAYS; $d++) {
            $date      = Carbon::now()->subDays($d)->startOfDay();
            $dayOfWeek = (int) $date->dayOfWeek;
            $monthNum  = (int) $date->month;

            $activeMembers = $allMembers->filter(
                fn($m) => $m->created_at->lte($date) && $m->membership_expiry_date->gte($date)
            );

            foreach ($activeMembers as $member) {
                // APPLIED LIMIT: Hard stop when we hit 100
                if ($totalInserted >= self::MAX_CHECKINS) {
                    break 2; // Breaks entirely out of both the member loop and the days loop
                }

                $daysSinceJoined = (int) $date->diffInDays($member->created_at);
                $prob = $this->calcAttendanceProbability($member->membership_type, $daysSinceJoined, $dayOfWeek, $monthNum);

                if ((rand(1, 1000) / 1000) > $prob) continue; 

                // ── Primary check-in ────────────────────────────────────────
                $hour        = $this->getWeightedHour($monthNum, $dayOfWeek);
                $checkInTime = $date->copy()->setTime($hour, rand(0, 59), rand(0, 59));

                if ($checkInTime->isFuture()) continue;

                [$minDur, $maxDur] = $this->getSessionDuration($member->membership_type, $hour);
                $checkOutTime = $checkInTime->copy()->addMinutes(rand($minDur, $maxDur));
                if ($checkOutTime->isFuture()) $checkOutTime = null;

                $checkInBuffer[] = $this->buildCheckInRow($member->id, $checkInTime, $checkOutTime);
                $auditBuffer[]   = $this->buildAuditLogRow('member.checked_in', $member, $checkInTime);
                
                if ($checkOutTime) {
                    $auditBuffer[] = $this->buildAuditLogRow('member.checked_out', $member, $checkOutTime);
                }
                
                $totalInserted++;

                // ── Rare double-session ─────────────────────────────────────
                if ($member->membership_type === 'regular' && rand(1, 100) <= 3) {
                    
                    // Enforce the limit on double sessions too
                    if ($totalInserted >= self::MAX_CHECKINS) break 2;

                    $secondHour = ($hour < 12) ? rand(16, 18) : rand(6, 8);
                    $secondIn   = $date->copy()->setTime($secondHour, rand(0, 59), rand(0, 59));
                    
                    if (!$secondIn->isFuture() && $secondIn->gt($checkOutTime ?? $checkInTime)) {
                        $secondOut = $secondIn->copy()->addMinutes(rand(30, 60));
                        if ($secondOut->isFuture()) $secondOut = null;
                        
                        $checkInBuffer[] = $this->buildCheckInRow($member->id, $secondIn, $secondOut);
                        $auditBuffer[]   = $this->buildAuditLogRow('member.checked_in', $member, $secondIn);
                        
                        if ($secondOut) {
                            $auditBuffer[] = $this->buildAuditLogRow('member.checked_out', $member, $secondOut);
                        }
                        $totalInserted++;
                    }
                }

                // Batch insert to protect memory
                if (count($checkInBuffer) >= self::BATCH_SIZE) {
                    CheckIn::insert($checkInBuffer);
                    AuditLog::insert($auditBuffer); 
                    $checkInBuffer = [];
                    $auditBuffer   = [];
                }
            }
        }

        if (!empty($checkInBuffer)) {
            CheckIn::insert($checkInBuffer);
            AuditLog::insert($auditBuffer);
        }

        return $totalInserted;
    }

    // =========================================================================
    // BUILDER HELPERS
    // =========================================================================

    private function buildCheckInRow(int $memberId, Carbon $in, ?Carbon $out): array
    {
        return [
            'member_id'    => $memberId,
            'created_at'   => $in,
            'check_out_at' => $out,
            'updated_at'   => $out ?? $in,
        ];
    }

    private function buildAuditLogRow(string $activity, Member $member, Carbon $timestamp): array
    {
        return [
            'user_id'       => null, 
            'activity'      => $activity,
            'loggable_id'   => $member->id,
            'loggable_type' => get_class($member), 
            'details'       => json_encode(['member_name' => $member->name]),
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'AuraPass Background Seeder',
            'created_at'    => $timestamp->copy(),
            'updated_at'    => $timestamp->copy(),
        ];
    }

    private function calcAttendanceProbability(string $membershipType, int $daysSinceJoined, int $dayOfWeek, int $month): float
    {
        $prob = match ($membershipType) {
            'promo' => max(0.0, 0.65 * pow(0.93, $daysSinceJoined)),
            'discount' => $this->studentProbability($daysSinceJoined, $month),
            default => max(0.15, 0.42 * pow(0.998, $daysSinceJoined)),
        };

        $prob += match ($dayOfWeek) {
            1 => 0.12, 2 => 0.05, 3 => 0.02, 4 => -0.03,
            5 => -0.10, 6 => -0.18, 0 => -0.20, default => 0.0,
        };

        if ($month === 12) $prob -= 0.18;
        if (in_array($month, [7, 8])) $prob -= 0.08;
        if ($month === 1 && $daysSinceJoined <= 14) $prob += 0.10;
        if ($month === 2) $prob += 0.05;

        return (float) max(0.02, min($prob, 0.95));
    }

    private function studentProbability(int $daysSinceJoined, int $month): float
    {
        $base = 0.38;
        if (in_array($month, [12, 6, 7])) return 0.05;
        if (in_array($month, [11, 4])) $base = 0.20;
        if (in_array($month, [8, 1]) && $daysSinceJoined <= 21) $base = 0.55;
        $base *= pow(0.995, $daysSinceJoined);
        return max(0.04, $base);
    }

    private function getWeightedHour(int $month, int $dayOfWeek): int
    {
        $weights = self::HOUR_WEIGHTS;
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            $weights[7] = (int) ($weights[7] * 1.3);
            $weights[17] = (int) ($weights[17] * 0.7);
        }
        if (in_array($month, [3, 4, 5])) {
            $weights[6] = (int) ($weights[6] * 0.6);
            $weights[8] = (int) ($weights[8] * 1.3);
        }
        $total = array_sum($weights);
        $rand  = rand(1, $total);
        foreach ($weights as $hour => $weight) {
            $rand -= $weight;
            if ($rand <= 0) return $hour;
        }
        return 17;
    }

    private function getSessionDuration(string $type, int $hour): array
    {
        if ($hour <= 7) return [35, 65];
        return match ($type) {
            'promo'   => [25, 55],
            'discount' => [45, 80],
            default   => [50, 110],
        };
    }

    private function pickPersona(int $month): string
    {
        $distribution = match (true) {
            in_array($month, [1, 2])    => [43, 30, 20, 5,  2],
            in_array($month, [3, 4, 5]) => [25, 42, 5,  20, 8],
            in_array($month, [6, 7])    => [20, 45, 3,  22, 10],
            in_array($month, [8, 9])    => [60, 20, 2,  12, 6],
            in_array($month, [10, 11])  => [45, 30, 2,  15, 8],
            default                     => [20, 42, 5,  25, 8],
        };
        $personas = ['student', 'worker', 'resolutioner', 'senior', 'athlete'];
        $rand = rand(1, 100);
        $cumulative = 0;
        foreach ($distribution as $i => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) return $personas[$i];
        }
        return 'worker';
    }

    private function getPersonaDuration(string $persona): int
    {
        return match ($persona) {
            'student'      => rand(3, 5),
            'worker'       => rand(5, 14),
            'resolutioner' => rand(1, 2),
            'senior'       => rand(8, 24),
            'athlete'      => rand(10, 24),
            default        => rand(2, 4),
        };
    }

    private function getPersonaType(string $persona): string
    {
        return match ($persona) {
            'student'      => 'discount',
            'worker'       => 'regular',
            'resolutioner' => 'promo',
            'senior'       => 'discount',
            'athlete'      => 'regular',
            default        => 'regular',
        };
    }
}