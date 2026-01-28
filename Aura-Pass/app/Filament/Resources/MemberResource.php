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
// New Imports for Actions & Storage
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Actions; // <--- Import Actions container
use Filament\Support\Enums\Alignment;      // <--- Import Alignment
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
// Import Layout Components
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Group;

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
                
                // NEW: Membership Type Selector
                Forms\Components\Select::make('membership_type')
                    ->options([
                        'regular' => 'Regular',
                        'discount' => 'Discount',
                        'promo' => 'Promo',
                    ])
                    ->required()
                    ->default('regular'),

                Forms\Components\DatePicker::make('membership_expiry_date')->required(),
                
                Forms\Components\Section::make('Photo Identification')
                    ->schema([
                        // 1. Webcam Input
                        WebcamCapture::make('webcam_data')
                            ->label('Take Photo (Admin Camera)')
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
                Tables\Columns\ImageColumn::make('profile_photo')
                    ->label('Photo')
                    ->circular()
                    ->disk('public') 
                    ->defaultImageUrl(url('/images/placeholder-face.png')),

                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                
                // NEW: Membership Type Column
                Tables\Columns\TextColumn::make('membership_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'regular' => 'info',
                        'discount' => 'success',
                        'promo' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

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
                // Use a Section with a 3-column grid to compact the layout
                Section::make('Member Information')
                    ->columns(3)
                    ->schema([
                        // Column 1: Verification Photo Group
                        Group::make([
                            ImageEntry::make('profile_photo')
                                ->label('Member Photo')
                                ->height(250)
                                ->circular(false)
                                ->disk('public')
                                // Center the image horizontally
                                ->extraImgAttributes(['class' => 'mx-auto']),
                            
                            // Place Action BELOW the image
                            Actions::make([
                                Action::make('view_full')
                                    ->icon('heroicon-m-magnifying-glass-plus')
                                    ->label('Enlarge Photo')
                                    ->color('gray')
                                    ->visible(fn ($record) => $record->profile_photo)
                                    ->modalHeading('Verification Photo')
                                    ->modalContent(fn ($record) => new HtmlString(
                                        '<div class="flex justify-center"><img src="' . Storage::url($record->profile_photo) . '" style="max-height: 80vh; max-width: 100%; border-radius: 8px;" /></div>'
                                    ))
                                    ->modalSubmitAction(false)
                                    ->modalCancelAction(false),
                            ])->alignment(Alignment::Center), // Center the button
                        ]),

                        // Column 2: Text Details (Grouped together)
                        Group::make([
                            TextEntry::make('name')->weight('bold')->size('lg')->copyable()->copyMessage('Copied!')->copyMessageDuration(1500),
                            TextEntry::make('email')->size('md'),
                            
                            // NEW: Membership Type Entry
                            TextEntry::make('membership_type')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'regular' => 'info',
                                    'discount' => 'success',
                                    'promo' => 'primary',
                                    default => 'gray',
                                })
                                ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                            TextEntry::make('membership_expiry_date')->date()->label('Expiry Date'),
                        ]),

                        // Column 3: QR Code Group
                        Group::make([
                            ImageEntry::make('qr_code')
                                ->label('Member QR Code')
                                ->height(250) 
                                ->default(function ($record) {
                                    $qrCode = QrCode::format('svg')
                                                    ->size(250)
                                                    ->generate($record->unique_id);
                                    return 'data:image/svg+xml;base64,' . base64_encode($qrCode);
                                })
                                ->extraImgAttributes(['class' => 'mx-auto']),

                            // Place Action BELOW the image
                            Actions::make([
                                Action::make('download_png')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->label('Download PNG')
                                    ->color('primary')
                                    ->tooltip('Download High-Res PNG')
                                    ->action(function ($record) {
                                        return response()->streamDownload(function () use ($record) {
                                            echo QrCode::format('png')
                                                    ->size(500)
                                                    ->margin(2)
                                                    ->generate($record->unique_id);
                                        }, $record->name . '-qr.png');
                                    }),
                            ])->alignment(Alignment::Center),
                        ]),
                    ]),
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