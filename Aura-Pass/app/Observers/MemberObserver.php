<?php

namespace App\Observers;

use App\Models\Member;
use App\Services\AuditLogService;
use Carbon\Carbon;

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
        $this->auditLogService->logActivity(
            'member.created', 
            $member, 
            ['member_name' => $member->name]
        );
    }

    /**
     * Handle the Member "updated" event.
     */
    public function updated(Member $member): void
    {
        // 1. Detect Membership Renewal Time Jumps
        if ($member->wasChanged('membership_expiry_date')) {
            $oldExpiry = $member->getOriginal('membership_expiry_date');
            $newExpiry = $member->membership_expiry_date;

            if ($oldExpiry && $newExpiry) {
                $oldDate = Carbon::parse($oldExpiry);
                $newDate = Carbon::parse($newExpiry);

                if ($newDate->greaterThan($oldDate)) {
                    $this->auditLogService->logActivity(
                        'member.renewed',
                        $member,
                        [
                            'member_name' => $member->name,
                            'old_expiry'  => $oldDate->format('Y-m-d'),
                            'new_expiry'  => $newDate->format('Y-m-d'),
                        ]
                    );
                    return;
                }
            }
        }

        // 2. Track Generic Member Parameter Changes
        $changes = [];
        foreach ($member->getChanges() as $attribute => $newValue) {
            if (!in_array($attribute, ['updated_at', 'membership_expiry_date'])) {
                $changes[$attribute] = [
                    'old' => $member->getOriginal($attribute),
                    'new' => $newValue,
                ];
            }
        }

        if (!empty($changes)) {
            $this->auditLogService->logActivity(
                'member.updated',
                $member,
                [
                    'member_name' => $member->name,
                    'changes'     => $changes,
                ]
            );
        }
    }

    /**
     * Handle the Member "deleting" event.
     * This runs BEFORE the member is deleted, allowing us to anonymize the PII.
     */
    public function deleting(Member $member): void
    {
        // Capture the real name BEFORE scrubbing — $member->name is 'Deleted Member' after updateQuietly
        $realName = $member->name;
        // 1. Log with the real name
        $this->auditLogService->logActivity(
            'member.deleted',
            null,
            ['member_name' => $realName]
        );
        // 2. Now scrub PII
        $member->updateQuietly([
            'name'          => 'Deleted Member',
            'email'         => 'deleted@aurapass.test',
            'profile_photo' => null,
        ]);
    }
    /**
     * Handle the Member "restored" event.
     */
    public function restored(Member $member): void { }

    /**
     * Handle the Member "force deleted" event.
     */
    public function forceDeleted(Member $member): void { }
}