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
/**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        // 1. Define the absolute path to your gym's logo.
        // Ensure you have a logo file placed at public/images/logo.png
        $logoPath = public_path('images/logo.png');

        // Generate the PNG as a raw binary string
        $qrCode = (string) QrCode::format('png')
            ->size(400)
            ->errorCorrection('H') // 2. CRITICAL: Sets damage tolerance to 30%
            ->merge($logoPath, 0.2, true) // 3. NEW: Injects logo at 20% size (true = absolute path)
            ->backgroundColor(255, 255, 255) 
            ->color(0, 0, 0) 
            ->margin(2) 
            ->generate($this->member->unique_id);

        // Attach the binary string data as a .png file
        return [
            Attachment::fromData(fn () => $qrCode, 'Your-QR-Code.png')
                ->withMime('image/png'),
        ];
    }
}