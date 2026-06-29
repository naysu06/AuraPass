<?php

namespace App\Listeners;

use App\Events\MemberCheckedInManually;
use App\Services\AuditLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogMemberCheckedInManually
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
    public function handle(MemberCheckedInManually $event): void
    {
        $this->auditLogService->logActivity('member.checked_in_manually', $event->member);
    }
}
