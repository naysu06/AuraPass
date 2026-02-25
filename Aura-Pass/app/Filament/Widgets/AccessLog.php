<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

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
            ->poll('2s') // Refresh every 2 seconds
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
                Tables\Actions\Action::make('history')
                    ->label('Full History')
                    ->icon('heroicon-m-chevron-right')
                    ->url(route('filament.admin.resources.access-logs.index'))
                    ->link()
                    ->size('xs'),
            ]);
    }
}