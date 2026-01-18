<?php

namespace App\Jobs;

use App\Events\MemberCheckedIn;
use App\Events\MemberCheckedOut;
use App\Events\MemberScanFailed;
use App\Models\Member;
use App\Models\User; 
use Filament\Notifications\Notification; 
use Filament\Notifications\Actions\Action; // <--- 1. Import this for buttons
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessQrScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $qrData;
    public $force;

    public function __construct(string $qrData, bool $force = false)
    {
        $this->qrData = $qrData;
        $this->force = $force;
    }

    public function handle(): void
    {
        $member = Member::where('unique_id', $this->qrData)->first();
        $admins = User::all(); 

        // -----------------------------------------
        // SCENARIO 1: MEMBER NOT FOUND (Invalid QR)
        // -----------------------------------------
        if (!$member) {
            event(new MemberScanFailed(null, 'not_found'));

            Notification::make()
                ->title('Scan Failed')
                ->body("Invalid QR Code Scanned: {$this->qrData}")
                ->danger() 
                ->broadcast($admins)
                ->sendToDatabase($admins);
            
            return; 
        }

        // -----------------------------------------
        // SCENARIO 2: MEMBERSHIP EXPIRED
        // -----------------------------------------
        if ($member->membership_expiry_date < now()->startOfDay()) {
            event(new MemberScanFailed($member, 'expired'));
            
            Notification::make()
                ->title('Entry Denied')
                ->body("Expired Membership: {$member->name}")
                ->danger() 
                // <--- 2. Add Button to View Profile
                ->actions([
                    Action::make('view')
                        ->label('View Profile')
                        ->button()
                        ->url("/admin/members/{$member->id}", shouldOpenInNewTab: true),
                ])
                ->broadcast($admins)
                ->sendToDatabase($admins);
            
            return; 
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
            
            if (!$this->force && $activeSession->created_at->diffInMinutes(now()) < 2) {
                 event(new MemberScanFailed($member, 'ignored'));
                 return;
            }

            $activeSession->update([
                'check_out_at' => $activeSession->freshTimestamp(),
            ]);

            event(new MemberCheckedOut($member));

            Notification::make()
                ->title('Member Left')
                ->body("{$member->name} checked out.")
                ->info() 
                // <--- 3. Add Button to Verify Face
                ->actions([
                    Action::make('verify')
                        ->label('Verify Face')
                        ->button()
                        ->url("/admin/members/{$member->id}", shouldOpenInNewTab: true),
                ])
                ->broadcast($admins)
                ->sendToDatabase($admins);

        } else {
            // --- LOGIC: CHECK IN ---
            $member->checkIns()->create();

            event(new MemberCheckedIn($member));

            Notification::make()
                ->title('Member Entered')
                ->body("{$member->name} checked in.")
                ->success() 
                // <--- 4. Add Button to Verify Face
                ->actions([
                    Action::make('verify')
                        ->label('Verify Face')
                        ->button()
                        ->url("/admin/members/{$member->id}", shouldOpenInNewTab: true),
                ])
                ->broadcast($admins)
                ->sendToDatabase($admins);
        }
    }
}