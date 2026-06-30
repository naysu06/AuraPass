<?php

namespace App\Listeners;

use App\Events\MemberCheckedOutManually;
use App\Services\AuditLogService;

class LogMemberCheckedOutManually
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function handle(MemberCheckedOutManually $event): void
    {
        // Snapshot the name into the JSON details payload
        $this->auditLogService->logActivity('member.checked_out_manually', $event->member, [
            'member_name' => $event->memberName,
        ]);
    }
}