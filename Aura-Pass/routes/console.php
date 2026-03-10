<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1. Your Existing Email Reminder
Schedule::command('gym:remind-expiring')
    ->dailyAt('08:00') // Run every day at 8 AM
    ->timezone('Asia/Manila');

// 2. NEW: The Auto-Checkout Sweeper
// We set this to run every hour. 
Schedule::command('gym:auto-checkout')
    ->hourly()
    ->timezone('Asia/Manila');