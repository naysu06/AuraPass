<?php

namespace App\Listeners;

use App\Events\MemberCheckedOut;
use App\Services\AuditLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogMemberCheckedOut
{
    protected $auditLogService;

    /**
     * Create the event listener.
     */
    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Handle the event.
     */
    public function handle(MemberCheckedOut $event): void
    {
        $this->auditLogService->logActivity('member.checked_out', $event->member);
    }
}
