<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage; // <--- Import Storage
use Illuminate\Support\Str;             // <--- Import Str

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. The Default Delete Button
            Actions\DeleteAction::make(),

            // 2. The Resend Email Button
            Actions\Action::make('resend_email')
                ->label('Resend QR Email')
                ->icon('heroicon-o-envelope')
                ->color('info') 
                ->requiresConfirmation()
                ->modalHeading('Resend Welcome Email')
                ->modalDescription(fn ($record) => "Are you sure you want to resend the QR code email to {$record->email}?")
                ->action(function ($record) {
                    
                    // Email Logic
                    Mail::to($record->email)->queue(new \App\Mail\WelcomeEmail($record));

                    // Notification
                    Notification::make()
                        ->title('Email Resent')
                        ->body("A fresh QR code has been sent to {$record->name}.")
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * NEW LOGIC: Handle Webcam Image Update
     * This runs BEFORE the member is updated in the database.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Check if new webcam data exists (user retook photo)
        if (isset($data['webcam_data']) && $data['webcam_data']) {
            
            // 1. Decode Base64
            $image_parts = explode(";base64,", $data['webcam_data']);
            
            if (count($image_parts) >= 2) {
                $image_base64 = base64_decode($image_parts[1]);
                
                // 2. Generate Filename
                $filename = 'member-photos/' . Str::random(40) . '.jpg';
                
                // 3. Save to Public Disk
                Storage::disk('public')->put($filename, $image_base64);
                
                // 4. Set the actual DB column to this path
                $data['profile_photo'] = $filename;
            }
            
            // 5. Clean up temp field
            unset($data['webcam_data']);
        }

        return $data;
    }
}