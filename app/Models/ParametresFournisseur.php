<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ParametresFournisseur extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'parametres_fournisseur';

    protected $fillable = [
        // Informations société
        'nom_societe',
        'sigle',
        'logo',

        // Coordonnées
        'adresse',
        'ville',
        'pays',
        'code_postal',
        'boite_postale',
        'telephone',
        'fax',
        'email_general',
        'email_support',
        'email_commercial',
        'site_web',

        // Informations légales
        'numero_contribuable',
        'rccm',
        'forme_juridique',
        'numero_agrement',

        // Informations logiciel
        'nom_logiciel',
        'version_logiciel',
        'description_logiciel',
        'url_documentation',
        'url_guide_utilisateur',

        // Support
        'telephone_support',
        'telephone_urgence',
        'horaires_support',

        // Copyright
        'copyright_texte',
        'copyright_annee_debut',
        'mentions_legales',
        'conditions_utilisation',

        // Réseaux sociaux
        'facebook',
        'twitter',
        'linkedin',
        'youtube',

        // Paramètres système
        'actif',
        'afficher_footer',
        'afficher_badge_licence',
        'couleur_principale',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'afficher_footer' => 'boolean',
        'afficher_badge_licence' => 'boolean',
        'copyright_annee_debut' => 'integer',
    ];

    // ====================================
    // MÉTHODES PRINCIPALES
    // ====================================

    /**
     * Récupérer les paramètres actifs (singleton)
     */
    public static function getParametres(): ?self
    {
        return static::where('actif', true)->first();
    }

    /**
     * Récupérer ou créer les paramètres
     */
    public static function getOrCreateParametres(): self
    {
        $params = static::getParametres();

        if (!$params) {
            $params = static::create([
                'nom_societe' => 'Votre Société',
                'nom_logiciel' => 'Budget Manager',
                'version_logiciel' => '1.0.0',
                'actif' => true,
            ]);
        }

        return $params;
    }

    // ====================================
    // ACCESSEURS
    // ====================================

    /**
     * URL complète du logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo && Storage::disk('public')->exists($this->logo)) {
            return Storage::disk('public')->url($this->logo);
        }
        return null;
    }

    /**
     * Nom complet avec sigle
     */
    public function getNomCompletAttribute(): string
    {
        return $this->sigle ? "{$this->nom_societe} ({$this->sigle})" : $this->nom_societe;
    }

    /**
     * Texte copyright complet
     */
    public function getCopyrightCompletAttribute(): string
    {
        $annee = date('Y');
        $texte = $this->copyright_texte ?? 'Tous droits réservés';

        if ($this->copyright_annee_debut && $this->copyright_annee_debut < $annee) {
            return "© {$this->copyright_annee_debut}-{$annee} {$this->nom_societe} - {$texte}";
        }

        return "© {$annee} {$this->nom_societe} - {$texte}";
    }

    /**
     * Coordonnées complètes formatées
     */
    public function getCoordonneesCompletesAttribute(): array
    {
        return [
            'adresse_complete' => collect([
                $this->adresse,
                $this->code_postal ? "{$this->code_postal} {$this->ville}" : $this->ville,
                $this->pays,
            ])->filter()->implode(', '),

            'boite_postale' => $this->boite_postale ? "BP: {$this->boite_postale}" : null,
            'telephone' => $this->telephone,
            'fax' => $this->fax,
            'email' => $this->email_general,
            'site_web' => $this->site_web,
        ];
    }

    /**
     * Informations de contact pour le footer
     */
    public function getContactFooterAttribute(): array
    {
        return [
            'email_support' => $this->email_support ?? $this->email_general,
            'telephone_support' => $this->telephone_support ?? $this->telephone,
            'telephone_urgence' => $this->telephone_urgence,
            'horaires' => $this->horaires_support,
        ];
    }

    /**
     * Liens de documentation
     */
    public function getDocumentationLinksAttribute(): array
    {
        return [
            'documentation' => $this->url_documentation,
            'guide_utilisateur' => $this->url_guide_utilisateur,
            'site_web' => $this->site_web,
        ];
    }

    /**
     * Liens réseaux sociaux (filtrés)
     */
    public function getReseauxSociauxAttribute(): array
    {
        return collect([
            'facebook' => $this->facebook,
            'twitter' => $this->twitter,
            'linkedin' => $this->linkedin,
            'youtube' => $this->youtube,
        ])->filter()->toArray();
    }

    /**
     * Informations pour le header
     */
    public function getInfosHeaderAttribute(): array
    {
        return [
            'nom_logiciel' => $this->nom_logiciel,
            'logo_url' => $this->logo_url,
            'couleur_principale' => $this->couleur_principale,
        ];
    }

    /**
     * Informations pour le footer
     */
    public function getInfosFooterAttribute(): array
    {
        return [
            'afficher' => $this->afficher_footer,
            'nom_societe' => $this->nom_societe,
            'nom_complet' => $this->nom_complet,
            'description' => $this->description_logiciel,
            'copyright' => $this->copyright_complet,
            'contact' => $this->contact_footer,
            'documentation' => $this->documentation_links,
            'reseaux_sociaux' => $this->reseaux_sociaux,
        ];
    }

    /**
     * Version complète (ex: v1.0.0)
     */
    public function getVersionCompleteAttribute(): string
    {
        return 'v' . $this->version_logiciel;
    }

    // ====================================
    // MÉTHODES UTILITAIRES
    // ====================================

    /**
     * Vérifier si un élément doit être affiché
     */
    public function afficher(string $element): bool
    {
        return match ($element) {
            'footer' => $this->afficher_footer,
            'badge_licence' => $this->afficher_badge_licence,
            default => true,
        };
    }

    /**
     * Formater un numéro de téléphone pour href
     */
    public function formatTelephoneHref(string $telephone): string
    {
        return 'tel:' . preg_replace('/[^0-9+]/', '', $telephone);
    }

    /**
     * Obtenir l'email de contact prioritaire
     */
    public function getEmailContactPrioritaireAttribute(): string
    {
        return $this->email_support ?? $this->email_general ?? $this->email_commercial ?? 'contact@example.com';
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function boot()
    {
        parent::boot();

        // Un seul paramétrage actif à la fois (singleton)
        static::creating(function ($parametres) {
            if ($parametres->actif) {
                static::where('actif', true)->update(['actif' => false]);
            }
        });

        static::updating(function ($parametres) {
            if ($parametres->actif && $parametres->isDirty('actif')) {
                static::where('id', '!=', $parametres->id)
                    ->where('actif', true)
                    ->update(['actif' => false]);
            }
        });
    }

    // ====================================
    // SCOPES
    // ====================================

    /**
     * Scope: Paramètres actifs
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
