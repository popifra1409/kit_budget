<?php

namespace App\Filament\Budget\Resources\RegieAvanceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Notifications\Notification;
use App\Models\ParametresStructure;

class DepensesRelationManager extends RelationManager
{
    protected static string $relationship = 'depenses';
    protected static ?string $title       = 'Dépenses';

    public function form(Forms\Form $form): Forms\Form
    {
        $regie = $this->getOwnerRecord();
        $seuil = ParametresStructure::where('actif', true)
            ->value('seuil_achat_direct_regie') ?? 500000;

        return $form->schema([
            Forms\Components\Grid::make(3)->schema([

                Forms\Components\DatePicker::make('date_depense')
                    ->label('Date')->default(now())->required(),

                // ── Ligne régie concernée ─────────────────────
                Forms\Components\Select::make('ligne_regie_avance_id')
                    ->label('Ligne budgétaire')
                    ->options(function () use ($regie) {
                        return $regie->lignes()
                            ->with('nomenclature')
                            ->get()
                            ->mapWithKeys(fn($l) => [
                                $l->id => "{$l->nomenclature->code} — {$l->nomenclature->libelle} "
                                    . "(Dispo: " . number_format($l->montant_disponible, 0, ',', ' ') . " FCFA)"
                            ]);
                    })
                    ->required()->searchable(),

                // ── Décaissement rattaché ─────────────────────
                Forms\Components\Select::make('decaissement_regie_id')
                    ->label('Tranche (décaissement)')
                    ->options(function () use ($regie) {
                        return $regie->decaissements()
                            ->whereIn('statut', ['verse', 'apure'])
                            ->get()
                            ->mapWithKeys(fn($d) => [
                                $d->id => "{$d->numero} — {$d->libelle_tranche} "
                                    . "(" . number_format($d->montant_accorde, 0, ',', ' ') . " FCFA)"
                            ]);
                    })
                    ->searchable()->nullable()
                    ->helperText('Optionnel — rattacher à une tranche versée'),
            ]),

            Forms\Components\TextInput::make('objet')
                ->label('Objet de la dépense')
                ->required()->maxLength(255)->columnSpanFull(),

            Forms\Components\Grid::make(3)->schema([
                // ── Fournisseur ───────────────────────────────
                Forms\Components\Select::make('fournisseur_id')
                    ->label('Fournisseur référencé')
                    ->relationship('fournisseur', 'raison_sociale')
                    ->searchable()->preload()->nullable(),

                Forms\Components\TextInput::make('fournisseur_libre')
                    ->label('Ou fournisseur libre (non référencé)')
                    ->maxLength(255)
                    ->placeholder('Nom du fournisseur'),

                Forms\Components\Placeholder::make('seuil_info')
                    ->label('Seuil achat direct')
                    ->content(new \Illuminate\Support\HtmlString(
                        '<span class="text-sm text-gray-500">Seuil : '
                            . number_format($seuil, 0, ',', ' ')
                            . ' FCFA TTC — en dessous = achat direct, au-dessus = BCR/BCM</span>'
                    )),
            ]),

            // ── Montants ──────────────────────────────────────
            Forms\Components\Section::make('Montants')
                ->schema([
                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\TextInput::make('montant_ht')
                            ->label('Montant HT')
                            ->numeric()->default(0)->prefix('FCFA')
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            ),

                        Forms\Components\TextInput::make('taux_tva')
                            ->label('TVA %')->numeric()
                            ->default(19.25)->suffix('%')
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            ),

                        Forms\Components\TextInput::make('taux_ir')
                            ->label('IR %')->numeric()
                            ->default(0)->suffix('%')
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn(Get $get, Set $set) =>
                                static::recalculer($get, $set)
                            ),

                        Forms\Components\Placeholder::make('type_depense_affiche')
                            ->label('Type détecté')
                            ->content(function (Get $get) use ($seuil) {
                                $ttc = (float) ($get('montant_ttc') ?? 0);
                                if ($ttc <= 0) return '—';
                                return $ttc <= $seuil
                                    ? '✅ Achat direct'
                                    : '📋 Bon de commande requis';
                            }),
                    ]),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('montant_tva')
                            ->label('Montant TVA')->numeric()
                            ->prefix('FCFA')->disabled()->dehydrated(),

