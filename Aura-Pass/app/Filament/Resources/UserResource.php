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
use Filament\Notifications\Notification;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 1;

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
                    // PRO-TIP: Hide the button entirely if the record is the logged-in user
                    ->hidden(fn (User $record) => $record->id === auth()->id())
                    ->before(function (DeleteAction $action, User $record) {
                        // Guard 1: Double-check for the last Superadmin
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
                            // 1. Check if the user is trying to bulk-delete themselves
                            if ($records->contains(auth()->user())) {
                                Notification::make()
                                    ->warning()
                                    ->title('Self-Deletion Prevented')
                                    ->body('Your own account was automatically removed from the deletion list.')
                                    ->send();
                                
                                // Remove current user from the collection before proceeding
                                $records->forget($records->search(fn($user) => $user->id === auth()->id()));
                            }

                            // 2. Guard against deleting all superadmins
                            $superAdminsInSelection = $records->where('role', 'superadmin')->count();
                            $totalSuperAdmins = User::where('role', 'superadmin')->count();

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