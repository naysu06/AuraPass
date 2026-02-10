<?php

namespace App\Jobs;

use App\Events\MemberCheckedIn;
use App\Events\MemberCheckedOut;
use App\Events\MemberScanFailed;
use App\Models\Member;
use App\Models\User; 
use App\Models\GymSetting;
use Filament\Notifications\Notification; 
use Filament\Notifications\Actions\Action; 
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

        $settings = GymSetting::first();
        $debounceSeconds = $settings ? $settings->kiosk_debounce_seconds : 10;
        $strictMode = $settings ? $settings->strict_mode : false;
        $autoCheckoutHours = $settings ? $settings->auto_checkout_hours : 12;

        // 1. MEMBER NOT FOUND
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

        // 2. MEMBERSHIP EXPIRED
        if ($member->membership_expiry_date < now()->startOfDay()) {
            event(new MemberScanFailed($member, 'expired'));
            
            Notification::make()
                ->title('Entry Denied')
                ->body("Expired Membership: {$member->name}")
                ->danger() 
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

        // 3. STRICT MODE CHECK (Updated)
        if ($strictMode && empty($member->profile_photo)) {
            // <--- CHANGED: Send 'no_photo' instead of 'expired'
            event(new MemberScanFailed($member, 'no_photo')); 

            Notification::make()
                ->title('Strict Mode Denied')
                ->body("{$member->name} has no profile photo.")
                ->danger()
                ->actions([
                    Action::make('upload')
                        ->label('Take Photo')
                        ->button()
                        ->url("/admin/members/{$member->id}/edit", shouldOpenInNewTab: true),
                ])
                ->broadcast($admins)
                ->sendToDatabase($admins);

            return;
        }

        // ... (Rest of logic: Active Session check, Check In/Out) ...
        $activeSession = $member->checkIns()
            ->whereNull('check_out_at')
            ->where('created_at', '>=', now()->subHours($autoCheckoutHours)) 
            ->latest()
            ->first();

        if ($activeSession) {
            // CHECK OUT
            if (!$this->force && $activeSession->created_at->diffInSeconds(now()) < $debounceSeconds) {
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
                ->actions([
                    Action::make('verify')
                        ->label('Verify Face')
                        ->button()
                        ->url("/admin/members/{$member->id}", shouldOpenInNewTab: true),
                ])
                ->broadcast($admins)
                ->sendToDatabase($admins);

        } else {
            // CHECK IN
            $member->checkIns()->create();

            event(new MemberCheckedIn($member));

            Notification::make()
                ->title('Member Entered')
                ->body("{$member->name} checked in.")
                ->success() 
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