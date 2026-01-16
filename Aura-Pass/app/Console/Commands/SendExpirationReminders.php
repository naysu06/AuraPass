<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Mail\MembershipExpiringEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendExpirationReminders extends Command
{
    /**
     * The signature allows you to run this manually: "php artisan gym:remind-expiring"
     */
    protected $signature = 'gym:remind-expiring';

    protected $description = 'Send email reminders to members expiring in 7, 3, 1 days, or today';

    public function handle()
    {
        // 1. Calculate the specific target dates
        $inSevenDays = now()->addDays(7)->toDateString();
        $inThreeDays = now()->addDays(3)->toDateString();
        $tomorrow    = now()->addDays(1)->toDateString();
        $today       = now()->toDateString(); // <--- Added TODAY

        $this->info("Checking for members expiring on: {$inSevenDays}, {$inThreeDays}, {$tomorrow}, or {$today}");

        // 2. Find members whose expiry date matches ANY of these dates
        // We use whereDate to ignore the time component (00:00:00 vs 14:30:00)
        $expiringMembers = Member::whereDate('membership_expiry_date', $inSevenDays)
            ->orWhereDate('membership_expiry_date', $inThreeDays)
            ->orWhereDate('membership_expiry_date', $tomorrow)
            ->orWhereDate('membership_expiry_date', $today) // <--- Check for today
            ->get();

        if ($expiringMembers->isEmpty()) {
            $this->info("No members found expiring on these specific days.");
            return;
        }

        // 3. Send Emails
        foreach ($expiringMembers as $member) {
            // Using 'queue' pushes it to your existing worker (fast!)
            Mail::to($member->email)->queue(new MembershipExpiringEmail($member));
            
            // Calculate days left for the console log just for info
            // Use startOfDay() for accurate "0 days" calc
            $daysLeft = now()->startOfDay()->diffInDays($member->membership_expiry_date->startOfDay(), false);
            
            $this->info("Queued reminder for: {$member->name} (Expires in ~{$daysLeft} days)");
        }

        $this->info("Successfully queued " . $expiringMembers->count() . " reminders.");
    }
}