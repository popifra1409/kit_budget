<?php

namespace App\Filament\Planification\Resources\PlanStrategiqueEpResource\RelationManagers;

use App\Models\Exercice;
use App\Models\Programme;
use App\Models\SousProgrammeEp;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SousProgrammesRelationManager extends RelationManager
{
    protected static string $relationship = 'sousProgrammes';

    // B1b — le mot « sous-programme » désigne ici la décomposition de l'hôpital, pas le
    // programme du MINSANTE : l'onglet voisin les liste, les deux titres devaient cesser
    // de se ressembler.
    protected static ?string $title = "Sous-programmes de l'EP";

    protected static ?string $recordTitleAttribute = 'libelle';

    /**
     * Programmes de l'exercice actif, sans le scope global 'exercice'
     * (evite les doublons P-410 2026 / P-410 2027 apres reconduction).
     */
    protected static function programmesExerciceActif()
    {
        return Programme::withoutGlobalScope('exercice')
            ->where('exercice_id', Exercice::getActif()?->id)
            ->orderBy('code');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()->unique(ignoreRecord: true)->maxLength(50),
            Forms\Components\TextInput::make('libelle')
                ->required()->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->columnSpanFull(),
            Forms\Components\Select::make('type')
                ->label('Type de sous-programme')
                ->options([
                    'operationnel' => 'Opérationnel',
                    'support' => 'Support',
                ])
                ->default('operationnel')
                ->required()
                ->helperText('Maximum 4 sous-programmes par EP : 3 opérationnels + 1 support (Instruction du 22 janvier 2026).'),

            Forms\Components\Textarea::make('objectif')
                ->label('Objectif du sous-programme'),
            Forms\Components\Textarea::make('strategie')
                ->label('Stratégie du sous-programme')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('cadre_institutionnel')
                ->label('Cadre institutionnel de mise en œuvre')
                ->columnSpanFull(),
            \App\Filament\Planification\Components\ResponsableSelect::make()
                ->label('Responsable (agent)')
                ->required()
                ->helperText('Agent de la table des personnels : la revue des libellés et la matrice d\'arrimage ne lui montrent que ses sous-programmes. Sans agent désigné — ou sans fiche reliée à un login — le sous-programme n\'est visible que des comptes habilités « Tous les libellés ».'),

            // ── Rattachement à la tutelle ───────────────────────────────
            // Une seule saisie : le code sous lequel les actions sont rangées en découle
            // (`SousProgrammeEp::saving()`), et le lien direct action → sous-programme arrive
            // à l'étape suivante de la correction.
            Forms\Components\Select::make('programme_budgetaire_id')
                ->label('Programme budgétaire du MINSANTE')
                ->helperText('Programme de la tutelle (ex : 412 Renforcement des capacités techniques), lui-même issu des objectifs de développement du pays (SND30) et de la stratégie sectorielle de santé. Un seul sous-programme de l\'EP par programme.')
                ->options(fn() => static::programmesExerciceActif()
                    ->where('niveau', 'programme')
                    ->get()
                    ->mapWithKeys(fn($p) => [$p->id => "{$p->code} — {$p->libelle}"]))
                ->searchable()
                ->required()
                // `rules()` et non `rule()` : Filament évalue la fermeture au montage, où
                // `$attribute`/`$value`/`$fail` ne sont pas résolus — `rule()` ferait mourir
                // l'onglet. La fermeture reçoit `$get` et retourne la règle.
                ->rules(function ($get) {
                    return [function (string $attribute, $value, \Closure $fail) use ($get) {
                        $conflit = SousProgrammeEp::autreSousProgrammeDuMemeProgramme(
                            $value ? (int) $value : null,
                            saufCode: $get('code'),
                        );

                        if ($conflit) {
                            $fail("Ce programme du MINSANTE a déjà son sous-programme de l'EP :"
                                . " « {$conflit->code} » ({$conflit->libelle}).");
                        }
                    }];
                })
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->searchable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors(['primary' => 'operationnel', 'warning' => 'support']),
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('libelle')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('responsable.nom_complet')->label('Responsable'),
                // Une colonne pour le rattachement : le code porteur des actions en découle.
                Tables\Columns\TextColumn::make('programmeBudgetaire.code')
                    ->label('Programme du MINSANTE')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'danger')
                    ->placeholder('Non rattaché'),
                Tables\Columns\BadgeColumn::make('statut')->colors([
                    'gray' => 'brouillon',
                    'warning' => 'en_transmission',
                    'success' => ['valide', 'en_vigueur'],
                    'danger' => 'cloture',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => auth()->user()->can('create_sous_programme_ep')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('gererActions')
                        ->label('Gérer les actions')
                        ->icon('heroicon-o-squares-2x2')
                        ->url(
                            fn(SousProgrammeEp $record) =>
                            \App\Filament\Planification\Resources\SousProgrammeEpResource::getUrl('edit', ['record' => $record])
                        ),

                    Tables\Actions\Action::make('libelles')
                        ->label('Tableau des libellés')
                        ->icon('heroicon-o-list-bullet')
                        ->visible(fn() => auth()->user()->can('view_arborescence_libelles'))
                        ->url(fn(SousProgrammeEp $record) => \App\Filament\Planification\Pages\ArborescenceLibelles::getUrl([
                            'psp' => $record->plan_strategique_ep_id,
                            'sp'  => $record->id,
                        ]))
                        ->openUrlInNewTab(),

                    Tables\Actions\EditAction::make()
                        ->visible(
                            fn(SousProgrammeEp $record) =>
                            auth()->user()->can('update_sous_programme_ep') && $record->estModifiable()
                        ),

                    Tables\Actions\Action::make('transmettre')
                        ->label('Transmettre')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(
                            fn(SousProgrammeEp $record) =>
                            $record->peutEtreTransmis() && auth()->user()->can('transmettre_sous_programme_ep')
                        )
                        ->form([
                            Forms\Components\Select::make('destinataire_id')
                                ->label('Destinataire')
                                ->options(fn() => User::orderBy('name')->pluck('name', 'id'))
                                ->searchable()->required(),
                            Forms\Components\Select::make('action_attendue')
                                ->options([
                                    'validation' => 'Validation',
                                    'avis' => 'Avis',
                                    'correction' => 'Correction',
                                ])->required(),
                            Forms\Components\Textarea::make('commentaire'),
                        ])
                        ->action(function (SousProgrammeEp $record, array $data) {
                            $record->transmettreA(
                                User::findOrFail($data['destinataire_id']),
                                $data['action_attendue'],
                                $data['commentaire'] ?? null,
                            );
                            $record->update(['statut' => 'en_transmission']);
                        }),

                    // Le droit commande le bouton, plus la transmission : un brouillon se
                    // valide ici sans avoir circulé. Le refus, lui, reste lisible sur la ligne.
                    Tables\Actions\Action::make('valider')
                        ->label('Valider')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn() => auth()->user()->can('valider_sous_programme_ep'))
                        ->requiresConfirmation()
                        ->modalHeading(fn(SousProgrammeEp $record) => "Valider — {$record->code}")
                        ->modalDescription('Le sous-programme passe à « Validé ». Transmettre reste le chemin tracé entre deux agents, ce n\'est plus une condition de la validation.')
                        ->action(function (SousProgrammeEp $record) {
                            // « visible » n'empêche pas l'appel : le droit est revérifié ici.
                            if (!auth()->user()?->can('valider_sous_programme_ep')) {
                                Notification::make()
                                    ->title('Action non autorisée')
                                    ->danger()
                                    ->body('Votre rôle ne donne pas le droit de valider un sous-programme.')
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            try {
                                $record->valider();
                                Notification::make()
                                    ->title("« {$record->code} » est validé")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Validation refusée')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    // Bouton visible dès que l'utilisateur a le droit, même si le
                    // sous-programme n'est pas encore en état : le refus doit se lire,
                    // pas disparaître de la ligne.
                    Tables\Actions\Action::make('mettreEnVigueur')
                        ->label('Mettre en vigueur')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->visible(fn() => auth()->user()->can('mettre_en_vigueur_sous_programme_ep'))
                        ->requiresConfirmation()
                        ->modalHeading(fn(SousProgrammeEp $record) => "Mettre en vigueur — {$record->code}")
                        ->modalDescription('Le sous-programme passe de « Validé » à « En vigueur » : son contenu devient la référence de l\'exercice.')
                        ->action(function (SousProgrammeEp $record) {
                            // Le « visible » ci-dessus ne protège pas l'appel : un action
                            // masquée reste exécutable en POSTant son nom.
                            if (!auth()->user()?->can('mettre_en_vigueur_sous_programme_ep')) {
                                Notification::make()
                                    ->title('Action non autorisée')
                                    ->danger()
                                    ->body('Votre rôle ne donne pas le droit de mettre un sous-programme en vigueur.')
                                    ->persistent()
                                    ->send();
                                return;
                            }

                            try {
                                $record->mettreEnVigueur();
                                Notification::make()
                                    ->title("« {$record->code} » est en vigueur")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Mise en vigueur refusée')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('retourner')
                        ->label('Retourner')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->visible(
                            fn(SousProgrammeEp $record) =>
                            $record->estDestinataireActuel() && auth()->user()->can('retourner_sous_programme_ep')
                        )
                        ->form([Forms\Components\Textarea::make('motif')->required()])
                        ->action(
                            fn(SousProgrammeEp $record, array $data) =>
                            $record->retournerPourCorrection($data['motif'])
                        ),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: \Filament\Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete_sous_programme_ep')),
                ]),
            ]);
    }
}
