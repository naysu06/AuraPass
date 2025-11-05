<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Resources\Pages\CreateRecord;

// We need to import the Mail and Mailable classes
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    // This function runs automatically after the member is created
    protected function afterCreate(): void
    {
        // '$this->record' is the new member
        $newMember = $this->record;

        // Send the email to the queue.
        // It will be processed by the queue worker.
        Mail::to($newMember->email)
            ->later(now()->addSeconds(5), new WelcomeEmail($newMember));
    }
}