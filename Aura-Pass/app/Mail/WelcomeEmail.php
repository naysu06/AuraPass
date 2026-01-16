<?php

namespace App\Mail;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; 
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment; 
use SimpleSoftwareIO\QrCode\Facades\QrCode; 

class WelcomeEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Member $member;

    /**
     * Create a new message instance.
     */
    public function __construct(Member $member)
    {
        $this->member = $member;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to the Gym! Your QR Code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.welcome', // Points to our template
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        // Generate the PNG as a raw binary string
        $qrCode = (string) QrCode::format('png')
                        ->size(400)
                        ->backgroundColor(255, 255, 255) // White background
                        ->color(0, 0, 0) // Black foreground (optional, but explicit)
                        ->margin(2) // Add margin for better scanning
                        ->generate($this->member->unique_id);

        // Attach the binary string data as a .png file
        return [
            Attachment::fromData(fn () => $qrCode, 'Your-QR-Code.png')
                ->withMime('image/png'),
        ];
    }
}