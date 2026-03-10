<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CheckIn;
use App\Models\GymSetting;
use Carbon\Carbon;

class AutoCheckoutMembers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gym:auto-checkout';

    /**
     * The console command description.
     */
    protected $description = 'Automatically check out members who forgot to scan out based on the gym settings threshold.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Auto-Checkout Sweeper...');

        // 1. Get the Gym Settings
        $settings = GymSetting::first();
        
        // If settings exist AND the hours are greater than 0, use it. Otherwise, force 4 hours.
        $thresholdHours = ($settings && $settings->auto_checkout_hours > 0) 
            ? $settings->auto_checkout_hours 
            : 4; 

        // 3. Find all "Abandoned" Check-Ins
        $abandonedCheckIns = CheckIn::whereNull('check_out_at')
            ->where('created_at', '<=', Carbon::now()->subHours($thresholdHours))
            ->get();

        $count = 0;

        // 4. Loop through and forcefully check them out
        foreach ($abandonedCheckIns as $checkIn) {
            
            // We set their check-out time to exactly when their threshold expired.
            $checkIn->update([
                'check_out_at' => $checkIn->created_at->addHours($thresholdHours),
            ]);
            
            $count++;
        }

        // 5. Print success message
        $this->info("Sweeper Complete! Automatically checked out {$count} abandoned sessions using a {$thresholdHours}-hour threshold.");
    }
}