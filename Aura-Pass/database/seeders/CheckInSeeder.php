<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CheckIn;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Baguio City Gym — Realistic Behavioral CheckIn Seeder
 *
 * Personas & retention modeled after real Baguio demographics:
 *   - Students (SLU, UB, BSU, BCU) — semester-driven, holiday exodus
 *   - BPO/Office Workers         — consistent 9-5 schedule, evening crowd
 *   - Resolutioners              — Jan spike, rapid decay
 *   - Tourists / Panagbenga      — Feb surge, short stays
 *   - Seniors / Retirees         — morning loyalists, steady long-term
 *   - Athletes / Gym Rats        — hardcore, near-daily, multi-year
 *
 * Seasonal calendar baked in:
 *   - Jan–Feb  : Resolutioner surge + Panagbenga Festival (tourist wave)
 *   - Mar–May  : Kadayawan / Holy Week lull, summer for non-students
 *   - Jun–Jul  : Rainy season + inter-school break → sharp drop
 *   - Aug–Sep  : 1st semester starts → student flood
 *   - Oct–Nov  : Mid-semester slump, stable workers
 *   - Dec      : Christmas break → near-ghost town
 */
class CheckInSeeder extends Seeder
{
    // ─── Tuneable Constants ───────────────────────────────────────────────────
    private const SEED_MONTHS   = 18;   // months of historical member data
    private const CHECKIN_DAYS  = 120;  // days of actual check-in logs
    private const BATCH_SIZE    = 750;

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
        Schema::enableForeignKeyConstraints();

        $this->command->info('🏋️  [1/3] Generating Baguio-realistic member cohorts...');
        $members = $this->generateMembers();

        $this->command->info("✅  Created {$members->count()} members across " . self::SEED_MONTHS . " months.");
        $this->command->info('📅  [2/3] Simulating individual daily check-in behavior (' . self::CHECKIN_DAYS . ' days)...');

        $totalInserted = $this->simulateCheckIns($members);

