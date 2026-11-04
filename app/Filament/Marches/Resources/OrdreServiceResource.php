<?php

namespace App\Filament\Marches\Resources;

use App\Filament\Concerns\AutorisationParPermissions;
use App\Filament\Marches\Resources\OrdreServiceResource\Pages;
use App\Models\Marche;
use App\Models\OrdreService;
use App\Models\TypeOrdreService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Ordres de service — écran d'exécution du module /marches (étape E).
 *
 * La liste des actes émis, et leur retouche : le démarrage, les arrêts, les
 * reprises, avec la date portée sur le document et la pièce jointe. L'écran ne
 * crée rien — émettre un ordre de service se fait sur le marché lui-même, par
 * l'action « Émettre un ordre de service », parce que l'acte commande un marché et
 * que c'est son cycle qui décide si la commande est recevable. Une fiche d'émission
 * autonome laisserait choisir le marché dans la liste de tous les marchés, sans
 * dire lequel est prêt à obéir.
 *
 * Deux fenêtres, et elles ne se recouvrent pas :
 *
 * - le contenu d'un ordre de service se corrige tant que le marché n'est pas clos
 *   (`canEdit`) : une date mal relue ou un objet tronqué restent rattrapables ;
 * - l'ordre de service de démarrage, lui, ne se retire pas (`refuserRetrait`) : il
 *   est le point de départ du délai d'exécution, et son absence laisserait le marché
 *   en exécution sans acte qui l'ouvre.
 */
class OrdreServiceResource extends Resource
{
    use AutorisationParPermissions;

    protected static ?string $model = OrdreService::class;
    // Le pluriel mécanique de Filament écrirait « ordre-services ». L'URL se lit dans
    // les comptes rendus comme le menu : c'est le français qui tient la plume.
    protected static ?string $slug = 'ordres-service';
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    // Le groupe « Suivi d'Exécution » est celui que la sidebar du module annonce depuis
    // le scan ; l'ordre de service en est la première ligne vivante. Un groupe neuf
    // n'aurait pas été affiché à la place prévue, et les étapes F (engagements,
    // tranches) s'y rattachent déjà.
    protected static ?string $navigationGroup = 'Suivi d\'Exécution';
    protected static ?string $navigationLabel = 'Ordres de service';
    protected static ?string $modelLabel = 'ordre de service';
    protected static ?string $pluralModelLabel = 'ordres de service';
    protected static ?string $recordTitleAttribute = 'numero';
    protected static ?int $navigationSort = 1;

    protected static function prefixePermission(): string
    {
        return 'ordre_service';
    }

    // =========================================================
    // AUTORISATION
    // =========================================================

    /**
     * Il n'y a pas de saisie libre d'un ordre de service : l'émission est un acte
     * sur le marché (Marche::emettreOrdreService), et c'est là qu'elle se prend.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Retoucheable tant que le marché vit encore. Passé « terminé », « résilié » ou
     * « annulé », l'ordre de service n'est plus une pièce que l'on ajuste : il a
     * produit ses effets, et sa correction passe par la main de l'administration
     * (force_update_ordre_service), pas par celle du service qui l'a émis.
     */
    public static function canEdit($record): bool
    {
        $marche = $record->marche;

        return auth()->user()?->can('force_update_ordre_service')
            || (auth()->user()?->can('update_ordre_service') && $marche && !$marche->estClo());
    }

