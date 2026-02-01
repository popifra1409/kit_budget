<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class MonProfil extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.pages.mon-profil';

    protected static ?string $navigationLabel = 'Mon Profil';

    protected static ?string $title = 'Mon Profil';

    protected static ?int $navigationSort = 100;

    // Données du profil
    public ?string $name = '';
    public ?string $email = '';

    // Données du mot de passe
    public ?string $current_password = '';
    public ?string $password = '';
    public ?string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
    }

    public function updateProfile(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . auth()->id(),
        ]);

        $user = auth()->user();
        $user->name = $this->name;
        $user->email = $this->email;
        $user->save();

        Notification::make()
            ->title('Profil mis à jour')
            ->success()
            ->body('Vos informations ont été mises à jour avec succès.')
            ->send();
    }

    public function updatePassword(): \Illuminate\Http\RedirectResponse
    {
        $this->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', Password::default(), 'same:password_confirmation'],
            'password_confirmation' => 'required',
        ]);

        $user = auth()->user();
        $user->password = Hash::make($this->password);
        $user->save();

        // Notification avant déconnexion
        Notification::make()
            ->title('Mot de passe modifié')
            ->success()
            ->body('Votre mot de passe a été changé. Vous allez être déconnecté.')
            ->send();

        // Déconnecter l'utilisateur
        auth()->logout();

        // Invalider la session
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        // Rediriger vers la page de login
        return redirect()->route('filament.admin.auth.login');
    }
}
