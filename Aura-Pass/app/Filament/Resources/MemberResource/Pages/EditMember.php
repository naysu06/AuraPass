<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. The Default Delete Button (Keep this)
            Actions\DeleteAction::make(),

            // 2. THE NEW RESEND BUTTON
            Actions\Action::make('resend_email')
                ->label('Resend QR Email')
                ->icon('heroicon-o-envelope')
                ->color('info') // Blue button
                ->requiresConfirmation()
                ->modalHeading('Resend Welcome Email')
                ->modalDescription(fn ($record) => "Are you sure you want to resend the QR code email to {$record->email}?")
                ->action(function ($record) {
                    
                    // --- A. EMAIL LOGIC ---
                    // Since you already have a queue worker sending emails, 
                    // you likely have a Mailable class (e.g., WelcomeMemberMail).
                    // Uncomment and update the line below:
                    
                    Mail::to($record->email)->queue(new \App\Mail\WelcomeEmail($record));

                    // --- B. NOTIFICATION ---
                    Notification::make()
                        ->title('Email Resent')
                        ->body("A fresh QR code has been sent to {$record->name}.")
                        ->success()
                        ->send();
                }),
        ];
    }
}