    /**
     * Le bouton reste visible même sur la ligne que le modèle refuse : c'est à
     * l'écran que l'agent lit pourquoi elle ne part pas, et la désactivation du type
     * ou la résiliation du marché sont les chemins qu'on lui propose alors.
     */
    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_ordre_service') ?? false;
    }

    /**
     * Émettable : la permission de la ligne nouvelle, `create_ordre_service`, et pas
     * une speciale de plus — émettre un ordre de service insère une ligne dans
     * `ordres_service`, c'est exactement ce que ce mot décrit. Elle s'exerce sur le
     * marché, faute d'écran d'émission ici.
     */
    public static function canEmettre(): bool
    {
        return auth()->user()?->can('create_ordre_service') ?? false;
    }

    // =========================================================
    // FORMULAIRE (retouche)
    // =========================================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('L\'acte émis')->schema([
                Forms\Components\TextInput::make('numero')->label('Référence')
                    // `fontFamily()` est une méthode de colonne de table, pas de champ :
                    // sur un TextInput elle lève une BadMethodCallException au rendu.
                    ->disabled()->extraInputAttributes(['class' => 'font-mono']),
                Forms\Components\Placeholder::make('emis_lu')->label('Émis par')
                    ->content(fn (OrdreService $record): string => $record->emetteur?->name ?? '—'),

                // Le marché et son type ne se reprennent plus : le premier porte le
                // cycle que l'acte a fait avancer, le second porte la règle de cet
                // effet (demarre_lexecution). Les changer serait déplacer
                // rétroactivement une exécution déjà engagée.
                Forms\Components\Select::make('marche_id')->label('Marché')
                    // Les marchés où l'acte serait encore recevable, plus celui de la
                    // ligne — même clos, même hors de la fenêtre : ce champ existe pour
                    // lire de quoi il s'agit, pas pour proposer un choix.
                    ->options(function (?OrdreService $record) {
                        $marches = Marche::sansExercice()
                            ->whereIn('statut', ['homologue', 'en_execution'])
                            ->orderByDesc('numero')->get();
                        if ($record?->marche) {
                            $marches->prepend($record->marche);
                        }

                        return $marches->unique('id')->mapWithKeys(fn (Marche $m) => [
                            $m->id => $m->numero . ' — ' . $m->titre,
                        ]);
                    })
                    ->disabled()->searchable(),
                Forms\Components\Select::make('type_ordre_service_id')
                    ->label("Type d'ordre de service")
                    ->options(fn () => TypeOrdreService::orderBy('ordre')->pluck('libelle', 'id'))
                    ->disabled()->searchable(),

                Forms\Components\DatePicker::make('date_emission')
                    ->label('Date portée sur le document')->required()->native()->live(),
                Forms\Components\Textarea::make('objet')->label('Service commandé')
                    ->required()->rows(3)->columnSpanFull(),

                Forms\Components\Placeholder::make('regard')
                    ->label('')
                    ->columnSpanFull()
                    ->content(function (callable $get, OrdreService $record): ?HtmlString {
                        $marche  = $record->marche;
                        $alertes = [];

                        if ($marche && $marche->estClo()) {
                            $alertes[] = '🔒 Le marché est « ' . Marche::statutLibelle($marche->statut)
                                . ' » : cet ordre de service a produit ses effets, il ne se retouche plus '
                                . 'qu\'avec la permission de l\'administration.';
                        }
                        if ($marche && $marche->date_homologation && $get('date_emission')) {
                            $date = Carbon::parse($get('date_emission'));
                            if ($date->lt($marche->date_homologation->toDateString())) {
                                $alertes[] = '⛔ Date antérieure à l\'homologation du marché ('
                                    . $marche->date_homologation->format('d/m/Y')
                                    . ') : un ordre de service exécute une attribution, il ne la précède pas.';
                            }
                        }
                        if ($record->demarreLexecution()) {
                            $alertes[] = '▶️ Cet ordre de service est ce qui a ouvert l\'exécution du marché '
                                . '(type « ' . ($record->type?->libelle ?? '—') . ' ») : il ne se retire pas.';
                        }

                        return $alertes === [] ? null : new HtmlString(
                            '<div class="rounded-lg p-3 text-sm bg-amber-50 dark:bg-amber-900/30 '
                                . 'text-amber-800 dark:text-amber-200 border border-amber-300 '
                                . 'dark:border-amber-700">' . implode('<br>', $alertes) . '</div>'
                        );
                    }),
            ])->columns(2),

            Forms\Components\Section::make('La pièce')->schema([
                Forms\Components\FileUpload::make('fichier_os')
                    ->label('Ordre de service (scan ou photo)')
                    ->disk('public')->directory('marches/ordres-service')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(10240)->downloadable()->openable(),
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
            ->defaultSort('date_emission', 'desc')
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('Référence')
                    ->fontFamily('mono')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('marche.numero')->label('Marché')
                    ->fontFamily('mono')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type.libelle')->label('Type')
                    ->searchable()->limit(40)
                    // Le type qui ouvre l'exécution se lit dans la liste, sinon la
                    // colonne « Émis par » est la seule trace du geste.
                    ->icon(fn (OrdreService $record): ?string => $record->demarreLexecution()
                        ? 'heroicon-o-play' : null)
                    ->color(fn (OrdreService $record): ?string => $record->demarreLexecution()
                        ? 'success' : null),
                Tables\Columns\TextColumn::make('date_emission')->label('Notifié le')
                    ->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('objet')->label('Service commandé')
                    ->wrap()->limit(90)->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('marche.statut')->label('Le marché')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Marche::statutLibelle($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'en_execution' => 'success',
                        'homologue'    => 'info',
                        'termine'      => 'gray',
                        'resilie', 'annule' => 'danger',
                        default        => 'gray',
                    }),
                Tables\Columns\TextColumn::make('emetteur.name')->label('Émis par')
                    ->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('fichier_os')->label('Pièce')
                    ->formatStateUsing(fn (?string $state) => $state ? '📎' : '—')
                    ->url(fn (OrdreService $record) => $record->fichier_os
                        ? Storage::disk('public')->url($record->fichier_os)
                        : null, true),
            ])
            // Le paramètre doit s'appeler $query : Filament résout les closures par
            // nom, un autre nom lui fait injecter un Builder vide de modèle.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['marche', 'type', 'emetteur']))
            ->filters([
                Tables\Filters\SelectFilter::make('marche_id')->label('Marché')
                    // Les marchés qui ont réellement reçu un ordre de service, hors
                    // portée d'exercice : la chronologie d'une exécution ne se coupe
                    // pas au 1er janvier.
                    ->options(fn () => Marche::sansExercice()->whereHas('ordresService')
                        ->orderByDesc('numero')->pluck('numero', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('type_ordre_service_id')
                    ->label("Type d'ordre de service")
                    // Le référentiel complet, actif ou non : un ordre émis sous un type
                    // depuis désactivé reste dans la liste et doit rester filtrable.
                    ->options(fn () => TypeOrdreService::orderBy('ordre')->pluck('libelle', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('demarrage')
                    ->label('Seuls les ordres de démarrage')
                    ->query(fn (Builder $query) => $query->demarrage()),
            ])
            ->actions(self::actionsOrdreService())
            // Pas d'action groupée : un ordre de service retiré du dossier est un
            // acte que l'on assume de retirer, ligne par ligne, avec son motif.
            ->bulkActions([]);
    }

    // =========================================================
    // ACTIONS DE LA LIGNE
    // =========================================================

    /**
     * Le jeu de la ligne, isolé de table() pour que la fenêtre de retouche et le
     * verrou du démarrage se vérifient hors écran.
     */
    public static function actionsOrdreService(): array
    {
        return [
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()
                ->before(fn (OrdreService $record) => static::refuserRetrait($record)),
        ];
    }

    /**
     * Le verrou de l'acte d'ouverture : l'ordre de service de démarrage reste au
     * dossier. Le modèle le refuse déjà (hook deleting), et la clé étrangère ne
     * parlerait que sur une suppression dure — ce refus à l'écran existe pour que le
     * service lise pourquoi, et sur quel chemin aller.
     */
    public static function refuserRetrait(OrdreService $record): void
    {
        if (!$record->demarreLexecution()) {
            return;
        }

        Notification::make()
            ->title('❌ Retrait refusé')
            ->body(sprintf(
                '« %s » est l\'ordre de service qui a ouvert l\'exécution du marché %s : il ne se retire '
                    . 'pas. Ce qui se corrige encore, tant que le marché n\'est pas clos, c\'est sa date, '
                    . 'son objet ou sa pièce jointe — par « Modifier ».',
                $record->numero,
                $record->marche?->numero ?? 'un marché',
            ))
            ->danger()->persistent()->send();

        throw new Halt;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdresService::route('/'),
            'edit'  => Pages\EditOrdreService::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $jour = OrdreService::emisAujourdhui()->count();

        return $jour > 0 ? (string) $jour : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
