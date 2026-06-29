<?php

namespace App\Observers;

use App\Models\Member;
use App\Services\AuditLogService;

class MemberObserver
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Handle the Member "created" event.
     */
    public function created(Member $member): void
    {
        $this->auditLogService->logActivity('member.created', $member);
    }

    /**
     * Handle the Member "updated" event.
     */
    public function updated(Member $member): void
    {
        $changes = [];
        foreach ($member->getChanges() as $attribute => $newValue) {
            if ($attribute !== 'updated_at') {
                $changes[$attribute] = [
                    'old' => $member->getOriginal($attribute),
                    'new' => $newValue,
                ];
            }
        }

        if (!empty($changes)) {
            $this->auditLogService->logActivity('member.updated', $member, $changes);
        }
    }

    /**
     * Handle the Member "deleted" event.
     */
    public function deleted(Member $member): void
    {
        $this->auditLogService->logActivity('member.deleted', $member);
    }

    /**
     * Handle the Member "restored" event.
     */
    public function restored(Member $member): void
    {
        //
    }

    /**
     * Handle the Member "force deleted" event.
     */
    public function forceDeleted(Member $member): void
    {
        //
    }
}
