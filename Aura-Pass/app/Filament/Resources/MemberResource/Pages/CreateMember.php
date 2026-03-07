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

// NEW: Import Validation Exception
use Illuminate\Validation\ValidationException;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    /**
     * 1. HANDLE VALIDATION & WEBCAM IMAGE
     * This runs BEFORE the member is saved to the database.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // --- STRICT BACKEND VALIDATION ---

        // 1. Enforce Email Requirement
        if (empty($data['email'])) {
            throw ValidationException::withMessages([
                'data.email' => 'An active email address is required to send the Welcome QR Code.',
            ]);
        }

        // 2. Enforce Photo Requirement (Must have EITHER an uploaded file OR a webcam capture)
        $hasWebcamPhoto = !empty($data['webcam_data']);
        $hasUploadedPhoto = !empty($data['profile_photo']);

        if (!$hasWebcamPhoto && !$hasUploadedPhoto) {
            throw ValidationException::withMessages([
                // We map this error to 'webcam_data' so the red text appears right under the photo area
                'data.webcam_data' => 'A profile photo is strictly required for identity verification. Please upload a file or take a picture.',
            ]);
        }

        // --- END VALIDATION ---


        // --- EXISTING WEBCAM PROCESSING ---
        
        // Check if webcam data exists from our custom field
        if ($hasWebcamPhoto) {
            
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
                // This ensures the DB saves the path just like a normal file upload
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
        if ($newMember->email) {
            Mail::to($newMember->email)
                ->later(now()->addSeconds(5), new WelcomeEmail($newMember));
        }
    }
}