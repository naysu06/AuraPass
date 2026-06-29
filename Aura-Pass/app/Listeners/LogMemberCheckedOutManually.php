<?php

namespace App\Listeners;

use App\Events\MemberCheckedOutManually;
use App\Services\AuditLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogMemberCheckedOutManually
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
    public function handle(MemberCheckedOutManually $event): void
    {
        $this->auditLogService->logActivity('member.checked_out_manually', $event->member);
    }
}
