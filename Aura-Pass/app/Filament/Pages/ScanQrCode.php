<?php

namespace App\Filament\Pages;

use App\Models\CheckIn;
use App\Models\Member; // 1. Import your Member model
use Filament\Notifications\Notification; // 2. Import Notifications
use Filament\Pages\Page;
use App\Events\MemberScanned;

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
            MemberScanned::dispatch(null, 'not_found');
            return; // Stop here
        }

        // 3. CHECK: Verify membership
        if ($member->membership_expiry_date->isPast()) {

            // 4. REJECT: Membership is expired
            MemberScanned::dispatch($member, 'expired');
            return; // Stop here
        }

        // 5. SUCCESS: Create the check-in
        $member->checkIns()->create();
        MemberScanned::dispatch($member, 'active');
    }
}