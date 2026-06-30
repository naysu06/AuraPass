<?php

namespace App\Events;

use App\Models\Member;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberScanFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?Member $member; 
    public string $reason; 
    public ?string $scannedCode; // NEW: Added to capture unmapped QR inputs

    public function __construct(?Member $member, string $reason, ?string $scannedCode = null)
    {
        $this->member = $member;
        $this->reason = $reason;
        $this->scannedCode = $scannedCode; // Assign the raw input string
    }

    public function broadcastOn(): array
    {
        return [new Channel('monitor-screen')];
    }

    public function broadcastAs(): string
    {
        return 'member.scan_failed';
    }
}