<?php

// app/Models/Member.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; // <-- Import this

class Member extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'membership_expiry_date']; // Add fillable fields

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
}