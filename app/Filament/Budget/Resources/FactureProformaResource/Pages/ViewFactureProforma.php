<?php

namespace App\Filament\Budget\Resources\FactureProformaResource\Pages;

use App\Filament\Budget\Resources\FactureProformaResource;
use App\Filament\Budget\Resources\BonCommandeResource;
use App\Models\LigneFactureProforma;
use App\Models\BonCommande;
use App\Models\LigneBonCommande;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewFactureProforma extends ViewRecord
{
    protected static string $resource = FactureProformaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ✅ Aperçu — disponible même en brouillon
            Actions\Action::make('apercu')
                ->label('👁 Aperçu')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading(fn() => 'Aperçu — ' . $this->record->numero)
                ->modalWidth('4xl')
                ->modalContent(fn() => view('pdf.templates.facture-proforma', [
                    'donnees' => [
                        '_raw'         => $this->record->load(['lignes', 'fournisseur']),
                        '_etat_config' => null,
                    ],
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer'),

            // ✅ Export PDF
            Actions\Action::make('exportPdf')
                ->label('📄 Export PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('danger')
                ->action(function () {
                    $facture = $this->record->load(['lignes', 'fournisseur']);

                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.templates.facture-proforma', [
                        'donnees' => ['_raw' => $facture, '_etat_config' => null],
                    ])->setPaper('a4', 'portrait');

                    return response()->streamDownload(
                        fn() => print($pdf->output()),
                        "Facture-Proforma-{$facture->numero}.pdf"
                    );
                }),

            // ✅ Export Word (.docx) — pour modification sous MS Office
            Actions\Action::make('exportWord')
                ->label('📝 Export Word')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->action(function () {
                    return $this->genererWord();
                }),

            Actions\EditAction::make()
                ->visible(fn() => $this->record->estModifiable()),

            // ✅ Action clé — génère un Bon de Commande à partir des lignes
            //    sélectionnées (celles pas encore reprises dans un autre BC).
            Actions\Action::make('genererBonCommande')
                ->label('📄 Générer Bon de Commande')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->visible(
                    fn() =>
                    in_array($this->record->statut, ['validee', 'utilisee'])
                        && $this->record->lignes()->whereNull('ligne_bon_commande_id')->exists()
                )
                ->form(function () {
                    $lignesDisponibles = $this->record->lignes()
                        ->whereNull('ligne_bon_commande_id')
                        ->get();

                    return [
                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content('Sélectionnez les lignes à reprendre dans le nouveau Bon de Commande. Les lignes déjà utilisées dans un autre BC n\'apparaissent pas ici.'),

                        Forms\Components\CheckboxList::make('lignes_ids')
                            ->label('Lignes disponibles')
                            ->options(
                                $lignesDisponibles->mapWithKeys(fn($l) => [
                                    $l->id => "{$l->designation} — Qté: {$l->quantite} × "
                                        . number_format($l->prix_unitaire_ht, 0, ',', ' ') . " FCFA = "
                                        . number_format($l->montant_ttc, 0, ',', ' ') . " FCFA TTC"
                                        . ($l->estIssueMercuriale() ? ' 📋' : ' ✏️'),
                                ])
                            )
                            ->required()
                            ->columns(1)
                            ->bulkToggleable(),
                    ];
                })
                ->requiresConfirmation()
                ->modalHeading('Générer un Bon de Commande')
                ->modalDescription('Un nouveau BC en brouillon sera créé avec les lignes sélectionnées. Vous pourrez ensuite compléter les informations manquantes (type d\'engagement, service demandeur, ligne d\'imputation...) avant de le valider.')
                ->action(function (array $data) {
                    $lignesSelectionnees = LigneFactureProforma::whereIn('id', $data['lignes_ids'])->get();

                    if ($lignesSelectionnees->isEmpty()) {
                        Notification::make()
                            ->title('❌ Aucune ligne sélectionnée')
                            ->danger()->send();
                        return;
                    }

                    $bc = null;

                    DB::transaction(function () use ($lignesSelectionnees, &$bc) {
                        $bc = BonCommande::create([
                            'fournisseur_id' => $this->record->fournisseur_id,
                            'objet'          => $this->record->objet,
                            'statut'         => 'brouillon',
                            'observations'   => "Généré depuis la facture proforma {$this->record->numero}",
                            'created_by'     => auth()->id(),
                        ]);

                        foreach ($lignesSelectionnees as $ligne) {
                            $ligneBC = LigneBonCommande::create([
                                'bon_commande_id'         => $bc->id,
                                'reference_mercuriale_id' => $ligne->reference_mercuriale_id,
                                'designation'             => $ligne->designation,
                                'unite'                   => $ligne->unite,
                                'quantite'                => $ligne->quantite,
                                'prix_unitaire_ht'        => $ligne->prix_unitaire_ht,
                                'taux_tva'                => $ligne->taux_tva,
                            ]);

                            $ligne->update(['ligne_bon_commande_id' => $ligneBC->id]);
                        }

                        // ✅ Marquer la proforma "utilisée" si toutes ses lignes
                        //    sont désormais reprises dans un BC (partiellement
                        //    ou totalement — reste réutilisable sinon).
                        if ($this->record->fresh()->estEntierementUtilisee()) {
                            $this->record->update(['statut' => 'utilisee']);
                        }
                    });

                    Notification::make()
                        ->title('✅ Bon de commande créé : ' . $bc->numero)
                        ->body('Complétez les informations manquantes avant de le valider.')
                        ->success()
                        ->send();

                    $this->redirect(BonCommandeResource::getUrl('edit', ['record' => $bc]));
                }),
        ];
    }

    /**
     * ✅ Génère un document Word (.docx) éditable, avec la même structure
     * que le PDF — pratique pour ajuster/annoter la proforma sous MS Office
     * avant de la renvoyer au fournisseur pour signature par exemple.
     */
    protected function genererWord()
    {
        $facture = $this->record->load(['lignes', 'fournisseur']);

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'marginTop' => 700,
            'marginBottom' => 700,
            'marginLeft' => 900,
            'marginRight' => 900,
        ]);

        $section->addText(
            'FACTURE PROFORMA N° ' . $facture->numero,
            ['bold' => true, 'size' => 14],
            ['alignment' => 'center', 'spaceAfter' => 200]
        );

        // ── Infos générales ──────────────────────────────────
        $infoTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 60]);
        $infoTable->addRow();
        $infoTable->addCell(2500)->addText('Fournisseur :', ['bold' => true]);
        $infoTable->addCell(6500)->addText($facture->fournisseur?->raison_sociale ?? '');
        $infoTable->addRow();
        $infoTable->addCell(2500)->addText('Date :', ['bold' => true]);
        $infoTable->addCell(6500)->addText($facture->date_facture?->format('d/m/Y') ?? '');
        $infoTable->addRow();
        $infoTable->addCell(2500)->addText('Objet :', ['bold' => true]);
        $infoTable->addCell(6500)->addText($facture->objet);

        $section->addTextBreak(1);

        // ── Tableau des lignes ───────────────────────────────
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 80,
        ];
        $headerStyle = ['bgColor' => 'E6E6E6', 'bold' => true];

        $table = $section->addTable($tableStyle);

        $table->addRow();
        $table->addCell(500, $headerStyle)->addText('N°', $headerStyle);
        $table->addCell(2800, $headerStyle)->addText('Désignation', $headerStyle);
        $table->addCell(800, $headerStyle)->addText('Unité', $headerStyle);
        $table->addCell(800, $headerStyle)->addText('Qté', $headerStyle);
        $table->addCell(1200, $headerStyle)->addText('P.U HT', $headerStyle);
        $table->addCell(800, $headerStyle)->addText('TVA', $headerStyle);
        $table->addCell(1200, $headerStyle)->addText('Montant TTC', $headerStyle);
        $table->addCell(1000, $headerStyle)->addText('IR', $headerStyle);
        $table->addCell(1200, $headerStyle)->addText('Net à Percevoir', $headerStyle);

        foreach ($facture->lignes as $i => $ligne) {
            $table->addRow();
            $table->addCell(500)->addText((string) ($i + 1));
            $table->addCell(2800)->addText($ligne->designation);
            $table->addCell(800)->addText($ligne->unite ?? '-');
            $table->addCell(800)->addText(number_format($ligne->quantite, 2, ',', ' '));
            $table->addCell(1200)->addText(number_format($ligne->prix_unitaire_ht, 0, ',', ' '));
            $table->addCell(800)->addText(number_format($ligne->taux_tva, 2, ',', '') . '%');
            $table->addCell(1200)->addText(number_format($ligne->montant_ttc, 0, ',', ' '));
            $table->addCell(1000)->addText(number_format($ligne->montant_ir, 0, ',', ' '));
            $table->addCell(1200)->addText(number_format($ligne->net_a_percevoir, 0, ',', ' '));
        }

        $table->addRow();
        $totalStyle = ['bgColor' => 'F0F0F0', 'bold' => true];
        $table->addCell(5900, $totalStyle)->addText('TOTAL', $totalStyle, ['alignment' => 'right']);
        $table->addCell(1200, $totalStyle)->addText(number_format($facture->montant_ht, 0, ',', ' '), $totalStyle);
        $table->addCell(800, $totalStyle)->addText('');
        $table->addCell(1200, $totalStyle)->addText(number_format($facture->montant_ttc, 0, ',', ' '), $totalStyle);
        $table->addCell(1000, $totalStyle)->addText(number_format($facture->montant_ir, 0, ',', ' '), $totalStyle);
        $table->addCell(1200, $totalStyle)->addText(number_format($facture->net_a_percevoir, 0, ',', ' '), $totalStyle);

        $section->addTextBreak(1);
        $section->addText(
            'Arrêtée la présente facture proforma à la somme TTC de '
                . strtoupper(\App\Helpers\NombreEnLettres::montantCFA($facture->montant_ttc ?? 0)) . '.',
            [],
            ['spaceBefore' => 100, 'spaceAfter' => 100]
        );

        if ($facture->observations) {
            $section->addTextBreak(1);
            $section->addText('Observations :', ['bold' => true]);
            $section->addText($facture->observations);
        }

        $section->addTextBreak(3);
        $section->addText('Fait le ' . now()->format('d/m/Y'), [], ['alignment' => 'right']);
        $section->addTextBreak(2);
        $section->addText('Le Fournisseur', ['bold' => true], ['alignment' => 'right']);

        $nomFichier = "Facture-Proforma-{$facture->numero}.docx";
        $cheminTemp = storage_path('app/temp/' . $nomFichier);

        if (!is_dir(dirname($cheminTemp))) {
            mkdir(dirname($cheminTemp), 0755, true);
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($cheminTemp);

        return response()->download($cheminTemp, $nomFichier)->deleteFileAfterSend(true);
    }
}
