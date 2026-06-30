<?php

namespace App\Listeners;

use App\Events\MemberCheckedOut;
use App\Services\AuditLogService;

class LogMemberCheckedOut
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function handle(MemberCheckedOut $event): void
    {
        // Snapshot the name into the JSON details payload
        $this->auditLogService->logActivity('member.checked_out', $event->member, [
            'member_name' => $event->memberName,
        ]);
    }
}