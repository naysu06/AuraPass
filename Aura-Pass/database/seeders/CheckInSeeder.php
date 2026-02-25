<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CheckIn;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CheckInSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        CheckIn::truncate();
        Member::truncate(); 
        Schema::enableForeignKeyConstraints();

        $this->command->info('Generating 300+ Realistic Members (Structured Cohorts)...');

        $members = [];

        // --- GROUP A: THE TARGETS (Guaranteed to trigger Emails & Dashboard Alerts) ---
        $alertTargets = [
            ['name' => "Urgent User (1 Day)", 'days' => 1, 'type' => 'regular'],
            ['name' => "Warning User (3 Days)", 'days' => 3, 'type' => 'discount'],
            ['name' => "Heads Up User (7 Days)", 'days' => 7, 'type' => 'promo'],
            ['name' => "Critical User (Today)", 'days' => 0, 'type' => 'regular'],
        ];

        foreach ($alertTargets as $target) {
            $member = Member::factory()->create([
                'name' => $target['name'],
                'created_at' => Carbon::now()->subMonths(6), 
                'membership_expiry_date' => Carbon::now()->addDays($target['days']),
                'membership_type' => $target['type'],
            ]);
            $members[] = $member;
        }

        // --- GROUP B: HISTORICAL / EXPIRED (Churned Members) ---
        // People who joined 6-12 months ago and already expired. 
        // This gives us historical data without bloating current active numbers.
        for ($i = 0; $i < 40; $i++) {
            $created = Carbon::now()->subMonths(rand(6, 12))->subDays(rand(0, 30));
            $expired = $created->copy()->addMonths(rand(1, 3)); // Stayed for 1-3 months then quit
            
            $members[] = Member::factory()->create([
                'created_at' => $created,
                'membership_expiry_date' => $expired,
                'membership_type' => $this->getRandomType(),
            ]);
        }

        // --- GROUP C: ACTIVE CORE (The bulk of the gym) ---
        // Joined between 1 and 8 months ago, expiring randomly over the NEXT 6 months.
        // This is what makes the "Future Trends" chart look beautiful and curved.
        for ($i = 0; $i < 200; $i++) {
            $created = Carbon::now()->subMonths(rand(1, 8))->subDays(rand(0, 30));
            $expires = Carbon::now()->addDays(rand(10, 180)); // Expires within next 6 months
            
            $members[] = Member::factory()->create([
                'created_at' => $created,
                'membership_expiry_date' => $expires,
                'membership_type' => $this->getRandomType(),
            ]);
        }

        // --- GROUP D: NEW SIGNUPS (Recent Growth) ---
        // Joined in the last 30 days, expiry far in the future.
        for ($i = 0; $i < 40; $i++) {
            $created = Carbon::now()->subDays(rand(0, 30));
            $expires = $created->copy()->addMonths([1, 3, 6, 12][array_rand([1, 3, 6, 12])]);
            
            $members[] = Member::factory()->create([
                'created_at' => $created,
                'membership_expiry_date' => $expires,
                'membership_type' => $this->getRandomType(),
            ]);
        }

        $allMembers = collect($members);
        $this->command->info("Generated {$allMembers->count()} Members. Simulating 90 Days of Check-ins...");

        // ---------------------------------------------------------
        // CHECK-IN SIMULATION (90 Days)
        // ---------------------------------------------------------
        
        $daysToSeed = 90; 
        
        // Accurate Philippine Gym Peak Hours
        $hourWeights = [
            0 => 0,  1 => 0,  2 => 0,  3 => 0,  4 => 1,  5 => 5,   // Early Birds
            6 => 25, 7 => 40, 8 => 25, 9 => 15, 10 => 10, 11 => 10, // Morning Rush
            12 => 15, 13 => 15, 14 => 20,                           // Lunch Lull
            15 => 35, 16 => 55,                                     // Afternoon Pickup
            17 => 90, 18 => 100, 19 => 85,                          // EVENING PEAK
            20 => 50, 21 => 20,                                     // Winding down
            22 => 5,  23 => 0                                       
        ];

        $buffer = []; 
        $batchSize = 500; 
        $totalInserted = 0;
        
        for ($i = $daysToSeed; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayOfWeek = $date->dayOfWeek;
            
            // Filter pool: Only members who existed on this date, and hadn't expired more than 7 days prior
            $activeMembersOnThisDate = $allMembers->filter(function($m) use ($date) {
                return $m->created_at <= $date && $m->membership_expiry_date >= $date->copy()->subDays(7);
            })->values();

            if ($activeMembersOnThisDate->isEmpty()) continue;

            // Determine how busy the gym is based on the day of the week
            // e.g., Mondays see ~35% of active members, Sundays see ~15%
            if ($dayOfWeek == 1) { // Monday
                $attendanceRate = rand(30, 40) / 100;
            } elseif ($dayOfWeek >= 2 && $dayOfWeek <= 4) { // Tue-Thu
                $attendanceRate = rand(25, 35) / 100;
            } elseif ($dayOfWeek == 5) { // Friday
                $attendanceRate = rand(20, 25) / 100;
            } else { // Weekend
                $attendanceRate = rand(10, 18) / 100;
            }

            // Calculate total visitors for the day
            $totalVisitors = ceil($activeMembersOnThisDate->count() * $attendanceRate);

            for ($v = 0; $v < $totalVisitors; $v++) {
                $hour = $this->getWeightedRandomHour($hourWeights);
                $checkInTime = $date->copy()->setTime($hour, rand(0, 59), rand(0, 59));
                
                // FIX: If the randomly generated check-in time is in the future, ignore it entirely!
                // This prevents the system from pretending 50 people checked in tonight before tonight even happens.
                if ($checkInTime->isFuture()) {
                    continue; 
                }
                
                // Duration: Shorter in mornings (people have work), longer in evenings
                $minDuration = ($hour < 9) ? 45 : 60;
                $maxDuration = ($hour < 9) ? 75 : 120;
                $checkOutTime = $checkInTime->copy()->addMinutes(rand($minDuration, $maxDuration));

                // If check-out is in the future, they are "Currently Inside" the gym!
                if ($checkOutTime->isFuture()) {
                    $checkOutTime = null; 
                }

                // Pick a random eligible member
                $randomMember = $activeMembersOnThisDate->random();

                $buffer[] = [
                    'member_id' => $randomMember->id, 
                    'created_at' => $checkInTime,     
                    'check_out_at' => $checkOutTime,  
                    'updated_at' => $checkOutTime ?? $checkInTime,
                ];

                $totalInserted++;

                if (count($buffer) >= $batchSize) {
                    CheckIn::insert($buffer);
                    $buffer = [];
                }
            }
        }

        if (!empty($buffer)) {
            CheckIn::insert($buffer);
        }
        
        $this->command->info("Done! Seeded {$totalInserted} realistic check-in logs over 90 days.");
    }

    private function getRandomType(): string
    {
        $rand = rand(1, 100);
        return ($rand <= 60) ? 'regular' : (($rand <= 85) ? 'discount' : 'promo');
    }

    private function getWeightedRandomHour(array $weights): int
    {
        $rand = rand(1, array_sum($weights));
        foreach ($weights as $hour => $weight) {
            $rand -= $weight;
            if ($rand <= 0) return $hour;
        }
        return 17; // Fallback to 5 PM
    }
}