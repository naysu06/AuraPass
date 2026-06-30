<?php

namespace App\Events;

use App\Models\Member;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MemberCheckedOut implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $member;
    public string $memberName;


    public function __construct(Member $member)
    {
        $this->member = $member;
        $this->memberName = $member->name; // Store the member's name at the time of event creation
    }

    public function broadcastOn(): array
    {
        return [new Channel('monitor-screen')];
    }

    public function broadcastAs(): string
    {
        return 'member.checked_out';
    }
}