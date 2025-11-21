<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AccessLog extends BaseWidget
{
    protected static ?int $sort = 0;
    
    protected static ?string $heading = 'Live Log Feed';

    // Force it to be compact if your layout allows
    protected int | string | array $columnSpan = '1';

    public function table(Table $table): Table
    {
        return $table
            ->poll(2) // Refresh every 2 seconds
            ->query(
                // Show last 3 events
                CheckIn::query()->latest()->limit(3)
            )
            ->columns([
                // 1. Member Name
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Member')
                    ->weight('bold')
                    ->limit(15), // Keep it short for the small box

                // 2. Time (e.g., "2 mins ago")
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Time')
                    ->since() 
                    ->color('gray')
                    ->size('xs'), // Small text to save space

                // 3. Status Badge (In vs Out)
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Action')
                    ->getStateUsing(function (CheckIn $record) {
                        return $record->check_out_at ? 'OUT' : 'IN';
                    })
                    ->colors([
                        'success' => 'IN',  // Green
                        'gray'    => 'OUT', // Gray
                    ]),
            ])
            ->paginated(false) // Remove the "Page 1 of 1" footer
            ->headerActions([
                // A tiny button to go to the full logs
                Tables\Actions\Action::make('history')
                    ->label('Full History')
                    ->icon('heroicon-m-chevron-right')
                    ->url(route('filament.admin.resources.access-logs.index'))
                    ->link()
                    ->size('xs'),
            ]);
    }
}