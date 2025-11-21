<?php

namespace App\Filament\Resources;

use Filament\Infolists\Components\ImageEntry;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Filament\Resources\MemberResource\Pages;
use App\Filament\Resources\MemberResource\RelationManagers;
use App\Models\Member;
use App\Jobs\ProcessQrScan; // <--- IMPORT THE JOB
use Filament\Notifications\Notification; // <--- IMPORT NOTIFICATIONS
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists;
use Filament\Infolists\Components\TextEntry;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('email')->email(),
                Forms\Components\DatePicker::make('membership_expiry_date')->required(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('membership_expiry_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('unique_id')->label('QR ID')->searchable(),
            ])
            ->actions([
                // 1. EDIT
                Tables\Actions\EditAction::make(),
                
                // 2. VIEW
                Tables\Actions\ViewAction::make(),

                // 3. FORCE CHECK-IN/OUT (The New Action)
                Tables\Actions\Action::make('force_scan')
                    ->label('Force Scan')
                    ->icon('heroicon-o-qr-code') // Looks like a scan
                    ->color('warning') // Yellow to indicate "Staff Override"
                    ->requiresConfirmation()
                    ->modalHeading('Manual Check-In / Check-Out')
                    ->modalDescription('This will simulate a QR scan for this member. The standard rules (Debounce, 12-hour limit, Timezone fix) will apply exactly as if they used the kiosk.')
                    ->action(function (Member $record) {
                        // Dispatch the exact same job the Kiosk uses
                        ProcessQrScan::dispatch($record->unique_id);

                        Notification::make()
                            ->title('Scan Sent to Queue')
                            ->body("Processing check-in/out for {$record->name}...")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(Infolists\Infolist $infolist): Infolists\Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('membership_expiry_date')->date(),

                ImageEntry::make('qr_code')
                    ->label('Member QR Code')
                    ->default(function ($record) {
                        $qrCode = QrCode::format('svg')
                                        ->size(250)
                                        ->generate($record->unique_id);
                        return 'data:image/svg+xml;base64,' . base64_encode($qrCode);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'view' => Pages\ViewMember::route('/{record}'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}