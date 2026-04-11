<?php

namespace App\Filament\Budget\Resources\EngagementResource\Pages;

use App\Filament\Budget\Resources\EngagementResource;
use App\Models\BonCommande;
use App\Models\DecisionAdministrative;
use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateEngagement extends CreateRecord
{
    protected static string $resource = EngagementResource::class;

    public function form(Form $form): Form
    {
        return $form->schema([

            // ── ÉTAPE 1 : Type de document source ────────────
            Forms\Components\Section::make('Étape 1 — Source de l\'engagement')
                ->description('Sélectionnez le type de document à engager')
                ->schema([
                    Forms\Components\Radio::make('type_source')
                        ->label('Type de document')
                        ->options([
                            'bc' => '📦 Bon de Commande (BC)',
                            'da' => '📋 Décision Administrative (DA)',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            // Réinitialiser les champs liés au changement de type
                            $set('source_id', null);
                            $set('nomenclature_principale_id', null);
                            $set('montant_engage', null);
                            $set('objet', null);
                        })
                        ->inline(false),
                ]),

            // ── ÉTAPE 2 : Sélection du document ──────────────
            Forms\Components\Section::make('Étape 2 — Sélection du document')
                ->description('Choisissez un document validé à engager')
                ->schema([

                    // Sélection BC
                    Forms\Components\Select::make('source_id')
                        ->label(
                            fn(Get $get) => $get('type_source') === 'bc'
                                ? 'Bon de Commande validé'
                                : 'Décision Administrative validée'
                        )
                        ->options(function (Get $get) {
                            $type = $get('type_source');
                            if (!$type) return [];

                            if ($type === 'bc') {
                                return BonCommande::where('statut', 'valide')
                                    ->whereDoesntHave('engagement')
                                    ->with(['fournisseur', 'exercice'])
                                    ->get()
                                    ->mapWithKeys(
                                        fn($bc) =>
                                        [$bc->id => "[{$bc->numero}] {$bc->fournisseur?->raison_sociale} — " .
                                            number_format($bc->montant_ttc, 0, ',', ' ') . " FCFA"]
                                    );
                            }

                            if ($type === 'da') {
                                return DecisionAdministrative::where('statut', 'validee')
                                    ->where('engagee', false)
                                    ->with(['personnel', 'fournisseur', 'exercice'])
                                    ->get()
                                    ->mapWithKeys(fn($da) => [
                                        $da->id => "[{$da->numero}] " .
                                            ($da->personnel?->nom_complet
                                                ?? $da->fournisseur?->raison_sociale
                                                ?? 'N/A') .
                                            " — " . number_format($da->montant_brut, 0, ',', ' ') . " FCFA"
                                    ]);
                            }

                            return [];
                        })
                        ->searchable()
                        ->required()
                        ->live()
                        ->visible(fn(Get $get) => filled($get('type_source')))
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            if (!$state) return;

                            $type = $get('type_source');

                            if ($type === 'bc') {
                                $bc = BonCommande::with(['lignes', 'fournisseur', 'exercice', 'budget'])
                                    ->find($state);
                                if (!$bc) return;

                                $set('objet',          $bc->objet);
                                $set('montant_engage', $bc->montant_ttc);
                                $set('exercice_id',    $bc->exercice_id);
                                $set('budget_id',      $bc->budget_id);

                                // Nomenclature principale = première ligne
                                $nomenclatureId = $bc->lignes->first()?->nomenclature_id;
                                $set('nomenclature_principale_id', $nomenclatureId);

                                // Numéro d'engagement pré-calculé
                                $set('numero_preview', 'BE-' . $bc->numero);
                            } elseif ($type === 'da') {
                                $da = DecisionAdministrative::with(['personnel', 'fournisseur', 'exercice'])
                                    ->find($state);
                                if (!$da) return;

                                $set('objet',          $da->objet);
                                $set('montant_engage', $da->montant_brut);
                                $set('exercice_id',    $da->exercice_id);
                                $set('budget_id',      $da->budget_id);
                                $set('numero_preview', 'BE-' . $da->numero);
                            }
                        })
                        ->helperText('Seuls les documents validés et non encore engagés sont affichés'),

                ])
                ->visible(fn(Get $get) => filled($get('type_source'))),

            // ── ÉTAPE 3 : Aperçu du document sélectionné ─────
            Forms\Components\Section::make('Étape 3 — Aperçu du document')
                ->description('Informations du document sélectionné')
                ->schema([
                    Forms\Components\View::make('filament.forms.components.engagement-document-preview')
                        ->viewData(function (Get $get) {
                            $type     = $get('type_source');
                            $sourceId = $get('source_id');

                            if (!$type || !$sourceId) {
                                return ['document' => null, 'type' => null];
                            }

                            if ($type === 'bc') {
                                $document = \App\Models\BonCommande::with([
                                    'fournisseur',
                                    'serviceDemandeur',
                                    'exercice',
                                    'lignes.nomenclature',
                                ])->find($sourceId);
                            } else {
                                $document = \App\Models\DecisionAdministrative::with([
                                    'personnel',
                                    'fournisseur',
                                    'typeDecision',
                                    'exercice',
                                ])->find($sourceId);
                            }

                            return ['document' => $document, 'type' => $type];
                        }),
                ])
                ->visible(fn(Get $get) => filled($get('type_source')) && filled($get('source_id'))),

            // ── ÉTAPE 4 : Paramètres d'engagement ────────────
            Forms\Components\Section::make('Étape 4 — Paramètres d\'engagement')
                ->description('Confirmez la nomenclature et le montant à engager')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([

                        Forms\Components\Placeholder::make('numero_preview')
                            ->label('N° Engagement (généré)')
                            ->content(fn(Get $get) => new \Illuminate\Support\HtmlString(
                                '<span style="font-weight:700;font-size:1rem;color:#1d4ed8;">' .
                                    ($get('numero_preview') ?? '—') .
                                    '</span>'
                            )),

                        Forms\Components\Placeholder::make('budget_libelle')
                            ->label('Budget')
                            ->content(function (Get $get) {
                                $budgetId = $get('budget_id');
                                if (!$budgetId) return '—';
                                return \App\Models\Budget::find($budgetId)?->libelle ?? '—';
                            }),
                    ]),

                    Forms\Components\Select::make('nomenclature_principale_id')
                        ->label('Nomenclature budgétaire principale')
                        ->options(function (Get $get) {
                            $budgetId = $get('budget_id');
                            if (!$budgetId) return [];

                            return LigneBudgetaire::where('budget_id', $budgetId)
                                ->with('nomenclature')
                                ->get()
                                ->filter(fn($lb) => $lb->nomenclature)
                                ->mapWithKeys(fn($lb) => [
                                    $lb->nomenclature_id =>
                                    "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} " .
                                        "(Dispo: " . number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA)"
                                ])->toArray();
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->helperText(function (Get $get) {
                            $nomenclatureId = $get('nomenclature_principale_id');
                            $budgetId       = $get('budget_id');
                            $montant        = $get('montant_engage');
                            if (!$nomenclatureId || !$budgetId || !$montant) return '';

                            $lb = LigneBudgetaire::where('budget_id', $budgetId)
                                ->where('nomenclature_id', $nomenclatureId)->first();
                            if (!$lb) return '';

                            if ($montant > $lb->disponible_engagement) {
                                return '⚠️ Crédit insuffisant ! Disponible : ' .
                                    number_format($lb->disponible_engagement, 0, ',', ' ') . ' FCFA';
                            }
                            return '✅ Disponible : ' .
                                number_format($lb->disponible_engagement, 0, ',', ' ') . ' FCFA — ' .
                                '(Après engagement : ' .
                                number_format($lb->disponible_engagement - $montant, 0, ',', ' ') . ' FCFA)';
                        }),

                    Forms\Components\TextInput::make('montant_engage')
                        ->label('Montant à engager (FCFA)')
                        ->required()
                        ->numeric()
                        ->prefix('FCFA')
                        ->live(onBlur: true)
                        ->readOnly(fn(Get $get) => filled($get('source_id'))), // Auto depuis le document

                    Forms\Components\DatePicker::make('date_engagement')
                        ->label('Date d\'engagement')
                        ->required()
                        ->default(now()),

                    Forms\Components\Textarea::make('objet')
                        ->label('Objet de l\'engagement')
                        ->required()
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->visible(fn(Get $get) => filled($get('source_id'))),

            // ── Champs cachés (remplis automatiquement) ───────
            Forms\Components\Hidden::make('type_source'),
            Forms\Components\Hidden::make('source_id'),
            Forms\Components\Hidden::make('exercice_id'),
            Forms\Components\Hidden::make('budget_id'),
            Forms\Components\Hidden::make('numero_preview'),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type     = $data['type_source'];
        $sourceId = $data['source_id'];

        if ($type === 'bc') {
            $bc = \App\Models\BonCommande::find($sourceId);
            if (!$bc) throw new \Exception("Bon de commande introuvable.");
            if ($bc->statut !== 'valide') throw new \Exception("Ce BC n'est plus en statut validé.");

            $data['engageable_type']    = \App\Models\BonCommande::class;
            $data['engageable_id']      = $bc->id;
            $data['type_engagement']    = 'BC';
            $data['beneficiaire_type']  = \App\Models\Fournisseur::class;
            $data['beneficiaire_id']    = $bc->fournisseur_id;
            $data['reference_document'] = $bc->numero;
            $data['numero']             = 'BE-' . $bc->numero;
        } elseif ($type === 'da') {
            $da = \App\Models\DecisionAdministrative::find($sourceId);
            if (!$da) throw new \Exception("Décision administrative introuvable.");
            if ($da->statut !== 'validee') throw new \Exception("Cette DA n'est plus en statut validée.");

            $data['engageable_type']    = \App\Models\DecisionAdministrative::class;
            $data['engageable_id']      = $da->id;
            $data['type_engagement']    = 'Décision';
            $data['reference_document'] = $da->numero;
            $data['numero']             = 'BE-' . $da->numero;

            if ($da->type_beneficiaire === 'fournisseur' && $da->fournisseur_id) {
                $data['beneficiaire_type'] = \App\Models\Fournisseur::class;
                $data['beneficiaire_id']   = $da->fournisseur_id;
            } else {
                $data['beneficiaire_type'] = \App\Models\Personnel::class;
                $data['beneficiaire_id']   = $da->personnel_id;
            }
        }

        // ✅ Nettoyer les champs du wizard non stockés en base
        unset(
            $data['type_source'],
            $data['source_id'],
            $data['numero_preview'],
            $data['budget_libelle'],
            $data['beneficiaire_fournisseur_id'], // ← n'existe pas en base
            $data['beneficiaire_personnel_id'],   // ← n'existe pas en base
            $data['type_beneficiaire'],           // ← n'existe pas en base
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $engagement = $this->record;
        $type       = $engagement->engageable_type;

        // ✅ Engager le crédit budgétaire
        $lb = LigneBudgetaire::where('budget_id', $engagement->budget_id)
            ->where('nomenclature_id', $engagement->nomenclature_principale_id)
            ->first();

        if ($lb) {
            if ($engagement->montant_engage > $lb->disponible_engagement) {
                $engagement->forceDelete();
                throw new \Exception(
                    "Crédit insuffisant sur la ligne budgétaire.\n" .
                        "Disponible : " . number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA — " .
                        "Demandé : " . number_format($engagement->montant_engage, 0, ',', ' ') . " FCFA"
                );
            }

            // Créer la ligne d'engagement
            \App\Models\LigneEngagement::create([
                'engagement_id'  => $engagement->id,
                'nomenclature_id' => $engagement->nomenclature_principale_id,
                'numero_ligne'   => 1,
                'libelle'        => $engagement->objet,
                'montant'        => $engagement->montant_engage,
            ]);

            // Engager le crédit
            $lb->engage += $engagement->montant_engage;
            $lb->save();
        }

        // ✅ Marquer le document source comme engagé
        if ($type === \App\Models\BonCommande::class) {
            $bc = \App\Models\BonCommande::find($engagement->engageable_id);
            if ($bc) {
                $bc->updateQuietly([
                    'engage'          => true,
                    'montant_engage'  => $engagement->montant_engage,
                    'date_engagement' => now(),
                    'statut'          => 'engage',
                ]);
            }
        } elseif ($type === \App\Models\DecisionAdministrative::class) {
            $da = \App\Models\DecisionAdministrative::find($engagement->engageable_id);
            if ($da) {
                $da->updateQuietly([
                    'engagee'         => true,
                    'montant_engage'  => $engagement->montant_engage,
                    'date_engagement' => now(),
                    'statut'          => 'engagee',
                ]);
            }
        }

        Notification::make()
            ->title('✅ Engagement créé avec succès')
            ->success()
            ->body("Engagement {$engagement->numero} créé — crédits engagés sur la ligne budgétaire.")
            ->duration(6000)
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
