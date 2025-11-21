<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ExpiringMembers extends BaseWidget
{
    // 1. Sort Order: Put it next to AccountWidget (which is usually sort 1)
    protected static ?int $sort = 3;
    
    // 2. Heading for the box
    protected static ?string $heading = 'Membership Expiring Soon';

    // 3. Optional: Polling if you want it to update automatically
    // protected static ?string $pollingInterval = '60s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Member::query()
                    ->where('membership_expiry_date', '>=', now()) // Not expired yet
                    ->where('membership_expiry_date', '<=', now()->addDays(7)) // Expiring within 7 days
                    ->orderBy('membership_expiry_date', 'asc') // Show soonest first
            )
            ->columns([
                // Name + Avatar (if you have one)
                Tables\Columns\TextColumn::make('name')
                    ->weight('bold')
                    ->limit(20), // Truncate long names to fit

                // Relative Time ("5 days from now")
                Tables\Columns\TextColumn::make('membership_expiry_date')
                    ->label('Expires')
                    ->date('M d, Y') // "Nov 25, 2025"
                    ->description(fn (Member $record) => $record->membership_expiry_date->diffForHumans()),
                
                // A visual badge
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (Member $record) => 
                        $record->membership_expiry_date->diffInDays(now()) <= 7 ? 'danger' : 'warning'
                    )
                    ->getStateUsing(fn () => 'Expiring'),
            ])
            ->paginated(false) // Hide bulky pagination controls
            ->recordUrl(
                // Make the row clickable -> goes to Edit Member page
                fn (Member $record): string => route('filament.admin.resources.members.edit', ['record' => $record]),
            )
            ->actions([
                // Quick Action button
                Tables\Actions\Action::make('renew')
                    ->icon('heroicon-m-arrow-path')
                    ->color('primary')
                    ->iconButton() // Keeps it compact
                    ->tooltip('Renew Membership')
                    ->action(function (Member $record) {
                        // Simple +30 days logic (You can make this a modal later)
                        $record->update([
                            'membership_expiry_date' => $record->membership_expiry_date->addMonth()
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Renewed')
                            ->body("{$record->name} extended for 1 month.")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
            ]);
    }
}