        $this->command->info("✅  [3/3] Seeded {$totalInserted} behaviorally-accurate check-ins. Analytics ready!");
    }

    // =========================================================================
    // MEMBER GENERATION
    // =========================================================================

    private function generateMembers(): \Illuminate\Support\Collection
    {
        $members = collect();

        for ($m = self::SEED_MONTHS; $m >= 0; $m--) {
            $targetMonth = now()->subMonthsNoOverflow($m);
            $monthNum    = (int) $targetMonth->month;

            $cohort = $this->buildMonthlyCohort($monthNum, $targetMonth);
            foreach ($cohort as $memberData) {
                $members->push(Member::factory()->create($memberData));
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
            $members->push(Member::factory()->create([
                'name'                    => $f['name'],
                'created_at'              => Carbon::now()->subMonths(rand(2, 5)),
                'membership_expiry_date'  => Carbon::now()->addDays($f['days']),
                'membership_type'         => $f['type'],
            ]));
        }

        return $members;
    }

    /**
     * Returns an array of member attribute arrays for a given month.
     * Volume + persona mix are shaped by Baguio's seasonal reality.
     */
    private function buildMonthlyCohort(int $month, Carbon $targetMonth): array
    {
        $cohort = [];

        // Base new-member volumes by month
        $volumeMap = [
            1  => 38,  // Jan  — Resolutioners + post-holiday motivation
            2  => 28,  // Feb  — Panagbenga tourists + continuing resolutioners
            3  => 14,  // Mar  — Holy Week lull starts
            4  => 10,  // Apr  — Summer break, Baguio tourists but not gym-goers
            5  => 10,  // May  — Pre-enrolment calm
            6  => 7,   // Jun  — Rainy season, school re-enrolling, few gym joiners
            7  => 6,   // Jul  — Deep rainy season
            8  => 32,  // Aug  — 1st Sem starts, student flood
            9  => 26,  // Sep  — Semester momentum
            10 => 14,  // Oct  — Mid-sem, stable
            11 => 12,  // Nov  — Pre-finals
            12 => 5,   // Dec  — Christmas break exodus
        ];

        $volume = $volumeMap[$month] ?? 10;
        // Add some month-to-month noise (±20%)
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
    // CHECK-IN SIMULATION
    // =========================================================================

    private function simulateCheckIns(\Illuminate\Support\Collection $allMembers): int
    {
        $buffer        = [];
        $totalInserted = 0;

        for ($d = self::CHECKIN_DAYS; $d >= 0; $d--) {
            $date      = Carbon::now()->subDays($d)->startOfDay();
            $dayOfWeek = (int) $date->dayOfWeek; // 0=Sun … 6=Sat
            $monthNum  = (int) $date->month;

            // Members active on this date (still within expiry, already joined)
            $activeMembers = $allMembers->filter(
                fn($m) => $m->created_at->lte($date) && $m->membership_expiry_date->gte($date)
            );

            foreach ($activeMembers as $member) {
                $daysSinceJoined = (int) $date->diffInDays($member->created_at);
                $prob = $this->calcAttendanceProbability(
                    $member->membership_type,
                    $daysSinceJoined,
                    $dayOfWeek,
                    $monthNum
                );

                if ((rand(1, 1000) / 1000) > $prob) {
                    continue; // Member skipped the gym today
                }

                // ── Primary check-in ────────────────────────────────────────
                $hour        = $this->getWeightedHour($monthNum, $dayOfWeek);
                $checkInTime = $date->copy()->setTime($hour, rand(0, 59), rand(0, 59));

                if ($checkInTime->isFuture()) continue;

                [$minDur, $maxDur] = $this->getSessionDuration($member->membership_type, $hour);
                $checkOutTime = $checkInTime->copy()->addMinutes(rand($minDur, $maxDur));
                if ($checkOutTime->isFuture()) $checkOutTime = null;

                $buffer[] = $this->buildCheckInRow($member->id, $checkInTime, $checkOutTime);
                $totalInserted++;

                // ── Rare double-session (gym rats ~5%, serious regulars ~2%) ─
                if ($member->membership_type === 'regular' && rand(1, 100) <= 3) {
                    $secondHour = ($hour < 12) ? rand(16, 18) : rand(6, 8);
                    $secondIn   = $date->copy()->setTime($secondHour, rand(0, 59), rand(0, 59));
                    if (!$secondIn->isFuture() && $secondIn->gt($checkOutTime ?? $checkInTime)) {
                        $secondOut = $secondIn->copy()->addMinutes(rand(30, 60));
                        if ($secondOut->isFuture()) $secondOut = null;
                        $buffer[] = $this->buildCheckInRow($member->id, $secondIn, $secondOut);
                        $totalInserted++;
                    }
                }

                if (count($buffer) >= self::BATCH_SIZE) {
                    CheckIn::insert($buffer);
                    $buffer = [];
                }
            }
        }

        if (!empty($buffer)) {
            CheckIn::insert($buffer);
        }

        return $totalInserted;
    }

    /**
     * Core probability engine.
     * Returns a float 0.0–0.95 representing likelihood of gym visit today.
     */
    private function calcAttendanceProbability(
        string $membershipType,
        int    $daysSinceJoined,
        int    $dayOfWeek,
        int    $month
    ): float {
        $prob = match ($membershipType) {
            // PROMO = Resolutioner. Very high start, exponential decay.
            // Real data: 67% drop attendance by week 3 (IHRSA).
            'promo' => max(0.0, 0.65 * pow(0.93, $daysSinceJoined)),

            // DISCOUNT = Students/Seniors. Semester-sensitive steady state.
            'discount' => $this->studentProbability($daysSinceJoined, $month),

            // REGULAR = BPO workers, locals, gym rats. Stable with slow decay.
            default => max(0.15, 0.42 * pow(0.998, $daysSinceJoined)),
        };

        // ── Day-of-week adjustments ──────────────────────────────────────────
        $prob += match ($dayOfWeek) {
            1 => 0.12,             // Monday — "chest day", high intent
            2 => 0.05,             // Tuesday — still motivated
            3 => 0.02,             // Wednesday — midweek neutral
            4 => -0.03,            // Thursday — slight fatigue
            5 => -0.10,            // Friday — social plans, Baguio nightlife
            6 => -0.18,            // Saturday — sleep in, family
            0 => -0.20,            // Sunday — rest day culture
            default => 0.0,
        };

        // ── Seasonal / holiday overrides ────────────────────────────────────
        // Christmas–New Year slump
        if ($month === 12) $prob -= 0.18;
        // Deep rainy season (Baguio gets very heavy rains)
        if (in_array($month, [7, 8])) $prob -= 0.08;
        // Post-New Year peak (first 2 weeks of January, high discipline)
        if ($month === 1 && $daysSinceJoined <= 14) $prob += 0.10;
        // Panagbenga month — visitors but also locals inspired
        if ($month === 2) $prob += 0.05;

        return (float) max(0.02, min($prob, 0.95));
    }

    private function studentProbability(int $daysSinceJoined, int $month): float
    {
        // Students follow semester rhythm
        $base = 0.38;

        // Semester break / holiday → near-absent
        if (in_array($month, [12, 6, 7])) return 0.05;

        // Finals weeks (Nov, Apr) — skip gym for studying
        if (in_array($month, [11, 4])) $base = 0.20;

        // Fresh semester burst
        if (in_array($month, [8, 1]) && $daysSinceJoined <= 21) $base = 0.55;

        // Gradual enthusiasm decay through semester
        $base *= pow(0.995, $daysSinceJoined);

        return max(0.04, $base);
    }

    /**
     * Hour distribution shifts based on month (cold = early morning preference in Baguio).
     */
    private function getWeightedHour(int $month, int $dayOfWeek): int
    {
        $weights = self::HOUR_WEIGHTS;

        // Weekends: morning shift, softer evening
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            $weights[7] = (int) ($weights[7] * 1.3);
            $weights[17] = (int) ($weights[17] * 0.7);
        }

        // Baguio's summer months (Mar–May): later mornings, people sleep in
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

    /**
     * Returns [minMinutes, maxMinutes] for a workout session.
     */
    private function getSessionDuration(string $type, int $hour): array
    {
        // Early-morning sessions tend to be shorter (beat the rush / commute)
        if ($hour <= 7) return [35, 65];

        return match ($type) {
            'promo'   => [25, 55],   // Resolutioners do shorter, less focused workouts
            'discount' => [45, 80],  // Students hang out a bit
            default   => [50, 110],  // Regular members full workout
        };
    }

    private function buildCheckInRow(int $memberId, Carbon $in, ?Carbon $out): array
    {
        return [
            'member_id'    => $memberId,
            'created_at'   => $in,
            'check_out_at' => $out,
            'updated_at'   => $out ?? $in,
        ];
    }

    // =========================================================================
    // PERSONA HELPERS
    // =========================================================================

    /**
     * Persona probability mix varies month by month.
     *
     * Personas:
     *   student      — SLU/UB/BSU/BCU, bulk of membership
     *   worker       — BPO, government, retail staff
     *   resolutioner — Jan–Feb burst, no sticking power
     *   tourist      — Feb (Panagbenga), short-term promos
     *   senior       — retirees, consistent morning crowd
     *   athlete      — competitive lifters / runners, high frequency
     */
    private function pickPersona(int $month): string
    {
        // [student, worker, resolutioner, tourist, senior, athlete] cumulative %
        $distribution = match (true) {
            in_array($month, [1, 2])    => [40, 25, 20, 8, 5, 2],   // Jan–Feb
            in_array($month, [3, 4, 5]) => [25, 40, 5,  2, 20, 8],  // Summer
            in_array($month, [6, 7])    => [20, 45, 3,  0, 22, 10], // Rainy
            in_array($month, [8, 9])    => [60, 20, 2,  0, 12, 6],  // Sem starts
            in_array($month, [10, 11])  => [45, 30, 2,  0, 15, 8],  // Mid-sem
            default                     => [20, 40, 5,  2, 25, 8],  // Dec
        };

        $personas = ['student', 'worker', 'resolutioner', 'tourist', 'senior', 'athlete'];
        $rand = rand(1, 100);
        $cumulative = 0;
        foreach ($distribution as $i => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) return $personas[$i];
        }
        return 'worker';
    }

    /** Membership duration in months by persona (reflects real churn data). */
    private function getPersonaDuration(string $persona): int
    {
        return match ($persona) {
            'student'      => rand(3, 5),    // One semester
            'worker'       => rand(5, 14),   // Steady ~1 year
            'resolutioner' => rand(1, 2),    // Burns out fast
            'tourist'      => 1,             // Short-term visitor pass
            'senior'       => rand(8, 24),   // Long-term loyalists
            'athlete'      => rand(10, 24),  // Highly committed
            default        => rand(2, 4),
        };
    }

    private function getPersonaType(string $persona): string
    {
        return match ($persona) {
            'student'      => 'discount',
            'worker'       => 'regular',
            'resolutioner' => 'promo',
            'tourist'      => 'promo',
            'senior'       => 'discount',
            'athlete'      => 'regular',
            default        => 'regular',
        };
    }
}