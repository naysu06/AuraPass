<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CheckIn;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema; // Needed for safe truncation

class CheckInSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Disable Foreign Key Checks to safely wipe tables
        Schema::disableForeignKeyConstraints();
        CheckIn::truncate();
        Member::truncate(); // We wipe members to ensure we generate the specific "Scenario Data"
        Schema::enableForeignKeyConstraints();

        $this->command->info('Generating 50 Realistic Members...');

        $members = [];

        // --- GROUP A: THE URGENT ONES (Expiring in 1-3 days) ---
        // Good for testing the "Expiring Soon" widget red badge
        foreach(range(1, 5) as $i) {
            $members[] = Member::factory()->create([
                'name' => "Urgent User $i", // Optional: helps identify them
                'created_at' => Carbon::now()->subMonths(rand(3, 12)), // Joined a while ago
                'membership_expiry_date' => Carbon::now()->addDays(rand(1, 3)),
            ])->id;
        }

        // --- GROUP B: THE WARNING ONES (Expiring in 4-7 days) ---
        // Good for testing the yellow badge
        foreach(range(1, 5) as $i) {
            $members[] = Member::factory()->create([
                'created_at' => Carbon::now()->subMonths(rand(2, 6)),
                'membership_expiry_date' => Carbon::now()->addDays(rand(4, 7)),
            ])->id;
        }

        // --- GROUP C: THE EXPIRED ONES (Expired 1-10 days ago) ---
        // Good for testing Access Denied red screens
        foreach(range(1, 5) as $i) {
            $members[] = Member::factory()->create([
                'created_at' => Carbon::now()->subMonths(rand(6, 12)),
                'membership_expiry_date' => Carbon::now()->subDays(rand(1, 10)), // Past date!
            ])->id;
        }

        // --- GROUP D: THE FRESH BLOOD (Joined this week) ---
        // Good for "New Members" reports
        foreach(range(1, 5) as $i) {
            $members[] = Member::factory()->create([
                'created_at' => Carbon::now()->subDays(rand(0, 6)),
                'membership_expiry_date' => Carbon::now()->addMonth(),
            ])->id;
        }

        // --- GROUP E: THE REGULARS (Healthy expiry dates) ---
        // The rest of the 30 members
        foreach(range(1, 30) as $i) {
            $members[] = Member::factory()->create([
                'created_at' => Carbon::now()->subDays(rand(20, 300)), // Varied join dates
                'membership_expiry_date' => Carbon::now()->addMonths(rand(1, 6)),
            ])->id;
        }

        $this->command->info('Members generated. Starting Check-in Simulation (Furukawa Profile)...');

        // ---------------------------------------------------------
        // CHECK-IN SIMULATION (Your existing logic)
        // ---------------------------------------------------------
        
        $daysToSeed = 60; 
        $targetTotal = 2000; 
        
        // Furukawa Gym Profile (La Trinidad)
        $hourWeights = [
            0 => 0,  1 => 0,  2 => 0,  3 => 0,  4 => 0,  5 => 2,   
            6 => 20, 7 => 35, 8 => 25, 9 => 15, 10 => 10, 11 => 10, 
            12 => 15, 13 => 15, 14 => 20,                           
            15 => 35, 16 => 55,                                     
            17 => 85, 18 => 90, 19 => 75,                           
            20 => 40, 21 => 15,                                     
            22 => 5,  23 => 0                                       
        ];

        $buffer = []; 
        $batchSize = 500; 
        $totalInserted = 0;
        
        for ($i = $daysToSeed; $i >= 0; $i--) {
            
            $date = Carbon::now()->subDays($i);
            $isWeekend = $date->isWeekend();
            
            $growthFactor = ($daysToSeed - $i) * 0.2; 
            $baseVisitors = $isWeekend ? rand(20, 30) : rand(30, 45);
            $totalVisitors = ceil($baseVisitors + $growthFactor);

            for ($v = 0; $v < $totalVisitors; $v++) {
                if ($totalInserted >= $targetTotal) break 2; 

                $hour = $this->getWeightedRandomHour($hourWeights);
                
                $checkInTime = $date->copy()->setTime($hour, rand(0, 59), rand(0, 59));
                
                $minDuration = ($hour < 9) ? 45 : 60;
                $maxDuration = ($hour < 9) ? 75 : 120;
                
                $checkOutTime = $checkInTime->copy()->addMinutes(rand($minDuration, $maxDuration));

                if ($checkOutTime->isFuture()) {
                    $checkOutTime = null; 
                }

                // Pick a random member ID from the ones we just created
                $randomMemberId = $members[array_rand($members)];

                $buffer[] = [
                    'member_id' => $randomMemberId, 
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
        
        $this->command->info("Done! Seeded {$totalInserted} visits across 50 realistic member profiles.");
    }

    private function getWeightedRandomHour(array $weights): int
    {
        $rand = rand(1, array_sum($weights));
        foreach ($weights as $hour => $weight) {
            $rand -= $weight;
            if ($rand <= 0) return $hour;
        }
        return 17;
    }
}