<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1. Your Existing Email Reminder
// We set this to run every minute.
Schedule::command('gym:remind-expiring')
    ->dailyAt('12:00') // Run every day at 12 PM
    ->timezone('Asia/Manila');

// 2. NEW: The Auto-Checkout Sweeper
// We set this to run every minute. 
Schedule::command('gym:auto-checkout')
    ->everyMinute()
    ->timezone('Asia/Manila');

// Run the Daily Report every night at 10:00 PM
Schedule::command('aurapass:generate-reports daily')->dailyAt('22:30')
    //->everyMinute()
    ->timezone('Asia/Manila');

// Run the Weekly Report every Monday morning at 6:00 AM
Schedule::command('aurapass:generate-reports weekly')->weeklyOn(1, '06:00')
    //->everyMinute()
    ->timezone('Asia/Manila');

// Run the Monthly Report on the 1st of every month at 6:00 AM
Schedule::command('aurapass:generate-reports monthly')->monthlyOn(1, '06:00')
    //->everyMinute()
    ->timezone('Asia/Manila');