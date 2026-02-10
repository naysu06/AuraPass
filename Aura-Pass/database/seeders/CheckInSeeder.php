<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CheckIn;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class CheckInSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Disable Foreign Key Checks to safely wipe tables
        Schema::disableForeignKeyConstraints();
        CheckIn::truncate();
        Member::truncate(); 
        Schema::enableForeignKeyConstraints();

        $this->command->info('Generating 50 Realistic Members with Membership Types...');

        $members = [];
        $types = ['regular', 'discount', 'promo'];

        // --- GROUP A: THE URGENT ONES (Expiring in 1-3 days) ---
        foreach(range(1, 12) as $i) {
            $members[] = Member::factory()->create([
                'name' => "Urgent User $i", 
                'created_at' => Carbon::now()->subMonths(rand(3, 12)), 
                'membership_expiry_date' => Carbon::now()->addDays(rand(1, 3)),
                'membership_type' => $types[array_rand($types)],
            ])->id;
        }

        // --- GROUP B: THE WARNING ONES (Expiring in 4-7 days) ---
        // Reduced to 1 (Part of the "Only 3 Expiring" request)
        foreach(range(1, 1) as $i) {
            $members[] = Member::factory()->create([
                'name' => "Warning User $i", 
                'created_at' => Carbon::now()->subMonths(rand(2, 6)),
                'membership_expiry_date' => Carbon::now()->addDays(rand(4, 7)),
                'membership_type' => 'regular',
            ])->id;
        }

        // --- GROUP C: THE EXPIRED ONES (Expired 1-10 days ago) ---
        // Kept at 5 to test Access Denied functionality
        foreach(range(1, 5) as $i) {
            $members[] = Member::factory()->create([
                'created_at' => Carbon::now()->subMonths(rand(6, 12)),
                'membership_expiry_date' => Carbon::now()->subDays(rand(1, 10)), 
                'membership_type' => $types[array_rand($types)],
            ])->id;
        }

        // --- GROUP D: THE FRESH BLOOD (Joined this week) ---
        foreach(range(1, 5) as $i) {
            $members[] = Member::factory()->create([
                'created_at' => Carbon::now()->subDays(rand(0, 6)),
                'membership_expiry_date' => Carbon::now()->addMonth(),
                'membership_type' => 'promo',
            ])->id;
        }

        // --- GROUP E: THE REGULARS ---
        // Adjusted to 37 to keep total at 50
        foreach(range(1, 37) as $i) {
            $rand = rand(1, 100);
            $type = ($rand <= 60) ? 'regular' : (($rand <= 90) ? 'discount' : 'promo');

            $members[] = Member::factory()->create([
                'created_at' => Carbon::now()->subDays(rand(20, 300)), 
                'membership_expiry_date' => Carbon::now()->addMonths(rand(1, 6)),
                'membership_type' => $type,
            ])->id;
        }

        $this->command->info('Members generated. Starting Realistic Check-in Simulation...');

        // ---------------------------------------------------------
        // CHECK-IN SIMULATION (Realistic Patterns)
        // ---------------------------------------------------------
        
        $daysToSeed = 60; 
        $targetTotal = 3000; 
        
        // Furukawa Gym Profile (Peak at 6PM)
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
            $dayOfWeek = $date->dayOfWeek; // 0 (Sun) - 6 (Sat)
            
            // --- DAILY VOLUME LOGIC ---
            // Monday (1): Busiest day of the week
            // Midweek (2-4): Steady
            // Friday (5): Drop off
            // Weekend (0, 6): Low volume
            if ($dayOfWeek == 1) {
                $baseVisitors = rand(45, 55); // Monday Surge
            } elseif ($dayOfWeek == 5) {
                $baseVisitors = rand(30, 40); // Friday Lull
            } elseif ($date->isWeekend()) {
                $baseVisitors = rand(15, 25); // Weekend
            } else {
                $baseVisitors = rand(35, 45); // Tue-Thu
            }

            // Trend: Gym is getting popular (+10-15% over 2 months)
            $growth = ($daysToSeed - $i) * 0.2; 
            
            $totalVisitors = ceil($baseVisitors + $growth);

            for ($v = 0; $v < $totalVisitors; $v++) {
                if ($totalInserted >= $targetTotal) break 2; 

                $hour = $this->getWeightedRandomHour($hourWeights);
                $checkInTime = $date->copy()->setTime($hour, rand(0, 59), rand(0, 59));
                
                $minDuration = ($hour < 9) ? 45 : 60;
                $maxDuration = ($hour < 9) ? 75 : 120;
                $checkOutTime = $checkInTime->copy()->addMinutes(rand($minDuration, $maxDuration));

                if ($checkOutTime->isFuture()) $checkOutTime = null; 

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

        if (!empty($buffer)) CheckIn::insert($buffer);
        
        $this->command->info("Done! Seeded {$totalInserted} visits with Monday Surges and Weekend Lulls.");
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