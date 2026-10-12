<?php

namespace App\Filament\Programmation\Resources\CdmtExerciceResource\RelationManagers;

use App\Models\Action;
use App\Models\Activite;
use App\Models\CdmtLigne;
use App\Models\NomenclatureBudgetaire;
use App\Models\SousProgrammeEp;
use App\Services\Programmation\GenerationCdmtService;
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

/**
 * CDMT par TÂCHE × CODE BUDGÉTAIRE (Annexes B/C) :
 *  - lignes de référence (LR) générées depuis les sous-tâches de N ; mesures nouvelles (MN) ajoutées ;
 *  - projections N+1..N+3 (AE/CP) modifiables dans le tableau ;
 *  - contrôle face au plafond du CBMT, au total et par titre.
 */
class LignesRelationManager extends RelationManager
{
    protected static string $relationship = 'lignes';

    protected static ?string $title = 'Programmation par tâche et code budgétaire (Annexes B/C)';

    protected function annee(int $d = 0): string
    {
        return (string) ($this->getOwnerRecord()->anneeReference() + $d);
    }

    protected function modifiable(): bool
    {
        $cdmt = $this->getOwnerRecord();

        return !method_exists($cdmt, 'estModifiable') || $cdmt->estModifiable();
    }

    // ════════════════════════════════════════════════════════
    // FORMULAIRE (mesure nouvelle / modification)
    // ════════════════════════════════════════════════════════

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('sous_programme_ep_id')
                ->label('Sous-programme')
                ->options(SousProgrammeEp::orderBy('code')->get()->mapWithKeys(fn($s) => [$s->id => "{$s->code} — {$s->libelle}"]))
                ->searchable()->required()->live(),

            Forms\Components\Select::make('action_id')
                ->label('Action')
                ->options(fn() => Action::withoutGlobalScope('exercice')->orderBy('code')->get()->mapWithKeys(fn($a) => [$a->id => "{$a->code} — {$a->libelle}"]))
                ->searchable()->live(),

            Forms\Components\Select::make('activite_id')
                ->label('Activité')
                ->options(fn(Forms\Get $get) => $get('action_id')
                    ? Activite::withoutGlobalScope('exercice')->where('action_id', $get('action_id'))->orderBy('code')->get()->mapWithKeys(fn($a) => [$a->id => "{$a->code} — {$a->libelle}"])
                    : [])
                ->searchable(),

