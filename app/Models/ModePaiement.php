<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModePaiement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'modes_paiement';

    protected $fillable = [
        'code',
        'libelle',
        'description',
        'icone',
        'actif',
        'ordre',
        'applicable_op',   // applicable aux OP standard
        'applicable_opt',  // applicable aux OPT (impôts)
    ];

    protected $casts = [
        'actif'          => 'boolean',
        'applicable_op'  => 'boolean',
        'applicable_opt' => 'boolean',
        'ordre'          => 'integer',
    ];

    // ── Scopes ───────────────────────────────────────────────
    public function scopeActifs($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }

    public function scopePourOP($query)
    {
        return $query->where('actif', true)->where('applicable_op', true)->orderBy('ordre');
    }

    public function scopePourOPT($query)
    {
        return $query->where('actif', true)->where('applicable_opt', true)->orderBy('ordre');
    }

    // ── Helper statique ──────────────────────────────────────
    public static function optionsPourOP(): array
    {
        return static::pourOP()->pluck('libelle', 'code')->toArray()
            ?: static::optionsDefaut();
    }

    public static function optionsPourOPT(): array
    {
        return static::pourOPT()->pluck('libelle', 'code')->toArray()
            ?: static::optionsDefaut();
    }

    public static function optionsDefaut(): array
    {
        return [
            'virement'       => '🏦 Virement bancaire',
            'cheque'         => '📄 Chèque',
            'especes'        => '💵 Espèces',
            'ordre_virement' => '📋 Ordre de virement',
            'mandat'         => '📮 Mandat postal',
            'mobile_money'   => '📱 Mobile Money (MTN/Orange)',
            'autre'          => '🔷 Autre',
        ];
    }
}
