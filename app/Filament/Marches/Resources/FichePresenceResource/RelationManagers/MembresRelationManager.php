<?php

namespace App\Filament\Marches\Resources\FichePresenceResource\RelationManagers;

use App\Filament\Marches\Resources\FichePresenceResource;
use App\Models\FichePresence;
use App\Models\FichePresenceMembre;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * La feuille elle-même : qui siégeait, qui est venu, qui a visé.
 *
 * Le gestionnaire n'a pas d'en-tête à saisir — la fiche est dressée sur le marché. Il
 * porte les trois actes de la séance :
 *
 * - l'émargement (`present`, avec son motif d'absence) : la case que l'agent remplit ;
 * - le visa : la case que le membre remplit. Il ne se retourne dans aucune fiche de
 *   champ, seulement par « Viser » et « Lever le visa », parce que ces deux actes
 *   écrivent au journal la main qui a signé et celle qui a défait ;
 * - la ligne nouvelle : un membre convoqué pour la séance que le référentiel de la
 *   commission ne connaît pas.
 *
 * Une ligne visée gèle son émargement et son identité, et ne se retire pas : le visa se
 * lève d'abord. Ce n'est pas un caprice d'écran — `FichePresenceMembre` l'écrit dans ses
 * hooks — et le refus se lit ici, ligne par ligne, plutôt que de disparaître en 500.
 */
class MembresRelationManager extends RelationManager
{
    protected static string $relationship = 'membres';

    protected static ?string $title = 'Émargements et visas de la séance';
    protected static ?string $modelLabel = 'membre de la séance';
    protected static ?string $pluralModelLabel = 'membres de la séance';

    // =========================================================
    // FENÊTRE ET MAINS
    // =========================================================

    protected function fiche(): FichePresence
    {
        return $this->getOwnerRecord();
    }

    /** La main de l'administration, seule à pouvoir toucher une fiche qui a servi. */
    protected function administration(): bool
    {
        return auth()->user()?->can('force_update_fiche_presence') ?? false;
    }

    /**
     * La séance est-elle encore vivante ? Passé la clôture du marché, la fiche a servi :
     * ni l'émargement ni le visa n'y changent plus rien, et une signature ajoutée après
     * coup n'atteste plus une réunion.
     */
    protected function seanceEnCours(): bool
    {
        $marche = $this->fiche()->marche;

        return (bool) $marche && !$marche->estClo();
    }

    protected function retouchable(): bool
    {
        return $this->administration()
            || ($this->seanceEnCours() && (auth()->user()?->can('update_fiche_presence') ?? false));
    }

    protected function peutViser(): bool
    {
        return $this->administration()
            || ($this->seanceEnCours() && (auth()->user()?->can('viser_fiche_presence') ?? false));
    }

    protected function peutLeverVisa(): bool
    {
        return $this->administration()
            || ($this->seanceEnCours() && (auth()->user()?->can('lever_visa_fiche_presence') ?? false));
    }

    /**
     * Aucune policy n'est déclarée sur la ligne : sans cette surcharge, Filament
     * laisserait le gestionnaire ouvert à n'importe quel utilisateur du panneau.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view_fiche_presence') ?? false;
    }

    protected function canCreate(): bool
    {
        return $this->retouchable();
    }

    protected function canEdit(Model $record): bool
    {
        return $this->retouchable();
    }

    protected function canDelete(Model $record): bool
    {
        // Le bouton reste visible sur la ligne visée : c'est à l'écran que l'agent lit
        // que le visa empêche le retrait, et « Lever le visa » est le chemin proposé.
        return $this->retouchable();
    }

    protected function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Le refus que l'agent lit : la permission manquante ou le verrou du visa, avec le
     * chemin pour le défaire. `throw new Halt` ferme la fenêtre sans rien écrire.
     */
    protected function refuser(string $titre, string $message): void
    {
        Notification::make()->title($titre)->body($message)
            ->danger()->persistent()->send();

        throw new Halt;
    }

    // =========================================================
    // FORMULAIRE DE LA LIGNE
    // =========================================================

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nom')->label('Nom du membre')
                ->required()->maxLength(255)
                ->helperText('Le nom recopié au dressage se corrige ici tant que le membre n\'a pas visé : '
                    . 'la fiche atteste le papier, pas le paramétrage.'),
            Forms\Components\Select::make('titre')->label('Qualité')->required()
                ->options(config('marches.titres_membre'))->searchable()
                ->helperText('La qualité ordonne les cases de signature au bas de la fiche.'),
            Forms\Components\TextInput::make('telephone')->label('Téléphone')->maxLength(30),

