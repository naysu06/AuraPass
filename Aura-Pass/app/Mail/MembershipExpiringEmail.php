<?php

namespace App\Mail;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MembershipExpiringEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Member $member;

    public function __construct(Member $member)
    {
        $this->member = $member;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Calculate days left relative to "Today"
        $daysLeft = now()->startOfDay()->diffInDays($this->member->membership_expiry_date->startOfDay(), false);
        
        // Determine the Subject Line dynamically
        if ($daysLeft < 0) {
            $subject = 'Notice: Your Membership Has Expired';
        } elseif ($daysLeft == 0) {
            $subject = 'Urgent: Your Membership Expires TODAY!';
        } elseif ($daysLeft == 1) {
            $subject = 'Urgent: Your Membership Expires TOMORROW!';
        } else {
            $subject = "Reminder: Membership Expires in {$daysLeft} Days";
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.expiring',
        );
    }
}