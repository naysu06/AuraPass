<?php

// app/Models/Member.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'membership_expiry_date',
        'profile_photo',
        'membership_type',
    ];
    // This casts the 'membership_expiry_date' attribute to a Carbon instance, allowing for easy date manipulation.
    protected $casts = [
        'membership_expiry_date' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    // Automatically generate a UID when creating a new member
    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            $member->unique_id = 'mem_' . Str::random(8);
        });
    }

    /**
     * Get the check-ins for the member.
     */
    // Defines a one-to-many relationship between Member and CheckIn models.
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    /**
     * Members whose membership is currently active.
     *
     * Usage:
     * Member::active()->count();
     */
    // This scope is used to filter members whose membership has not expired.
    public function scopeActive($query)
    {
        return $query->where('membership_expiry_date', '>=', now());
    }

    /**
     * Members expiring within a given number of days.
     *
     * Usage:
     * Member::expiringWithin(7)->get();
     */
    // This scope is used to find members whose membership will expire within a certain number of days.
    public function scopeExpiringWithin($query, int $days)
    {
        return $query->whereBetween('membership_expiry_date', [now(), now()->addDays($days)]);
    }

    /**
     * Calculates the individual churn risk score for this specific member.
     * Returns a float between 0.0 (Safe) and 1.0 (High Risk).
     */
    public function getChurnRiskScoreAttribute(): float
    {
        $risk = 0.0;

        // 1. Recency
        $lastVisit = $this->checkIns()->latest('created_at')->value('created_at');
        $daysSinceVisit = $lastVisit ? now()->diffInDays($lastVisit) : 999;

        if ($daysSinceVisit >= 20)     $risk += 0.40;
        elseif ($daysSinceVisit >= 14) $risk += 0.25;
        elseif ($daysSinceVisit >= 7)  $risk += 0.10;

        // 2. Membership Age
        $monthsOld = (int) $this->created_at->diffInMonths(now());

        if ($monthsOld <= 1)      $risk += 0.20;  
        elseif ($monthsOld <= 6)  $risk += 0.15;  
        elseif ($monthsOld >= 12) $risk -= 0.15;  

        // 3. Expiry Window
        if ($this->membership_expiry_date) {
            $daysToExpiry = (int) now()->diffInDays($this->membership_expiry_date, false);
            if ($daysToExpiry <= 7)       $risk += 0.20;
            elseif ($daysToExpiry <= 14)  $risk += 0.10;
        }

        // 4. Plan Type
        $risk += match ($this->membership_type) {
            'promo'    => 0.20,
            'discount' => 0.05,
            default    => 0.0,
        };

        return (float) max(0.0, min($risk, 1.0));
    }
}