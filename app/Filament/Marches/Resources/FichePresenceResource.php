<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Concerns\AutorisationParPermissions;
use App\Filament\Marches\Resources\FichePresenceResource\Pages;
use App\Models\Commission;
use App\Models\FichePresence;
use App\Models\Marche;
use App\Services\FichePresencePdfService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Fiches de présence — écran de la séance (étape E du module /marches).
 *
 * La liste des séances constatées et leur émargement. L'écran ne dresse rien : une
 * fiche de présence est la trace d'une réunion tenue pour un marché, et c'est le
 * marché qui sait si cette réunion a une matière — le dressage se fait par l'action
 * « Fiche de présence » sur la fiche du marché. Un écran de création autonome
 * laisserait dater une séance pour un marché dont les plis n'ont pas été ouverts.
 *
 * Ce que l'agent trouve ici, dans cet ordre :
 *
 * - la fiche elle-même, retouchable tant que le marché vit encore (`canEdit`) : une
 *   date mal relue ou un objet tronqué restent rattrapables ;
 * - ses lignes, dans le gestionnaire de la relation : présent ou absent, motif de
 *   l'absence, et le visa que chaque présent appose ;
 * - le PDF de la fiche, à imprimer avant la séance et à verser scanné une fois signée.
 *
 * Le visa n'est pas une colonne de l'en-tête : il se pose ligne par ligne, par
 * l'action « Viser », et ne se retouche pas dans le formulaire de la ligne. C'est la
 * seule manière de garder au journal la main qui signe et celle qui défait.
 */
class FichePresenceResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = FichePresence::class;
    // Le pluriel mécanique écrirait « fiche-presences ». La fiche se lit au féminin.
    protected static ?string $slug = 'fiches-presence';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    // Le groupe « Évaluation & Attribution » est celui de la séance : la commission
    // se réunit pour ouvrir les plis et juger les offres, pas pour commander le
    // service — les ordres de service restent en « Suivi d'Exécution ».
    protected static ?string $navigationGroup = 'Évaluation & Attribution';
    protected static ?string $navigationLabel = 'Fiches de présence';
    protected static ?string $modelLabel = 'fiche de présence';
    protected static ?string $pluralModelLabel = 'fiches de présence';
    protected static ?string $recordTitleAttribute = 'numero';
    protected static ?int $navigationSort = 2;

    protected static function prefixePermission(): string
    {
        return 'fiche_presence';
    }

    // =========================================================
    // AUTORISATION
    // =========================================================

    /**
     * Pas de saisie libre : dresser une fiche de présence est un acte sur le marché
     * (Marche::dresserFichePresence), et c'est là qu'il se prend.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Retouchable tant que le marché vit encore — la même fenêtre que l'ordre de
     * service. Passé la clôture, la fiche a servi : elle ne se réécrit plus que par
     * la main de l'administration (force_update_fiche_presence).
     */
    public static function canEdit($record): bool
    {
        $marche = $record->marche;

        return auth()->user()?->can('force_update_fiche_presence')
            || (auth()->user()?->can('update_fiche_presence') && $marche && !$marche->estClo());
    }

    /**
     * Le bouton reste visible sur la fiche que le modèle refuse : c'est à l'écran que
     * l'agent lit qu'un visa empêche le retrait, et « Lever le visa » est le chemin
     * qu'on lui propose alors.
     */
    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_fiche_presence') ?? false;
    }

    /**
     * Imprimable : la fiche se dresse depuis le marché, sous la permission de la
     * ligne nouvelle — pas de permission doublon, comme pour l'ordre de service.
     */
    public static function canDresser(): bool
    {
        return auth()->user()?->can('create_fiche_presence') ?? false;
    }

    public static function canTelecharger(): bool
    {
        return auth()->user()?->can('telecharger_fiche_presence') ?? false;
    }

    // =========================================================
    // FORMULAIRE (retouche de la fiche)
    // =========================================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('La séance')->schema([
                Forms\Components\TextInput::make('numero')->label('Référence')
                    ->disabled()->extraInputAttributes(['class' => 'font-mono']),
                Forms\Components\Placeholder::make('etablie_lue')->label('Dressée par')
                    ->content(fn (FichePresence $record): string => $record->etabliePar?->name ?? '—'),

                // Le marché et la commission ne se reprennent pas : ils disent de quelle
                // séance il s'agit. Les déplacer attacherait des signatures à une autre
                // réunion — le modèle le refuse, le champ le montre en lecture.
                Forms\Components\Select::make('marche_id')->label('Marché')
                    ->options(function (?FichePresence $record) {
                        $marches = Marche::sansExercice()
                            ->whereIn('statut', FichePresence::STATUTS_SEANCE)
                            ->orderByDesc('numero')->get();
                        if ($record?->marche) {
                            $marches->prepend($record->marche);
                        }

                        return $marches->unique('id')->mapWithKeys(fn (Marche $m) => [
                            $m->id => $m->numero . ' — ' . $m->titre,
                        ]);
                    })
                    ->disabled()->searchable(),
                Forms\Components\Select::make('commission_id')->label('Commission réunie')
                    ->options(fn () => Commission::orderBy('libelle')->pluck('libelle', 'id'))
                    ->disabled()->searchable(),

                Forms\Components\DatePicker::make('date_seance')
                    ->label('Jour de la séance')->required()->native()->live()
                    ->maxDate(now())
                    ->helperText('Ce jour-là, la commission s\'est rassemblée. Une fiche par jour et par marché.'),
                Forms\Components\TextInput::make('lieu')->label('Lieu')
                    ->maxLength(255)->placeholder('Salle des délibérations'),

                Forms\Components\Textarea::make('objet')->label('Objet de la séance')
                    ->required()->rows(2)->columnSpanFull(),

                Forms\Components\Placeholder::make('regard')
                    ->label('')
                    ->columnSpanFull()
                    ->content(function (callable $get, FichePresence $record): ?HtmlString {
                        $marche  = $record->marche;
                        $alertes = [];

                        // Les cases encore vides se comptent ici, pas sur le papier :
                        // c'est ce qui reste à faire avant de considérer la séance close.
                        $attentes = $record->attentes();
                        if ($attentes !== []) {
                            $alertes[] = ['bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 '
                                . 'border-amber-300 dark:border-amber-700',
                                '📝 Il reste à porter à la fiche : ' . implode(' ; ', $attentes) . '.'];
                        }
                        if ($record->membresVises()->exists()) {
                            $alertes[] = ['bg-gray-50 dark:bg-gray-900/30 text-gray-700 dark:text-gray-300 '
                                . 'border-gray-300 dark:border-gray-700',
                                '👁️ ' . $record->membresVises()->count() . ' visa(s) déjà posé(s) : une ligne '
                                    . 'visée ne se retourne plus — le visa se lève d\'abord, ligne par ligne.'];
                        }
                        if ($marche && $marche->estClo()) {
                            $alertes[] = ['bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 '
                                . 'border-red-300 dark:border-red-700',
                                '🔒 Le marché est « ' . Marche::statutLibelle($marche->statut)
                                    . ' » : cette fiche a servi, elle ne se retouche plus qu\'avec la '
                                    . 'permission de l\'administration.'];
                        }
                        if ($marche && $marche->date_ouverture_plis && $get('date_seance')
                            && \Illuminate\Support\Carbon::parse($get('date_seance'))
                                ->lt($marche->date_ouverture_plis->toDateString())) {
                            $alertes[] = ['bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 '
                                . 'border-red-300 dark:border-red-700',
                                '⛔ Antérieure à l\'ouverture des plis ('
                                    . $marche->date_ouverture_plis->format('d/m/Y')
                                    . ') : la commission n\'avait rien à examiner ce jour-là.'];
                        }

                        if ($alertes === []) {
                            return null;
                        }

                        $boites = array_map(
                            fn ($alerte) => new HtmlString(
                                '<div class="rounded-lg p-3 text-sm border ' . $alerte[0] . '">'
                                    . $alerte[1] . '</div>'
                            ),
                            $alertes
                        );

                        return new HtmlString(implode('', $boites));
                    }),
            ])->columns(2),

            Forms\Components\Section::make('Le papier')->schema([
                Forms\Components\FileUpload::make('fichier')
                    ->label('Fiche émargée et visée (scan)')
                    ->disk('public')->directory('marches/fiches-presence')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(10240)->downloadable()->openable()
                    ->helperText('La fiche imprimée, signée par les présents, versée au dossier.'),
                Forms\Components\Textarea::make('observations')->label('Observations')
                    ->rows(2)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    // =========================================================
    // TABLEAU
    // =========================================================

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_seance', 'desc')
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('Référence')
                    ->fontFamily('mono')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('marche.numero')->label('Marché')
                    ->fontFamily('mono')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('date_seance')->label('Séance du')
                    ->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('commission.libelle')->label('Commission')
                    ->searchable()->limit(40),
                Tables\Columns\TextColumn::make('objet')->label('De quoi')
                    ->wrap()->limit(70)->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('membres_count')->label('Membres')
                    ->numeric()->sortable(),
                Tables\Columns\TextColumn::make('presents_count')->label('Présents')
                    ->numeric()->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('visas_count')->label('Visas')
                    ->numeric()->badge()
                    // Le visa qui manque est ce que le service attend : tant qu'un
                    // présent n'a pas visé, la fiche n'est pas signée.
                    ->color(fn (FichePresence $record): string => $record->visasManquants() > 0
                        ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('marche.statut')->label('Le marché')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Marche::statutLibelle($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'en_execution' => 'success',
                        'homologue'    => 'info',
                        'en_evaluation' => 'warning',
                        'attribue'     => 'primary',
                        'termine'      => 'gray',
                        'resilie', 'annule' => 'danger',
                        default        => 'gray',
                    }),
                Tables\Columns\TextColumn::make('fichier')->label('Pièce')
                    ->formatStateUsing(fn (?string $state) => $state ? '📎' : '—')
                    ->url(fn (FichePresence $record) => $record->fichier
                        ? Storage::disk('public')->url($record->fichier)
                        : null, true),
            ])
            // Le paramètre doit s'appeler $query : Filament résout les closures par
            // nom, un autre nom lui injecte un Builder vide de modèle.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['marche', 'commission'])
                ->withCount([
                    'membres',
                    'membres as presents_count' => fn (Builder $q) => $q->where('present', true),
                    'membres as visas_count'    => fn (Builder $q) => $q->where('visa', true),
                ]))
            ->filters([
                Tables\Filters\SelectFilter::make('marche_id')->label('Marché')
                    // Les marchés qui ont réellement tenu une séance, hors portée
                    // d'exercice : une commission réunie en janvier siège sur le DAO
                    // de décembre.
                    ->options(fn () => Marche::sansExercice()->whereHas('fichesPresence')
                        ->orderByDesc('numero')->pluck('numero', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('commission_id')->label('Commission')
                    // Le référentiel complet : une commission retirée du paramétrage
                    // laisse ses fiches au dossier, et elles doivent rester filtrables.
                    ->options(fn () => Commission::orderBy('libelle')->pluck('libelle', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('en_attente')
                    ->label('Fiches qui attendent un émargement ou un visa')
                    ->query(fn (Builder $query) => $query->enAttente()),
            ])
            ->actions(self::actionsFichePresence())
            // Pas d'action groupée : retirer une fiche de présence est un acte que
            // l'on assume ligne par ligne — les visas qu'elle porte se lèvent d'abord.
            ->bulkActions([]);
    }

    // =========================================================
    // ACTIONS DE LA LIGNE
    // =========================================================

    /**
     * Le jeu de la ligne, isolé de table() pour que le verrou du visa et le
     * téléchargement du PDF se vérifient hors écran.
     */
    public static function actionsFichePresence(): array
    {
        return [
            Tables\Actions\Action::make('telecharger_fiche_presence')
                ->label('Fiche PDF')
                ->icon('heroicon-o-document-arrow-down')->color('gray')
                ->visible(fn () => static::canTelecharger())
                ->action(function (FichePresence $record) {
                    // visible() ne protège pas : l'action est atteignable par le
                    // wire:id de la ligne. La permission se rejoue ici.
                    abort_unless(static::canTelecharger(), 403);

                    return response()->streamDownload(
                        fn () => print((new FichePresencePdfService)->genererPdf($record)->output()),
                        static::nomDuFichier($record)
                    );
                }),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()
                ->before(fn (FichePresence $record) => static::refuserRetrait($record)),
        ];
    }

    /**
     * Le verrou de la fiche signée : une fiche qui porte des visas ne se retire pas.
     * Le modèle le refuse déjà (hook deleting), et la clé étrangère ne parlerait que
     * sur une suppression dure — ce refus à l'écran existe pour que le service lise
     * pourquoi, et sur quel chemin aller.
     */
    public static function refuserRetrait(FichePresence $record): void
    {
        if (!$record->aUnVisa()) {
            return;
        }

        Notification::make()
            ->title('❌ Retrait refusé')
            ->body(sprintf(
                '« %s » porte %d visa(s) : une fiche visée est le papier sur lequel la commission a signé, '
                    . 'elle ne se retire pas. Ce qui se corrige encore, tant que le marché n\'est pas clos, '
                    . 'c\'est la date, le lieu ou l\'objet — par « Modifier », après levée des visas.',
                $record->numero,
                $record->membresVises()->count(),
            ))
            ->danger()->persistent()->send();

        throw new Halt;
    }

    public static function nomDuFichier(FichePresence $record): string
    {
        return 'fiche_presence_' . ($record->marche?->numero ?? 'marche') . '_'
            . $record->date_seance->format('Ymd') . '.pdf';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFichesPresence::route('/'),
            'edit'  => Pages\EditFichePresence::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        if (!static::canViewAny()) {
            return null;
        }

        $attente = FichePresence::query()->enAttente()->count();

        return $attente > 0 ? (string) $attente : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
