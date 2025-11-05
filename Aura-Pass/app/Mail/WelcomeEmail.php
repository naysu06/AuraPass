<?php

namespace App\Mail;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // We implement this
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment; // For the attachment
use SimpleSoftwareIO\QrCode\Facades\QrCode; // For the QR code

class WelcomeEmail extends Mailable implements ShouldQueue // Implement here
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
        // Generate the SVG as an HtmlString object
        $qrCode = QrCode::format('svg')
                        ->size(400)
                        ->generate($this->member->unique_id);

        // 1. Cast the HtmlString object to a plain string
        // 2. Attach the plain string data as an .svg file
        return [
            Attachment::fromData(fn () => (string) $qrCode, 'Your-QR-Code.svg')
                ->withMime('image/svg+xml'),
        ];
    }
}