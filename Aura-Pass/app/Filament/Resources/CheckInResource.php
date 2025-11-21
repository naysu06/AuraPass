<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CheckInResource\Pages;
use App\Models\CheckIn;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CheckInResource extends Resource
{
    // Link to the model your Job is populating
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
                    ->placeholder('Active Session') // Show this if NULL
                    ->sortable(),

                // 4. Duration Calculation (Optional but useful)
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(fn (CheckIn $record) => 
                        $record->check_out_at 
                            ? round($record->created_at->diffInMinutes($record->check_out_at, true), 0) . ' mins' 
                            : '-'
                    ),
            ])
            ->defaultSort('created_at', 'desc') // Show newest first
            ->filters([
                // Filter to see who is currently in the gym
                Tables\Filters\Filter::make('active')
                    ->label('Currently Inside')
                    ->query(fn ($query) => $query->whereNull('check_out_at')),
            ])
            ->actions([
                // Optional: Allow admin to manually delete a bad log
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCheckIns::route('/'),
        ];
    }
    
    // Hide the "Create" button since your Job/Scanner handles creation
    public static function canCreate(): bool
    {
        return false;
    }
}