            // `present` est nullable : la case vide est un état. Le cast boolean du
            // modèle rend true/false alors que les options sont 1/0 — sans cette
            // normalisation, une ligne déjà émargée s'afficherait vide à l'ouverture.
            Forms\Components\Select::make('present')->label('Émargement')
                ->options([1 => 'Présent', 0 => 'Absent'])
                ->placeholder('— pas encore porté')
                ->live()
                ->afterStateHydrated(function (Forms\Components\Component $component, $state): void {
                    $component->state(match ($state) {
                        true, 1, '1' => 1,
                        false, 0, '0' => 0,
                        default => null,
                    });
                }),

            Forms\Components\Textarea::make('motif_absence')->label('Motif de l\'absence')
                ->visible(fn (callable $get): bool => in_array($get('present'), [0, '0', false], true))
                ->required(fn (callable $get): bool => in_array($get('present'), [0, '0', false], true))
                ->rows(2)->columnSpanFull()
                ->helperText('Une fiche qui dit que quelqu\'un n\'est pas venu sans dire pourquoi ne se relit pas devant un recours.'),

            Forms\Components\Placeholder::make('visa_lu')->label('Visa')
                ->columnSpanFull()
                ->content(fn (?FichePresenceMembre $record): string => $record?->estVisee()
                    ? '👁️ visée le ' . $record->date_visa?->format('d/m/Y H:i')
                        . ' : son émargement et son identité ne se retouchent plus — « Lever le visa » d\'abord.'
                    : '⬜ pas de visa : il se pose par l\'action « Viser » de la ligne, jamais par cette fiche.'),
        ])->columns(2);
    }

    // =========================================================
    // TABLEAU DES LIGNES
    // =========================================================

    public function table(Table $table): Table
    {
        return $table
            // L'ordre du dressage (qualité du référentiel) est celui de la feuille ;
            // une ligne ajoutée après se range au bout.
            ->defaultSort('ordre')
            ->columns([
                Tables\Columns\TextColumn::make('ordre')->label('#')
                    ->numeric()->width('2rem'),
                Tables\Columns\TextColumn::make('nom')->label('Membre')
                    ->searchable()->weight('medium')->wrap(),
                Tables\Columns\TextColumn::make('titre')->label('Qualité')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => config('marches.titres_membre')[$state] ?? '—')
                    ->color(fn (?string $state): string => match ($state) {
                        'president'      => 'danger',
                        'vice_president' => 'warning',
                        'rapporteur'     => 'info',
                        'secretaire'     => 'primary',
                        default          => 'gray',
                    }),
                Tables\Columns\TextColumn::make('telephone')->label('Téléphone')
                    ->placeholder('—')->limit(20),
                Tables\Columns\TextColumn::make('present')->label('Émargement')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match (true) {
                        $state === true => '✔ présent',
                        $state === false => '✘ absent',
                        default => '⬜ à porter',
                    })
                    ->color(fn ($state): string => match (true) {
                        $state === true => 'success',
                        $state === false => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('motif_absence')->label('Motif')
                    ->wrap()->limit(40)->placeholder('—'),
                Tables\Columns\TextColumn::make('visa')->label('Visa')
                    ->badge()
                    ->formatStateUsing(fn (FichePresenceMembre $record): string => $record->estVisee()
                        ? '👁️ ' . $record->date_visa?->format('d/m/Y H:i')
                        : '⬜ —')
                    ->color(fn (FichePresenceMembre $record): string => $record->estVisee() ? 'info' : 'gray'),
                // L'origine de la ligne se lit : une fiche qui appelle un non-membre ne
                // doit pas le faire passer pour un membre du référentiel.
                Tables\Columns\TextColumn::make('commission_membre_id')->label('Origine')
                    ->formatStateUsing(fn (FichePresenceMembre $record): string => $record->commission_membre_id
                        ? 'référentiel'
                        : 'convoqué pour la séance')
                    ->color('gray'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter un membre convoqué')
                    ->modalHeading('Membre convoqué pour la séance')
                    ->modalDescription('Une commission siège aussi des personnes que son référentiel ne connaît pas : '
                        . 'la ligne les écrit à la fiche, avec la mention de leur venue.'),
            ])
            ->actions($this->actionsLigne())
            // Pas d'action groupée : un émargement se porte ligne par ligne, et un visa
            // se lève par la main qui l'a posé.
            ->bulkActions([]);
    }

    /**
     * Le jeu de la ligne, isolé de table() pour que le visa et son lever se vérifient
     * hors écran.
     *
     * @return array<Tables\Actions\Action>
     */
    public function actionsLigne(): array
    {
        return [
            Tables\Actions\Action::make('viser')
                ->label('Viser')
                ->icon('heroicon-o-check-badge')->color('info')
                ->visible(fn () => $this->peutViser())
                ->requiresConfirmation()
                ->modalHeading(fn (FichePresenceMembre $record) => 'Visa de ' . $record->nom)
                ->modalDescription(fn (FichePresenceMembre $record): string => $record->estVisee()
                    ? $record->nom . ' a déjà visé cette fiche.'
                    : 'Le membre a signé la case de la fiche de présence ' . $this->fiche()->numero
                        . '. Ce visa porte sa main au journal, avec la date de l\'enregistrement.')
                ->modalSubmitActionLabel('Poser le visa')
                ->action(function (FichePresenceMembre $record) {
                    // visible() ne protège pas : la ligne est atteignable par son
                    // wire:id. La main se rejoue ici.
                    if (!$this->peutViser()) {
                        $this->refuser('🔒 Visa refusé', 'Il faut la permission « viser_fiche_presence », et le '
                            . 'marché ne doit pas être clos, pour porter le visa d\'un membre.');
                    }

                    $user = auth()->user();
                    if (!$user) {
                        $this->refuser('🔒 Visa refusé', 'Aucune session : un visa s\'attache à une main identifiable.');
                    }

                    try {
                        $record->viser($user);
                    } catch (\Exception $e) {
                        $this->refuser('❌ Visa refusé', $e->getMessage());
                    }

                    Notification::make()
                        ->title('👁️ Visa posé')
                        ->body(sprintf('« %s » (%s) a visé la fiche %s.', $record->nom,
                            config('marches.titres_membre')[$record->titre] ?? $record->titre,
                            $this->fiche()->numero))
                        ->success()->send();
                }),

            Tables\Actions\Action::make('lever_visa')
                ->label('Lever le visa')
                ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn () => $this->peutLeverVisa())
                ->requiresConfirmation()
                ->modalHeading(fn (FichePresenceMembre $record) => 'Lever le visa de ' . $record->nom)
                ->modalDescription('Lever un visa est une écriture à son tour : elle reste au journal avec la main '
                    . 'qui la fait. La ligne redevient retouchable, et le visa se reposera si le membre signe à nouveau.')
                ->modalSubmitActionLabel('Lever le visa')
                ->action(function (FichePresenceMembre $record) {
                    if (!$this->peutLeverVisa()) {
                        $this->refuser('🔒 Levée refusée', 'Il faut la permission « lever_visa_fiche_presence », et le '
                            . 'marché ne doit pas être clos, pour défaire un visa.');
                    }

                    $user = auth()->user();
                    if (!$user) {
                        $this->refuser('🔒 Levée refusée', 'Aucune session : la main qui défait un visa s\'écrit au journal.');
                    }

                    try {
                        $record->leverVisa($user);
                    } catch (\Exception $e) {
                        $this->refuser('❌ Levée refusée', $e->getMessage());
                    }

                    Notification::make()
                        ->title('↩️ Visa levé')
                        ->body(sprintf('Le visa de « %s » sur la fiche %s est levé : la ligne se retouche.',
                            $record->nom, $this->fiche()->numero))
                        ->success()->send();
                }),

            // La sauvegarde passe par le modèle, dont les hooks écrivent le verrou du
            // visa et l'absence muette. EditAction les laisserait remonter en page
            // d'erreur : le refus se lit ici, et la fenêtre se rouvre sur la ligne.
            Tables\Actions\EditAction::make()
                ->label('Modifier')
                ->action(function (FichePresenceMembre $record, array $data) {
                    if (!$this->retouchable()) {
                        $this->refuser('🔒 Écriture refusée', 'La fiche n\'est plus retouchable : '
                            . 'le marché est clos, ou la permission « update_fiche_presence » manque. '
                            . 'L\'administration seule y revient, par « force_update_fiche_presence ».');
                    }

                    try {
                        $record->update($data);
                    } catch (\Exception $e) {
                        $this->refuser('✏️ Ligne refusée', $e->getMessage());
                    }

                    Notification::make()
                        ->title('✏️ Ligne mise à jour')
                        ->body(sprintf('« %s » est à jour sur la fiche %s.', $record->nom, $this->fiche()->numero))
                        ->success()->send();
                }),

            Tables\Actions\DeleteAction::make()
                ->label('Retirer')
                ->before(function (FichePresenceMembre $record) {
                    if (!$this->retouchable()) {
                        $this->refuser('🔒 Retrait refusé', 'La fiche n\'accepte plus d\'écriture : le marché est clos '
                            . 'ou la permission « update_fiche_presence » manque.');
                    }

                    if ($record->estVisee()) {
                        $this->refuser('🗑️ Retrait refusé', sprintf(
                            '« %s » a visé cette fiche : sa ligne ne se retire pas. Levez le visa d\'abord — c\'est '
                                . 'l\'acte qui dit qui défait — puis retirez la ligne si le membre n\'a pas siégé.',
                            $record->nom,
                        ));
                    }
                }),
        ];
    }
}
