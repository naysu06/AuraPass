<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gym:remind-expiring')
    ->dailyAt('08:00') // Run every day at 8 AM
    ->timezone('Asia/Manila'); // Important for your location!