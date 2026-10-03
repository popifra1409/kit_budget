<?php

namespace App\Filament\Resources\PersonnelResource\Pages;

use App\Filament\Resources\PersonnelResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewPersonnel extends ViewRecord
{
    protected static string $resource = PersonnelResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Section Identité
                Infolists\Components\Section::make('Identité et informations personnelles')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('matricule')
                                    ->label('Matricule')
                                    ->badge()
                                    ->color('primary')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('civilite')
                                    ->label('Civilité')
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('sexe')
                                    ->label('Sexe')
                                    ->formatStateUsing(fn($state) => match ($state) {
                                        'M' => 'Masculin',
                                        'F' => 'Féminin',
                                        default => '-'
                                    })
                                    ->badge()
                                    ->color(fn($state) => $state === 'M' ? 'info' : 'warning'),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('nom')
                                    ->label('Nom')
                                    ->weight('bold')
                                    ->size('lg'),

                                Infolists\Components\TextEntry::make('prenoms')
                                    ->label('Prénoms')
                                    ->weight('bold')
                                    ->size('lg'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('date_naissance')
                                    ->label('Date de naissance')
                                    ->date('d/m/Y')
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('lieu_naissance')
                                    ->label('Lieu de naissance')
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('nationalite')
                                    ->label('Nationalité'),
                            ]),

                        Infolists\Components\TextEntry::make('age')
                            ->label('Âge')
                            ->getStateUsing(function ($record) {
                                if (!$record->date_naissance) {
                                    return '-';
                                }
                                return $record->age . ' ans';
                            })
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(1),

                // Section Affectation professionnelle
                Infolists\Components\Section::make('Affectation professionnelle')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('service.nom')
                                    ->label('Service')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('Non affecté'),

                                Infolists\Components\TextEntry::make('fonction')
                                    ->label('Fonction')
                                    ->weight('bold')
                                    ->placeholder('-'),
                            ]),

                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('grade')
                                    ->label('Grade')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('categorie')
                                    ->label('Catégorie')
                                    ->badge()
                                    ->color(fn($state) => match ($state) {
                                        'A' => 'primary',
                                        'B' => 'success',
                                        'C' => 'warning',
                                        'D' => 'danger',
                                        default => 'gray',
                                    })
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('echelon')
                                    ->label('Échelon')
                                    ->badge()
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('indice')
                                    ->label('Indice')
                                    ->badge()
                                    ->placeholder('-'),
                            ]),

                        Infolists\Components\TextEntry::make('anciennete')
                            ->label('Ancienneté')
                            ->getStateUsing(function ($record) {
                                if (!$record->date_prise_service) {
                                    return '-';
                                }
                                $anciennete = $record->anciennete;
                                if ($anciennete === 0) {
                                    return 'Moins d\'un an';
                                }
                                return $anciennete . ' an' . ($anciennete > 1 ? 's' : '');
                            })
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(1)
                    ->collapsible(),

                // Section Dates importantes
                Infolists\Components\Section::make('Dates importantes')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('date_prise_service')
                                    ->label('Prise de service')
                                    ->date('d/m/Y')
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('date_titularisation')
                                    ->label('Titularisation')
                                    ->date('d/m/Y')
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('date_depart_retraite')
                                    ->label('Départ retraite')
                                    ->date('d/m/Y')
                                    ->placeholder('-')
                                    ->color(fn($record) => $record->estProcheRetraite() ? 'danger' : 'gray'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // Section Contact
                Infolists\Components\Section::make('Coordonnées')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('telephone')
                                    ->label('Téléphone')
                                    ->icon('heroicon-o-phone')
                                    ->copyable()
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('email')
                                    ->label('Email')
                                    ->icon('heroicon-o-envelope')
                                    ->copyable()
                                    ->placeholder('-'),
                            ]),

                        Infolists\Components\TextEntry::make('adresse')
                            ->label('Adresse')
                            ->icon('heroicon-o-map-pin')
                            ->columnSpanFull()
                            ->placeholder('-'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // Section Informations bancaires et administratives
                Infolists\Components\Section::make('Informations bancaires et administratives')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('numero_cni')
                                    ->label('N° CNI')
                                    ->copyable()
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('numero_cnps')
                                    ->label('N° CNPS')
                                    ->copyable()
                                    ->placeholder('-'),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('numero_compte_bancaire')
                                    ->label('N° Compte bancaire')
                                    ->copyable()
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('banque')
                                    ->label('Banque')
                                    ->placeholder('-'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // Section Statut
                Infolists\Components\Section::make('Statut et compte utilisateur')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('statut')
                                    ->label('Statut')
                                    ->badge()
                                    ->formatStateUsing(fn(string $state): string => match ($state) {
                                        'actif' => 'Actif',
                                        'conge' => 'En congé',
                                        'detache' => 'Détaché',
                                        'disponibilite' => 'En disponibilité',
                                        'suspendu' => 'Suspendu',
                                        'retraite' => 'Retraité',
                                        'demissionnaire' => 'Démissionnaire',
                                        default => $state,
                                    })
                                    ->color(fn(string $state): string => match ($state) {
                                        'actif' => 'success',
                                        'conge' => 'info',
                                        'detache', 'disponibilite' => 'warning',
                                        'suspendu', 'demissionnaire' => 'danger',
                                        'retraite' => 'secondary',
                                        default => 'gray',
                                    }),

                                Infolists\Components\IconEntry::make('actif')
                                    ->label('Actif dans le système')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),

                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('Compte utilisateur')
                                    ->badge()
                                    ->color('primary')
                                    ->icon('heroicon-o-user')
                                    ->placeholder('Aucun compte lié'),
                            ]),
                    ]),

                // ✅ AJOUT — Synthèse financière (tous exercices), détaillée dans les onglets ci-dessous
                Infolists\Components\Section::make('Historique financier')
                    ->description('Tous exercices confondus — le détail figure dans les onglets en bas de page.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Infolists\Components\Grid::make(5)
                            ->schema([
                                Infolists\Components\TextEntry::make('synthese_engagements')
                                    ->label('Engagements')
                                    ->getStateUsing(fn($record) => $this->synthese()['nb_engagements'] . ' engagement(s)')
                                    ->badge()->color('info'),

                                Infolists\Components\TextEntry::make('synthese_engage')
                                    ->label('Total engagé')
                                    ->getStateUsing(fn($record) => $this->synthese()['total_engage'])
                                    ->money('XAF')->weight('bold'),

                                Infolists\Components\TextEntry::make('synthese_ordonnance')
                                    ->label('Total ordonnancé (net)')
                                    ->getStateUsing(fn($record) => $this->synthese()['total_ordonnance'])
                                    ->money('XAF'),

                                Infolists\Components\TextEntry::make('synthese_paye')
                                    ->label('Total payé (net)')
                                    ->getStateUsing(fn($record) => $this->synthese()['total_paye'])
                                    ->money('XAF')->color('success')->weight('bold'),

                                Infolists\Components\TextEntry::make('synthese_reste')
                                    ->label('Reste à payer')
                                    ->getStateUsing(fn($record) => $this->synthese()['reste_a_payer'])
                                    ->money('XAF')
                                    ->color(fn($state) => $state > 0 ? 'warning' : 'gray')
                                    ->weight('bold'),
                            ]),
                    ]),

                // Section Statistiques
                Infolists\Components\Section::make('Statistiques')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('decisions_count')
                                    ->label('Décisions administratives')
                                    ->getStateUsing(fn($record) => $this->synthese()['nb_decisions'])
                                    ->badge()
                                    ->color('info')
                                    ->suffix(' décision(s)'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Fiche créée le')
                                    ->dateTime('d/m/Y à H:i'),

                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Dernière modification')
                                    ->dateTime('d/m/Y à H:i')
                                    ->since(),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('creer_decision')
                ->label('Créer une décision')
                ->icon('heroicon-o-document-text')
                ->color('success')
                // ✅ CORRIGÉ — les DA n'existent que dans le module Budget : adresse construite pour ce panel
                //    (depuis le panel Administration, l'ancienne adresse provoquait une erreur)
                ->visible(fn() => auth()->user()?->can('create_decision_administrative'))
                ->url(fn() => \App\Filament\Budget\Resources\DecisionAdministrativeResource::getUrl('create', [
                    'personnel_id' => $this->record->id
                ], panel: 'budget')),

            Actions\DeleteAction::make()
                // ✅ CORRIGÉ — suppression impossible dès qu'il existe un historique (DA, engagement ou OP),
                //    tous exercices confondus
                ->visible(fn() => !$this->record->aUnHistorique())
                ->requiresConfirmation()
                ->modalDescription('Êtes-vous sûr de vouloir supprimer ce personnel ? Cette action est irréversible.'),
        ];
    }

    // ✅ AJOUT — Historique de l'agent en onglets (comme les dossiers sur la fiche fournisseur)
    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Resources\PersonnelResource\RelationManagers\DecisionsRelationManager::class,
            \App\Filament\Resources\PersonnelResource\RelationManagers\EngagementsRelationManager::class,
            \App\Filament\Resources\PersonnelResource\RelationManagers\OrdonnancesRelationManager::class,
            \App\Filament\Resources\PersonnelResource\RelationManagers\AvenantsRelationManager::class,
        ];
    }

    /** Synthèse calculée une seule fois par affichage (5 cartes de montants). */
    protected ?array $syntheseCache = null;

    protected function synthese(): array
    {
        return $this->syntheseCache ??= $this->record->syntheseHistorique();
    }
}
