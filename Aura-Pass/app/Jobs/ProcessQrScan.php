<?php

namespace App\Jobs;

use App\Events\MemberCheckedIn;
use App\Events\MemberCheckedOut;
use App\Events\MemberScanFailed;
use App\Models\Member;
use App\Models\User; 
use Filament\Notifications\Notification; 
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessQrScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $qrData;

    public function __construct(string $qrData)
    {
        $this->qrData = $qrData;
    }

    public function handle(): void
    {
        $member = Member::where('unique_id', $this->qrData)->first();
        $admins = User::all(); 

        // -----------------------------------------
        // SCENARIO 1: MEMBER NOT FOUND (Invalid QR)
        // -----------------------------------------
        if (!$member) {
            // 1. Update Kiosk Screen
            event(new MemberScanFailed(null, 'not_found'));

            // 2. Alert Admin (Red Toast)
            Notification::make()
                ->title('Scan Failed')
                ->body("Invalid QR Code Scanned: {$this->qrData}")
                ->danger() // Red Color
                ->broadcast($admins)
                ->sendToDatabase($admins);
            
            return; // Stop processing
        }

        // -----------------------------------------
        // SCENARIO 2: MEMBERSHIP EXPIRED
        // -----------------------------------------
        if ($member->membership_expiry_date < now()->startOfDay()) {
            // 1. Update Kiosk Screen
            event(new MemberScanFailed($member, 'expired'));
            
            // 2. Alert Admin (Red Toast)
            Notification::make()
                ->title('Entry Denied')
                ->body("Expired Membership: {$member->name}")
                ->danger() // Red Color
                ->actions([
                    // Optional: Add a button to quickly view the member
                    \Filament\Notifications\Actions\Action::make('view')
                        ->button()
                        ->url("/admin/members/{$member->id}", shouldOpenInNewTab: true),
                ])
                ->broadcast($admins)
                ->sendToDatabase($admins);
            
            return; // Stop processing
        }

        // -----------------------------------------
        // VALID MEMBER FOUND - CHECK SESSION
        // -----------------------------------------
        $activeSession = $member->checkIns()
            ->whereNull('check_out_at')
            ->where('created_at', '>=', now()->subHours(12)) 
            ->latest()
            ->first();

        if ($activeSession) {
            // --- LOGIC: CHECK OUT ---
            
            // Debounce (Double Scan Protection)
            if ($activeSession->created_at->diffInMinutes(now()) < 2) {
                 event(new MemberScanFailed($member, 'ignored'));
                 // We DO NOT notify admin here to avoid spamming "ignored" alerts
                 return;
            }

            $activeSession->update([
                'check_out_at' => $activeSession->freshTimestamp(),
            ]);

            event(new MemberCheckedOut($member));

            // Notify Admin: Left
            Notification::make()
                ->title('Member Left')
                ->body("{$member->name} checked out.")
                ->info() // Blue
                ->broadcast($admins)
                ->sendToDatabase($admins);

        } else {
            // --- LOGIC: CHECK IN ---
            $member->checkIns()->create();

            event(new MemberCheckedIn($member));

            // Notify Admin: Entered
            Notification::make()
                ->title('Member Entered')
                ->body("{$member->name} checked in.")
                ->success() // Green
                ->broadcast($admins)
                ->sendToDatabase($admins);
        }
    }
}