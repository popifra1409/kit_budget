<?php

namespace App\Filament\Budget\Resources\ClotureExerciceResource\Pages;

use App\Filament\Budget\Resources\ClotureExerciceResource;
use App\Services\Budget\ClotureExerciceService;
use App\Services\ParametresExecution;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/** Circuit : recalcul → arrêté de l'ordonnateur → avis conforme du CA (→ reprise en N+1 : étape 5b). */
class ViewClotureExercice extends ViewRecord
{
    protected static string $resource = ClotureExerciceResource::class;

    protected function service(): ClotureExerciceService
    {
        return app(ClotureExerciceService::class);
    }

    protected function executer(callable $etape, string $succes): void
    {
        try {
            $etape();
            Notification::make()->success()->title($succes)->send();
            $this->redirect(ClotureExerciceResource::getUrl('view', ['record' => $this->getRecord()]));
        } catch (\DomainException $e) {
            Notification::make()->warning()->title('Action impossible')->body($e->getMessage())->persistent()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $r = fn() => $this->getRecord();
        $piece = fn(string $nom, string $label) => Forms\Components\FileUpload::make($nom)->label($label)
            ->disk('public')->directory('clotures')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']);

        return [
            // ── Exports : dossier PDF (conseil d'administration) et classeur Excel ──
            Actions\ActionGroup::make([
                Actions\Action::make('export_pdf')
                    ->label('Dossier PDF')->icon('heroicon-o-document-arrow-down')
                    ->url(fn() => route('budget.cloture.pdf', ['cloture' => $r()]))
                    ->openUrlInNewTab(),
                Actions\Action::make('export_excel')
                    ->label('Classeur Excel')->icon('heroicon-o-table-cells')
                    ->url(fn() => route('budget.cloture.excel', ['cloture' => $r()]))
                    ->openUrlInNewTab(),
            ])
                ->label('Exporter')->icon('heroicon-o-arrow-down-tray')->color('gray')->button(),

            Actions\Action::make('recalculer')
                ->label('Recalculer la situation')->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn() => $r()->statut === 'preparation' && auth()->user()?->can('gerer_cloture_exercice'))
                ->requiresConfirmation()
                ->modalDescription('Les montants sont recalculés à partir des engagements et des paiements. Les reports modifiés à la main sont conservés s\'ils restent valides.')
                ->action(fn() => $this->executer(fn() => $this->service()->calculer($r()), 'Situation recalculée')),

            Actions\Action::make('arreter')
                ->label("Arrêter les reports (ordonnateur)")->icon('heroicon-o-document-check')->color('warning')
                ->visible(fn() => $r()->statut === 'preparation' && auth()->user()?->can('arreter_reports_credits'))
                ->modalDescription(fn() => 'Reports retenus : ' . number_format((float) ($r()->totaux['report_retenu'] ?? 0), 0, ',', ' ')
                    . ' FCFA ; annulations : ' . number_format((float) ($r()->totaux['annule'] ?? 0), 0, ',', ' ') . ' FCFA. '
                    . (ParametresExecution::get('decideur_report') === 'ordonnateur_avis_ca' ? "L'avis conforme du CA sera ensuite requis." : ''))
                ->form([
                    Forms\Components\TextInput::make('reference_arrete')->label("Référence de l'arrêté")->required(),
                    Forms\Components\DatePicker::make('date_arrete')->label('Date')->default(now())->required(),
                    $piece('piece_arrete', 'Arrêté signé'),
                ])
                ->action(fn(array $data) => $this->executer(fn() => $this->service()->arreter($r(), $data), 'Arrêté de report enregistré')),

            Actions\Action::make('avis_ca')
                ->label('Avis du conseil d\'administration')->icon('heroicon-o-building-library')->color('info')
                ->visible(fn() => $r()->statut === 'arretee' && auth()->user()?->can('enregistrer_avis_ca'))
                ->form([
                    Forms\Components\Radio::make('avis_ca')->label('Avis')->options(['conforme' => 'Conforme', 'defavorable' => 'Défavorable'])->required()->live(),
                    Forms\Components\TextInput::make('reference_avis_ca')->label('Référence (délibération)')->required(),
                    Forms\Components\DatePicker::make('date_avis_ca')->label('Date')->default(now())->required(),
                    Forms\Components\Textarea::make('motif')->label('Motif (avis défavorable)')
                        ->visible(fn(Forms\Get $get) => $get('avis_ca') === 'defavorable')->required(fn(Forms\Get $get) => $get('avis_ca') === 'defavorable'),
                    $piece('piece_avis_ca', 'Délibération'),
                ])
                ->action(fn(array $data) => $this->executer(
                    fn() => $this->service()->enregistrerAvisCa($r(), $data),
                    $data['avis_ca'] === 'conforme' ? 'Avis conforme enregistré' : 'Avis défavorable : reports à revoir (retour en préparation)'
                )),

            Actions\Action::make('reprendre_n1')
                ->label('Reprendre les reports en N+1')->icon('heroicon-o-arrow-right-circle')->color('success')
                ->visible(fn() => $r()->statut === 'avis_ca' && auth()->user()?->can('gerer_cloture_exercice'))
                ->requiresConfirmation()
                ->modalHeading('Reprise des reports dans l\'exercice suivant')
                ->modalDescription(function () use ($r) {
                    $suivant = $this->service()->exerciceSuivant($r());
                    $budget = $suivant ? $this->service()->budgetSuivant($suivant) : null;
                    return $suivant && $budget
                        ? "Un PROJET de collectif « Reports de crédits de l'exercice {$r()->exercice->annee} » sera créé dans l'exercice {$suivant->annee} "
                        . "(budget {$budget->libelle}), pour " . number_format((float) ($r()->totaux['report_retenu'] ?? 0), 0, ',', ' ') . ' FCFA. '
                        . 'Il sera sans effet tant qu\'il n\'est pas adopté.'
                        : 'Exercice ' . ($r()->exercice->annee + 1) . ' ou son budget introuvable : créez-les avant la reprise.';
                })
                ->action(function () use ($r) {
                    try {
                        $b = $this->service()->reprendreEnN1($r());
                        $corps = "Collectif {$b['collectif']} (projet) : {$b['lignes_reprises']} ligne(s), "
                            . number_format($b['montant_repris'], 0, ',', ' ') . ' FCFA.'
                            . (count($b['lignes_manquantes']) ? "\n⚠️ " . count($b['lignes_manquantes']) . " ligne(s) absente(s) du budget {$b['exercice_suivant']} : voir la fiche." : '')
                            . ($b['avertissement_recette'] ? "\n⚠️ " . $b['avertissement_recette'] : '');
                        Notification::make()->success()->title('Reports repris en ' . $b['exercice_suivant'])->body($corps)->persistent()->send();
                        $this->redirect(\App\Filament\Budget\Resources\ClotureExerciceResource::getUrl('view', ['record' => $r()]));
                    } catch (\DomainException $e) {
                        Notification::make()->warning()->title('Reprise impossible')->body($e->getMessage())->persistent()->send();
                    }
                }),

            Actions\Action::make('rouvrir')
                ->label('Retour en préparation')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                ->visible(fn() => $r()->statut === 'arretee' && auth()->user()?->can('arreter_reports_credits'))
                ->form([Forms\Components\Textarea::make('motif')->required()])
                ->action(fn(array $data) => $this->executer(fn() => $this->service()->rouvrirPreparation($r(), $data['motif']), 'Clôture remise en préparation')),
        ];
    }
}
