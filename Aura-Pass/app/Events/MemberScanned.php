<?php

namespace App\Events;

use App\Models\Member;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; // <-- Implement this
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberScanned implements ShouldBroadcast // <-- Implement this
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // These properties will be sent in the broadcast
    public ?Member $member;
    public string $status;

    /**
     * Create a new event instance.
     */
    public function __construct(?Member $member, string $status)
    {
        $this->member = $member;
        $this->status = $status; // 'active', 'expired', or 'not_found'
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // This is a public channel anyone can listen to
        return [
            new Channel('monitor-screen'),
        ];
    }

    /**
     * The name of the event as it's broadcast.
     */
    public function broadcastAs(): string
    {
        return 'member.scanned';
    }
}