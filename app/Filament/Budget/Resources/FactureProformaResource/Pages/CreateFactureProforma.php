<?php

namespace App\Filament\Budget\Resources\FactureProformaResource\Pages;

use App\Filament\Budget\Resources\FactureProformaResource;
use App\Models\Exercice;
use App\Models\ReferenceMercuriale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;

class CreateFactureProforma extends CreateRecord
{
    protected static string $resource = FactureProformaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * ✅ Formulaire dédié à la création — inclut un Repeater pour saisir
     * les lignes directement, sans attendre l'enregistrement (contrairement
     * à la RelationManager, qui elle n'apparaît qu'après création/en édition).
     */
    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations de la facture proforma')
                ->schema([
                    Forms\Components\Select::make('exercice_id')
                        ->label('Exercice')
                        ->relationship('exercice', 'annee')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->default(fn() => Exercice::getActif()?->id),

                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur')
                        ->relationship('fournisseur', 'raison_sociale')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('raison_sociale')->required(),
                        ]),

                    Forms\Components\DatePicker::make('date_facture')
                        ->label('Date de la facture proforma')
                        ->required()
                        ->default(now())
                        ->maxDate(now()),

                    Forms\Components\TextInput::make('objet')
                        ->label('Objet')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Lignes de la facture proforma')
                ->schema([
                    Forms\Components\Repeater::make('lignes')
                        ->relationship('lignes')
                        ->label('')
                        ->schema([
                            Forms\Components\Radio::make('mode_saisie')
                                ->label('Mode de saisie')
                                ->options([
                                    'mercuriale' => '📋 Référence Mercuriale',
                                    'libre'      => '✏️ Saisie libre',
                                ])
                                ->default('libre')
                                ->live()
                                ->columnSpanFull()
                                ->dehydrated(false),

                            Forms\Components\Select::make('reference_mercuriale_id')
                                ->label('Référence Mercuriale')
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search) {
                                    return ReferenceMercuriale::actif()
                                        ->where(function ($q) use ($search) {
                                            $q->where('code_reference', 'ilike', "%{$search}%")
                                                ->orWhere('designation', 'ilike', "%{$search}%");
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($r) => [$r->id => $r->libelle_complet])
                                        ->toArray();
                                })
                                ->getOptionLabelUsing(fn($value) => ReferenceMercuriale::find($value)?->libelle_complet)
                                ->visible(fn(callable $get) => $get('mode_saisie') === 'mercuriale')
                                ->required(fn(callable $get) => $get('mode_saisie') === 'mercuriale')
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if (!$state) return;
                                    $ref = ReferenceMercuriale::find($state);
                                    if ($ref) {
                                        $set('designation', $ref->designation);
                                        $set('unite', $ref->unite);
                                        $set('prix_unitaire_ht', (float) $ref->prix_reference);
                                    }
                                })
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('designation')
                                ->label('Désignation')
                                ->required()
                                ->disabled(fn(callable $get) => $get('mode_saisie') === 'mercuriale' && filled($get('reference_mercuriale_id')))
                                ->dehydrated()
                                ->columnSpanFull(),

                            Forms\Components\Grid::make(5)->schema([
                                Forms\Components\TextInput::make('unite')
                                    ->label('Unité')
                                    ->maxLength(30),

                                Forms\Components\TextInput::make('quantite')
                                    ->label('Quantité')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(0.001)
                                    ->step(0.001),

                                Forms\Components\TextInput::make('prix_unitaire_ht')
                                    ->label('Prix Unitaire HT')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->suffix('FCFA')
                                    ->disabled(fn(callable $get) => $get('mode_saisie') === 'mercuriale' && filled($get('reference_mercuriale_id')))
                                    ->dehydrated(),

                                Forms\Components\TextInput::make('taux_tva')
                                    ->label('Taux TVA (%)')
                                    ->numeric()
                                    ->default(19.25)
                                    ->suffix('%'),

                                Forms\Components\TextInput::make('taux_ir')
                                    ->label('Taux IR (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->helperText('Laisser vide pour un calcul automatique (barème par défaut)'),
                            ]),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                            if (($data['mode_saisie'] ?? 'libre') === 'libre') {
                                $data['reference_mercuriale_id'] = null;
                            }
                            unset($data['mode_saisie']);
                            return $data;
                        })
                        ->addActionLabel('➕ Ajouter une ligne')
                        ->itemLabel(fn(array $state): ?string => $state['designation'] ?? 'Nouvelle ligne')
                        ->columnSpanFull()
                        ->minItems(1)
                        ->required(),
                ]),
        ]);
    }
}
