<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Component;
use Illuminate\Validation\ValidationException;
use App\Services\AuditLogService;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Support\Facades\Request as FacadesRequest;

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
     * Override the authenticate method to inject the Audit Log tracker safely.
     */
    public function authenticate(): ?LoginResponse
    {
        // 1. Let Filament log the user in completely
        $response = parent::authenticate();

        // 2. Safely grab the username directly from Filament's Livewire state array
        $username = $this->data['username'] ?? 'Unknown';

        // 3. Log the activity cleanly
        app(\App\Services\AuditLogService::class)->logActivity(
            'admin.logged_in',
            null,
            ['username' => $username]
        );

        return $response;
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}