<?php

namespace App\Listeners;

use App\Events\MemberScanFailed;
use App\Services\AuditLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogMemberScanFailed
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
    public function handle(MemberScanFailed $event): void
    {
        $this->auditLogService->logActivity(
            'member.scan_failed',
            $event->member,
            ['reason' => $event->reason]
        );
    }
}
