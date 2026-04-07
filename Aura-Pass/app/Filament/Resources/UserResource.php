<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification; // Required for alerts
use Filament\Tables\Actions\DeleteAction; // Required for hook typing
use Filament\Tables\Actions\DeleteBulkAction; // Required for hook typing
use Illuminate\Support\Collection; // Required for bulk actions

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 1;

    // RBAC Logic - ONLY Superadmins can see/access this resource
    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'superadmin';
    }

    public static function form(Forms\Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\Select::make('role')
                    ->options([
                        'superadmin' => 'Super Administrator',
                        'admin' => 'Normal Administrator',
                    ])
                    ->required()
                    ->default('admin'),

                // Password handling
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
            ]);
    }

    public static function table(Tables\Table $table): Table
        {
            return $table
                ->columns([
                    Tables\Columns\TextColumn::make('username')
                        ->searchable(),
                        
                    Tables\Columns\TextColumn::make('role')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'superadmin' => 'danger',
                            'admin' => 'info',
                        })
                        ->formatStateUsing(fn (string $state) => ucfirst($state)),

                    Tables\Columns\TextColumn::make('created_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                ->actions([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        // NEW: Dynamic warning if deleting self
                        ->modalHeading(fn (User $record) => auth()->id() === $record->id 
                            ? 'Delete Your Own Account?' 
                            : 'Delete User')
                        ->modalDescription(fn (User $record) => auth()->id() === $record->id 
                            ? "You are currently logged in as {$record->username}. Deleting this account will log you out immediately and you will lose access. Are you absolutely sure?" 
                            : 'Are you sure you want to delete this user? This action cannot be undone.')
                        ->modalSubmitActionLabel(fn (User $record) => auth()->id() === $record->id 
                            ? 'Yes, delete my account' 
                            : 'Delete')
                        ->before(function (DeleteAction $action, User $record) {
                            // Guard 1: Prevent deleting the LAST superadmin
                            if ($record->role === 'superadmin') {
                                $superAdminCount = User::where('role', 'superadmin')->count();

                                if ($superAdminCount <= 1) {
                                    Notification::make()
                                        ->warning()
                                        ->title('Action Denied')
                                        ->body('To prevent a system lockout, you cannot delete the last remaining Superadmin.')
                                        ->persistent()
                                        ->send();

                                    $action->halt();
                                }
                            }
                        }),
                ])
                ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DeleteBulkAction::make()
                            ->before(function (DeleteBulkAction $action, Collection $records) {
                                $superAdminsInSelection = $records->where('role', 'superadmin')->count();
                                $totalSuperAdmins = User::where('role', 'superadmin')->count();

                                // Guard 2: Prevent mass-deleting all superadmins
                                if ($superAdminsInSelection >= $totalSuperAdmins) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Bulk Deletion Blocked')
                                        ->body('You cannot delete all Superadmin accounts at once. At least one must remain.')
                                        ->send();

                                    $action->halt();
                                }
                            }),
                    ]),
                ]);
        }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}