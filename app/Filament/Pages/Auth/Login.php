<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /**
     * Surcharge avec la signature correcte de Filament v3.3.47
     * Force session()->regenerate() AVANT le redirect
     * pour éviter le 419 causé par le token CSRF périmé
     */
    public function authenticate(): ?LoginResponse
    {
        $this->rateLimit(5);

        $data = $this->form->getState();

        if (! \Illuminate\Support\Facades\Auth::attempt(
            $this->getCredentialsFromFormData($data),
            $data['remember'] ?? false,
        )) {
            throw ValidationException::withMessages([
                'data.email' => __('filament-panels::pages/auth/login.messages.failed'),
            ]);
        }

        session()->regenerate();

        // Force navigation complète — pas de swap Livewire
        $this->js("window.location.href = '/portal'");

        return null;
    }
    
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent()
                            ->autocomplete('off')
                            ->extraAttributes([
                                'autocomplete'   => 'off',
                                'autocorrect'    => 'off',
                                'autocapitalize' => 'off',
                                'spellcheck'     => 'false',
                            ]),
                        $this->getPasswordFormComponent()
                            ->autocomplete('off')
                            ->extraAttributes([
                                'autocomplete'   => 'new-password',
                                'data-form-type' => 'other',
                            ]),
                        $this->getRememberFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email')
            ->email()
            ->required()
            ->autocomplete('off')
            ->autofocus()
            ->extraAttributes([
                'autocomplete'   => 'off',
                'autocorrect'    => 'off',
                'autocapitalize' => 'off',
                'spellcheck'     => 'false',
            ])
            ->extraInputAttributes(['autocomplete' => 'off']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Mot de passe')
            ->password()
            ->required()
            ->autocomplete('off')
            ->extraAttributes([
                'autocomplete'   => 'new-password',
                'data-form-type' => 'other',
            ])
            ->extraInputAttributes(['autocomplete' => 'new-password']);
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()->default(false);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Connexion Sécurisée';
    }

    public function getSubHeading(): string|Htmlable|null
    {
        return 'Pour votre sécurité, ne cochez pas "Se souvenir de moi" sur un ordinateur partagé.';
    }
}
