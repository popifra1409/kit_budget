<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
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
