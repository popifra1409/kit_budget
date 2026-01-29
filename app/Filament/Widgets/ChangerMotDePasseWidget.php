<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangerMotDePasseWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.changer-mot-de-passe-widget';

    protected int | string | array $columnSpan = 'full';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Changer mon mot de passe')
                    ->schema([
                        Forms\Components\TextInput::make('current_password')
                            ->label('Mot de passe actuel')
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword(),

                        Forms\Components\TextInput::make('password')
                            ->label('Nouveau mot de passe')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::default())
                            ->same('password_confirmation')
                            ->helperText('Minimum 8 caractères'),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirmer le nouveau mot de passe')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function changerMotDePasse(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();
        $user->password = Hash::make($data['password']);
        $user->save();

        $this->form->fill();

        Notification::make()
            ->title('Mot de passe modifié')
            ->success()
            ->body('Votre mot de passe a été changé avec succès.')
            ->send();
    }
}
