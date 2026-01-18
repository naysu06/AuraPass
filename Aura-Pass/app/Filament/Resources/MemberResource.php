<?php

namespace App\Filament\Resources;

use Filament\Infolists\Components\ImageEntry;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Filament\Resources\MemberResource\Pages;
use App\Filament\Resources\MemberResource\RelationManagers;
use App\Models\Member;
use App\Jobs\ProcessQrScan; 
use Filament\Notifications\Notification; 
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists;
use Filament\Infolists\Components\TextEntry;
use App\Forms\Components\WebcamCapture;

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
                
                Forms\Components\Section::make('Photo Identification')
                    ->schema([
                        // 1. Webcam Input
                        WebcamCapture::make('webcam_data')
                            ->label('Take Photo (Admin Camera)')
                            // ->dehydrated(false) <--- REMOVED THIS LINE (Fix #1)
                            ->reactive(),

                        // 2. Standard Upload
                        Forms\Components\FileUpload::make('profile_photo')
                            ->label('Current Photo')
                            ->image()
                            ->directory('member-photos')
                            ->disk('public')
                            ->visibility('public'),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                // 3. Table Image Display
                Tables\Columns\ImageColumn::make('profile_photo')
                    ->label('Photo')
                    ->circular()
                    ->disk('public') // <--- ADDED THIS (Fix #2)
                    ->defaultImageUrl(url('/images/placeholder-face.png')),

                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('membership_expiry_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('unique_id')->label('QR ID')->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
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
                // 4. View Page Image Display
                ImageEntry::make('profile_photo')
                    ->label('Verification Photo')
                    ->height(200)
                    ->circular(false)
                    ->disk('public'), // <--- ADDED THIS (Fix #2)

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