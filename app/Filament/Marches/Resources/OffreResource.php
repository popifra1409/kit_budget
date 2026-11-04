<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Concerns\AutorisationParPermissions;
use App\Filament\Marches\Resources\OffreResource\Pages;
use App\Models\Fournisseur;
use App\Models\Marche;
use App\Models\Offre;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Réception des offres — écran du service des marchés (scan du 27/10 17.29).
 *
 * Le formulaire reprend les cinq lignes du scan, dans l'ordre : le soumissionnaire
 * (référencé, pas recopié), le marché, la date de réception, la quittance — upload
 * ou ses informations — puis la décision. La décision ne se saisit pas ici : elle
 * passe par les actions du tableau, qui sont les seules à connaître la machine à
 * états du modèle.
 *
 * Rien ici n'est caché derrière un état que l'écran existe pour vérifier : une
 * offre hors délai ou déposée par un soumissionnaire blacklisté s'enregistre et
 * s'affiche, avec son avertissement, et le refus porte sur sa validation, pas sur
 * sa présence au dossier.
 */
class OffreResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = Offre::class;
    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationGroup = 'Appels d\'Offres';
    protected static ?string $navigationLabel = 'Offres reçues';
    protected static ?string $modelLabel = 'offre';
    protected static ?string $pluralModelLabel = 'offres';
    protected static ?string $recordTitleAttribute = 'numero';
    protected static ?int $navigationSort = 2;

    protected static function prefixePermission(): string
    {
        return 'offre';
    }

    // =========================================================
    // AUTORISATION
    // =========================================================

    /**
     * Une offre décidée n'est plus retapée : on lève la décision, puis on corrige.
     * force_update_offre reste l'issue de dernier recours de l'administration, pas
     * celle du service — sinon la décision du matin se réécrirait le soir.
     */
    public static function canEdit($record): bool
    {
        return auth()->user()?->can('force_update_offre')
            || (auth()->user()?->can('update_offre') && $record->statut === 'recue');
    }

    /** Une offre déjà décidée reste au dossier, même soft-delete hors de propos. */
    public static function canDelete($record): bool
    {
        return $record->statut === 'recue' && (auth()->user()?->can('delete_offre') ?? false);
    }

    public static function canDecider(): bool
    {
        return auth()->user()?->can('valider_offre') ?? false;
    }

    public static function canRejeter(): bool
    {
        return auth()->user()?->can('rejeter_offre') ?? false;
    }

    // =========================================================
    // FORMULAIRE
    // =========================================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Le marché visé')->schema([
                Forms\Components\Select::make('marche_id')->label('Marché')
                    ->options(function (?Offre $record) {
                        // sansExercice() : un DAO publié le 15 décembre avec dépôt
                        // jusqu'au 15 janvier reste ouvert quand la session a basculé
                        // sur le nouvel exercice — le guichet doit continuer à recevoir.
                        $marches = Marche::sansExercice()
                            ->whereIn('statut', ['publie', 'en_evaluation'])
                            ->orderByDesc('date_publication_dao')
                            ->get();
                        if ($record?->marche) {
                            $marches->prepend($record->marche);
                        }

                        return $marches->unique('id')->mapWithKeys(fn (Marche $m) => [
                            $m->id => $m->numero . ' — ' . $m->titre
                                . ($m->date_limite_depot ? ' (dépôt jusqu\'au ' . $m->date_limite_depot->format('d/m/Y') . ')' : ''),
                        ]);
                    })
                    ->required()->searchable()->preload()->live()
                    ->disabledOn('edit')
                    ->helperText('Seuls les marchés dont le DAO est publié accueillent des offres'),

                Forms\Components\Placeholder::make('fenetre_depot')
                    ->label('Fenêtre de dépôt')
                    ->visibleOn('create')
                    ->content(function (callable $get): string {
                        $marche = Marche::sansExercice()->find($get('marche_id'));
                        if (!$marche) {
                            return 'Choisir le marché pour voir son échéance et les offres déjà au dossier.';
                        }
                        $deja = $marche->offres()->count();

                        return sprintf(
                            'DAO publié le %s · dépôt jusqu\'au %s · %s offre(s) déjà enregistrée(s).',
                            $marche->date_publication_dao?->format('d/m/Y') ?? '—',
                            $marche->date_limite_depot?->format('d/m/Y') ?? '—',
                            $deja,
                        );
                    }),
            ])->columns(2),

            Forms\Components\Section::make('Le soumissionnaire')->schema([
                Forms\Components\Select::make('fournisseur_id')->label('Soumissionnaire')
                    ->options(function (?Offre $record) {
                        $fournisseurs = Fournisseur::query()->orderBy('raison_sociale')->get();
                        if ($record?->fournisseur) {
                            $fournisseurs->prepend($record->fournisseur);
                        }

                        return $fournisseurs->unique('id')->mapWithKeys(fn (Fournisseur $f) => [
                            $f->id => $f->code . ' — ' . $f->getNomComplet()
                                . ($f->blackliste ? '  ⛔ blacklisté' : ''),
                        ]);
                    })
                    ->required()->searchable()->preload()->live()
                    ->helperText('Un soumissionnaire blacklisté peut déposer : son offre se rejette avec motif, elle ne disparaît pas'),

                Forms\Components\Placeholder::make('identification')
                    ->label('Fiche du soumissionnaire')
                    ->content(function (callable $get): string {
                        $f = Fournisseur::find($get('fournisseur_id'));
                        if (!$f) {
                            return 'Sélectionner le soumissionnaire pour lire sa fiche : rien n\'est recopié ici.';
                        }

                        return sprintf(
                            '%s · RCCM %s · NIF %s · %s · %s',
                            $f->raison_sociale,
                            $f->rccm ?: '—',
                            $f->nif ?: '—',
                            $f->ville ?: '—',
                            $f->blackliste ? '⛔ blacklisté : ' . $f->motif_blacklist : 'non blacklisté',
                        );
                    }),
            ])->columns(2),

            Forms\Components\Section::make('L\'offre')->schema([
                Forms\Components\DatePicker::make('date_depot')
                    ->label('Date de réception')
                    ->required()->default(now()->toDateString())
                    ->maxDate(now()->toDateString())->native()->live(),
                Forms\Components\TextInput::make('montant_offert')
                    ->label('Montant offert')->numeric()->default(0)->suffix('FCFA')->live(),
                Forms\Components\TextInput::make('delai_propose')
                    ->label('Délai proposé')->numeric()->integer()->minValue(1)->suffix('mois'),

                Forms\Components\Placeholder::make('regard')
                    ->label('')
                    ->columnSpanFull()
                    ->content(function (callable $get): ?HtmlString {
                        $marche   = Marche::sansExercice()->find($get('marche_id'));
                        $date     = $get('date_depot');
                        $montant  = (float) $get('montant_offert');
                        $alertes  = [];

                        if ($marche && $marche->date_limite_depot && $date) {
                            $depot = \Illuminate\Support\Carbon::parse($date);
                            if ($depot->gt($marche->date_limite_depot)) {
                                $alertes[] = '⛔ dépôt hors délai (échéance du DAO le '
                                    . $marche->date_limite_depot->format('d/m/Y')
                                    . ') : l\'offre sera rejetée avec motif, pas validée.';
                            }
                        }
                        if ($marche && (float) $marche->montant_estime > 0 && $montant > 0) {
                            $ecart = round(($montant - (float) $marche->montant_estime)
                                / (float) $marche->montant_estime * 100);
                            $alertes[] = match (true) {
                                $ecart > 0  => "📈 {$ecart} % au-dessus du montant estimatif du marché.",
                                $ecart < 0  => '📉 ' . abs($ecart) . ' % sous le montant estimatif du marché.',
                                default     => '📊 Montant offert égal à l\'estimation du marché.',
                            };
                        }

                        return $alertes === [] ? null : new HtmlString(
                            '<div class="rounded-lg p-3 text-sm bg-amber-50 dark:bg-amber-900/30 '
                                . 'text-amber-800 dark:text-amber-200 border border-amber-300 '
                                . 'dark:border-amber-700">' . implode('<br>', $alertes) . '</div>'
                        );
                    }),
            ])->columns(3),

            Forms\Components\Section::make('La quittance de paiement')
                ->description('Le scan laisse le choix : téléverser la quittance, ou en reporter les informations. '
                    . 'Le numéro et la date sont obligatoires pour déclarer l\'offre recevable.')
                ->schema([
                    Forms\Components\TextInput::make('numero_quittance')
                        ->label('N° de quittance')->maxLength(50),
                    Forms\Components\DatePicker::make('date_quittance')
                        ->label('Date de quittance')->native(),
                    Forms\Components\TextInput::make('montant_quittance')
                        ->label('Montant versé')->numeric()->suffix('FCFA'),
                    Forms\Components\FileUpload::make('fichier_quittance')
                        ->label('Quittance (scan ou photo)')
                        ->disk('public')->directory('marches/offres')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(10240)->downloadable()->openable(),
                ])->columns(2),

            Forms\Components\Section::make('Décision')
                ->schema([
                    Forms\Components\Placeholder::make('statut_lu')
                        ->label('Statut')
                        ->content(fn (Offre $record) => Offre::statutLibelle($record->statut)
                            . ' — le statut se règle par les actions du tableau, jamais à la main.'),
                    Forms\Components\Placeholder::make('validation_lue')
                        ->label('Recevable')
                        ->content(fn (Offre $record) => $record->date_validation
                            ? $record->date_validation->format('d/m/Y H:i') . ' — ' . ($record->valideur?->name ?? '')
                            : '—'),
                    Forms\Components\Placeholder::make('rejet_lu')
                        ->label('Rejet')
                        ->content(fn (Offre $record) => $record->date_rejet
                            ? new HtmlString($record->date_rejet->format('d/m/Y H:i') . ' — '
                                . e($record->rejeteur?->name ?? '') . '<br>' . nl2br(e((string) $record->motif_rejet)))
                            : '—'),
                    Forms\Components\Textarea::make('observations')->label('Observations')
                        ->rows(2)->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn ($record) => (bool) $record),
        ]);
    }

    // =========================================================
    // TABLEAU
    // =========================================================

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_depot', 'desc')
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('Référence')
                    ->fontFamily('mono')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('marche.numero')->label('Marché')
                    ->fontFamily('mono')->sortable(),
                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')->label('Soumissionnaire')
                    ->searchable()->limit(40),
                Tables\Columns\TextColumn::make('date_depot')->label('Reçue le')
                    ->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('montant_offert')->label('Montant offert')
                    ->money('XAF')->sortable()
                    ->color(fn (Offre $record): ?string => $record->estHorsDelai() ? 'warning' : null),
                Tables\Columns\TextColumn::make('delai_propose')->label('Délai')
                    ->suffix(' mois')->placeholder('—'),
                Tables\Columns\TextColumn::make('statut')->label('Décision')->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'validee' => 'success',
                        'rejetee' => 'danger',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => Offre::statutLibelle($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('numero_quittance')->label('Quittance')
                    ->placeholder('—')
                    ->formatStateUsing(function (?string $state, Offre $record) {
                        if (!$state) {
                            return null;
                        }

                        return $state . ($record->fichier_quittance ? ' 📎' : '');
                    })
                    // Le lien existe pour ouvrir la pièce, pas pour certifier qu'une
                    // pièce existe : sans fichier, la cellule reste du texte simple.
                    ->url(fn (Offre $record) => $record->fichier_quittance
                        ? Storage::disk('public')->url($record->fichier_quittance)
                        : null, true),
                Tables\Columns\TextColumn::make('marche.date_limite_depot')->label('Échéance du DAO')
                    ->date('d/m/Y')->placeholder('—'),
            ])
            // Le paramètre doit s'appeler $query : Filament résout les closures par
            // nom, un autre nom lui fait injecter un Builder vide de modèle.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['marche', 'fournisseur']))
            ->filters([
                Tables\Filters\SelectFilter::make('marche_id')->label('Marché')
                    // Les marchés réellement présents dans la liste, et pas « ceux de
                    // l'exercice en cours » : une offre d'an dernier reste au dossier,
                    // et un DAO ouvert à cheval sur deux exercices aussi.
                    ->options(fn () => Marche::sansExercice()->whereHas('offres')
                        ->orderByDesc('numero')->pluck('numero', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('statut')->label('Décision')
                    ->options(fn () => Offre::statuts())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('fournisseur_id')->label('Soumissionnaire')
                    ->options(fn () => Fournisseur::orderBy('raison_sociale')
                        ->pluck('raison_sociale', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('hors_delai')
                    ->label('Déposées hors délai')
                    ->query(fn (Builder $query) => $query->horsDelai()),
            ])
            ->actions(self::actionsOffre())
            // Pas d'action groupée : décider de vingt offres d'un coup n'a pas de
            // sens, chacune porte une motivation que l'audit relit une par une.
            ->bulkActions([]);
    }

    // =========================================================
    // DÉCISIONS
    // =========================================================

    /**
     * Le jeu de la ligne, isolé de table() pour que la visibilité de chaque action
     * se vérifie hors écran — les trois décisions se prennent sur un statut, et un
     * bouton qui ment sur ce que le modèle refuse est aussi grave ici qu'ailleurs.
     */
    public static function actionsOffre(): array
    {
        return [
            self::actionValiderOffre(),
            self::actionRejeterOffre(),
            self::actionLeverDecision(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ];
    }

    public static function actionValiderOffre(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('valider_offre')
            ->label('Déclarer recevable')
            ->icon('heroicon-o-check-badge')->color('success')
            ->visible(fn (Offre $record) => $record->statut === 'recue' && static::canDecider())
            ->requiresConfirmation()
            ->modalHeading(fn (Offre $record) => "Déclarer l'offre recevable — {$record->numero}")
            ->modalDescription('L\'offre entre dans le classement de l\'évaluation. Le contrôle (délai de dépôt, soumissionnaire blacklisté, quittance, montant) est fait par le modèle avant d\'enregistrer.')
            ->action(function (Offre $record) {
                try {
                    $record->valider(auth()->user());
                    Notification::make()->title('✅ Offre recevable')
                        ->body("{$record->numero} — " . ($record->fournisseur?->getNomComplet() ?? ''))
                        ->success()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Décision refusée')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public static function actionRejeterOffre(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('rejeter_offre')
            ->label('Rejeter')
            ->icon('heroicon-o-x-circle')->color('danger')
            ->visible(fn (Offre $record) => $record->statut === 'recue' && static::canRejeter())
            ->modalHeading(fn (Offre $record) => "Rejeter l'offre — {$record->numero}")
            ->modalDescription('La motivation est enregistrée avec son auteur et sa date : c\'est ce que lira une contestation.')
            ->form([
                Forms\Components\Textarea::make('motif_rejet')
                    ->label('Motif du rejet')->rows(3)->required()->columnSpanFull()
                    ->placeholder('Ex. : dépôt hors délai — quittance du dossier de participation non présentée à l\'échéance.'),
            ])
            ->action(function (Offre $record, array $data) {
                try {
                    $record->rejeter(auth()->user(), trim((string) ($data['motif_rejet'] ?? '')));
                    Notification::make()->title('🚫 Offre rejetée')
                        ->body("{$record->numero} — la motivation est au dossier")
                        ->warning()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Rejet refusé')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    /**
     * Le symétrique des deux précédentes : le service défait sa décision tant que
     * le marché n'est pas attribué, et l'offre redevient décidable.
     */
    public static function actionLeverDecision(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('lever_decision')
            ->label('Lever la décision')
            ->icon('heroicon-o-arrow-uturn-left')->color('warning')
            ->visible(fn (Offre $record) => $record->statut !== 'recue' && static::canDecider())
            ->requiresConfirmation()
            ->modalHeading(fn (Offre $record) => "Lever la décision — {$record->numero}")
            ->modalDescription('L\'offre retourne en attente de décision ; la validation ou le rejet est effacé des colonnes, mais la ligne reste au journal.')
            ->action(function (Offre $record) {
                try {
                    $record->leverDecision(auth()->user());
                    Notification::make()->title('↩ Décision levée')
                        ->body("{$record->numero} est de nouveau en attente")
                        ->warning()->send();
                } catch (\Exception $e) {
                    Notification::make()->title('❌ Levée refusée')
                        ->danger()->body($e->getMessage())->persistent()->send();
                }
            });
    }

    // =========================================================
    // NAVIGATION
    // =========================================================

    public static function getNavigationBadge(): ?string
    {
        return static::canViewAny()
            ? (string) Offre::query()->enAttenteDeDecision()->count()
            : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOffres::route('/'),
            'create' => Pages\CreateOffre::route('/create'),
            'edit'   => Pages\EditOffre::route('/{record}/edit'),
        ];
    }
}
