<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditLogService;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        app(AuditLogService::class)->logActivity(
            'admin.created',
            $user,
            [
                'username' => $user->username,
                'role'     => $user->role,
            ]
        );
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $changes = [];

        // Check if specific RBAC columns were altered
        if ($user->wasChanged('username')) {
            $changes['username'] = [
                'old' => $user->getOriginal('username'),
                'new' => $user->username,
            ];
        }

        if ($user->wasChanged('role')) {
            $changes['role'] = [
                'old' => $user->getOriginal('role'),
                'new' => $user->role,
            ];
        }

        if ($user->wasChanged('password')) {
            $changes['password'] = 'Password was reset/changed'; // Never log the actual hash!
        }

        // Only log if something critical actually changed
        if (!empty($changes)) {
            app(AuditLogService::class)->logActivity(
                'admin.updated',
                $user,
                [
                    'username' => $user->username, // Fallback for UI
                    'changes'  => $changes,
                ]
            );
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // We pass 'null' for the model here because the row is gone.
        // Passing the model could cause UI crashes when it tries to find a deleted relation.
        app(AuditLogService::class)->logActivity(
            'admin.deleted',
            null, 
            [
                'username' => $user->username,
                'role'     => $user->role,
            ]
        );
    }
}