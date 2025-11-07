<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Import this

class CheckIn extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['member_id'];

    /**
     * Get the member that owns the check-in.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}