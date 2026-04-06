<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use App\Models\Member;
use App\Jobs\ProcessQrScan; 
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder; 
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;     
use Illuminate\Support\HtmlString;          

class AccessLog extends BaseWidget
{
    protected static ?string $heading = 'Live Log Feed';

    protected int | string | array $columnSpan = '1';

    protected function getExtraAttributes(): array
    {
        return [
            'class' => 'custom-fixed-table',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('20s') // Refresh every 20 seconds
            ->query(
                CheckIn::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Member')
                    ->weight('bold')
                    ->limit(15), 

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Time')
                    ->since() 
                    ->color('gray')
                    ->size('xs'), 

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Action')
                    ->getStateUsing(function (CheckIn $record) {
                        return $record->check_out_at ? 'OUT' : 'IN';
                    })
                    ->colors([
                        'success' => 'IN',  
                        'gray'    => 'OUT', 
                    ]),
            ])
            ->paginated(false) 
            ->headerActions([
                // 1. Manual Check-In Button
                Tables\Actions\Action::make('manual_check_in')
                    ->label('Manual Member Check-In')
                    ->icon('heroicon-m-user-plus')
                    ->color('primary') 
                    ->button() 
                    ->size('xs')
                    ->modalHeading('Manual Member Override')
                    ->modalDescription('Manually check in a member who forgot their QR code. This will run through standard system validation.')
                    ->modalWidth('md')
                    ->form([
                        Select::make('member_id')
                            ->label('Search Member Name')
                            ->searchable() 
                            ->options(Member::query()->pluck('name', 'id'))
                            ->required()
                            ->live() 
                            ->searchDebounce(500),

                        // Dynamic Photo Verification Box with Expiration Status
                        Placeholder::make('identity_verification')
                            ->label('Identity Verification')
                            ->hidden(fn (\Filament\Forms\Get $get) => empty($get('member_id')))
                            ->content(function (\Filament\Forms\Get $get) {
                                $memberId = $get('member_id');
                                if (!$memberId) return null;

                                $member = Member::find($memberId);
                                if (!$member) return null;

                                // Fetch photo or use fallback
                                $photoUrl = $member->profile_photo 
                                    ? Storage::disk('public')->url($member->profile_photo) 
                                    : url('/images/placeholder.jpg');

                                // Check expiration status for UI feedback
                                // (Assumes expiry_date is cast to a date in the Member model)
                                $isExpired = $member->membership_expiry_date && $member->membership_expiry_date < now()->startOfDay();
                                
                                $statusLabel = $isExpired 
                                    ? '<span class="mt-2 px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full uppercase">Expired Membership</span>'
                                    : '<span class="mt-2 px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full uppercase">Active Member</span>';

                                $borderColor = $isExpired ? '#ef4444' : 'white'; // Red border if expired

                                // Render the photo in a styled card
                                return new HtmlString('
                                    <div class="flex flex-col items-center justify-center p-4 mt-2 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-inner">
                                        <img src="' . $photoUrl . '" alt="Member Photo" style="width: 140px; height: 140px; object-fit: cover; border-radius: 9999px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15); border: 4px solid ' . $borderColor . ';" />
                                        <span class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">' . $member->name . '</span>
                                        ' . $statusLabel . '
                                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-2">Please verify identity before proceeding.</span>
                                    </div>
                                ');
                            }),
                    ])
                    ->action(function (array $data) {
                        $member = Member::find($data['member_id']);
                        
                        if ($member) {
                            // Check if membership is expired before dispatching the job
                            if ($member->membership_expiry_date && $member->membership_expiry_date < now()->startOfDay()) {
                                Notification::make()
                                ->title('Check-In Denied')
                                ->body("Cannot check in {$member->name}. Their membership expired on " . $member->membership_expiry_date->format('M d, Y') . ".")
                                    ->danger()
                                    ->persistent()
                                    ->send();
                    
                                return; // Stop process here
                            }

                            // If active, dispatch the scanner job
                            dispatch(new ProcessQrScan($member->unique_id));
                            
                            Notification::make()
                                ->title('Manual Check-In Queued')
                                ->body("Processing entry for {$member->name}...")
                                ->success()
                                ->send();
                        }
                    }),

                // 2. The Full History Link
                Tables\Actions\Action::make('history')
                    ->label('Full History')
                    ->icon('heroicon-m-chevron-right')
                    ->url(route('filament.admin.resources.access-logs.index'))
                    ->link()
                    ->size('xs'),
            ]);
    }
}