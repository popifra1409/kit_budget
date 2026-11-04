<?php

namespace App\Filament\Marches\Resources\FichePresenceResource\Pages;

use App\Filament\Marches\Resources\FichePresenceResource;
use App\Models\FichePresence;
use App\Services\FichePresencePdfService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

/**
 * La retouche d'une fiche de présence dressée : sa date, son lieu, son objet, la pièce
 * scannée, ses observations. Le marché et la commission sont fermés à l'écran — ils
 * disent de quelle séance il s'agit, et le modèle les refuse au changement.
 *
 * La fenêtre de la séance se rejoue ici : le modèle ne contrôle le futur et
 * l'antériorité qu'à la création (hook `creating`), alors que la date, elle, se corrige.
 * Une fiche re-datée avant l'ouverture des plis attestrait une réunion portant sur des
 * plis que personne n'avait encore ouverts.
 */
class EditFichePresence extends EditRecord
{
    protected static string $resource = FichePresenceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;
        $marche = $record instanceof FichePresence ? $record->marche : null;

        if (!$marche || blank($data['date_seance'] ?? null)) {
            return $data;
        }

        $date    = \Illuminate\Support\Carbon::parse($data['date_seance']);
        $refuser = function (string $message) {
            Notification::make()->title('❌ Séance refusée')->body($message)
                ->danger()->persistent()->send();

            throw ValidationException::withMessages(['date_seance' => $message]);
        };

        if ($date->isFuture()) {
            $refuser(sprintf(
                'La séance du %s n\'a pas encore eu lieu : on n\'émarge pas une réunion future, on la constate le jour dit.',
                $date->format('d/m/Y'),
            ));
        }

        if ($marche->date_ouverture_plis && $date->lt($marche->date_ouverture_plis->toDateString())) {
            $refuser(sprintf(
                'La séance du %s serait antérieure à l\'ouverture des plis du marché %s (%s) : la commission '
                    . 'n\'aurait rien eu à examiner ce jour-là.',
                $date->format('d/m/Y'),
                $marche->numero,
                $marche->date_ouverture_plis->format('d/m/Y'),
            ));
        }

        // Une séance d'un même jour pour un même marché ne se dresse pas deux fois, et
        // la fiche retirée compte encore : son numéro se restaure plutôt que de laisser
        // deux feuilles datées le même jour au même dossier.
        $deja = FichePresence::withTrashed()
            ->where('marche_id', $marche->id)
            ->whereDate('date_seance', $date->toDateString())
            ->when($record instanceof FichePresence, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->first();

        if ($deja) {
            $refuser(sprintf(
                'La fiche %s%s porte déjà le %s pour le marché %s : une séance d\'un jour ne se dresse pas deux '
                    . 'fois. Corrigez-la, ou restaurez-la si elle avait été retirée.',
                $deja->numero,
                $deja->trashed() ? ' (retirée)' : '',
                $date->format('d/m/Y'),
                $marche->numero,
            ));
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            // La fiche s'imprime avant la séance et se relit après : l'impression est
            // donc dans l'en-tête de la fiche, à côté de ses émargements.
            Actions\Action::make('telecharger_fiche_presence')
                ->label('Imprimer la fiche')
                ->icon('heroicon-o-document-arrow-down')->color('gray')
                ->visible(fn () => FichePresenceResource::canTelecharger())
                ->action(function () {
                    // visible() ne protège pas : l'action est atteignable par son nom.
                    if (!FichePresenceResource::canTelecharger()) {
                        Notification::make()->title('🔒 Impression refusée')
                            ->body('La permission « telecharger_fiche_presence » est nécessaire pour éditer la fiche.')
                            ->danger()->persistent()->send();

                        throw new Halt;
                    }

                    $fiche = $this->record;

                    return response()->streamDownload(
                        fn () => print((new FichePresencePdfService)->genererPdf($fiche)->output()),
                        FichePresenceResource::nomDuFichier($fiche)
                    );
                }),
            Actions\DeleteAction::make()
                ->before(fn (FichePresence $record) => FichePresenceResource::refuserRetrait($record)),
        ];
    }

    /**
     * Les émargements et les visas se portent sous la fiche : c'est la feuille elle-
     * même, et elle ne se résume pas à son en-tête.
     */
    public function getRelationManagers(): array
    {
        return [
            FichePresenceResource\RelationManagers\MembresRelationManager::class,
        ];
    }

    public function getSubheading(): ?string
    {
        $record = $this->record;

        if (!$record instanceof FichePresence) {
            return null;
        }

        $attentes = $record->attentes();

        return sprintf(
            '%s · commission %s · séance du %s · %d émargement(s), %d visa(s)%s',
            $record->marche?->numero ?? '—',
            $record->commission?->libelle ?? '—',
            $record->date_seance?->format('d/m/Y') ?? '—',
            $record->membres()->count(),
            $record->membresVises()->count(),
            $attentes === [] ? '' : ' · attend : ' . implode(' ; ', $attentes)
        );
    }
}
