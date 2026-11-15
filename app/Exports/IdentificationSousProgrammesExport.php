<?php

namespace App\Exports;

use App\Models\PlanStrategiqueEp;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class IdentificationSousProgrammesExport implements FromCollection, WithHeadings
{
    public function __construct(protected PlanStrategiqueEp $psp) {}

    public function headings(): array
    {
        return [
            'Sous-Programme',
            'Programme de rattachement',
            'Objectif',
            'Indicateur',
            'Unité',
            'Valeur de référence',
            'Valeur cible (fin de période)',
            'Stratégie',
            'Cadre institutionnel',
            'Responsable',
        ];
    }

    /**
     * Une ligne par indicateur, pas une ligne par sous-programme : l'ancien pli « Indicateurs »
     * juxtaposait les libellés séparés par « | » et perdait les références et les cibles que le
     * tableau 14 existe justement à porter. Les colonnes du sous-programme sont répétées sur
     * chacune de ses lignes.
     */
    public function collection()
    {
        return $this->psp->sousProgrammes->flatMap(function ($sp) {
            // Une ligne avalée par le writer serait un décalage de toutes les suivantes : le
            // sous-programme sans indicateur rend ses cellules vides, jamais un tableau vide.
            $video = ['', '', '', ''];

            if ($sp->indicateurs->isEmpty()) {
                return [array_merge($this->entete($sp), $video, $this->queue($sp))];
            }

            return $sp->indicateurs->map(fn($ind) => array_merge(
                $this->entete($sp),
                [$ind->libelle, $ind->unite_mesure ?? '', $ind->referenceFormatee(), $ind->cibleFormatee()],
                $this->queue($sp),
            ))->all();
        })->values();
    }

    private function entete($sp): array
    {
        return [
            $sp->libelle,
            $sp->programmeBudgetaire
                ? "{$sp->programmeBudgetaire->code} - {$sp->programmeBudgetaire->libelle}"
                : '—',
            $sp->objectif ?? '',
        ];
    }

    private function queue($sp): array
    {
        return [$sp->strategie ?? '', $sp->cadre_institutionnel ?? '', $sp->responsable?->nom_complet ?? ''];
    }
}
