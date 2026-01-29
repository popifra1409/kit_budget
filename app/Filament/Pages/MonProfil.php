<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class MonProfil extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.pages.mon-profil';

    protected static ?string $navigationLabel = 'Mon Profil';

    protected static ?string $title = 'Mon Profil';

    protected static ?int $navigationSort = 100;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations personnelles')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom complet')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique('users', 'email', ignoreRecord: true)
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Changer le mot de passe')
                    ->schema([
                        Forms\Components\TextInput::make('current_password')
                            ->label('Mot de passe actuel')
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('password')
                            ->label('Nouveau mot de passe')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::default())
                            ->dehydrated(fn($state) => filled($state))
                            ->live(debounce: 500)
                            ->same('password_confirmation')
                            ->helperText('Minimum 8 caractères'),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirmer le mot de passe')
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false),
                    ])
                    ->columns(3)
                    ->description('Laissez vide si vous ne souhaitez pas changer votre mot de passe'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();

        // Mettre à jour les informations de base
        $user->name = $data['name'];
        $user->email = $data['email'];

        // Mettre à jour le mot de passe si fourni
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // Réinitialiser le formulaire
        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        Notification::make()
            ->title('Profil mis à jour')
            ->success()
            ->body('Vos informations ont été mises à jour avec succès.')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Enregistrer les modifications')
                ->submit('save'),
        ];
    }
}
