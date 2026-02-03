<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * Actions d'en-tête
     */
    protected function getHeaderActions(): array
    {
        return [
            // Voir les utilisateurs ayant ce rôle
            Actions\Action::make('voir_utilisateurs')
                ->label('Voir les utilisateurs')
                ->icon('heroicon-o-users')
                ->color('info')
                ->badge(fn() => $this->record->users()->count())
                ->visible(fn() => $this->record->users()->count() > 0)
                ->url(fn() => route('filament.admin.resources.users.index', [
                    'tableFilters' => [
                        'role' => ['value' => $this->record->id],
                    ],
                ]))
                ->openUrlInNewTab(),

            // Dupliquer le rôle
            Actions\Action::make('dupliquer')
                ->label('Dupliquer')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Dupliquer ce rôle')
                ->modalDescription('Créer un nouveau rôle avec les mêmes permissions')
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Nom du nouveau rôle')
                        ->required()
                        ->unique('roles', 'name')
                        ->default(fn() => $this->record->name . '_copie'),

                    \Filament\Forms\Components\TextInput::make('niveau_hierarchique')
                        ->label('Niveau hiérarchique')
                        ->numeric()
                        ->required()
                        ->default(fn() => $this->record->niveau_hierarchique),
                ])
                ->action(function (array $data) {
                    $nouveauRole = \Spatie\Permission\Models\Role::create([
                        'name' => $data['name'],
                        'guard_name' => $this->record->guard_name,
                        'niveau_hierarchique' => $data['niveau_hierarchique'],
                    ]);

                    // Copier les permissions
                    $nouveauRole->syncPermissions($this->record->permissions);

                    Notification::make()
                        ->success()
                        ->title('Rôle dupliqué')
                        ->body("Le rôle {$nouveauRole->name} a été créé avec succès")
                        ->send();

                    return redirect()->route('filament.admin.resources.roles.edit', $nouveauRole);
                }),

            // Supprimer
            Actions\DeleteAction::make()
                ->label('Supprimer')
                ->before(function (Actions\DeleteAction $action) {
                    // Empêcher la suppression si des utilisateurs ont ce rôle
                    if ($this->record->users()->count() > 0) {
                        Notification::make()
                            ->danger()
                            ->title('Impossible de supprimer')
                            ->body('Ce rôle est assigné à ' . $this->record->users()->count() . ' utilisateur(s)')
                            ->persistent()
                            ->send();

                        $action->cancel();
                    }

                    // Empêcher la suppression des rôles système
                    $rolesProtected = ['super_admin', 'admin'];
                    if (in_array($this->record->name, $rolesProtected)) {
                        Notification::make()
                            ->danger()
                            ->title('Rôle protégé')
                            ->body('Ce rôle système ne peut pas être supprimé')
                            ->persistent()
                            ->send();

                        $action->cancel();
                    }
                })
                ->successNotificationTitle('Rôle supprimé avec succès'),
        ];
    }

    /**
     * Modifier les données avant la sauvegarde
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Empêcher la modification du nom des rôles système
        $rolesProtected = ['super_admin', 'admin'];
        if (in_array($this->record->name, $rolesProtected)) {
            $data['name'] = $this->record->name;

            Notification::make()
                ->warning()
                ->title('Modification restreinte')
                ->body('Le nom de ce rôle système ne peut pas être modifié')
                ->send();
        }

        return $data;
    }

    /**
     * Actions après la sauvegarde
     */
    protected function afterSave(): void
    {
        $role = $this->record;

        // Notification
        Notification::make()
            ->success()
            ->title('Rôle mis à jour')
            ->body("Le rôle **{$role->name}** a été modifié avec succès")
            ->send();

        // Log de l'activité
        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties([
                'niveau_hierarchique' => $role->niveau_hierarchique,
                'permissions_count' => $role->permissions()->count(),
                'users_count' => $role->users()->count(),
            ])
            ->log("Modification du rôle {$role->name}");
    }

    /**
     * Message de succès
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Modifications enregistrées';
    }

    /**
     * Actions du formulaire
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Enregistrer les modifications'),

            $this->getCancelFormAction()
                ->label('Annuler'),
        ];
    }
}
