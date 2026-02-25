<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Forms\Components\TextInput; 
use Filament\Notifications\Notification;

class ExpiringMembers extends BaseWidget
{
    protected static ?string $heading = 'Membership Expiring Soon';

    protected static ?string $pollingInterval = '60s';

    protected function getExtraAttributes(): array
    {
        return [
            'class' => 'custom-fixed-table',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Member::query()
                    ->where('membership_expiry_date', '>=', now()->startOfDay()) 
                    ->where('membership_expiry_date', '<=', now()->addDays(7)->endOfDay()) 
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
            ->paginated([3]) 
            ->recordUrl(
                fn (Member $record): string => route('filament.admin.resources.members.edit', ['record' => $record]),
            )
            ->actions([
                Tables\Actions\Action::make('renew')
                    ->icon('heroicon-m-arrow-path')
                    ->color('primary')
                    ->iconButton()
                    ->tooltip('Renew Membership')
                    ->form([
                        TextInput::make('months')
                            ->label('Duration to Add')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(12)
                            ->suffix('Month(s)')
                            ->required()
                            ->autofocus(),
                    ])
                    ->modalHeading('Renew Subscription')
                    ->modalDescription(fn ($record) => "Extend membership for {$record->name}?")
                    ->modalSubmitActionLabel('Confirm Renewal')
                    ->action(function (Member $record, array $data) {
                        $monthsToAdd = (int) $data['months'];

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