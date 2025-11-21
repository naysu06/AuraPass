<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccessLogResource\Pages;
use App\Models\CheckIn;
use App\Jobs\ProcessQrScan;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccessLogResource extends Resource
{
    protected static ?string $model = CheckIn::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Access Logs';
    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Member Name
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),

                // 2. Check In Time
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Checked In')
                    ->dateTime('M d, h:i A')
                    ->sortable(),

                // 3. Check Out Time
                Tables\Columns\TextColumn::make('check_out_at')
                    ->label('Checked Out')
                    ->dateTime('h:i A')
                    ->placeholder('Active Session')
                    ->sortable(),

                // 4. Duration Calculation
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(fn (CheckIn $record) => 
                        $record->check_out_at 
                            ? round($record->created_at->diffInMinutes($record->check_out_at, true), 0) . ' mins' 
                            : '-'
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Filter to see who is currently in the gym
                Tables\Filters\Filter::make('active')
                    ->label('Currently Inside')
                    ->query(fn ($query) => $query->whereNull('check_out_at')),
            ])
            ->actions([
                // 1. Force Scan - Manual Check-In/Out
                Tables\Actions\Action::make('force_scan')
                    ->label('Force Check-Out') // Label clarification
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('warning')
                    // <--- 1. ONLY SHOW FOR ACTIVE SESSIONS (Check Out is Null)
                    ->visible(fn (CheckIn $record) => $record->check_out_at === null)
                    ->requiresConfirmation()
                    ->modalHeading('Force Check-Out')
                    ->modalDescription(fn (CheckIn $record) => 
                        "This will forcefully check out {$record->member->name}. Debounce protection will be bypassed."
                    )
                    ->action(function (CheckIn $record) {
                        // <--- 2. PASS 'TRUE' TO FORCE THE JOB
                        ProcessQrScan::dispatchSync($record->member->unique_id, true);

                        Notification::make()
                            ->title('Force Check-Out Queued')
                            ->body("Processing for {$record->member->name}...")
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccessLog::route('/'),
            'create' => Pages\CreateAccessLog::route('/create'),
            'edit' => Pages\EditAccessLog::route('/{record}/edit'),
        ];
    }
    
    public static function canCreate(): bool
    {
        return false;
    }
}