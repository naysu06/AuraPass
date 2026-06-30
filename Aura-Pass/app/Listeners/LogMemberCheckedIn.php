<?php

namespace App\Listeners;

use App\Events\MemberCheckedIn;
use App\Services\AuditLogService;

class LogMemberCheckedIn
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function handle(MemberCheckedIn $event): void
    {
        // Snapshot the name into the JSON details payload
        $this->auditLogService->logActivity('member.checked_in', $event->member, [
            'member_name' => $event->memberName,
        ]);
    }
}