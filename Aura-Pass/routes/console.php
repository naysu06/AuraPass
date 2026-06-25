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