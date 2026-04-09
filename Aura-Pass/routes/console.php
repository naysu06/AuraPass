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
    ->everyMinute() // Run every day at 8 AM
    ->timezone('Asia/Manila');

// 2. NEW: The Auto-Checkout Sweeper
// We set this to run every minute. 
Schedule::command('gym:auto-checkout')
    ->everyMinute()
    ->timezone('Asia/Manila');