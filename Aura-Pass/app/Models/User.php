<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\HasName; // <-- Existing: Required for Filament display names
use Filament\Models\Contracts\FilamentUser; // <-- NEW: Required for production access
use Filament\Panel; // <-- NEW: Required for production access

// Implementing HasName tells Filament what to display instead of 'name'
// Implementing FilamentUser tells Filament who is allowed in the dashboard
class User extends Authenticatable implements HasName, FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Filament will call this method to get the user's display name.
     */
    public function getFilamentName(): string
    {
        return $this->username;
    }

    /**
     * NEW: Determines if the user can access the Filament panel in production.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // By returning true, you bypass the production 403 Forbidden error.
        // If you ever want to restrict this in the future, you could change this to:
        // return $this->role === 'superadmin';
        return true;
    }
}