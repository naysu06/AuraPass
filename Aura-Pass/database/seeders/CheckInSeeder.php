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

        $daysToSeed = 60; 
        
        // Get real member IDs from your database
        $members = Member::pluck('id')->toArray();

        // Safety Net: If you haven't created members yet, let's make 10 dummy ones
        // so the seeder doesn't crash.
        if (empty($members)) {
            $this->command->info('No members found. Creating 10 dummy members...');
            $members = \App\Models\Member::factory()->count(10)->create()->pluck('id')->toArray();
        }

        // WEIGHTS: Higher number = More traffic at that hour (24h format)
        // Notice the spikes at 7am and 6pm (18:00)
        $hourWeights = [
            0 => 1,  1 => 0,  2 => 0,  3 => 0,  4 => 1,  5 => 3,  
            6 => 15, 7 => 30, 8 => 25, 9 => 15, 10 => 10, 11 => 8, 
            12 => 12, 13 => 10, 14 => 10, 15 => 12, 16 => 15,      
            17 => 40, 18 => 50, 19 => 45, 20 => 30, 21 => 15,      
            22 => 5,  23 => 2                                      
        ];

        $data = [];
        
        // Loop backwards from 60 days ago until today
        for ($i = $daysToSeed; $i >= 0; $i--) {
            
            $date = Carbon::now()->subDays($i);
            $isWeekend = $date->isWeekend();
            
            // Logic: Weekends are 50% quieter than weekdays
            // Logic: Traffic has been growing slightly over the last 2 months
            $growthFactor = ($daysToSeed - $i) * 0.3; 
            $baseVisitors = $isWeekend ? rand(15, 30) : rand(35, 70);
            $totalVisitors = ceil($baseVisitors + $growthFactor);

            for ($v = 0; $v < $totalVisitors; $v++) {
                $hour = $this->getWeightedRandomHour($hourWeights);
                
                // 1. CHECK IN TIME
                $checkInTime = $date->copy()->setTime($hour, rand(0, 59), rand(0, 59));

                // 2. CHECK OUT TIME 
                // Randomly stay between 45 mins and 2 hours
                $duration = rand(45, 120); 
                $checkOutTime = $checkInTime->copy()->addMinutes($duration);

                // REALISM: If the calculated checkout time is in the future (e.g., they came in 30 mins ago),
                // set checkout to NULL. They are currently working out.
                if ($checkOutTime->isFuture()) {
                    $checkOutTime = null; 
                }

                $data[] = [
                    'member_id' => $members[array_rand($members)], 
                    'created_at' => $checkInTime,     // This acts as Check In
                    'check_out_at' => $checkOutTime,  // This acts as Check Out
                    'updated_at' => $checkOutTime ?? $checkInTime,
                ];
            }
        }

        // Bulk Insert (Fast)
        foreach (array_chunk($data, 500) as $chunk) {
            CheckIn::insert($chunk);
        }
        
        $this->command->info('Successfully simulated ' . count($data) . ' gym visits!');
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