<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Forms\Components\TextInput; // <--- Import this
use Filament\Notifications\Notification;

class ExpiringMembers extends BaseWidget
{
    protected static ?int $sort = 1; // Position it after AccessLog
    
    protected static ?string $heading = 'Membership Expiring Soon';

    // Polling allows the table to refresh if someone renews elsewhere
    protected static ?string $pollingInterval = '60s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Member::query()
                    ->where('membership_expiry_date', '>=', now()) 
                    ->where('membership_expiry_date', '<=', now()->addDays(7)) 
                    ->orderBy('membership_expiry_date', 'asc') 
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight('bold')
                    ->limit(20), 

                Tables\Columns\TextColumn::make('membership_expiry_date')
                    ->label('Expires')
                    ->date('M d, Y') 
                    ->description(fn (Member $record) => $record->membership_expiry_date->diffForHumans()),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (Member $record) => 
                        $record->membership_expiry_date->diffInDays(now()) <= 7 ? 'danger' : 'warning'
                    )
                    ->getStateUsing(fn () => 'Expiring'),
            ])
            ->paginated(false) 
            ->recordUrl(
                fn (Member $record): string => route('filament.admin.resources.members.edit', ['record' => $record]),
            )
            ->actions([
                Tables\Actions\Action::make('renew')
                    ->icon('heroicon-m-arrow-path')
                    ->color('primary')
                    ->iconButton()
                    ->tooltip('Renew Membership')
                    
                    // 1. DEFINE THE MODAL FORM
                    ->form([
                        TextInput::make('months')
                            ->label('Duration to Add')
                            ->numeric()      // Adds up/down arrows
                            ->default(1)     // Default to 1 month
                            ->minValue(1)    // Cannot be 0
                            ->maxValue(12)   // Optional limit
                            ->suffix('Month(s)')
                            ->required()
                            ->autofocus(),
                    ])
                    ->modalHeading('Renew Subscription')
                    ->modalDescription(fn ($record) => "Extend membership for {$record->name}?")
                    ->modalSubmitActionLabel('Confirm Renewal')
                    
                    // 2. HANDLE THE DATA
                    ->action(function (Member $record, array $data) {
                        $monthsToAdd = (int) $data['months'];

                        // Smart Logic: 
                        // If expired, start renewal from TODAY. 
                        // If active, add to their CURRENT expiry date.
                        $startDate = $record->membership_expiry_date->isPast() 
                            ? now() 
                            : $record->membership_expiry_date;

                        $record->update([
                            'membership_expiry_date' => $startDate->copy()->addMonths($monthsToAdd)
                        ]);
                        
                        Notification::make()
                            ->title('Renewed Successfully')
                            ->body("{$record->name} extended by {$monthsToAdd} month(s).")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}