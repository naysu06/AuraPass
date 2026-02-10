<?php

namespace App\Filament\Pages;

use App\Models\GymSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class GymSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    // Group it under "Settings" so it appears next to "Users"
    protected static ?string $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.gym-settings';

    // Holds the form data
    public ?array $data = [];

    public function mount(): void
    {
        // Get the first settings row, or create defaults if missing
        $settings = GymSetting::firstOrCreate([], [
            'camera_mirror' => true,
            'kiosk_debounce_seconds' => 10,
            'strict_mode' => false,
            'auto_checkout_hours' => 12,
            'email_reminders_enabled' => true,
        ]);

        // Load data into the form
        $this->form->fill($settings->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Kiosk & Camera')
                    ->description('Configure how the scanning kiosk behaves.')
                    ->schema([
                        Forms\Components\Toggle::make('camera_mirror')
                            ->label('Mirror Camera Feed')
                            ->helperText('Flip the webcam video horizontally (like a mirror).')
                            ->default(true),

                        Forms\Components\TextInput::make('kiosk_debounce_seconds')
                            ->label('Debounce Time (Seconds)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->helperText('Prevent double-scanning the same person within this time.')
                            ->required(),

                        Forms\Components\Toggle::make('strict_mode')
                            ->label('Strict Identity Mode')
                            ->helperText('If enabled, rejects entry if the member does not have a profile photo.'),
                    ])->columns(2),

                Forms\Components\Section::make('Automation Rules')
                    ->schema([
                        Forms\Components\TextInput::make('auto_checkout_hours')
                            ->label('Auto-Checkout Threshold (Hours)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->helperText('Automatically close sessions older than this (prevents "Ghost Sessions").')
                            ->required(),

                        Forms\Components\Toggle::make('email_reminders_enabled')
                            ->label('Enable Email Reminders')
                            ->helperText('Send automated emails 7, 3, and 1 day before expiration.')
                            ->default(true),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
            
            // Update the single settings row
            $settings = GymSetting::first();
            $settings->update($data);

            Notification::make() 
                ->success()
                ->title('Settings Saved')
                ->send();
                
        } catch (Halt $exception) {
            return;
        }
    }
}