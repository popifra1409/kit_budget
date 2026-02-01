<?php

namespace App\Filament\Actions;

use Filament\Tables;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use App\Models\User;

class WorkflowActions
{
    /**
     * Génère les actions standard du workflow administratif
     *
     * @param bool $avecEngagement  Active l'action "Engager"
     */
    public static function make(bool $avecEngagement = false): array
    {
        $actions = [
            Tables\Actions\ViewAction::make(),

            Tables\Actions\EditAction::make()
                ->visible(fn($record) => $record->estModifiable()),

            self::valider(),
            self::transmettre(),
            self::retourner(),
            self::cloturer(),
            self::historique(),
        ];

        if ($avecEngagement) {
            $actions[] = self::engager();
        }

        return $actions;
    }

    /* =========================
     | ACTION : VALIDER
     ========================= */
    private static function valider(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('valider')
            ->label('Valider')
            ->icon('heroicon-o-check-circle')
            ->color('warning')
            ->visible(fn($record) => $record->statut === 'brouillon')
            ->requiresConfirmation()
            ->action(function ($record) {
                $record->valider(auth()->user());

                Notification::make()
                    ->title('Document validé')
                    ->success()
                    ->send();
            });
    }

    /* =========================
     | ACTION : ENGAGER
     ========================= */
    private static function engager(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('engager')
            ->label('Engager')
            ->icon('heroicon-o-banknotes')
            ->color('primary')
            ->visible(
                fn($record) =>
                in_array($record->statut, ['valide', 'validee'])
                    && !($record->engage ?? $record->engagee)
            )
            ->requiresConfirmation()
            ->action(function ($record) {
                try {
                    // Compatible BC et Décision
                    method_exists($record, 'engagerBudget')
                        ? $record->engagerBudget()
                        : null;

                    Notification::make()
                        ->title('Budget engagé avec succès')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Erreur lors de l\'engagement')
                        ->danger()
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /* =========================
     | ACTION : TRANSMETTRE
     ========================= */
    private static function transmettre(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('transmettre')
            ->label('Transmettre')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->visible(
                fn($record) =>
                method_exists($record, 'peutEtreTransmis')
                    && $record->peutEtreTransmis()
                    && in_array($record->statut, ['brouillon', 'valide', 'validee'])
            )
            ->form([
                Forms\Components\Select::make('destinataire_id')
                    ->label('Transmettre à')
                    ->options(User::whereNotNull('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('action_attendue')
                    ->label('Action attendue')
                    ->options([
                        'validation' => 'Validation',
                        'engagement' => 'Engagement',
                        'verification' => 'Vérification',
                        'signature' => 'Signature',
                        'information' => 'Pour information',
                    ])
                    ->required()
                    ->default('validation'),

                Forms\Components\Textarea::make('commentaire')
                    ->label('Commentaire')
                    ->rows(3),

                Forms\Components\Select::make('priorite')
                    ->label('Priorité')
                    ->options(fn(Get $get) => self::priorites($get))
                    ->default('normale')
                    ->required(),

                Forms\Components\DatePicker::make('date_limite')
                    ->label('Date limite (optionnel)')
                    ->minDate(now()),
            ])
            ->action(function ($record, array $data) {
                try {
                    $destinataire = User::findOrFail($data['destinataire_id']);

                    $record->transmettreA(
                        $destinataire,
                        $data['action_attendue'],
                        $data['commentaire'] ?? null,
                        [
                            'priorite' => $data['priorite'],
                            'date_limite' => $data['date_limite'] ?? null,
                        ]
                    );

                    Notification::make()
                        ->title('Document transmis')
                        ->success()
                        ->body("Transmis à {$destinataire->name}")
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Erreur lors de la transmission')
                        ->danger()
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /* =========================
     | ACTION : RETOURNER
     ========================= */
    private static function retourner(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('retourner')
            ->label('Retourner')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(
                fn($record) =>
                method_exists($record, 'estDestinataireActuel')
                    && $record->estDestinataireActuel()
                    && $record->transmissionEnCours()
            )
            ->form([
                Forms\Components\Textarea::make('motif')
                    ->label('Motif du retour')
                    ->required()
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->action(function ($record, array $data) {
                try {
                    $record->retournerPourCorrection($data['motif']);

                    Notification::make()
                        ->title('Document retourné')
                        ->warning()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Erreur')
                        ->danger()
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /* =========================
     | ACTION : CLÔTURER TRANSMISSION
     ========================= */
    private static function cloturer(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('cloturer_transmission')
            ->label('Clôturer')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(
                fn($record) =>
                method_exists($record, 'estDestinataireActuel')
                    && $record->estDestinataireActuel()
                    && $record->transmissionEnCours()
            )
            ->form([
                Forms\Components\Textarea::make('reponse')
                    ->label('Réponse / Commentaire')
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->action(function ($record, array $data) {
                $record->cloturerTransmission($data['reponse'] ?? null);

                Notification::make()
                    ->title('Transmission clôturée')
                    ->success()
                    ->send();
            });
    }

    /* =========================
     | ACTION : HISTORIQUE
     ========================= */
    private static function historique(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('historique_transmissions')
            ->label('Historique')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->visible(
                fn($record) =>
                method_exists($record, 'aEteTransmis') && $record->aEteTransmis()
            )
            ->modalHeading(
                fn($record) =>
                'Historique des transmissions - ' . ($record->numero ?? $record->id)
            )
            ->modalContent(
                fn($record) =>
                view('filament.modals.historique-transmissions', [
                    'transmissions' => $record->historiqueTransmissions(),
                ])
            )
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer');
    }

    /* =========================
     | UTIL : PRIORITÉS
     ========================= */
    private static function priorites(Get $get): array
    {
        $destinataireId = $get('destinataire_id');

        if (!$destinataireId) {
            return ['normale' => 'Normale'];
        }

        $destinataire = User::find($destinataireId);
        $expediteur = auth()->user();

        if ($destinataire && $expediteur->peutImposerPrioriteA($destinataire)) {
            return [
                'basse' => 'Basse',
                'normale' => 'Normale',
                'haute' => 'Haute',
                'urgente' => 'Urgente',
            ];
        }

        return [
            'basse' => 'Basse',
            'normale' => 'Normale',
        ];
    }
}
