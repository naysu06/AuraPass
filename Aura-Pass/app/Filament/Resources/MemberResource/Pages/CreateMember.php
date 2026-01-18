<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Resources\Pages\CreateRecord;

// Imports for Email Logic
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;

// Imports for Webcam Logic
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    /**
     * 1. NEW LOGIC: Handle Webcam Image
     * This runs BEFORE the member is saved to the database.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Check if webcam data exists from our custom field
        if (isset($data['webcam_data']) && $data['webcam_data']) {
            
            // A. Separate the metadata from the image data
            // Format: "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
            $image_parts = explode(";base64,", $data['webcam_data']);
            
            if (count($image_parts) >= 2) {
                $image_base64 = base64_decode($image_parts[1]);
                
                // B. Generate a unique filename
                $filename = 'member-photos/' . Str::random(40) . '.jpg';
                
                // C. Save the file to the 'public' disk
                Storage::disk('public')->put($filename, $image_base64);
                
                // D. Update the data array: Set 'profile_photo' to the file path
                $data['profile_photo'] = $filename;
            }
            
            // E. Remove the temporary base64 string so it doesn't try to save to DB
            unset($data['webcam_data']);
        }

        return $data;
    }

    /**
     * 2. EXISTING LOGIC: Send Email
     * This runs AFTER the member is saved to the database.
     */
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