            Forms\Components\Select::make('nomenclature_id')
                ->label('Code budgétaire')
                ->options(fn() => NomenclatureBudgetaire::where('type', 'depense')->where('niveau', 'paragraphe')->orderBy('code')->get()
                    ->mapWithKeys(fn($n) => [$n->id => "{$n->code} — {$n->libelle}"]))
                ->searchable()->required()->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $n = NomenclatureBudgetaire::find($state);
                    $titre = app(TitreNomenclatureService::class)->titreDe($n);
                    $set('titre', $titre);
                    $set('est_investissement', $titre === 5);
                }),

            Forms\Components\Hidden::make('titre'),

            Forms\Components\TextInput::make('libelle')->required()->columnSpanFull(),

            Forms\Components\Select::make('nature')
                ->label('Ligne de référence / mesure nouvelle')
                ->options(['LR' => 'Ligne de référence (LR)', 'MN' => 'Mesure nouvelle (MN)'])
                ->default('MN')->required(),

            Forms\Components\Toggle::make('est_investissement')
                ->label('Dépense d\'investissement')->live()
                ->helperText('Visa de maturité obligatoire (Guide 2025, Encadré 6).'),

            Forms\Components\Select::make('maturite')
                ->label('Maturité')
                ->options([
                    'etudes' => 'Études / faisabilité',
                    'dao' => 'Projet de DAO',
                    'en_cours' => 'En cours de réalisation',
                    'mature' => 'Mature (visa obtenu)',
                    'non_requise' => 'Non requise (hors investissement)',
                ])
                ->required(fn(Forms\Get $get) => $get('est_investissement')),

            Forms\Components\Fieldset::make('Programmation (AE / CP)')
                ->schema([
                    Forms\Components\TextInput::make('n_plus_1_ae')->label(fn() => $this->annee(1) . ' AE')->numeric()->default(0),
                    Forms\Components\TextInput::make('n_plus_1_cp')->label(fn() => $this->annee(1) . ' CP')->numeric()->default(0),
                    Forms\Components\TextInput::make('n_plus_2_ae')->label(fn() => $this->annee(2) . ' AE')->numeric()->default(0),
                    Forms\Components\TextInput::make('n_plus_2_cp')->label(fn() => $this->annee(2) . ' CP')->numeric()->default(0),
                    Forms\Components\TextInput::make('n_plus_3_ae')->label(fn() => $this->annee(3) . ' AE')->numeric()->default(0),
                    Forms\Components\TextInput::make('n_plus_3_cp')->label(fn() => $this->annee(3) . ' CP')->numeric()->default(0),
                ])->columns(2),

            Forms\Components\Textarea::make('commentaire')->columnSpanFull(),
        ])->columns(2);
    }

    // ════════════════════════════════════════════════════════
    // TABLEAU
    // ════════════════════════════════════════════════════════

    public function table(Table $table): Table
    {
        $somme = fn() => Sum::make()->label('')->numeric(0);
        $projection = fn(string $col, string $label) => Tables\Columns\TextInputColumn::make($col)
            ->label($label)->type('number')->rules(['numeric', 'min:0'])
            ->disabled(fn() => !$this->modifiable())
            ->summarize($somme());

        return $table
            ->description(fn() => $this->descriptionEcart())
            ->defaultSort('ordre')
            ->groups([
                Group::make('sous_programme_ep_id')
                    ->label('Sous-programme')
                    ->getTitleFromRecordUsing(fn(CdmtLigne $record) => $record->sousProgrammeEp
                        ? "{$record->sousProgrammeEp->code} — {$record->sousProgrammeEp->libelle}"
                        : 'Sous-programme non déterminé')
                    ->collapsible(),
                Group::make('titre')
                    ->label('Titre')
                    ->getTitleFromRecordUsing(fn(CdmtLigne $record) => $record->libelle_titre)
                    ->collapsible(),
                Group::make('activite_id')
                    ->label('Activité')
                    ->getTitleFromRecordUsing(fn(CdmtLigne $record) => $record->activite ? "{$record->activite->code} — {$record->activite->libelle}" : '—')
                    ->collapsible(),
            ])
            ->defaultGroup('sous_programme_ep_id')
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['sousProgrammeEp', 'activite', 'tache']))
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Compte')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('libelle')->label('Tâche')->wrap()->limit(60)->searchable(),
                Tables\Columns\TextColumn::make('titre')->badge()->formatStateUsing(fn($state) => $state ? "T{$state}" : '—')
                    ->color(fn($state) => (int) $state === 5 ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('nature')->label('LR/MN')->badge()
                    ->color(fn($state) => $state === 'MN' ? 'warning' : 'success'),
                // ✅ Complétude : AE et CP renseignés pour chaque année de programmation
                Tables\Columns\IconColumn::make('complete')
                    ->label('Complétude')
                    ->getStateUsing(fn(CdmtLigne $record) => empty($record->montantsManquants($this->getOwnerRecord()->anneeReference())))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')->falseColor('warning')
                    ->tooltip(fn(CdmtLigne $record) => ($m = $record->montantsManquants($this->getOwnerRecord()->anneeReference()))
                        ? 'À renseigner : ' . implode(', ', $m)
                        : 'AE et CP renseignés pour toutes les années'),

                Tables\Columns\TextColumn::make('maturite')->label('Maturité')
                    ->formatStateUsing(fn(CdmtLigne $record) => $record->est_investissement ? ($record->getAlerteMaturite() ?? '✅ Mature') : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('n_ae')->label(fn() => $this->annee() . ' AE')->numeric(0)->summarize($somme())->toggleable(),
                Tables\Columns\TextColumn::make('n_cp')->label(fn() => $this->annee() . ' CP')->numeric(0)->summarize($somme()),

                $projection('n_plus_1_ae', $this->annee(1) . ' AE'),
                $projection('n_plus_1_cp', $this->annee(1) . ' CP'),
                $projection('n_plus_2_ae', $this->annee(2) . ' AE'),
                $projection('n_plus_2_cp', $this->annee(2) . ' CP'),
                $projection('n_plus_3_ae', $this->annee(3) . ' AE'),
                $projection('n_plus_3_cp', $this->annee(3) . ' CP'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('nature')->label('LR/MN')->options(['LR' => 'Lignes de référence', 'MN' => 'Mesures nouvelles']),
                Tables\Filters\SelectFilter::make('titre')->options([1 => 'Titre 1', 2 => 'Titre 2', 3 => 'Titre 3', 4 => 'Titre 4', 5 => 'Titre 5', 6 => 'Titre 6']),
                Tables\Filters\SelectFilter::make('sous_programme_ep_id')->label('Sous-programme')
                    ->options(SousProgrammeEp::orderBy('code')->pluck('code', 'id')),
                Tables\Filters\TernaryFilter::make('est_investissement')->label('Investissement'),
                Tables\Filters\Filter::make('a_completer')
                    ->label('À compléter (AE ou CP manquants)')
                    ->query(fn(Builder $query) => $query->aCompleter()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generer')
                    ->label(fn() => 'Générer depuis les tâches ' . $this->annee())
                    ->icon('heroicon-o-arrow-path')->color('primary')
                    ->visible(fn() => $this->modifiable())
                    ->form([
                        Forms\Components\Toggle::make('reinitialiser')
                            ->label('Réinitialiser les projections des lignes de référence (LR)')
                            ->helperText('Sinon, seules les nouvelles lignes reçoivent la reconduction ; les projections saisies sont conservées. Les mesures nouvelles ne sont jamais modifiées.'),
                        Forms\Components\Toggle::make('ignorer_nulles')
                            ->label('Ignorer les sous-tâches sans AE ni CP')->default(true),
                    ])
                    ->action(function (array $data) {
                        try {
                            $service = app(GenerationCdmtService::class);
                            $b = $service->generer($this->getOwnerRecord(), (bool) ($data['reinitialiser'] ?? false), (bool) ($data['ignorer_nulles'] ?? true));
                            $aCompleter = $this->getOwnerRecord()->lignes()->aCompleter()->count();
                            Notification::make()->success()->title('CDMT généré')
                                ->body("{$b['creees']} ligne(s) créée(s), {$b['mises_a_jour']} mise(s) à jour, {$b['ignorees_nulles']} sous-tâche(s) sans montant ignorée(s)."
                                    . ($b['sans_sous_programme'] ? "\n⚠️ {$b['sans_sous_programme']} ligne(s) sans sous-programme : vérifier le rattachement des programmes." : '')
                                    . ($aCompleter ? "\n⚠️ {$aCompleter} ligne(s) sans AE ou CP pour au moins une année : renseignez-les pour obtenir un état complet (filtre « À compléter »)." : '')
                                    . ($b['ignorees_nulles'] ? "\n⚠️ Les {$b['ignorees_nulles']} sous-tâche(s) sans montant ne sont pas reprises : renseignez leurs AE et CP dans le Module 01, puis relancez la génération." : ''))
                                ->persistent()
                                ->send();
                        } catch (\DomainException $e) {
                            Notification::make()->warning()->title('Génération impossible')->body($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('controleTitres')
                    ->label('Contrôle par titre (CBMT)')
                    ->icon('heroicon-o-scale')->color('gray')
                    ->modalHeading('CP programmés au CDMT face au plafond du CBMT, par titre')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer')
                    ->modalContent(fn() => new \Illuminate\Support\HtmlString($this->tableauControleTitres())),

                Tables\Actions\CreateAction::make()
                    ->label('Ajouter une mesure nouvelle')
                    ->visible(fn() => $this->modifiable())
                    ->mutateFormDataUsing(function (array $data) {
                        $n = NomenclatureBudgetaire::find($data['nomenclature_id'] ?? null);
                        return $data + ['code' => $n?->code, 'ordre' => (int) preg_replace('/\D/', '', (string) $n?->code)];
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn() => $this->modifiable()),
                Tables\Actions\DeleteAction::make()->visible(fn(CdmtLigne $record) => $this->modifiable() && $record->nature === 'MN'),
            ]);
    }

    /** Écart CDMT (CP) / plafond CBMT, au total, pour chaque année. */
    protected function descriptionEcart(): string
    {
        $cdmt = $this->getOwnerRecord();
        if (!$cdmt->cbmtExercice) {
            return 'Aucun CBMT de rattachement.';
        }

        $parties = [];
        foreach ($cdmt->getEcartAvecCbmt() as $cle => $e) {
            $d = (int) substr($cle, -1);
            $parties[] = $this->annee($d) . ' : ' . ($e['ecart'] >= -0.5
                ? '✅ dans le plafond (marge ' . number_format($e['ecart'], 0, ',', ' ') . ')'
                : '⚠️ dépassement de ' . number_format(abs($e['ecart']), 0, ',', ' '));
        }

        $texte = 'CP programmés face au plafond du CBMT — ' . implode(' · ', $parties);

        // ✅ Rappel : montants AE / CP à renseigner pour un état complet
        $aCompleter = $cdmt->lignes()->aCompleter()->count();
        $sansMontant = app(GenerationCdmtService::class)->sousTachesSansMontant($cdmt)->count();

        if ($aCompleter) {
            $texte .= " ⚠️ {$aCompleter} ligne(s) sans AE ou CP pour au moins une année : renseignez-les pour obtenir un état complet (filtre « À compléter »).";
        }
        if ($sansMontant) {
            $texte .= " ⚠️ {$sansMontant} sous-tâche(s) " . $this->annee() . " sans AE ni CP ne sont pas reprises : complétez-les dans le Module 01, puis relancez la génération.";
        }
        if (!$aCompleter && !$sansMontant) {
            $texte .= ' ✅ Toutes les lignes ont leurs AE et CP renseignés.';
        }

        return $texte;
    }

    /** Tableau HTML du contrôle par titre (modale). */
    protected function tableauControleTitres(): string
    {
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');
        $b = 'border:1px solid #d1d5db;padding:4px 6px;';
        $html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><tr><th style="' . $b . 'text-align:left;">Titre</th>';
        foreach ([1, 2, 3] as $d) {
            $html .= '<th style="' . $b . '">' . $this->annee($d) . ' plafond CBMT</th><th style="' . $b . '">' . $this->annee($d) . ' CP CDMT</th><th style="' . $b . '">Écart</th>';
        }
        $html .= '</tr>';

        foreach ($this->getOwnerRecord()->getControleParTitre() as $l) {
            $html .= '<tr><td style="' . $b . '">' . e($l['libelle']) . '</td>';
            foreach ($l['annees'] as $a) {
                $couleur = $a['ecart'] < -0.5 ? '#b91c1c' : '#166534';
                $html .= '<td style="' . $b . 'text-align:right;">' . $f($a['plafond']) . '</td><td style="' . $b . 'text-align:right;">' . $f($a['programme'])
                    . '</td><td style="' . $b . 'text-align:right;color:' . $couleur . ';font-weight:600;">' . $f($a['ecart']) . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</table></div><p style="font-size:11px;color:#6b7280;margin-top:6px;">Écart = plafond − programmé ; en rouge, un dépassement du plafond du titre.</p>';
    }
}