                        Forms\Components\TextInput::make('montant_ttc')
                            ->label('Montant TTC')->numeric()
                            ->prefix('FCFA')->disabled()->dehydrated(),

                        Forms\Components\TextInput::make('montant_ir')
                            ->label('Montant IR')->numeric()
                            ->prefix('FCFA')->disabled()->dehydrated(),
                    ]),

                    Forms\Components\TextInput::make('net_a_payer')
                        ->label('Net à payer')->numeric()
                        ->prefix('FCFA')->disabled()->dehydrated(),

                    // ✅ type_depense caché — calculé automatiquement
                    Forms\Components\Hidden::make('type_depense')->default('achat_direct'),
                ])
                ->columns(1),

            Forms\Components\Textarea::make('observations')
                ->label('Observations')->rows(2)->columnSpanFull(),
        ]);
    }

    protected static function recalculer(Get $get, Set $set): void
    {
        $ht     = (float) ($get('montant_ht')  ?? 0);
        $tauxTv = (float) ($get('taux_tva')    ?? 19.25);
        $tauxIr = (float) ($get('taux_ir')     ?? 0);

        $tva = round($ht * ($tauxTv / 100), 2);
        $ttc = round($ht + $tva, 2);
        $ir  = round($ht * ($tauxIr / 100), 2);
        $net = round($ht - $ir, 2);

        $set('montant_tva', $tva);
        $set('montant_ttc', $ttc);
        $set('montant_ir',  $ir);
        $set('net_a_payer', $net);

        // ✅ Détecter le type automatiquement
        $seuil = ParametresStructure::where('actif', true)
            ->value('seuil_achat_direct_regie') ?? 500000;
        $set('type_depense', $ttc <= $seuil ? 'achat_direct' : 'bon_commande');
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')->weight('bold')->copyable(),
                Tables\Columns\TextColumn::make('date_depense')
                    ->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(35)
                    ->tooltip(fn($record) => $record->objet),
                Tables\Columns\TextColumn::make('type_depense')
                    ->label('Type')->badge()
                    ->color(fn($state) => $state === 'achat_direct' ? 'success' : 'info')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'achat_direct'  => 'Achat direct',
                        'bon_commande'  => 'BCR/BCM',
                        default         => $state,
                    }),
                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('TTC')->money('XAF')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF'),
                    ]),
                Tables\Columns\TextColumn::make('montant_ir')
                    ->label('IR')->money('XAF')->color('warning')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF'),
                    ]),
                Tables\Columns\TextColumn::make('net_a_payer')
                    ->label('Net')->money('XAF')->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('XAF'),
                    ]),
                Tables\Columns\BadgeColumn::make('statut')
                    ->colors([
                        'gray'    => 'brouillon',
                        'warning' => 'valide',
                        'success' => 'paye',
                        'danger'  => 'annule',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nouvelle dépense')
                    ->visible(fn() => $this->getOwnerRecord()->statut === 'actif'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->statut === 'brouillon'),
                Tables\Actions\Action::make('valider')
                    ->label('Valider')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'brouillon'
                            && auth()->user()?->can('valider_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['statut' => 'valide'])),
                Tables\Actions\Action::make('annuler')
                    ->label('Annuler')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(
                        fn($record) =>
                        in_array($record->statut, ['brouillon', 'valide'])
                            && auth()->user()?->can('annuler_depense_regie')
                    )
                    ->requiresConfirmation()
                    ->action(fn($record) => $record->update(['statut' => 'annule'])),
            ])
            ->defaultSort('date_depense', 'desc');
    }
}
