<?php

namespace App\Filament\Programmation\Resources\CbmtExerciceResource\RelationManagers;

use App\Models\CbmtLigne;
use App\Models\NomenclatureBudgetaire;
use App\Services\Programmation\GenerationCbmtService;
use App\Services\Programmation\TitreNomenclatureService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Prévision à moyen terme des ressources et des dépenses PAR TITRES.
 * Les titres regroupent les lignes réelles (comptes de la nomenclature) :
 *  - lignes de référence (LR), reconduites de l'exercice N par génération ;
 *  - mesures nouvelles (MN), ajoutées à la main.
 */
class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';

    protected static ?string $title = 'Ressources et dépenses par titres';

    /** Année de l'exercice de référence (N), pour les en-têtes de colonnes. */
    protected function annee(int $decalage = 0): string
    {
        $n = (int) ($this->getOwnerRecord()->exerciceReference?->annee ?? now()->year);

        return (string) ($n + $decalage);
    }

    protected function modifiable(): bool
    {
        $cbmt = $this->getOwnerRecord();

        return !method_exists($cbmt, 'estModifiable') || $cbmt->estModifiable();
    }

    // ════════════════════════════════════════════════════════
    // FORMULAIRE (mesure nouvelle / modification)
    // ════════════════════════════════════════════════════════

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('nature')
                ->options(['ressource' => 'Ressource', 'depense' => 'Dépense'])
                ->live()->required()
                ->disabledOn('edit'),

            Forms\Components\Select::make('nomenclature_id')
                ->label('Compte (nomenclature)')
                ->options(fn(Forms\Get $get) => NomenclatureBudgetaire::query()
                    ->where('niveau', config('cbmt.niveau_ligne', 'paragraphe'))
                    ->where('type', $get('nature') === 'ressource' ? 'recette' : 'depense')
                    ->orderBy('code')
                    ->get()
                    ->mapWithKeys(fn($n) => [$n->id => "{$n->code} — {$n->libelle}"]))
                ->searchable()->required()
                ->disabledOn('edit')
                ->helperText('Le titre est déduit du compte (nomenclature).'),

            Forms\Components\Select::make('type_ligne')
                ->label('Type')
                ->options(CbmtLigne::TYPES_LIGNE)
                ->default('MN')->required(),

            Forms\Components\Fieldset::make('Exercice N et antérieur (référence)')
                ->schema([
                    Forms\Components\TextInput::make('montant_n_moins_1')->label(fn() => 'Réalisation ' . $this->annee(-1))->numeric()->default(0),
                    Forms\Components\TextInput::make('montant_n')->label(fn() => 'Prévision ' . $this->annee() . ' actualisée')->numeric()->default(0),
                ])->columns(2),

            Forms\Components\Fieldset::make('Projections')
                ->schema([
                    Forms\Components\TextInput::make('montant_n_plus_1')->label(fn() => $this->annee(1))->numeric()->default(0)->required(),
                    Forms\Components\TextInput::make('montant_n_plus_2')->label(fn() => $this->annee(2))->numeric()->default(0)->required(),
                    Forms\Components\TextInput::make('montant_n_plus_3')->label(fn() => $this->annee(3))->numeric()->default(0)->required(),
                ])->columns(3),
        ]);
    }

    /** Complète une ligne saisie : code, libellé et titre déduits du compte. */
    protected function completerDepuisNomenclature(array $data): array
    {
        $n = NomenclatureBudgetaire::find($data['nomenclature_id'] ?? null);

        if ($n) {
            $titre = app(TitreNomenclatureService::class)->titreDe($n);
            $titres = ($data['nature'] ?? null) === 'ressource' ? CbmtLigne::TITRES_RESSOURCES : CbmtLigne::TITRES_DEPENSES;

            $data += [
                'code'          => $n->code,
                'libelle'       => $n->libelle,
                'titre'         => $titre,
                'libelle_titre' => $titre !== null ? ($titres[$titre] ?? null) : null,
                'ordre'         => (int) preg_replace('/\D/', '', (string) $n->code),
            ];
        }

        return $data;
    }

    // ════════════════════════════════════════════════════════
    // TABLEAU
    // ════════════════════════════════════════════════════════

    public function table(Table $table): Table
    {
        $montant = fn(string $colonne, string $label) => Tables\Columns\TextColumn::make($colonne)
            ->label($label)->numeric(0)->alignEnd()->toggleable()
            ->summarize(Sum::make()->label('')->numeric(0));

        $projection = fn(string $colonne, int $decalage) => Tables\Columns\TextInputColumn::make($colonne)
            ->label($this->annee($decalage))
            ->type('number')->rules(['numeric', 'min:0'])
            ->disabled(fn() => !$this->modifiable())
            ->alignEnd()
            ->summarize(Sum::make()->label('')->numeric(0));

        return $table
            ->description(fn() => $this->descriptionEquilibre())
            ->defaultSort('ordre')
            ->groups([
                Group::make('titre')
                    ->label('Titre')
                    ->getTitleFromRecordUsing(fn(CbmtLigne $r) => $r->libelle_titre_complet)
                    ->orderQueryUsing(fn(Builder $q, string $direction) => $q->orderByRaw('titre IS NULL')->orderBy('titre', $direction)),
            ])
            ->defaultGroup('titre')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Compte')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('libelle')->label('Libellé')->wrap()->searchable()->limit(60),
                Tables\Columns\TextColumn::make('type_ligne')->label('LR/MN')->badge()
                    ->color(fn($state) => $state === 'MN' ? 'warning' : 'gray'),

                $montant('montant_n_moins_1', 'Réal. ' . $this->annee(-1))->toggleable(isToggledHiddenByDefault: true),
                $montant('prevision_n_initiale', 'Prév. ' . $this->annee() . ' initiale'),
                $montant('montant_n', 'Prév. ' . $this->annee() . ' actualisée'),
                $montant('realisation_n', 'Réal. ' . $this->annee() . ' (recouvré / engagé)'),
                $montant('realisation_n_ordonnance', 'Réal. ' . $this->annee() . ' ordonnancé'),

                $projection('montant_n_plus_1', 1),
                $projection('montant_n_plus_2', 2),
                $projection('montant_n_plus_3', 3),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('nature')
                    ->options(['ressource' => 'Ressources', 'depense' => 'Dépenses'])
                    ->default('ressource'),
                Tables\Filters\SelectFilter::make('type_ligne')->label('LR/MN')->options(CbmtLigne::TYPES_LIGNE),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generer')
                    ->label(fn() => 'Générer à partir de ' . $this->annee())
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->visible(fn() => $this->modifiable())
                    ->form([
                        Forms\Components\Toggle::make('reinitialiser')
                            ->label('Réinitialiser les projections des lignes de référence (LR)')
                            ->helperText('Sinon, seules les nouvelles lignes reçoivent la reconduction ; les projections déjà saisies sont conservées. Les mesures nouvelles ne sont jamais modifiées.'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $bilan = app(GenerationCbmtService::class)->generer($this->getOwnerRecord(), (bool) ($data['reinitialiser'] ?? false));

                            Notification::make()->success()->title('CBMT généré')
                                ->body("{$bilan['creees']} ligne(s) créée(s), {$bilan['mises_a_jour']} mise(s) à jour."
                                    . ($bilan['sans_titre'] ? "\n⚠️ {$bilan['sans_titre']} ligne(s) sans titre : classez leur compte dans la nomenclature." : ''))
                                ->send();
                        } catch (\DomainException $e) {
                            Notification::make()->warning()->title('Génération impossible')->body($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une mesure nouvelle')
                    ->icon('heroicon-o-plus')
                    ->visible(fn() => $this->modifiable())
                    ->mutateFormDataUsing(fn(array $data) => $this->completerDepuisNomenclature($data)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn() => $this->modifiable()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn(CbmtLigne $r) => $this->modifiable() && $r->type_ligne === 'MN'),
            ])
            ->bulkActions([]);
    }

    /** Équilibre ressources / dépenses pour chaque année projetée. */
    protected function descriptionEquilibre(): string
    {
        $test = $this->getOwnerRecord()->getTestSoutenabilite();
        $parties = [];

        foreach (['montant_n_plus_1' => 1, 'montant_n_plus_2' => 2, 'montant_n_plus_3' => 3] as $col => $decalage) {
            $ecart = (float) ($test[$col]['ecart'] ?? 0);
            $parties[] = $this->annee($decalage) . ' : ' . (abs($ecart) < 1
                ? '✅ équilibré'
                : '⚠️ écart ' . number_format($ecart, 0, ',', ' '));
        }

        return 'Équilibre ressources − dépenses — ' . implode(' · ', $parties);
    }
}
