<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GymSetting extends Model
{
    protected $fillable = [
        'camera_mirror',
        'kiosk_debounce_seconds',
        'strict_mode',
        'auto_checkout_hours',
        'email_reminders_enabled',
    ];

    protected $casts = [
        'camera_mirror' => 'boolean',
        'strict_mode' => 'boolean',
        'email_reminders_enabled' => 'boolean',
    ];
}