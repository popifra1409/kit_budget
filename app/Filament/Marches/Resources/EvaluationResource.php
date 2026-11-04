<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Concerns\AutorisationParPermissions;
use App\Filament\Marches\Resources\EvaluationResource\Pages;
use App\Models\ElementEvaluationDao;
use App\Models\Evaluation;
use App\Models\Marche;
use App\Models\Offre;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Grille d'évaluation des offres — écran du service des marchés (étape D).
 *
 * Le schéma du dossier d'appel d'offres note trois volets — pièce administrative,
 * pièce financière, description technique — et chaque volet se décompose en étapes
 * tenue au paramétrage, chacune avec son barème. Cette écran est la saisie de la
 * note : une offre, une étape, un nombre. Ni statut, ni case à cocher « offre
 * évaluée » : une offre est notée quand toutes les étapes actives ont leur nombre,
 * et le modèle le dit — `Offre::estEvaluee()` — sans qu'aucune colonne ne puisse
 * le contredire.
 *
 * La fenêtre est celle du marché : ouverte à l'ouverture des plis, fermée à
 * l'attribution. Un marché attribué a rendu son rapport ; y corriger une note
 * après coup écrirait l'après sur le devant, et le classement que l'attribution a
 * retenu ne se relirait plus. Le chemin d'un désaccord est l'action « Lever
 * l'attribution » du marché, pas ce formulaire.
 *
 * `force_update_evaluation` n'existe pas, délibérément : la correction d'une note
 * d'un dossier déjà attribué se paie par la levée de la décision, devant l'auteur
 * de cette décision et sa ligne au journal — jamais par un droit de réécriture
 * silencieuse.
 */
class EvaluationResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = Evaluation::class;
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationGroup = 'Évaluation & Attribution';
    protected static ?string $navigationLabel = "Grille d'évaluation";
    protected static ?string $modelLabel = 'note d\'évaluation';
    protected static ?string $pluralModelLabel = 'notes d\'évaluation';
    protected static ?int $navigationSort = 1;

    protected static function prefixePermission(): string
    {
        return 'evaluation';
    }

    // =========================================================
    // AUTORISATION
    // =========================================================

    /**
     * Noter n'est possible que dans la fenêtre du marché. La permission ne fait
     * qu'ouvrir la porte : c'est l'état du dossier qui décide si la note est encore
     * saisissable, et le modèle le répète à l'écriture.
     */
    public static function canEdit($record): bool
    {
        return static::autorise('update') && $record->fenetreOuverte();
    }

    public static function canDelete($record): bool
    {
        return static::autorise('delete') && $record->fenetreOuverte();
    }

    // =========================================================
    // FORMULAIRE
    // =========================================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Ce qui est noté')->schema([
                Forms\Components\Select::make('offre_id')->label('Offre')
                    // L'offre porte son marché : choisir l'une règle l'autre, et la
                    // liste des offres est celle de ce marché — pas du premier marché
                    // ouvert venu.
                    ->options(function (?Evaluation $record) {
                        $offres = Offre::query()
                            ->whereIn('marche_id', self::marchesNotables())
                            ->with(['marche', 'fournisseur', 'evaluations'])
                            ->orderBy('marche_id')
                            ->orderBy('numero')
                            ->get();

                        if ($record?->offre) {
                            $offres->prepend($record->offre);
                        }

                        return $offres->unique('id')->mapWithKeys(fn (Offre $o) => [
                            $o->id => $o->numero . ' — ' . ($o->fournisseur?->raison_sociale ?? 'soumissionnaire inconnu')
                                . ' · ' . self::statutCourt($o)
                                . ' · ' . $o->marche?->numero
                                . ($o->fournisseur?->blackliste ? ' ⛔' : ''),
                        ]);
                    })
                    ->required()->searchable()->preload()->live()->disabledOn('edit')
                    ->helperText('Les offres des marchés dont les plis sont ouverts. Une offre rejetée se note '
                        . 'aussi : sa note reste au dossier, elle n\'entre pas dans le classement.'),

                Forms\Components\Select::make('element_evaluation_dao_id')
                    ->label('Étape du DAO notée')
                    ->options(fn (?Evaluation $record) => static::optionsEtapes($record))
                    ->required()->searchable()->preload()->live()->disabledOn('edit')
                    ->helperText('Lignes actives du paramétrage, dans l\'ordre du schéma — le barème est celui de l\'étape'),

                Forms\Components\TextInput::make('note')
                    ->label('Note')->numeric()->required()->minValue(0)
                    ->helperText(fn (callable $get) => static::aideBareme($get)),

                Forms\Components\Textarea::make('commentaire')
                    ->label('Ce que la note motive')
                    ->rows(2)->columnSpanFull()
                    ->placeholder('Ex. : RCCM présenté mais périmé au jour du dépôt — 8/20.'),

                Forms\Components\Placeholder::make('grille_offre')
                    ->label('Grille de cette offre')
                    ->columnSpanFull()
                    ->content(fn (callable $get, ?Evaluation $record): ?HtmlString => static::grilleHtml($get, $record)),

                Forms\Components\Placeholder::make('fenetre')
                    ->label('Fenêtre de notation')
                    ->columnSpanFull()
                    ->content(fn (callable $get, ?Evaluation $record): HtmlString => static::fenetreHtml($get, $record)),
            ])->columns(2),

            Forms\Components\Section::make('La main qui note')
                ->schema([
                    Forms\Components\Placeholder::make('evaluateur_lu')->label('Évaluateur')
                        ->content(fn (Evaluation $record) => $record->evaluateur?->name ?? '—'),
                    Forms\Components\Placeholder::make('date_lue')->label('Note portée le')
                        ->content(fn (Evaluation $record) => $record->date_evaluation?->format('d/m/Y H:i') ?? '—'),
                    Forms\Components\Placeholder::make('marche_lu')->label('Marché')
                        ->content(fn (Evaluation $record) => $record->offre?->marche
                            ? $record->offre->marche->numero . ' — ' . Marche::statutLibelle($record->offre->marche->statut)
                            : '—'),
                    Forms\Components\Placeholder::make('offre_lue')->label('Offre')
                        ->content(fn (Evaluation $record) => $record->offre?->numero . ' — '
                            . ($record->offre?->fournisseur?->raison_sociale ?? '')),
                ])
                ->columns(2)
                ->visible(fn ($record) => (bool) $record),
        ]);
    }

    /**
     * Les marchés dont la fenêtre de notation est ouverte (plis ouverts), sous forme
     * d'ids : un whereIn veut une liste de valeurs, pas un Builder qui sélectionnerait
     * toutes les colonnes.
     *
     * sansExercice() : la notation d'un DAO suivi à cheval sur deux exercices ne
     * s'interrompt pas à la bascule de l'année (voir Offre::marche()).
     */
    protected static function marchesNotables(): array
    {
        return Marche::sansExercice()->where('statut', 'en_evaluation')
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Les étapes du référentiel, avec leur volet et leur barème dans le libellé.
     * Une étape désactivée reste proposée quand sa note est déjà en main : la
     * corriger ne doit pas obliger à la réactiver pour sauver le dossier.
     */
    public static function optionsEtapes(?Evaluation $record): array
    {
        $etapes  = ElementEvaluationDao::grille();
        $element = $record?->element;

        if ($element && !$etapes->contains('id', $element->id)) {
            $etapes->prepend($element);
        }

        $plusieursVolets = $etapes->unique('famille')->count() > 1;

        return $etapes->mapWithKeys(fn (ElementEvaluationDao $e) => [
            $e->id => ($plusieursVolets
                    ? (config('marches.familles_evaluation')[$e->famille] ?? $e->famille) . ' — '
                    : '')
                . $e->libelle . '  (barème ' . (float) $e->bareme_max . ')',
        ])->all();
    }

    protected static function aideBareme(callable $get): string
    {
        $element = ElementEvaluationDao::find($get('element_evaluation_dao_id'));

        return $element
            ? 'Note maximale de cette étape : ' . (float) $element->bareme_max . '. Au-delà, la note est refusée.'
            : "Choisir l'étape pour connaître son barème.";
    }

    protected static function statutCourt(Offre $offre): string
    {
        return match ($offre->statut) {
            'validee' => 'recevable',
            'rejetee' => 'rejetée',
            default   => 'en attente de décision',
        };
    }

    /**
     * La grille de l'offre choisie : ce qui est déjà noté, ce qui manque. Le service
     * voit là ce que l'attribution exigera, avant de saisir la ligne suivante — c'est
     * l'écran qui le dit, pas une colonne « évaluée » qu'il faudrait cocher.
     */
    protected static function grilleHtml(callable $get, ?Evaluation $record): ?HtmlString
    {
        $offre = Offre::find($get('offre_id'));
        if (!$offre) {
            return null;
        }

        $notees = $offre->evaluations()->get()->keyBy('element_evaluation_dao_id');

        $lignes = ElementEvaluationDao::grille()->map(function (ElementEvaluationDao $e) use ($notees, $record) {
            $note = $notees->get($e->id);
            $cellule = $note
                ? (float) $note->note . ' / ' . (float) $e->bareme_max
                : '<span style="color:#b45309">non notée</span>';

            return '<tr><td>' . e($e->libelle) . '</td><td>'
                . e(config('marches.familles_evaluation')[$e->famille] ?? $e->famille) . '</td><td>'
                . (float) $e->bareme_max . '</td><td>' . $cellule . '</td></tr>';
        })->all();

        $actuelle = ElementEvaluationDao::find($get('element_evaluation_dao_id'));

        return new HtmlString(
            '<div class="text-sm">'
                . '<div class="mb-2">Offre ' . e($offre->numero) . ' — '
                . number_format($offre->totalEvalue(), 2, ',', ' ') . ' sur '
                . number_format($offre->baremeTotal(), 2, ',', ' ')
                . ($offre->estEvaluee() ? ' · grille complète' : ' · grille incomplète')
                . '</div>'
                . '<table class="w-full text-left"><thead><tr>'
                . '<th>Étape</th><th>Volet</th><th>Barème</th><th>Note</th>'
                . '</tr></thead><tbody>' . implode('', $lignes) . '</tbody></table>'
                . ($actuelle ? '' : '<div class="mt-1 text-amber-700">'
                    . 'Choisir l\'étape pour voir sa place dans la grille.</div>')
                . '</div>'
        );
    }

    /**
     * La fenêtre, telle que le modèle va la juger. Écrite sur le marché choisi, donc
     * fausse si l'offre vient d'un marché déjà attribué — et l'enregistrement refusera
     * avec le même motif, ce qui est le but : l'écran ne promet rien que la base tienne.
     */
    protected static function fenetreHtml(callable $get, ?Evaluation $record): HtmlString
    {
        $marche = $record?->offre?->marche ?? Offre::find($get('offre_id'))?->marche;

        if (!$marche) {
            return new HtmlString('<div class="text-sm text-gray-500">Choisir l\'offre pour lire la fenêtre de son marché.</div>');
        }

        if ($marche->peutNoterDesOffres()) {
            return new HtmlString('<div class="rounded-lg p-3 text-sm bg-emerald-50 dark:bg-emerald-900/30 '
                . 'text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700">'
                . '✅ ' . e($marche->numero) . ' — plis ouverts : la notation est ouverte.</div>');
        }

        return new HtmlString('<div class="rounded-lg p-3 text-sm bg-amber-50 dark:bg-amber-900/30 '
            . 'text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700">'
            . '⛔ ' . e($marche->numero) . ' est « ' . e(Marche::statutLibelle($marche->statut))
            . ' » : les notes ne se portent qu\'après l\'ouverture des plis et se figent à l\'attribution. '
            . 'Sur un marché attribué, levez l\'attribution d\'abord.</div>');
    }

    // =========================================================
    // TABLEAU
    // =========================================================

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_evaluation', 'desc')
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('offre.numero')->label('Offre')
                    ->fontFamily('mono')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('offre.marche.numero')->label('Marché')
                    ->fontFamily('mono')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('offre.fournisseur.raison_sociale')
                    ->label('Soumissionnaire')->searchable()->limit(35),
                Tables\Columns\TextColumn::make('element.famille')->label('Volet')
                    ->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state) => config('marches.familles_evaluation')[$state] ?? $state)
                    ->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('element.libelle')->label('Étape')
                    ->weight('bold')->searchable()->limit(40)->placeholder('—'),
                Tables\Columns\TextColumn::make('note')->label('Note')->sortable()
                    ->formatStateUsing(fn ($state, Evaluation $record) => (float) $state . ' / ' . (float) $record->bareme())
                    ->color(fn (Evaluation $record): ?string => $record->horsBareme() ? 'danger' : null)
                    ->description(fn (Evaluation $record) => filled($record->commentaire)
                        ? Str::limit($record->commentaire, 60)
                        : null),
                Tables\Columns\TextColumn::make('evaluateur.name')->label('Évaluateur')
                    ->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('date_evaluation')->label('Portée le')
                    ->dateTime('d/m/Y H:i')->sortable(),
            ])
            // Le paramètre doit s'appeler $query : Filament résout les closures par
            // nom, un autre nom lui fait injecter un Builder vide de modèle.
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'offre.marche', 'offre.fournisseur', 'element', 'evaluateur',
            ]))
            ->filters([
                // Une note n'a pas de colonne marche_id : le marché se lit par son
                // offre, et le filtre doit le dire à la requête, sinon Filament
                // écrirait un where('marche_id') sur evaluations et la liste tomberait.
                Tables\Filters\SelectFilter::make('marche_id')->label('Marché')
                    ->options(fn () => Marche::sansExercice()->whereHas('offres.evaluations')
                        ->orderByDesc('numero')->pluck('numero', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->surMarche((int) $data['value'])
                        : $query),
                Tables\Filters\SelectFilter::make('offre_id')->label('Offre')
                    ->options(fn () => Offre::query()->whereHas('evaluations')
                        ->orderBy('numero')->pluck('numero', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('famille')->label('Volet')
                    ->options(fn () => config('marches.familles_evaluation'))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->parFamille($data['value'])
                        : $query),
                Tables\Filters\SelectFilter::make('element_evaluation_dao_id')->label('Étape')
                    ->options(fn () => ElementEvaluationDao::orderBy('ordre')->pluck('libelle', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            // Pas d'action groupée : une note se porte ligne par ligne, avec le
            // commentaire qui l'explique au rapport.
            ->bulkActions([]);
    }

    // =========================================================
    // NAVIGATION
    // =========================================================

    public static function getNavigationBadge(): ?string
    {
        // Le travail en cours : les marchés dont l'évaluation est ouverte.
        return static::canViewAny()
            ? (string) Marche::query()->where('statut', 'en_evaluation')->count()
            : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEvaluations::route('/'),
            'create' => Pages\CreateEvaluation::route('/create'),
            'edit'   => Pages\EditEvaluation::route('/{record}/edit'),
        ];
    }
}
