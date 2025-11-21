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

    public $member; // Can be null if not found
    public string $reason; // 'expired', 'not_found', or 'ignored'

    public function __construct(?Member $member, string $reason)
    {
        $this->member = $member;
        $this->reason = $reason;
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