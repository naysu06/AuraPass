<?php

namespace App\Listeners;

use App\Events\MemberScanFailed;
use App\Services\AuditLogService;

class LogMemberScanFailed
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function handle(MemberScanFailed $event): void
    {
        $details = [
            'reason' => $event->reason,
        ];

        if ($event->member) {
            // Snapshot the recognized member's name
            $details['member_name'] = $event->member->name;
        } else {
            // Log raw input payload if code did not match any system record
            $details['scanned_code'] = $event->scannedCode ?? 'Unknown QR Payload';
        }

        $this->auditLogService->logActivity(
            'member.scan_failed',
            $event->member, // Will be null if record was not found
            $details
        );
    }
}