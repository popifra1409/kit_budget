<?php

namespace App\Filament\Programmation\Resources\CdmtExerciceResource\Pages;

use App\Filament\Programmation\Resources\CdmtExerciceResource;
use App\Models\CdmtExercice;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

/**
 * Consultation d'un CDMT, quel que soit son statut (brouillon, transmis, validé) :
 * synthèse, soutenabilité face au CBMT (total et par titre), arbitrages, lignes en lecture seule
 * dès que le CDMT n'est plus modifiable.
 */
class ViewCdmtExercice extends ViewRecord
{
    protected static string $resource = CdmtExerciceResource::class;

    protected const STATUTS = [
        'brouillon'       => 'Brouillon',
        'en_transmission' => 'En transmission',
        'valide'          => 'Validé',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn(CdmtExercice $record) => $record->estModifiable()),

            Actions\ActionGroup::make([
                Actions\Action::make('excel')
                    ->label('Rapport CBMT/CDMT (Excel)')->icon('heroicon-o-table-cells')
                    ->url(fn(CdmtExercice $record) => route('programmation.rapports.cbmt-cdmt.excel', ['cdmt' => $record->id]))
                    ->openUrlInNewTab(),
                Actions\Action::make('pdf')
                    ->label('Rapport CBMT/CDMT (PDF)')->icon('heroicon-o-document-arrow-down')
                    ->url(fn(CdmtExercice $record) => route('programmation.rapports.cbmt-cdmt.pdf', ['cdmt' => $record->id]))
                    ->openUrlInNewTab(),
            ])->label('Exporter')->icon('heroicon-o-arrow-down-tray')->color('gray')->button(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');

        return $infolist->schema([
            // ── Identification ──
            Infolists\Components\Section::make('Identification')
                ->schema([
                    Infolists\Components\TextEntry::make('numero')->label('N°')->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('cbmtExercice.numero')->label('CBMT de rattachement')->badge()->color('info'),
                    Infolists\Components\TextEntry::make('annee_reference')->label('Exercice de référence (N)')
                        ->getStateUsing(fn(CdmtExercice $record) => $record->anneeReference()),
                    Infolists\Components\TextEntry::make('version')->badge()
                        ->formatStateUsing(fn($state) => $state === 'final' ? 'CDMT final' : 'CDMT initial')
                        ->color(fn($state) => $state === 'final' ? 'success' : 'gray'),
                    Infolists\Components\TextEntry::make('statut')->badge()
                        ->formatStateUsing(fn($state) => self::STATUTS[$state] ?? $state)
                        ->color(fn($state) => match ($state) {
                            'valide' => 'success',
                            'en_transmission' => 'warning',
                            default => 'gray'
                        })
                        ->helperText(fn(CdmtExercice $record) => $record->estModifiable() ? null : 'Lecture seule : le CDMT n\'est plus modifiable.'),
                    Infolists\Components\TextEntry::make('date_cdmt_initial')->label('CDMT initial')->date('d/m/Y')->placeholder('—'),
                    Infolists\Components\TextEntry::make('date_cdmt_final')->label('CDMT final')->date('d/m/Y')->placeholder('—'),
                ])
                ->columns(4),

            // ── Synthèse ──
            Infolists\Components\Section::make('Synthèse de la programmation')
                ->schema([
                    Infolists\Components\TextEntry::make('synthese')
                        ->label('')
                        ->getStateUsing(fn(CdmtExercice $record) => $this->tableauSynthese($record))
                        ->html()
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('nb_lignes')->label('Lignes')
                        ->getStateUsing(fn(CdmtExercice $record) => $record->lignes()->count()),
                    Infolists\Components\TextEntry::make('nb_mn')->label('Mesures nouvelles (MN)')
                        ->getStateUsing(fn(CdmtExercice $record) => $record->lignes()->where('nature', 'MN')->count()),
                    Infolists\Components\TextEntry::make('nb_invest')->label('Lignes d\'investissement')
                        ->getStateUsing(fn(CdmtExercice $record) => $record->lignes()->where('est_investissement', true)->count()),
                    Infolists\Components\TextEntry::make('nb_a_completer')->label('Lignes à compléter (AE ou CP manquants)')
                        ->getStateUsing(fn(CdmtExercice $record) => $record->lignes()->aCompleter()->count())
                        ->badge()
                        ->color(fn($state) => (int) $state > 0 ? 'warning' : 'success'),
                ])
                ->columns(4),

            // ── Soutenabilité ──
            Infolists\Components\Section::make('Soutenabilité face au plafond du CBMT')
                ->visible(fn(CdmtExercice $record) => $record->cbmtExercice !== null)
                ->schema([
                    Infolists\Components\TextEntry::make('ecart_total')
                        ->label('Au total (CP)')
                        ->getStateUsing(fn(CdmtExercice $record) => $this->tableauEcart($record))
                        ->html()->columnSpanFull(),
                    Infolists\Components\TextEntry::make('ecart_titres')
                        ->label('Par titre (CP)')
                        ->getStateUsing(fn(CdmtExercice $record) => $this->tableauTitres($record))
                        ->html()->columnSpanFull(),
                ])
                ->collapsible(),

            // ── Arbitrages ──
            Infolists\Components\Section::make('Arbitrages')
                ->visible(fn(CdmtExercice $record) => filled($record->note_arbitrages))
                ->schema([
                    Infolists\Components\TextEntry::make('note_arbitrages')->label('')->columnSpanFull(),
                ])
                ->collapsible(),
        ]);
    }

