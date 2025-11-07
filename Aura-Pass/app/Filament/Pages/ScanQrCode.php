<?php

namespace App\Filament\Pages;

use App\Models\CheckIn;
use App\Models\Member; // 1. Import your Member model
use Filament\Notifications\Notification; // 2. Import Notifications
use Filament\Pages\Page;

class ScanQrCode extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static string $view = 'filament.pages.scan-qr-code';

    /**
     * 3. This is the method called by your JavaScript.
     * Livewire automatically handles the data binding.
     */
public function processScan($qrData)
    {
        // 1. Find the member
        $member = Member::where('unique_id', $qrData)->first();

        if (!$member) {
            // 2. FAILURE: Member not found
            Notification::make()
                ->title('Scan Error')
                ->body('Invalid or unknown QR Code.')
                ->danger()
                ->send();
            return; // Stop here
        }

        // 3. NEW CHECK: Verify membership is active
        // We can do this check now because we cast the date in the model
        if ($member->membership_expiry_date->isPast()) {
            
            // 4. REJECT: Membership is expired
            Notification::make()
                ->title('Check-in Failed')
                ->body('Membership for ' . $member->name . ' expired on ' . $member->membership_expiry_date->format('M d, Y') . '.')
                ->danger()
                ->send();
            return; // Stop here
        }

        // 5. SUCCESS: Create the check-in (only if they passed)
        $member->checkIns()->create();

        Notification::make()
            ->title('Check-in Successful!')
            ->body('Welcome, ' . $member->name)
            ->success()
            ->send();
    }
}