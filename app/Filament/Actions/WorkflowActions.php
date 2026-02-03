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
     * @param string|null $pdfServiceClass  Classe du service PDF (ex: BonCommandePdfService::class)
     * @param string|null $pdfRouteName  Nom de la route pour l'aperçu PDF
     */

    /**
     * Actions PDF uniquement
     */
    public static function pdfActionsOnly(string $serviceClass, string $routeName): Tables\Actions\ActionGroup
    {
        return self::pdfActions($serviceClass, $routeName);
    }

    public static function make(
        bool $avecEngagement = false,
        ?string $pdfServiceClass = null,
        ?string $pdfRouteName = null
    ): array {
        $actions = [
            Tables\Actions\ViewAction::make(),

            Tables\Actions\EditAction::make()
                ->visible(fn($record) => $record->estModifiable() && !$record->estEnCoursDeTransmission()),
        ];

        // Actions PDF (si service fourni)
        if ($pdfServiceClass && $pdfRouteName) {
            $actions[] = self::pdfActions($pdfServiceClass, $pdfRouteName);
        }

        $actions = array_merge($actions, [
            self::valider(),
        ]);

        if ($avecEngagement) {
            $actions[] = self::engager();
        }

        $actions = array_merge($actions, [
            self::transmettre(),
            self::retourner(),
            self::cloturer(),
            self::historique(),
        ]);

        return $actions;
    }

    /* =========================
     | ACTIONS : PDF
     ========================= */
    /**
     * Actions PDF avec choix du type d'état
     */
    private static function pdfActions(string $serviceClass, string $routeName): Tables\Actions\ActionGroup
    {
        return Tables\Actions\ActionGroup::make([
            Tables\Actions\Action::make('apercu_pdf_simple')
                ->label('Aperçu BC')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn($record) => route('bons-commande.pdf.preview.simple', ['bonCommande' => $record->id]))
                ->openUrlInNewTab(),

            Tables\Actions\Action::make('apercu_pdf_complet')
                ->label('Aperçu BCA')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->url(fn($record) => route('bons-commande.pdf.preview.complet', ['bonCommande' => $record->id]))
                ->openUrlInNewTab(),

            Tables\Actions\Action::make('telecharger_pdf_simple')
                ->label('Télécharger BC')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn($record) => route('bons-commande.pdf.download.simple', ['bonCommande' => $record->id])),

            Tables\Actions\Action::make('telecharger_pdf_complet')
                ->label('Télécharger BCA')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('danger')
                ->url(fn($record) => route('bons-commande.pdf.download.complet', ['bonCommande' => $record->id])),
        ])
            ->label('Télécharger')
            ->icon('heroicon-o-document')
            ->size('sm')
            ->color('success')
            ->button()
            ->visible(fn($record) => !in_array($record->statut, ['brouillon']));
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
            ->visible(fn($record) => in_array($record->statut, ['brouillon']))
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
                    && !($record->engage ?? $record->engagee ?? false)
            )
            ->requiresConfirmation()
            ->action(function ($record) {
                try {
                    // Compatible BC et Décision
                    if (method_exists($record, 'engagerBudget')) {
                        $record->engagerBudget();

                        // Recharger pour avoir l'engagement
                        $record->refresh();

                        $numeroEngagement = $record->engagement?->reference_document ?? 'N/A';

                        Notification::make()
                            ->title('Budget engagé avec succès')
                            ->success()
                            ->body("Engagement créé : {$numeroEngagement}")
                            ->send();
                    }
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
                    ->preload()
                    ->live(),

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
                    ->required()
                    ->live()
                    ->helperText(function (Get $get) {
                        $destinataireId = $get('destinataire_id');

                        if (!$destinataireId) {
                            return '';
                        }

                        $destinataire = User::find($destinataireId);
                        $expediteur = auth()->user();

                        if (!$expediteur->peutImposerPrioriteA($destinataire)) {
                            return '⚠️ Vous ne pouvez pas définir une priorité haute ou urgente pour un supérieur hiérarchique.';
                        }

                        return '';
                    }),

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
            ->modalHeading('Retourner pour correction')
            ->modalDescription('Le document sera retourné à l\'expéditeur avec votre motif')
            ->action(function ($record, array $data) {
                try {
                    $record->retournerPourCorrection($data['motif']);

                    Notification::make()
                        ->title('Document retourné')
                        ->warning()
                        ->body('Le document a été retourné à l\'expéditeur')
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
            ->modalHeading('Clôturer la transmission')
            ->modalDescription('Confirmez que vous avez traité cette transmission')
            ->action(function ($record, array $data) {
                $record->cloturerTransmission($data['reponse'] ?? null);

                Notification::make()
                    ->title('Transmission clôturée')
                    ->success()
                    ->body('La transmission a été traitée avec succès')
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

        if ($destinataire && method_exists($expediteur, 'peutImposerPrioriteA') && $expediteur->peutImposerPrioriteA($destinataire)) {
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
