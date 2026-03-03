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
            ->filters([
                // NEW: Filter by Membership Type
                Tables\Filters\SelectFilter::make('membership_type')
                    ->label('Membership Type')
                    ->options([
                        'regular' => 'Regular',
                        'discount' => 'Discount',
                        'promo' => 'Promo',
                    ]),
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

                            // NEW: AI Churn Risk Score displayed directly on the member profile
                            TextEntry::make('churn_risk_score')
                                ->label('Churn Risk Score')
                                ->extraAttributes(['style' => 'width: fit-content;']) // FIX: Pulls the "i" icon right beside the badge
                                ->getStateUsing(fn (Member $record) => round($record->churn_risk_score * 100) . '%')
                                ->badge()
                                ->color(fn (Member $record) => match (true) {
                                    $record->churn_risk_score >= 0.60 => 'danger',  // Red for high risk
                                    $record->churn_risk_score >= 0.30 => 'warning', // Yellow for medium risk
                                    default => 'success',                           // Green for safe
                                })
                                ->icon(fn (Member $record) => match (true) {
                                    $record->churn_risk_score >= 0.60 => 'heroicon-m-exclamation-triangle',
                                    $record->churn_risk_score >= 0.30 => 'heroicon-m-shield-exclamation',
                                    default => 'heroicon-m-shield-check',
                                })
                                // ADDED: The Info Action Modal explaining the danger levels (With Inline CSS)
                                ->suffixAction(
                                    Action::make('explain_risk')
                                        ->icon('heroicon-m-information-circle')
                                        ->color('gray')
                                        ->modalHeading('Understanding Churn Risk Score')
                                        ->modalSubmitAction(false)
                                        ->modalCancelActionLabel('Got it')
                                        ->modalContent(fn () => new HtmlString('
                                            <div class="flex flex-col gap-4">
                                                <p class="text-sm text-white-600 dark:text-gray-300 leading-relaxed mb-1">
                                                    The Churn Risk Score analyzes a member\'s recent attendance, membership type, and tenure to predict their likelihood of not renewing their gym membership.
                                                </p>
                                                
                                                <div style="background-color: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3);" class="flex items-start gap-3 p-4 rounded-xl shadow-sm">
                                                    <div style="background-color: rgba(16, 185, 129, 0.15); color: #10B981;" class="mt-0.5 flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    </div>
                                                    <div>
                                                        <span style="color: #10B981;" class="text-sm font-bold">0% - 29% (Safe)</span>
                                                        <p class="text-xs mt-1 text-white-600 dark:text-white-400">Highly engaged. Regularly visits, loyal regular member, or recently renewed.</p>
                                                    </div>
                                                </div>

                                                <div style="background-color: rgba(249, 115, 22, 0.08); border: 1px solid rgba(249, 115, 22, 0.3);" class="flex items-start gap-3 p-4 rounded-xl shadow-sm">
                                                    <div style="background-color: rgba(249, 115, 22, 0.15); color: #F97316;" class="mt-0.5 flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    </div>
                                                    <div>
                                                        <span style="color: #F97316;" class="text-sm font-bold">30% - 59% (Medium Risk)</span>
                                                        <p class="text-xs mt-1 text-white-600 dark:text-white-400">Showing signs of disengagement. Hasn\'t visited in over a week, or is in the vulnerable 2-6 month "buyer\'s remorse" phase.</p>
                                                    </div>
                                                </div>

                                                <div style="background-color: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3);" class="flex items-start gap-3 p-4 rounded-xl shadow-sm">
                                                    <div style="background-color: rgba(239, 68, 68, 0.15); color: #EF4444;" class="mt-0.5 flex-shrink-0 flex items-center justify-center w-6 h-6 rounded-full">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    </div>
                                                    <div>
                                                        <span style="color: #EF4444;" class="text-sm font-bold">60%+ (High Risk)</span>
                                                        <p class="text-xs mt-1 text-white-600 dark:text-white-400">Critical danger of dropping out. Hasn\'t visited in 14-20+ days, membership expiring imminently, or on a short-term promo.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        '))
                                ),
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