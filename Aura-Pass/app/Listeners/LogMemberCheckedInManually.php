<?php

namespace App\Listeners;

use App\Events\MemberCheckedInManually;
use App\Services\AuditLogService;

class LogMemberCheckedInManually
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function handle(MemberCheckedInManually $event): void
    {
        // Snapshot the name into the JSON details payload
        $this->auditLogService->logActivity('member.checked_in_manually', $event->member, [
            'member_name' => $event->memberName,
        ]);
    }
}