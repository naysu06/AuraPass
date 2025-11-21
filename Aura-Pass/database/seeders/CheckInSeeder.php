<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CheckIn;
use App\Models\Member;
use Carbon\Carbon;

class CheckInSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clean the slate
        CheckIn::truncate(); 

        // CONFIGURATION for "Furukawa Gym" simulation
        $daysToSeed = 60; 
        $targetTotal = 2000; // Target ~33 visits/day for 50 members
        
        $members = Member::pluck('id')->toArray();

        if (empty($members)) {
            $this->command->info('No members found. Creating 50 dummy members...');
            $members = \App\Models\Member::factory()->count(50)->create()->pluck('id')->toArray();
        }

        // REALISTIC WEIGHTS (La Trinidad Profile)
        // 0-5: Closed/Empty
        // 6-8: Morning Rush (Workers)
        // 9-14: Quiet / Lunch
        // 15-16: Students arrive
        // 17-19: After-work Peak (Maximum traffic)
        // 20-21: Tapering off
        $hourWeights = [
            0 => 0,  1 => 0,  2 => 0,  3 => 0,  4 => 0,  5 => 2,   // 5 AM: Early birds
            6 => 20, 7 => 35, 8 => 25, 9 => 15, 10 => 10, 11 => 10, // Morning
            12 => 15, 13 => 15, 14 => 20,                           // Lunch / Slow
            15 => 35, 16 => 55,                                     // Student Surge
            17 => 85, 18 => 90, 19 => 75,                           // The "Furukawa Peak"
            20 => 40, 21 => 15,                                     // Cooldown
            22 => 5,  23 => 0                                       // Closing
        ];

        $buffer = []; 
        $batchSize = 500; 
        $totalInserted = 0;
        
        for ($i = $daysToSeed; $i >= 0; $i--) {
            
            $date = Carbon::now()->subDays($i);
            $isWeekend = $date->isWeekend();
            
            // VOLUME LOGIC (50 Members)
            // To hit ~2000 visits in 60 days, we need ~33 visits/day.
            // Weekends are usually quieter in commercial gyms.
            $growthFactor = ($daysToSeed - $i) * 0.2; // Slight growth trend
            $baseVisitors = $isWeekend ? rand(20, 30) : rand(30, 45);
            $totalVisitors = ceil($baseVisitors + $growthFactor);

            for ($v = 0; $v < $totalVisitors; $v++) {
                if ($totalInserted >= $targetTotal) {
                    break 2; 
                }

                $hour = $this->getWeightedRandomHour($hourWeights);
                
                // Randomized minute (0-59)
                $checkInTime = $date->copy()->setTime($hour, rand(0, 59), rand(0, 59));
                
                // DURATION LOGIC
                // Morning sessions are tighter (45-75 mins)
                // Evening sessions are longer (60-120 mins)
                $minDuration = ($hour < 9) ? 45 : 60;
                $maxDuration = ($hour < 9) ? 75 : 120;
                
                $checkOutTime = $checkInTime->copy()->addMinutes(rand($minDuration, $maxDuration));

                if ($checkOutTime->isFuture()) {
                    $checkOutTime = null; 
                }

                $buffer[] = [
                    'member_id' => $members[array_rand($members)], 
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
        
        $this->command->info("Successfully seeded {$totalInserted} realistic visits based on Furukawa profile!");
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