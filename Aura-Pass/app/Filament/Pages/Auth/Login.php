<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Component;
use Illuminate\Validation\ValidationException;
use App\Services\AuditLogService;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;

class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getUsernameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    /**
     * Override the authenticate method to inject the Audit Log tracker with username.
     */
    public function authenticate(): ?LoginResponse
    {
        // 1. Capture the username used in the form before parent::authenticate() clears it
        $username = $this->form->getState()['username'] ?? 'Unknown';

        // 2. Let Filament handle validation and credential checking natively
        $response = parent::authenticate();

        // 3. If no exception was thrown, log it along with the username in the details column
        app(AuditLogService::class)->logActivity(
            activity: 'admin.logged_in',
            model: null,
            details: ['username' => $username]
        );

        // 4. Complete the login response pipeline
        return $response;
    }

    /**
     * FIX: Override the failure exception to target the 'username' field 
     * instead of the default 'email' field.
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}