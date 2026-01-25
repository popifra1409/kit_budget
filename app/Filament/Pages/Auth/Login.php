<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    /**
     * Personnaliser le formulaire de connexion
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent()
                            ->autocomplete('off')
                            ->extraAttributes([
                                'autocomplete' => 'off',
                                'autocorrect' => 'off',
                                'autocapitalize' => 'off',
                                'spellcheck' => 'false',
                            ]),
                        $this->getPasswordFormComponent()
                            ->autocomplete('off')
                            ->extraAttributes([
                                'autocomplete' => 'new-password',
                                'data-form-type' => 'other',
                            ]),
                        $this->getRememberFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    /**
     * Champ Email sécurisé
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email')
            ->email()
            ->required()
            ->autocomplete('off')
            ->autofocus()
            ->extraAttributes([
                'autocomplete' => 'off',
                'autocorrect' => 'off',
                'autocapitalize' => 'off',
                'spellcheck' => 'false',
            ])
            ->extraInputAttributes([
                'autocomplete' => 'off',
            ]);
    }

    /**
     * Champ Mot de passe sécurisé
     */
    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Mot de passe')
            ->password()
            ->required()
            ->autocomplete('off')
            ->extraAttributes([
                'autocomplete' => 'new-password',
                'data-form-type' => 'other',
            ])
            ->extraInputAttributes([
                'autocomplete' => 'new-password',
            ]);
    }

    /**
     * Désactiver "Se souvenir de moi" par défaut
     */
    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->default(false);
    }

    /**
     * Titre de la page
     */
    public function getHeading(): string|Htmlable
    {
        return 'Connexion Sécurisée';
    }

    /**
     * Sous-titre avec avertissement de sécurité
     */
    public function getSubHeading(): string|Htmlable|null
    {
        return 'Pour votre sécurité, ne cochez pas "Se souvenir de moi" sur un ordinateur partagé.';
    }
}
