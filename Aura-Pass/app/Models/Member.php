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

    protected $fillable = ['name', 'email', 'membership_expiry_date', 'profile_photo']; // Add fillable fields

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'membership_expiry_date' => 'datetime', // <-- ADD THIS
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            // Create a simple, unique ID, e.g., "mem_aKqXv5Pz"
            $member->unique_id = 'mem_' . Str::random(8);
        });
    }

    /**
     * Get the check-ins for the member.
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }
}