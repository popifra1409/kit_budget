<?php

namespace App\Services\Administration;

use App\Support\ConservationJournal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpWord\PhpWord;

/**
 * Construit la charte des droits d'utilisation du SIGB (Word .docx).
 *
 * L'état des droits est relu dans la base à chaque exécution : le document décrit
 * ce que les rôles peuvent faire aujourd'hui, écran « Rôles » compris, et non ce
 * que les seeders prévoient. Lecture seule — aucune écriture en base.
 *
 * Les compteurs (permissions réellement testées par le code, auteurs du journal,
 * niveaux hiérarchiques, ordonnances sans gel) sont recalculés à chaque exécution :
 * si le code change, la charte le dit.
 */
class GenerateurCharteDroits
{
    /** @var array<int,string> Constats de la dernière génération, une ligne par constat. */
    protected array $notes = [];

    /**
     * @param  bool         $sansNominatifs omets l'annexe nominative (version diffusable)
     * @param  string|null  $sortie         chemin du .docx, storage/app par defaut
     * @return string       le chemin ecrit
     */
    public function generer(bool $sansNominatifs = false, ?string $sortie = null): string
    {
        $this->notes = [];
        $racine = base_path();
        $sortie ??= storage_path('app/charte-des-droits-sigb'
            .($sansNominatifs ? '-sans-nominatifs' : '').'.docx');

        // ─── 1. Ce que la base dit des habilitations ──────────────────────────────────────

        $rolesBase = DB::table('roles')->orderBy('name')->get();   // id + name, tels que configures

        // Droits par role, indexes par NOM de role : c'est le nom que le code teste.
        $droits = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->get(['roles.name as role', 'permissions.name as slug'])
            ->groupBy('role')
            ->map(fn($g) => $g->pluck('slug')->flip());

        $nbPermissions = $droits->map->count();

        $effectifs = DB::table('model_has_roles')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->get(['roles.name as role', 'users.id'])
            ->groupBy('role')
            ->map->count();

        $nominatifs = DB::table('model_has_roles')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->orderBy('users.name')->orderBy('roles.name')
            ->get(['users.name', 'users.actif', 'roles.name as role']);

        $sansRole = DB::table('users')
            ->whereNotIn('id', DB::table('model_has_roles')->pluck('model_id'))
            ->pluck('name');

        $catalogue  = DB::table('permissions')->pluck('name')->flip();   // slug => index
        $nbCatalogue = $catalogue->count();

        // ─── 2. Les permissions que le logiciel verifie reellement ────────────────────────
        //
        // Une permission accordee dans l'ecran Rôles mais testee nulle part dans app/ ne protege
        // rien : la charte ne doit s'appuyer que sur les permissions testees.
        //
        // Deux facons pour le code de tester une permission : l'ecrire en dur (->can('slug'),
        // peutUtiliser('slug'), hasAnyPermission([...])) ou employer le trait
        // AutorisationParPermissions, qui construit view_any_X / view_X / create_X / update_X /
        // delete_X a partir du prefixe declare par la ressource. Les deux comptent.

        $verifiees = collect();
        $mentionnees = '';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine.'/app'));
        foreach ($it as $f) {
            if (!$f->isFile() || !str_ends_with($f->getFilename(), '.php')) {
                continue;
            }
            $code = file_get_contents($f->getPathname());
            $mentionnees .= $code;

            if (preg_match_all(
                "/->can\(\s*['\"]([a-z0-9_]+)['\"]|::can\(\s*['\"]([a-z0-9_]+)['\"]"
                ."|peutUtiliser\(\s*['\"]([a-z0-9_]+)['\"]|hasAnyPermission\(\s*\[([^\]]*)\]/i",
                $code, $m, PREG_SET_ORDER)) {
                foreach ($m as $groupe) {
                    foreach ([$groupe[1] ?? null, $groupe[2] ?? null, $groupe[3] ?? null] as $s) {
                        if ($s) {
                            $verifiees->push($s);
                        }
                    }
                    if (($groupe[4] ?? '') !== '') {
                        preg_match_all("/['\"]([a-z0-9_]+)['\"]/", $groupe[4], $mm);
                        foreach ($mm[1] as $s) {
                            $verifiees->push($s);
                        }
                    }
                }
            }

            // Les cinq slugs que le trait construit pour chaque ressource qui l'emploie.
            if (preg_match_all("/prefixePermission\(\)[^{]*\{\s*return\s*['\"]([a-z0-9_]+)['\"]/", $code, $mp)) {
                foreach ($mp[1] as $prefixe) {
                    foreach (['view_any', 'view', 'create', 'update', 'delete'] as $action) {
                        $verifiees->push($action.'_'.$prefixe);
                    }
                }
            }
        }

        $verifiees = $verifiees->unique()->values();
        $nonTestees = $catalogue->keys()->reject(fn($slug) => $verifiees->contains($slug))->values();

        // Deux familles a distinguer : la permission n'est ecrite nulle part (fonction annoncee,
        // jamais codee) ou elle est citee dans le code sans jamais servir de test.
        $absentesDuCode = $nonTestees->filter(fn($slug) => !str_contains($mentionnees, '"'.$slug.'"')
            && !str_contains($mentionnees, "'".$slug."'"))->values();
        $citeesSansTest = $nonTestees->reject(fn($slug) => $absentesDuCode->contains($slug))->values();

        // Regroupement par ecran / document pour l'annexe : « bordereau », « stock », « offre »…
        $parCible = $nonTestees->groupBy(fn($s) => substr($s, strpos($s, '_') + 1))
            ->map(fn($groupe) => [
                'actions' => $groupe->map(fn($s) => substr($s, 0, strpos($s, '_')))->values(),
                'cittee'  => $groupe->contains(fn($s) => $citeesSansTest->contains($s)),
            ])
            ->sortByDesc(fn($c) => count($c['actions']));

        // ─── 3. Les actions retenues : libelle simple + permission verifiee par le code ────

        // Chaque ligne : libelle affiche | slugs exiges | preuve (le fichier qui teste)
        $actions = [
            'M1' => ['De la commande à l\'engagement', [
                ['Consulter les engagements',           ['view_any_engagement'],              'EngagementResource'],
                ['Saisir un bon de commande',           ['create_bon_commande'],              'BonCommandeResource'],
                ['Saisir une décision administrative',  ['create_decision_administrative'],   'BonCommande / Personnel'],
                ['Valider un bon de commande',          ['valider_bon_commande'],             'BonCommandeResource'],
                ['Valider une décision administrative', ['valider_decision_administrative'],  'DecisionAdministrativeResource'],
                ['Créer un certificat d\'engagement',   ['create_engagement'],                'EngagementResource'],
                ['Valider un engagement',               ['valider_engagement'],               'EngagementResource'],
                ['Annuler un engagement',               ['annuler_engagement'],               'EngagementResource'],
            ]],
            'M2' => ['Crédits, ordonnancement, paiement', [
                ['Fiche de contrôle des engagements',   ['generer_pdf_fiche_controle_engagements'], 'FicheControleEngagementsResource'],
                ['Créer un collectif budgétaire',       ['create_collectif_budgetaire'],      'CollectifBudgetaireResource'],
                ['Adopter un collectif budgétaire',     ['adopter_collectif_budgetaire'],     'CollectifBudgetaireResource'],
                ['Autoriser un virement',               ['approuver_virement_budgetaire'],    'VirementBudgetaireResource'],
                ['Exécuter un virement',                ['executer_virement_budgetaire'],     'VirementBudgetaireResource'],
                ['Créer une ordonnance de paiement',    ['create_ordonnance_paiement'],       'OrdonnancePaiementResource'],
                ['Modifier une ordonnance',             ['update_ordonnance_paiement'],       'OrdonnancePaiementResource'],
                ['Marquer une ordonnance payée',        ['marquer_payee_ordonnance_paiement'], 'OrdonnancePaiementResource:405'],
                ['Certifier le service fait',           ['certifier_service_fait'],           'ViewLiquidation'],
                ['Viser une liquidation',               ['viser_liquidation'],                'ViewLiquidation'],
            ]],
            'M3' => ['Régies, marchés, pilotage, administration', [
                ['Autoriser un paiement exceptionnel',  ['autoriser_paiement_exceptionnel'],  'ViewPaiementExceptionnel'],
                ['Suspendre une régie d\'avance',       ['suspendre_regie_avance'],           'RegieAvanceResource'],
                ['Clôturer une régie d\'avance',        ['cloturer_regie_avance'],            'RegieAvanceResource'],
                ['Superviser toutes les régies',        ['superviser_toutes_les_regies'],     'User.php:362'],
                ['Transmettre un bordereau',            ['transmettre_bordereau'],            'BordereauEngagementResource:324'],
                ['Homologuer un marché',                ['homologuer_marche'],                'MarcheResource'],
                ['Clôturer un exercice',                ['cloturer_exercice'],                'ExerciceResource'],
                ['Travailler hors période d\'exécution', ['operer_hors_periode_execution'],   'PeriodeExecutionService'],
                ['Consulter le journal d\'activité',    ['view_any_activity'],                'ActivityResource'],
                ['Modifier un compte utilisateur',      ['update_user'],                      'UserResource'],
                ['Modifier les rôles et leurs droits',  ['update_role'],                      'RoleResource'],
            ]],
        ];

        $slugsExiges = collect();
        foreach ($actions as $bloc) {
            foreach ($bloc[1] as $a) {
                $slugsExiges = $slugsExiges->merge($a[1]);
            }
        }
        $slugsExiges = $slugsExiges->unique()->values();

        // Libelle court + ce que le role designe a l'hopital. L'ordre d'ecriture est l'ordre
        // de lecture : direction, budget, finance, matieres, marches, pilotage, regies, admin.
        $libelles = [
            'directeur_general'              => ['Directeur Général', 'Direction de l\'établissement.'],
            'daaf'                           => ['DAAF', 'Direction administrative et financière.'],
            'controleur_financier'           => ['Contrôleur financier', 'Contrôle la légalité financière des actes.'],
            'personnel_controleur_financier' => ['Personnel contrôleur financier', 'Seconde lecture du contrôle financier.'],
            'agence_comptable'               => ['Agence comptable', 'Comptable public : paiement.'],
            'sous_directeur_budget'          => ['Sous-directeur Budget', 'Sous-direction du budget.'],
            'chef_service_budget'            => ['Chef de service Budget', 'Service budget.'],
            'operateur_budget'               => ['Opérateur Budget', 'Agent de saisie budgétaire.'],
            'responsable_sous_programme'     => ['Responsable de sous-programme', 'Pilote un sous-programme.'],
            'ordonnateur_matieres'           => ['Ordonnateur matières', 'Validation des mouvements de stock.'],
            'comptable_matieres'             => ['Comptable matières', 'Magasin / stocks.'],
            'comptable_matiere'              => ['Comptable matière (variante)', 'Doublon du précédent.'],
            'service_utilisateur'            => ['Service utilisateur', 'Service qui demande des fournitures.'],
            'chef_service_marches'           => ['Chef de service Marchés', 'Service des marchés publics.'],
            'responsable_planification'      => ['Responsable Planification', 'Plan stratégique, indicateurs.'],
            'responsable_programmation'      => ['Responsable Programmation', 'PPA / CDMT.'],
            'responsable_suivi_evaluation'   => ['Responsable Suivi-Evaluation', 'Rapports d\'activité.'],
            'responsable_regie'              => ['Responsable de régie', 'Régie d\'avance.'],
            'admin'                          => ['Administrateur', 'Administration fonctionnelle du logiciel.'],
            'super_admin'                    => ['Super administrateur', 'Informatique : tous les droits, y compris les comptes.'],
        ];

        $nomsEnBase   = $rolesBase->pluck('name');
        $ordreRoles   = collect($libelles)->keys()->filter(fn($r) => $nomsEnBase->contains($r))->values();
        $orphelins    = $nomsEnBase->reject(fn($n) => isset($libelles[$n]))->values();
        $ordreRoles   = $ordreRoles->concat($orphelins)->values();

        $peut = function (string $role, array $slugs) use ($droits): bool {
            $d = $droits[$role] ?? collect();
            foreach ($slugs as $s) {
                if (!$d->offsetExists($s)) {
                    return false;
                }
            }

            return $slugs !== [];
        };

        $colonneConnexion = Schema::hasColumn('users', 'last_login_at');

        $journal = [
            'total'   => DB::table('activity_log')->count(),
            'trente'  => DB::table('activity_log')->where('created_at', '>=', now()->subDays(30))->count(),
            'auteurs' => DB::table('activity_log')->whereNotNull('causer_id')->distinct()->count('causer_id'),
            'modeles' => DB::table('activity_log')->whereNotNull('subject_type')->distinct()->count('subject_type'),
            'modules' => DB::table('activity_log')->selectRaw('log_name, count(*) n')->groupBy('log_name')->orderByDesc('n')->limit(6)->get(),
            'evenements' => DB::table('activity_log')->selectRaw('event, count(*) n')->groupBy('event')->orderByDesc('n')->limit(6)->get(),
            'login'   => DB::table('activity_log')->where('event', 'login')->count(),
            'logout'  => DB::table('activity_log')->where('event', 'logout')->count(),
            'echecs'  => DB::table('activity_log')->where('event', 'login_failed')->count(),
            'acces_refuse' => DB::table('activity_log')->where('event', 'access_denied')->count(),
            'droles' => DB::table('activity_log')->whereIn('event', ['permissions_modifiees', 'permissions_attribuees'])->count(),
            'dates' => Schema::hasColumn('users', 'last_login_at')
                ? DB::table('users')->whereNotNull('last_login_at')->count()
                : 0,
        ];

        $nbStructures = DB::table('parametres_structure')->count();
        $nbComptes = DB::table('users')->count();

        // Conserver le journal : les deux seuils viennent de la même source que les
        // commandes (ConservationJournal) et l'état de l'archive est relevé en base, pour
        // que le document ne puisse plus annoncer « 90 jours » alors que le code applique
        // autre chose, ni prétendre que l'archivage a déjà eu lieu quand il ne l'a jamais
        // été exécuté ici.
        $seuilArchivage = ConservationJournal::seuilArchivage();
        $conservation = [
            'jours' => ConservationJournal::dureePurge(),
            'mois' => ConservationJournal::ageArchivage(),
            'archives' => Schema::hasTable('activity_log_archives')
                ? DB::table('activity_log_archives')->count()
                : 0,
            'derniere' => Schema::hasTable('activity_log_archives')
                ? DB::table('activity_log_archives')->max('archived_at')
                : null,
            'a_archiver' => DB::table('activity_log')->where('created_at', '<', $seuilArchivage)->count(),
            'a_risque' => DB::table('activity_log')
                ->where('created_at', '<', ConservationJournal::seuilPurge())
                ->where('created_at', '>=', $seuilArchivage)->count(),
            // Le planning est mesuré, pas deviné : schedule:list renvoie la liste réellement
            // déclarée (cache du planning compris), ce qui est seul à même de dire si la
            // bascule est programmée ou si elle attend encore quelqu'un pour la déclencher.
            'planning' => (function () {
                try {
                    Artisan::call('schedule:list', ['--json' => true]);
                    $events = json_decode(trim(Artisan::output()), true) ?: [];

                    return (bool) array_filter($events,
                        fn($e) => str_contains((string) ($e['command'] ?? ''), 'activity:archive'));
                } catch (Throwable) {
                    return false;
                }
            })(),
        ];
        $nbActifs  = DB::table('users')->where('actif', true)->count();
        $doublons  = ['comptable_matiere', 'comptable_matieres'];

        // Qui tient réellement la permission de transmettre un bordereau (le verrou est en dur).
        $porteurBordereau = DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'transmettre_bordereau')
            ->pluck('roles.name')->values();

        // Même permission « émettre » : accordée, jamais testée — le bouton ne la regarde pas.
        $porteurEmettre = DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'emettre_ordonnance_paiement')
            ->pluck('roles.name')->values();

        // Hiérarchie : la migration ne l'a renseignée que pour les rôles les plus anciens.
        $niveaux = DB::table('roles')->orderByDesc('niveau_hierarchique')->orderBy('name')
            ->get(['name', 'niveau_hierarchique'])->groupBy('niveau_hierarchique');
        $niveauZero = DB::table('roles')->where('niveau_hierarchique', 0)->pluck('name')->values();

        // Les ordonnances ne portent aucun gel : leurs montants se recalculent à l'impression.
        $tableOp = (new \App\Models\OrdonnancePaiement)->getTable();
        $colonnesOp = collect(Schema::getColumnListing($tableOp));
        $op = [
            'total'    => DB::table($tableOp)->count(),
            'sansGel'  => !$colonnesOp->contains(fn($c) => str_contains($c, 'snapshot')),
            'brutZero' => DB::table($tableOp)->where('montant_brut', 0)->count(),
        ];

        // Contrôles des faits énoncés dans le texte : rien n'est affirmé sans être dans la base.
        $verifsTexte = [
            'transmettre_bordereau'              => $catalogue->offsetExists('transmettre_bordereau'),
            'marquer_payee_ordonnance_paiement'  => $catalogue->offsetExists('marquer_payee_ordonnance_paiement'),
            'emettre_ordonnance_paiement'        => $catalogue->offsetExists('emettre_ordonnance_paiement'),
        ];

        $this->notes[] = 'base : '.$rolesBase->count().' roles, '.$nbCatalogue.' permissions, '.$nbComptes.' comptes'."\n";
        $this->notes[] = 'permissions verifiees par le code : '.$verifiees->count().' · non verifiees : '.$nonTestees->count()
            .' (dont aucune occurrence : '.$absentesDuCode->count().' · citees sans test : '.$citeesSansTest->count().")\n";
        $this->notes[] = 'actions retenues : '.count($slugsExiges).' · absentes du catalogue : '
            .($slugsExiges->reject(fn($s) => $catalogue->offsetExists($s))->implode(', ') ?: 'aucune')."\n";
        $this->notes[] = 'roles sans libelle : '.($orphelins->isEmpty() ? 'aucun' : $orphelins->implode(', ')).' · roles sans droit : '
            .($nomsEnBase->reject(fn($n) => isset($droits[$n]))->values()->implode(', ') ?: 'aucun')."\n";
        $this->notes[] = 'niveaux hierarchiques : '.$niveaux->keys()->implode(' / ').' · au niveau 0 : '.$niveauZero->count().
            ' ('.$niveauZero->implode(', ').")\n";
        $this->notes[] = 'ordonnances : '.$op['total'].' · gel absent : '.($op['sansGel'] ? 'oui' : 'non').
            ' · montant_brut a zero : '.$op['brutZero']."\n";
        $this->notes[] = 'porteurs de transmettre_bordereau : '.($porteurBordereau->implode(', ') ?: 'aucun')
            .' · de emettre_ordonnance_paiement : '.($porteurEmettre->implode(', ') ?: 'aucun')."\n";
        $this->notes[] = 'journal auth : login '.$journal['login'].' · logout '.$journal['logout'].' · failed '.$journal['echecs']
            .' · refus opposes '.$journal['acces_refuse']
            .' · droits de role detailles '.$journal['droles']
            .' · types de documents observés '.$journal['modeles']."\n";
        $this->notes[] = 'derniere connexion : colonne users.last_login_at '
            .($colonneConnexion ? 'OUI · '.$journal['dates'].' compte(s) date(s)' : 'ABSENTE — migration 2026_11_07_000002 non lancee')."\n";
        $this->notes[] = 'conservation : purge ' . $conservation['jours'] . 'j · archivage '
            . $conservation['mois'] . 'm · archive ' . $conservation['archives']
            . ' entree(s) · ' . $conservation['a_archiver'] . ' a archiver · '
            . $conservation['a_risque'] . ' en risque · planning '
            . ($conservation['planning'] ? 'OUI' : 'non') . "\n";
        foreach ($verifsTexte as $slug => $present) {
            $this->notes[] = 'au catalogue : '.($present ? 'OUI' : 'non  ').' — '.$slug."\n";
        }

        // ─── 4. Construction du document ─────────────────────────────────────────────────

        $bleu = '1F3864';
        $bleuClair = 'D9E2F3';
        $gris = '595959';
        $vert = '1F6E3F';
        $rouge = 'B00000';

        $phpWord = new PhpWord();
        $phpWord->setDefaultParagraphStyle(['size' => 10, 'fontName' => 'Calibri', 'spaceAfter' => 100, 'alignment' => 'both']);
        $phpWord->addTitleStyle(1, ['size' => 16, 'bold' => true, 'color' => $bleu], ['spaceBefore' => 240, 'spaceAfter' => 160]);
        $phpWord->addTitleStyle(2, ['size' => 12.5, 'bold' => true, 'color' => $bleu], ['spaceBefore' => 200, 'spaceAfter' => 100]);
        $phpWord->addTitleStyle(3, ['size' => 10.5, 'bold' => true, 'color' => $bleu], ['spaceBefore' => 140, 'spaceAfter' => 60]);
        $phpWord->addFontStyle('Petit', ['size' => 8.5, 'color' => $gris]);
        $phpWord->addFontStyle('Clair', ['size' => 9, 'color' => $gris], ['spaceAfter' => 60]);
        $phpWord->addParagraphStyle('Note', ['size' => 8.5, 'color' => $gris, 'spaceBefore' => 40, 'alignment' => 'left']);
        $phpWord->addTableStyle('Cadre',
            ['borderSize' => 4, 'borderColor' => 'BFBFBF', 'cellMarginSize' => 30, 'unit' => 'dxa', 'spacing' => 60],
            ['bgColor' => $bleuClair, 'bold' => true, 'color' => $bleu, 'size' => 8.5]);
        $phpWord->addTableStyle('Matrice',
            ['borderSize' => 4, 'borderColor' => 'BFBFBF', 'cellMarginSize' => 16, 'unit' => 'dxa', 'spacing' => 40],
            ['bgColor' => $bleuClair, 'bold' => true, 'size' => 6.5]);

        $piedDePage = function ($s) use ($bleu, $gris): void {
            $s->addFooter()->addPreserveText(
                'Charte des droits d\'utilisation du SIGB — CHUY — page {PAGE} / {NUMPAGES}',
                ['size' => 7.5, 'color' => $gris], ['alignment' => 'center']);
            $s->addHeader()->addText(
                'CENTRE HOSPITALIER UNIVERSITAIRE DE YOPOUGON — SYSTEME D\'INFORMATION DE GESTION BUDGETAIRE',
                ['size' => 7.5, 'color' => $bleu, 'bold' => true], ['alignment' => 'right']);
        };

        // — Couverture
        $section = $phpWord->addSection(['orientation' => 'portrait']);
        $piedDePage($section);
        $section->addTitle('Charte des droits d\'utilisation du SIGB', 1);
        $section->addText('Rôles, permissions et suivi du personnel — Centre Hospitalier Universitaire de Yopougon',
            ['size' => 12, 'color' => $gris]);
        $section->addTextBreak(1);

        $t = $section->addTable('Cadre');
        foreach ([['Document', 'Charte des droits d\'utilisation du SIGB (budget_suite)'],
                  ['Version', '1.0 — générée le '.now()->format('d/m/Y').' à partir des droits réellement configurés dans la base'],
                  ['Portée', 'Modules Budget, Comptabilité matières, Marchés publics, Planification, Programmation, Suivi-évaluation, Régies, Administration'],
                  ['Destinataires', 'Direction Générale, DAAF, Contrôle financier, Agence comptable, et tout agent utilisateur du logiciel'],
                  ['Approbation', 'Le Directeur Général, après signature du présent document par chaque responsable de rôle'],
                  ['Régénération', 'Le document se relit à tout moment : il reflète l\'état des rôles au jour de la génération, pas celui des manuels']
                 ] as $ligne) {
            $t->addRow();
            $t->addCell(2100, ['bgColor' => 'F2F2F2'])->addText($ligne[0], ['size' => 9, 'bold' => true]);
            $t->addCell(7300)->addText($ligne[1], ['size' => 9]);
        }

        $section->addTextBreak(1);
        $section->addTitle('Pourquoi cette charte', 2);
        $section->addText(
            'Le SIGB ne demande pas la permission « à la tête du client » : ce que chaque agent peut voir, créer, valider '.
            'ou payer dépend du rôle qu\'il porte. Cette charte dit trois choses : (1) ce que chaque rôle a le droit de '.
            'faire, aujourd\'hui, dans votre installation ; (2) ce que le logiciel enregistre de chaque geste, pour que '.
            'vous puissiez le vérifier ; (3) les points sur lesquels votre attention est nécessaire, parce que le '.
            'logiciel ne protège pas tout.'
        );

        $section->addTitle('Comment lire ce document', 2);
        foreach ([
            'Les matrices de la section 3 listent '.count($slugsExiges).' actions. « Oui » = le rôle peut le faire ; « — » = il ne peut pas.',
            'Une action fermée aujourd\'hui peut être ouverte demain : tout se règle écran Administration → Rôles, onglet du module concerné.',
            'La section 8 liste les limites connues. Elles sont volontairement techniques : elles disent où ne pas se fier au seul paramétrage.',
            'Chiffres clés au jour de la génération : '.$rolesBase->count().' rôles, '.$nbCatalogue.' permissions au catalogue, '
                .$nbComptes.' comptes dont '.$nbActifs.' actifs, '.$journal['total'].' entrées au journal.'
        ] as $ligne) {
            $section->addListItem($ligne, 0, ['size' => 9.5]);
        }

        // — 1. Principes
        $section->addPageBreak();
        $section->addTitle('1. Les quatre principes qui protègent le budget', 1);

        $section->addTitle('1.1 Séparation des pouvoirs', 2);
        $section->addText(
            'Personne ne peut, seul, faire sortir de l\'argent. La chaîne est découpée : la commande est validée par le '.
            'service budget, l\'engagement — le certificat d\'engagement — est validé à un niveau supérieur, la liquidation '.
            'est visée par le contrôle financier, le paiement est marqué par l\'agence comptable. Chaque maillon a son rôle, '.
            'et les matrices montrent où chaque rôle s\'arrête.'
        );

        $section->addTitle('1.2 Rien n\'est jamais définitivement perdu', 2);
        $section->addText(
            'Un document signé garde la trace des chiffres qui étaient les siens au jour de sa signature : les montants d\'un '.
            'certificat d\'engagement ne se recalculent pas au gré des virements et collectifs postérieurs. C\'est le « gel » '.
            'des états imprimés : en cas de contestation, le papier et la base disent la même chose. Attention : ce gel ne '.
            'couvre pas encore les ordonnances de paiement (section 8).'
        );

        $section->addTitle('1.3 Chacun ne voit que ce qui le regarde', 2);
        $section->addText(
            'Au-delà des permissions, le logiciel filtre ce qu\'il montre : un responsable de régie ne voit que les régies '
            .'dont il est le responsable, un agent ne travaille que dans l\'exercice ouvert, les écritures sont refusées hors '
            .'de la période d\'exécution. Un bordereau, lui, ne se transmet qu\'aux trois rôles habilités. Ces règles sont '
            .'rappelées en section 4.'
        );

        $section->addTitle('1.4 Tout geste est daté et nominatif', 2);
        $section->addText(
            'Chaque création, modification et validation est enregistrée avec son auteur, son horodatage, son adresse IP et '.
            'les valeurs avant / après. Au jour de la génération, le journal contenait '.$journal['total'].' entrées, dont '.
            $journal['trente'].' sur les trente derniers jours, réparties sur '.$journal['auteurs'].' auteurs distincts.'
        );

        // — 2. Les roles
        $section->addTitle('2. Les rôles du logiciel', 1);
        $section->addText(
            'Rôles présents dans la base : '.$rolesBase->count().'.'
            .($orphelins->isEmpty() ? '' : ' Dont '.count($orphelins).' sans libellé connu, non documentés ici : '.$orphelins->implode(', ').'.')
        );

        $t = $section->addTable('Cadre', ['width' => 9400]);
        $t->addRow(null, ['tblHeader' => true]);
        foreach (['Rôle', 'Qui cela désigne', 'Comptes', 'Droits', 'Ce que ce rôle ne peut pas faire (sélection)'] as $i => $c) {
            $t->addCell([1500, 2300, 850, 800, 3950][$i])->addText($c, null, ['alignment' => 'center']);
        }
        foreach ($ordreRoles as $nom) {
            [$libelle, $qui] = $libelles[$nom] ?? [$nom, 'Rôle créé en base, non documenté.'];
            $d = $droits[$nom] ?? collect();
            $refusees = [];
            foreach ($actions as $bloc) {
                foreach ($bloc[1] as $a) {
                    if (!$d->offsetExists($a[1][0])) {
                        $refusees[] = $a[0];
                    }
                }
            }
            $t->addRow();
            $t->addCell(1500)->addText($libelle, ['size' => 8.5, 'bold' => true]);
            $t->addCell(2300)->addText($qui, ['size' => 8]);
            $t->addCell(850)->addText((string) ($effectifs[$nom] ?? 0), ['size' => 8.5], ['alignment' => 'center']);
            $t->addCell(800)->addText((string) ($nbPermissions[$nom] ?? 0), ['size' => 8.5], ['alignment' => 'center']);
            $t->addCell(3950)->addText(
                $refusees === [] ? 'Tout lui est ouvert.'
                    : implode(' · ', array_slice($refusees, 0, 5))
                      .(count($refusees) > 5 ? ' (et '.(count($refusees) - 5).' autres actions sur '.count($slugsExiges).')' : ''),
                ['size' => 7.5]);
        }
        $noteRoles = '« Droits » = nombre de permissions accordées sur '.$nbCatalogue.' au catalogue. ';
        $noteRoles .= $sansRole->isEmpty()
            ? 'Tous les comptes portent au moins un rôle.'
            : 'ATTENTION : '.$sansRole->count().' compte(s) sans aucun rôle : '.$sansRole->implode(', ').'. Un compte sans rôle ne peut ouvrir aucun module.';
        $section->addText($noteRoles, 'Petit', 'Note');

        // — 3. Matrices (paysage)
        $paysage = $phpWord->addSection(['orientation' => 'landscape',
            'marginTop' => 700, 'marginBottom' => 900, 'marginLeft' => 700, 'marginRight' => 700]);
        $piedDePage($paysage);
        $paysage->addTitle('3. Matrice des droits : qui peut faire quoi', 1);
        $paysage->addText(
            '« Oui » = le rôle porte la permission qui protège l\'action. « — » = il ne la porte pas. Toutes les permissions '
            .'ci-dessous sont effectivement vérifiées par le logiciel (preuve indiquée en annexe B).', 'Clair');

        foreach ($actions as $code => [$titre, $liste]) {
            $paysage->addTitle($code.' — '.$titre, 2);

            $largeurRole = 2300;
            $largeurCol  = intdiv(14000 - $largeurRole, count($liste));
            $t = $paysage->addTable('Matrice', ['unit' => 'dxa']);
            $t->addRow(null, ['tblHeader' => true]);
            $t->addCell($largeurRole)->addText('Rôle');
            foreach ($liste as $a) {
                $t->addCell($largeurCol)->addText($a[0], null, ['alignment' => 'center']);
            }

            foreach ($ordreRoles as $nom) {
                $t->addRow();
                $t->addCell($largeurRole, ['bgColor' => 'F7F9FC'])
                  ->addText($libelles[$nom][0] ?? $nom, ['size' => 7, 'bold' => true]);
                foreach ($liste as $a) {
                    $ouvert = $peut($nom, $a[1]);
                    $t->addCell($largeurCol)->addText($ouvert ? 'Oui' : '—',
                        ['size' => 7.5, 'bold' => $ouvert, 'color' => $ouvert ? $vert : $gris],
                        ['alignment' => 'center']);
                }
            }
            $paysage->addTextBreak(1);
        }

        $paysage->addText(
            'Deux exceptions à lire : (1) « Marquer une ordonnance payée » s\'obtient aussi par la permission '
            .'« valider_ordonnance_paiement » ou par le seul portefeuille des rôles super_admin, admin, agence_comptable et '
            .'daaf (OrdonnancePaiementResource.php:404-407) — retirer la permission ne suffit pas à la fermer. '
            .'(2) Émettre une ordonnance et exporter sa liste ne dépendent d\'aucune permission : seul le statut du document '
            .'conditionne le bouton (:378-387).',
            ['size' => 8, 'italic' => true], 'Note');

        // — 4 a 8 (portrait)
        $portrait = $phpWord->addSection(['orientation' => 'portrait']);
        $piedDePage($portrait);

        $portrait->addTitle('4. Ce que le logiciel cloisonne sans permission', 1);
        $portrait->addText('Ces filtres ne se règlent pas dans l\'écran Rôles : ils tiennent aux données, pas au paramétrage.', 'Clair');
        foreach ([
            ['Régies d\'avance', 'Un responsable de régie ne voit que les régies dont il est le responsable. La permission « superviser_toutes_les_regies » est la seule dérogation (User.php:362).'],
            ['Bordereaux d\'engagement', 'Un bordereau ne se transmet que si l\'agent porte la permission « transmettre_bordereau » (BordereauEngagementResource.php:324). Le receveur, en revanche, est choisi sur une liste de rôles écrite dans le code — contrôleur financier, DAAF, directeur général (:361) : cette liste ne se règle pas dans l\'écran Rôles.'],
            ['Exercice budgétaire', 'Un agent ne travaille que dans l\'exercice ouvert ou sélectionné ; les écritures sont refusées hors de la période d\'exécution, sauf permission expresse de dérogation.'],
            ['Hiérarchie', 'Un document ne se transmet que vers un rôle de niveau supérieur et ne se retourne que vers un rôle de niveau '.
                'inférieur (Role.php:24, :34). Le niveau de chaque rôle est réglable écran Administration → Rôles ; la grille en '.
                'vigueur dans votre installation est rappelée ci-dessous.'],
        ] as $i => $bloc) {
            $portrait->addTitle('4.'.($i + 1).' '.$bloc[0], 3);
            $portrait->addText($bloc[1], ['size' => 9.5]);
        }

        $portrait->addTitle('4.5 Niveaux de transmission constatés en base', 3);
        $t = $portrait->addTable('Cadre', ['width' => 9400]);
        $t->addRow(null, ['tblHeader' => true]);
        $t->addCell(1300)->addText('Niveau', null, ['alignment' => 'center']);
        $t->addCell(8100)->addText('Rôles', null, ['alignment' => 'center']);
        foreach ($niveaux as $niveau => $groupe) {
            $t->addRow();
            $t->addCell(1300)->addText((string) $niveau, ['size' => 8.5, 'bold' => true], ['alignment' => 'center']);
            $t->addCell(8100)->addText($groupe->pluck('name')->map(fn($n) => $libelles[$n][0] ?? $n)->implode(' · '), ['size' => 8]);
        }
        $portrait->addText(
            $niveauZero->isEmpty() ? ''
                :'Les '.$niveauZero->count().' rôles au niveau 0 — '.implode(', ', $niveauZero->map(fn($n) => $libelles[$n][0] ?? $n)->all()).
                ' — ne peuvent recevoir aucun document retourné : aucun rôle n\'est « en dessous ». Ce n\'est pas une erreur '
                .'de blocage, mais une lacune de paramétrage à corriger si ces rôles doivent un jour être saisis en retour.',
            'Petit', 'Note');

        $portrait->addTitle('5. Ce que le journal enregistre — et ce qu\'il ne voit pas', 1);
        $portrait->addTitle('5.1 Ce qui est tracé', 2);
        $items51 = [
            'Créations, modifications et suppressions sur '.$journal['modeles'].' types de documents observés à ce jour, avec les valeurs avant et après (les mots de passe et jetons sont exclus de l\'enregistrement).',
            'Les gestes métier nommés : validation, engagement, désengagement, annulation d\'un bon de commande ou d\'une décision administrative ; paiement et annulation d\'une ordonnance ; cycle des virements ; clôtures et collectifs ; marchés ; transmissions.',
            'Les connexions et les déconnexions : '.$journal['login'].' entrées « login », '.$journal['logout'].' entrées « logout » à la génération. La question « qui était connecté, et quand » se pose donc au logiciel, sans intermédiaire.',
            'Les échecs d\'authentification : '.$journal['echecs'].' entrées « login_failed », avec l\'identifiant tenté, le fait que le compte existe, l\'IP et l\'agent utilisateur — jamais le mot de passe.',
            'Les refus que le logiciel a réellement opposés : '.$journal['acces_refuse'].' entrées « access_denied » — un `->authorize()` de Filament qui refuse, un `abort(403)` d\'un contrôleur d\'export ou d\'impression, un module refusé par le middleware, une session survécue à la désactivation du compte. Chacun de ces « non » laisse l\'auteur, la route tentée et le motif.'.($journal['acces_refuse'] == 0 ? ' (aucune à ce jour : le branchement est récent et le refus reste rare).' : ''),
            'Le détail des droits accordés ou retirés à un rôle : '.$journal['droles'].' entrées « permissions_modifiees » et « permissions_attribuees » à la génération. Chaque enregistrement de l\'écran Rôles nomme les permissions ajoutées et celles qui ont disparu, et un rôle créé ou dupliqué porte la liste complète de ses droits — le compteur seul ne répondait pas à la question qu\'on pose en contrôle.'.($journal['droles'] == 0 ? ' (aucune à ce jour : le branchement est récent, la première modification d\'un rôle en créera).' : ''),
            'Pour chaque entrée : auteur nommément désigné, horodatage, adresse IP, utilisateur-agent du navigateur, route appelée.',
        ];
        if ($colonneConnexion) {
            $items51[] = 'La date de dernière connexion : la colonne `last_login_at` de la table users se pose à chaque authentification réussie, et l\'écran d\'accueil affiche cette connexion ainsi que la précédente avec son adresse IP. '.$journal['dates'].' comptes sur '.$nbComptes.' en portent une ; les autres sont à `NULL`, faute de connexion depuis le branchement — une date de déploiement à leur place affirmerait une connexion qui n\'a pas eu lieu.';
        }
        foreach ($items51 as $l) {
            $portrait->addListItem($l, 0, ['size' => 9.5]);
        }

        $portrait->addTitle('5.2 Répartition constatée à la génération', 2);
        $t = $portrait->addTable('Cadre', ['width' => 9400]);
        $t->addRow(null, ['tblHeader' => true]);
        $t->addCell(4700)->addText('Module le plus journalisé (log_name — entrées)', null, ['alignment' => 'center']);
        $t->addCell(4700)->addText('Événement le plus fréquent (event — entrées)', null, ['alignment' => 'center']);
        for ($i = 0; $i < 6; $i++) {
            $t->addRow();
            $m = $journal['modules']->get($i);
            $e = $journal['evenements']->get($i);
            $t->addCell(4700)->addText($m ? $m->log_name.' — '.$m->n : '', ['size' => 8.5]);
            $t->addCell(4700)->addText($e ? $e->event.' — '.$e->n : '', ['size' => 8.5]);
        }

        $portrait->addTitle('5.3 Ce qui n\'est pas tracé', 2);
        $items53 = [
            'Les tentatives avortées avant d\'atteindre le serveur : un bouton que l\'interface cache à un agent (les quelque 620 garde-fous `visible()` du code) ne laisse aucune trace, parce qu\'il n\'exprime aucune intention — le refus journalisé est celui qui a été effectivement dit à quelqu\'un qui a agi. Une tentative devinée par l\'absence de bouton n\'est donc pas une preuve.',
            'Les accès en lecture : ouvrir un document, filtrer une liste, consulter un écran ne sont pas journalisés. Seules les écritures et les refus le sont — le journal répond à « qui a changé quoi, et à qui le logiciel a dit non », pas à « qui a regardé ».',
        ];
        if (!$colonneConnexion) {
            $items53[] = 'La date de dernière connexion : l\'écran d\'accueil l\'affiche, la colonne last_login_at n\'existe pas dans la table users — le champ reste donc vide.';
        }
        foreach ($items53 as $l) {
            $portrait->addListItem($l, 0, ['size' => 9.5]);
        }

        $portrait->addTitle('5.4 Conservation', 2);
        // Les deux seuils et l'état de l'archive viennent de la même source que les
        // commandes ; le texte ne peut plus prédire une valeur que le code a abandonnée.
        $portrait->addText(
            'Une entrée du journal d\'activité est conservée ' . $conservation['jours']
            . ' jours (' . round($conservation['jours'] / 30.44) . ' mois), puis supprimée '
            . 'par la commande logs:clean. Le délai est lu dans la table parametres_structure, '
            . 'et c\'est la plus longue durée des ' . $nbStructures . ' structures qui fait '
            . 'foi : le journal étant unique, la règle la plus protectrice des preuves '
            . 's\'applique à toutes. Le plancher, si aucune durée n\'est saisie, est celui '
            . 'de config/audit.php.'
        );
        $portrait->addText(
            'Avant cette suppression, les entrées de plus de ' . $conservation['mois']
            . ' mois sont basculées dans une table d\'archives (activity_log_archives) par la '
            . 'commande activity:archive : une entrée n\'est donc jamais détruite avant d\'avoir '
            . 'été conservée — la purge refuse de courir plus vite que l\'archivage. '
            . ($conservation['archives'] == 0
                ? 'Sur cette installation, l\'archive est encore vide : la bascule n\'a jamais '
                  . 'été exécutée ici, et ' . $conservation['a_archiver'] . ' entrée(s) du '
                  . 'journal ont déjà l\'âge d\'y être versées.'
                : 'L\'archive contient ' . $conservation['archives'] . ' entrée(s), la dernière '
                  . 'bascule date du ' . Carbon::parse($conservation['derniere'])->format('d/m/Y')
                  . '.')
        );
        $portrait->addText(
            $conservation['planning']
                // La bascule est déclarée dans le logiciel, mais une déclaration ne se
                // déclenche pas toute seule : encore faut-il que le serveur réveille le
                // planning. Le document ne peut pas le vérifier à lui tout seul — c'est
                // précisément le point à faire valider par l'administrateur du serveur.
                ? 'La bascule est bien programmée dans le logiciel (une fois par mois). Reste un '
                  . 'point que le logiciel ne peut pas vérifier depuis l\'intérieur : le planning '
                  . 'n\'a d\'effet que si le serveur l\'appelle, ce qui demande une tâche '
                  . '« php artisan schedule:run » côté système. Sans elle, ni archivage ni purge ne '
                  . 'se déclenchent, et le journal grossit sans jamais être archivé. '
                  . $conservation['a_archiver'] . ' entrée(s) sont dans ce cas aujourd\'hui.'
                : 'La bascule n\'est programmée nulle part : sans une tâche pour lancer '
                  . '« php artisan activity:archive » puis « php artisan logs:clean », le journal '
                  . 'grossit sans jamais être archivé.'
        );

        // — 6. Suivi DG
        $portrait->addTitle('6. Le suivi que la Direction Générale peut exiger', 1);
        $portrait->addText('Chaque ligne se lit sans intermédiaire technique, dans les écrans du logiciel.', 'Clair');
        $t = $portrait->addTable('Cadre', ['width' => 9400]);
        $t->addRow(null, ['tblHeader' => true]);
        foreach (['Contrôle', 'Fréquence conseillée', 'Où le voir', 'Signe qui doit alerter'] as $i => $c) {
            $t->addCell([2500, 1500, 2200, 3200][$i])->addText($c, null, ['alignment' => 'center']);
        }
        foreach ([
            ['Comptes sans rôle ou devenus inactifs', 'Mensuel', 'Administration → Utilisateurs', 'Un compte actif sans rôle : à corriger ou désactiver'],
            ['Comptes ayant changé de rôle', 'Mensuel', 'Administration → Journal d\'activité, filtre « role »', 'Tout changement non signé d\'un responsable de service'],
            ['Certificats d\'engagement annulés', 'Hebdomadaire', 'Budget → Engagements, filtre statut annulé', 'Volume d\'annulations d\'un même agent ; annulations en fin de mois'],
            ['Virements exécutés hors collectif', 'Mensuel', 'Budget → Virements budgétaires', 'Virement exécuté le dernier jour d\'un mois clôturé'],
            ['Collectifs adoptés', 'Trimestriel', 'Budget → Collectifs budgétaires', 'Collectif adopté sans passage par un bordereau'],
            ['Régies non apurées ou suspendues', 'Mensuel', 'Budget → Régies d\'avance', 'Une régie encore en cours à la clôture ; un responsable cumulant deux régies'],
            ['Ordonnances payées sans visa', 'Mensuel', 'Budget → Ordonnances de paiement', 'Un paiement marqué sans liquidation visée'],
            ['Dépassement d\'une ligne budgétaire', 'Mensuel', 'Tableau de bord Budget', 'Un disponible négatif, même présenté comme justifié'],
            ['Revue des droits par rôle', 'Trimestrielle', 'Administration → Rôles', 'Des droits cumulés sans que personne ne les ait ouverts'],
        ] as $ligne) {
            $t->addRow();
            foreach ($ligne as $i => $cellule) {
                $t->addCell([2500, 1500, 2200, 3200][$i])->addText($cellule, ['size' => 8.5]);
            }
        }

        // — 7. Engagements de l'agent
        $portrait->addTitle('7. Règles d\'usage que chaque agent signe', 1);
        foreach ([
            'Je n\'utilise que mon compte personnel. Je ne prête pas mon mot de passe, je ne travaille pas sous le compte d\'un collègue.',
            'Je ne valide que les actes de ma fonction. Si un document attend ma signature et ne relève pas de mon rôle, je le retourne.',
            'Je n\'annule pas une pièce engagée pour corriger une erreur de saisie : je passe par la procédure de correction, pour que la trace reste.',
            'Je signale immédiatement à ma hiérarchie et à l\'administrateur tout accès anormal, tout montant incohérent sur un document que j\'ai signé.',
            'Je comprends que chacun de mes actes dans le SIGB est nominatif et horodaté, et qu\'il peut être demandé en cas de contrôle.',
            'Je ne fais signer aucun document budgétaire à partir d\'une capture d\'écran : le document vient du logiciel.',
            'À ma sortie de fonction, mon compte est désactivé : la désactivation coupe immédiatement les sessions ouvertes.',
        ] as $l) {
            $portrait->addListItem($l, 0, ['size' => 9.5]);
        }

        // — 8. Vigilance
        $portrait->addTitle('8. Points de vigilance pour l\'administrateur', 1);
        $portrait->addText('Ces constats viennent du code, pas du paramétrage. Ils disent où un réglage dans l\'écran Rôles n\'a aucun effet.', 'Clair');
        $t = $portrait->addTable('Cadre', ['width' => 9400]);
        $t->addRow(null, ['tblHeader' => true]);
        foreach (['Constat', 'Conséquence pour la charte', 'Où le vérifier'] as $i => $c) {
            $t->addCell([3200, 3600, 2600][$i])->addText($c, null, ['alignment' => 'center']);
        }
        foreach ([
            [$nonTestees->count().' permissions du catalogue ne sont testées par aucun code d\'autorisation de l\'application ('
                .$absentesDuCode->count().' n\'apparaissent nulle part, '.$citeesSansTest->count().' sont citées sans jamais servir de test) : '
                .'émettre / viser / payer une ordonnance, valider un bordereau, valider une régie, ajuster un stock, transmettre ou retourner un document…',
             'Cocher ou décocher ces cases ne change rien au comportement. La charte ne s\'appuie que sur les '.count($slugsExiges).' permissions effectivement vérifiées.',
             'Annexe C — liste par écran'],
            ['La transmission d\'un bordereau d\'engagement est protégée par « transmettre_bordereau », que la base n\'accorde qu\'à '.$porteurBordereau->implode(', ')
                .' ; le receveur, lui, est choisi sur une liste de rôles écrite dans le code (contrôleur financier, DAAF, directeur général).',
             'Ce sont ces trois rôles — et eux seuls — qui peuvent engager une transmission, quel que soit le service demandeur. Vérifiez qu\'ils correspondent bien aux services habilités : la liste n\'est pas celle du circuit budgétaire habituel.',
             'BordereauEngagementResource.php:324, :361'],
            ['Le bouton « Émettre » d\'une ordonnance ne dépend d\'aucune permission : seul le statut brouillon l\'affiche (OrdonnancePaiementResource.php:378-387). Les exports Excel et PDF de la liste n\'en dépendent pas non plus (:802-925). La permission « emettre_ordonnance_paiement » existe pourtant au catalogue et est accordée à '.$porteurEmettre->implode(', ').'.',
             'Voir la liste des ordonnances suffit pour les exporter, et un brouillon à l\'écran peut être émis. Cocher « émettre » dans l\'écran Rôles n\'y change rien : à verrouiller côté code avant toute délégation large.',
             'OrdonnancePaiementResource.php:378-389, :802-925'],
            ['« Marquer payée » accepte deux replis : la permission « valider_ordonnance_paiement », puis quatre rôles (super_admin, admin, agence_comptable, daaf) même sans permission.',
             'Retirer la permission ne ferme pas l\'action pour ces quatre rôles. Le contrôle utile passe par le portefeuille de rôles, pas par la case cochée.',
             'OrdonnancePaiementResource.php:404-407, :492-502'],
            [$niveauZero->count().' rôles sont restés au niveau hiérarchique 0 (défaut de la migration qui a créé la colonne) : « retourner un document » ne peut jamais leur être adressé.',
             'La chaîne de transmission est à compléter écran Rôles avant de l\'inscrire dans la charte comme un contrôle acquis.',
             'migration 2026_02_01_051113, Role.php:34'],
            ['Le module financier est déclaré mais presque vide : seul son tableau de bord existe, et aucun rôle métier ne reçoit l\'accès.',
             'N\'annoncez pas la comptabilité comme disponible dans la charte signée cette année.',
             'app/Filament/Financier'],
            ['Deux rôles quasi homonymes coexistent en base ('.implode(' et ', $doublons).'), avec le même volume de droits.',
             'Risque d\'oubli à l\'affectation. À fusionner après vérification des comptes concernés.',
             'table roles, jointure model_has_roles'],
            ['Les ordonnances de paiement ne portent aucun gel : '.$op['brutZero'].' ordonnances sur '.$op['total'].' affichent un montant brut vide, recalculé à l\'impression.',
             'Un état relu après coup peut différer du papier signé. Tant que le gel n\'est pas posé sur ce document, la valeur probante reste le papier.',
             'table '.$tableOp.', aucune colonne snapshot'],
        ] as $ligne) {
            $t->addRow();
            foreach ($ligne as $i => $cellule) {
                $t->addCell([3200, 3600, 2600][$i])->addText($cellule, ['size' => 8]);
            }
        }

        // — Annexe A
        $portrait->addPageBreak();
        $portrait->addTitle('Annexe A — Qui porte quel rôle', 1);
        if ($sansNominatifs) {
            $portrait->addText(
                'Version sans noms (diffusable). Les effectifs par rôle figurent en section 2. Régénérez ce document sans '
                .'l\'option --sans-nominatifs pour obtenir la liste nominative destinée à la Direction Générale.', 'Clair');
        } else {
            $portrait->addText('État des comptes au '.now()->format('d/m/Y').'. Un compte sans rôle ne peut ouvrir aucun module.', 'Clair');
            $t = $portrait->addTable('Cadre', ['width' => 9400]);
            $t->addRow(null, ['tblHeader' => true]);
            foreach (['Agent', 'Rôle', 'Compte'] as $i => $c) {
                $t->addCell([5000, 3200, 1200][$i])->addText($c, null, ['alignment' => 'center']);
            }
            foreach ($nominatifs as $n) {
                $t->addRow();
                $t->addCell(5000)->addText($n->name, ['size' => 8.5]);
                $t->addCell(3200)->addText($libelles[$n->role][0] ?? $n->role, ['size' => 8.5]);
                $t->addCell(1200)->addText($n->actif ? 'actif' : 'désactivé', ['size' => 8.5], ['alignment' => 'center']);
            }
            if ($sansRole->isNotEmpty()) {
                $t->addRow();
                $t->addCell(5000)->addText('SANS AUCUN RÔLE : '.$sansRole->implode(', '), ['size' => 8.5, 'bold' => true, 'color' => $rouge]);
                $t->addCell(3200, ['bgColor' => 'F2F2F2'])->addText('');
                $t->addCell(1200, ['bgColor' => 'F2F2F2'])->addText('');
            }
        }

        // — Annexe B
        $portrait->addTitle('Annexe B — Les actions et la permission qui les protège', 1);
        $portrait->addText('Une seule de ces lignes suffit à redire où est le verrou. Les numéros de ligne correspondent à l\'état du code à la génération.', 'Clair');
        $t = $portrait->addTable('Cadre', ['width' => 9400]);
        $t->addRow(null, ['tblHeader' => true]);
        foreach (['Action', 'Permission vérifiée par le code', 'Où le logiciel la teste'] as $i => $c) {
            $t->addCell([3300, 3400, 2700][$i])->addText($c, null, ['alignment' => 'center']);
        }
        foreach ($actions as $bloc) {
            foreach ($bloc[1] as $a) {
                $t->addRow();
                $t->addCell(3300)->addText($a[0], ['size' => 8.5]);
                $t->addCell(3400)->addText(implode(' + ', $a[1]), ['size' => 8]);
                $t->addCell(2700)->addText($a[2], ['size' => 7.5, 'color' => $gris]);
            }
        }

        // — Annexe C
        $portrait->addPageBreak();
        $portrait->addTitle('Annexe C — Permissions accordables sans effet de verrouillage', 1);
        $portrait->addText(
            $nonTestees->count().' permissions du catalogue n\'apparaissent dans aucun test d\'autorisation du code de '
            .'l\'application ('.$racine.'/app). Les accorder ou les retirer ne change rien : ce n\'est pas un défaut de la '
            .'base, ce sont des fonctions annoncées et non verrouillées. Sont comptées comme vérifiées les permissions '
            .'testées en dur (->can(…), peutUtiliser(…), hasAnyPermission(…)) et celles que le trait '
            .'AutorisationParPermissions construit pour les ressources qui l\'emploient.', 'Clair');

        $t = $portrait->addTable('Cadre', ['width' => 9400]);
        $t->addRow(null, ['tblHeader' => true]);
        foreach (['Écran / document concerné', 'Actions concernées', 'Dans le code'] as $i => $c) {
            $t->addCell([2900, 4600, 1900][$i])->addText($c, null, ['alignment' => 'center']);
        }
        foreach ($parCible as $cible => $contenu) {
            $t->addRow();
            $t->addCell(2900)->addText(ucfirst(str_replace('_', ' ', $cible)), ['size' => 8, 'bold' => true]);
            $t->addCell(4600)->addText(implode(', ', $contenu['actions']->all()), ['size' => 7.5]);
            $t->addCell(1900)->addText($contenu['cittee'] ? 'citée, jamais testée' : 'aucune occurrence',
                ['size' => 7.5, 'color' => $contenu['cittee'] ? $rouge : $gris], ['alignment' => 'center']);
        }
        $portrait->addText(
            'Lecture : « aucune occurrence » = l\'action correspondante n\'est nulle part dans le code (l\'écran Rôles la '
            .'propose, le logiciel ne la connaît pas). « citée, jamais testée » = le slug apparaît dans le code (libellé, '
            .'commentaire, ancien test) sans servir de verrou — c\'est la catégorie à traiter en priorité par un correctif.',
            'Petit', 'Note');

        // — Emargement
        $portrait->addPageBreak();
        $portrait->addTitle('Faire approuver la charte', 1);
        $portrait->addText(
            'La charte prend effet à la signature du Directeur Général. Les signatures ci-dessous attestent que chaque '
            .'responsable a relu les droits de son rôle et de son personnel.', 'Clair');
        $t = $portrait->addTable('Cadre', ['width' => 9400]);
        $t->addRow(null, ['tblHeader' => true]);
        foreach (['Fonction', 'Nom', 'Date', 'Signature'] as $i => $c) {
            $t->addCell([2600, 2600, 1600, 2600][$i])->addText($c, null, ['alignment' => 'center']);
        }
        foreach (['Directeur Général', 'Directeur Administratif et Financier', 'Contrôleur financier', 'Agence comptable',
            'Chef de service Budget', 'Chef de service Marchés publics', 'Comptable des matières',
            'Responsable des régies d\'avance', 'Administrateur du SIGB'] as $f) {
            $t->addRow(700);
            $t->addCell(2600)->addText($f, ['size' => 9]);
            foreach ([2600, 1600, 2600] as $w) {
                $t->addCell($w, ['bgColor' => 'FFFFFF'])->addText('', ['size' => 9]);
            }
        }


        $phpWord->save($sortie, 'Word2007');
        $this->notes[] = 'écrit : '.$sortie.' ('.round(filesize($sortie) / 1024).' Ko)';
        $this->notes[] = 'rôles : '.$ordreRoles->count().' · actions : '.count($slugsExiges)
            .' · version sans nominatifs : '.($sansNominatifs ? 'oui' : 'non');

        return $sortie;
    }

    /** Les constats de la dernière génération, une ligne par constat. */
    public function notes(): array
    {
        return $this->notes;
    }
}