    // ── Tableaux HTML ─────────────────────────────────────────

    protected function cellule(string $contenu, string $style = ''): string
    {
        return '<td style="border:1px solid #d1d5db;padding:4px 8px;text-align:right;' . $style . '">' . $contenu . '</td>';
    }

    protected function entete(array $titres): string
    {
        return '<tr>' . collect($titres)->map(fn($t, $i) => '<th style="border:1px solid #d1d5db;padding:4px 8px;background:#f3f4f6;'
            . ($i === 0 ? 'text-align:left;' : '') . '">' . e($t) . '</th>')->implode('') . '</tr>';
    }

    protected function tableauSynthese(CdmtExercice $cdmt): HtmlString
    {
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');
        $n = $cdmt->anneeReference();
        $l = $cdmt->lignes()->get();

        $html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . $this->entete(['', "{$n} (référence)", (string) ($n + 1), (string) ($n + 2), (string) ($n + 3)]);

        foreach (['ae' => 'AE', 'cp' => 'CP'] as $champ => $libelle) {
            $html .= '<tr><td style="border:1px solid #d1d5db;padding:4px 8px;font-weight:600;">' . $libelle . '</td>'
                . $this->cellule($f($l->sum("n_{$champ}")))
                . collect([1, 2, 3])->map(fn($d) => $this->cellule($f($l->sum("n_plus_{$d}_{$champ}"))))->implode('')
                . '</tr>';
        }

        return new HtmlString($html . '</table></div>');
    }

    protected function tableauEcart(CdmtExercice $cdmt): HtmlString
    {
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');
        $n = $cdmt->anneeReference();

        $html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . $this->entete(['Année', 'Plafond CBMT', 'CP programmés', 'Écart (plafond − programmé)']);

        foreach ($cdmt->getEcartAvecCbmt() as $cle => $e) {
            $d = (int) substr($cle, -1);
            $couleur = $e['ecart'] < -0.5 ? 'color:#b91c1c;font-weight:600;' : 'color:#166534;font-weight:600;';
            $html .= '<tr><td style="border:1px solid #d1d5db;padding:4px 8px;">' . ($n + $d) . '</td>'
                . $this->cellule($f($e['plafond'])) . $this->cellule($f($e['programme']))
                . $this->cellule(($e['ecart'] < -0.5 ? '⚠️ ' : '✅ ') . $f($e['ecart']), $couleur) . '</tr>';
        }

        return new HtmlString($html . '</table></div>');
    }

    protected function tableauTitres(CdmtExercice $cdmt): HtmlString
    {
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');
        $n = $cdmt->anneeReference();

        $entetes = ['Titre'];
        foreach ([1, 2, 3] as $d) {
            array_push($entetes, ($n + $d) . ' plafond', ($n + $d) . ' programmé', ($n + $d) . ' écart');
        }

        $html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;">' . $this->entete($entetes);

        foreach ($cdmt->getControleParTitre() as $ligne) {
            $html .= '<tr><td style="border:1px solid #d1d5db;padding:4px 8px;">' . e($ligne['libelle']) . '</td>';
            foreach ($ligne['annees'] as $a) {
                $couleur = $a['ecart'] < -0.5 ? 'color:#b91c1c;font-weight:600;' : 'color:#166534;';
                $html .= $this->cellule($f($a['plafond'])) . $this->cellule($f($a['programme'])) . $this->cellule($f($a['ecart']), $couleur);
            }
            $html .= '</tr>';
        }

        return new HtmlString($html . '</table></div><p style="font-size:11px;color:#6b7280;margin-top:4px;">En rouge : dépassement du plafond du titre.</p>');
    }
}
