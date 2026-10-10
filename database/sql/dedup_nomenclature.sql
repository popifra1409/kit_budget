-- ============================================================================
-- FUSION DES NOMENCLATURES EN DOUBLON DE CODE + CONTRAINTE D'UNICITÉ EN BASE
-- ----------------------------------------------------------------------------
-- Le code est la clé métier d'une nomenclature dans un exercice. Quand deux
-- nomenclatures actives portent le même (exercice_id, code, type) — ce que
-- produisaient les collectifs « créer une nouvelle ligne » supprimés puis
-- repris — le même code se retrouve sur deux lignes de prévision de recettes
-- ou deux lignes budgétaires.
--
-- Utilisation (production comme dev) :
--   psql "postgresql://USER:MOTDEPASSE@HOTE:5432/NOM_BASE" \
--        -v ON_ERROR_STOP=1 -f database/sql/dedup_nomenclature.sql
--
-- Tout tient dans UNE transaction :
--   * un doublon irrécupérable (deux lignes actives du même document) lève une
--     exception et rien n'est écrit ;
--   * les doublons sont mis EN CORBEILLE (deleted_at), jamais effacés : les
--     références des documents, mouvements et recettes déjà enregistrées
--     restent valides.
--
-- Retour arrière : le rapport final liste les ids mis en corbeille, il suffit de
--   BEGIN; UPDATE nomenclature_budgetaire SET deleted_at = NULL WHERE id IN (<ids>);
--   DROP INDEX IF EXISTS nomenclature_exercice_code_type_active; COMMIT;
--   (les lignes réimputées reviennent d'elles-mêmes : elles pointaient déjà sur
--    la nomenclature canonique, qu'il faut réimputer à la main si besoin).
--
-- Équivalent Laravel, à préférer s'il tourne sur le serveur de prod (même effet,
-- avec le rapport par groupe) :
--   php artisan budget:dedup-nomenclature           -- aperçu, aucune écriture
--   php artisan budget:dedup-nomenclature --run     -- applique et crée l'index
-- ============================================================================

\set ON_ERROR_STOP on

BEGIN;

-- 1. Références actives de chaque nomenclature --------------------------------
DROP TABLE IF EXISTS tmp_refs;
CREATE TEMP TABLE tmp_refs AS
SELECT n.id, n.exercice_id, n.code, n.type, n.libelle, n.parent_id,
       (SELECT COUNT(*) FROM lignes_previsions_recettes lp
         WHERE lp.nomenclature_id = n.id AND lp.deleted_at IS NULL)
     + (SELECT COUNT(*) FROM lignes_budgetaires lb
         WHERE lb.nomenclature_id = n.id AND lb.deleted_at IS NULL) AS nb_lignes
FROM nomenclature_budgetaire n
WHERE n.deleted_at IS NULL
  AND n.exercice_id IS NOT NULL;

-- 2. Groupes en doublon : la canonique est celle qui porte déjà des lignes,
--    sinon la plus ancienne (plus petit id).
DROP TABLE IF EXISTS tmp_groupes;
CREATE TEMP TABLE tmp_groupes AS
SELECT exercice_id, code, type,
       COUNT(*) AS nb,
       (array_agg(id ORDER BY (nb_lignes > 0) DESC, id ASC))[1] AS canonique_id
FROM tmp_refs
GROUP BY exercice_id, code, type
HAVING COUNT(*) > 1;

DROP TABLE IF EXISTS tmp_doublons;
CREATE TEMP TABLE tmp_doublons AS
SELECT g.exercice_id, g.code, g.type, g.canonique_id,
       r.id AS nomenclature_id, r.libelle, r.parent_id, r.nb_lignes
FROM tmp_groupes g
JOIN tmp_refs r
  ON r.exercice_id = g.exercice_id
 AND r.code = g.code
 AND r.type IS NOT DISTINCT FROM g.type
 AND r.id <> g.canonique_id;

-- 3. Ce que la fusion ferait se percuter : deux lignes actives dans la même
--    prévision (côté recettes) ou le même budget (côté dépenses).
DROP TABLE IF EXISTS tmp_conflits;
CREATE TEMP TABLE tmp_conflits AS
SELECT d.exercice_id, d.code, d.type,
       'prévision de recettes n°' || lp.prevision_recette_id AS document
FROM tmp_doublons d
JOIN lignes_previsions_recettes lp
  ON lp.nomenclature_id = d.nomenclature_id AND lp.deleted_at IS NULL
JOIN lignes_previsions_recettes lc
  ON lc.nomenclature_id = d.canonique_id AND lc.deleted_at IS NULL
 AND lc.prevision_recette_id = lp.prevision_recette_id
UNION ALL
SELECT d.exercice_id, d.code, d.type,
       'budget n°' || lb.budget_id
FROM tmp_doublons d
JOIN lignes_budgetaires lb
  ON lb.nomenclature_id = d.nomenclature_id AND lb.deleted_at IS NULL
JOIN lignes_budgetaires bc
  ON bc.nomenclature_id = d.canonique_id AND bc.deleted_at IS NULL
 AND bc.budget_id = lb.budget_id;

DO $pla$
DECLARE
    v_conflits INT;
    v_liste    TEXT;
BEGIN
    SELECT COUNT(*) INTO v_conflits FROM tmp_conflits;

    IF v_conflits > 0 THEN
        SELECT E'\n  - ' || string_agg(d, E'\n  - ') INTO v_liste
        FROM (SELECT DISTINCT code || ' (exercice ' || exercice_id || ', type '
              || COALESCE(type, '-') || ') -> ' || document AS d FROM tmp_conflits) q;

        RAISE EXCEPTION 'ARRÊT : % juxtaposition(s) de lignes empêchent la fusion automatique : %.  Fusionnez les montants à la main puis relancez le script.',
            v_conflits, v_liste;
    END IF;
END
$pla$;

-- 4. Ce que la fusion va déplacer (compté avant les mises à jour) --------------
DROP TABLE IF EXISTS tmp_compte;
CREATE TEMP TABLE tmp_compte AS
SELECT (SELECT COUNT(*) FROM lignes_previsions_recettes lp
          JOIN tmp_doublons d ON d.nomenclature_id = lp.nomenclature_id
         WHERE lp.deleted_at IS NULL) AS nb_prevision,
       (SELECT COUNT(*) FROM lignes_budgetaires lb
          JOIN tmp_doublons d ON d.nomenclature_id = lb.nomenclature_id
         WHERE lb.deleted_at IS NULL) AS nb_budget,
       (SELECT COUNT(*) FROM nomenclature_budgetaire enf
          JOIN tmp_doublons d ON d.nomenclature_id = enf.parent_id
         WHERE enf.deleted_at IS NULL) AS nb_enfants;

-- 5. Budgets dont les totaux devront être recalculés
DROP TABLE IF EXISTS tmp_budgets_a_recalculer;
CREATE TEMP TABLE tmp_budgets_a_recalculer AS
SELECT DISTINCT lb.budget_id AS id
FROM lignes_budgetaires lb
JOIN tmp_doublons d ON d.nomenclature_id = lb.nomenclature_id
WHERE lb.deleted_at IS NULL;

-- 6. Réimputation des lignes de prévision de recettes (libellé de la canonique)
UPDATE lignes_previsions_recettes lp
   SET nomenclature_id      = c.id,
       code_nomenclature    = c.code,
       libelle_nomenclature = c.libelle,
       updated_at           = now()
FROM tmp_doublons d
JOIN nomenclature_budgetaire c ON c.id = d.canonique_id
WHERE lp.nomenclature_id = d.nomenclature_id
  AND lp.deleted_at IS NULL;

-- 7. Réimputation des lignes budgétaires
UPDATE lignes_budgetaires lb
   SET nomenclature_id = d.canonique_id,
       updated_at      = now()
FROM tmp_doublons d
WHERE lb.nomenclature_id = d.nomenclature_id
  AND lb.deleted_at IS NULL;

-- 8. Les enfants directs suivent la canonique, seulement si elle est rattachée
--    au même parent (sinon la hiérarchie OHADA serait déformée).
UPDATE nomenclature_budgetaire enf
   SET parent_id  = d.canonique_id,
       updated_at = now()
FROM tmp_doublons d
JOIN nomenclature_budgetaire can ON can.id = d.canonique_id
WHERE enf.parent_id = d.nomenclature_id
  AND enf.deleted_at IS NULL
  AND (d.parent_id IS NOT DISTINCT FROM can.parent_id);

-- 9. Corbeille des nomenclatures fusionnées (jamais de suppression physique :
--     les documents anciens pointent encore dessus et doivent rester lisibles).
UPDATE nomenclature_budgetaire n
   SET deleted_at = now(),
       updated_at = now()
FROM tmp_doublons d
WHERE n.id = d.nomenclature_id
  AND n.deleted_at IS NULL;

-- 10. Totaux des budgets touchés
UPDATE budgets b
   SET budget_total = COALESCE((SELECT SUM(l.budget_rectifie)
                                  FROM lignes_budgetaires l
                                 WHERE l.budget_id = b.id
                                   AND l.deleted_at IS NULL), 0),
       updated_at   = now()
FROM tmp_budgets_a_recalculer t
WHERE b.id = t.id;

-- 11. La contrainte : un code par exercice et par type, côté lignes actives
CREATE UNIQUE INDEX IF NOT EXISTS nomenclature_exercice_code_type_active
    ON nomenclature_budgetaire (exercice_id, code, type)
    WHERE deleted_at IS NULL;

-- 12. Rapport
SELECT 'groupes fusionnés' AS mesure,
       (SELECT COUNT(*) FROM tmp_groupes)::TEXT AS valeur
UNION ALL
SELECT 'nomenclatures en corbeille (ids à restaurer au besoin)',
       COALESCE((SELECT string_agg(nomenclature_id::TEXT, ', ') FROM tmp_doublons), '-')
UNION ALL
SELECT 'lignes de prévision réimputées', nb_prevision::TEXT FROM tmp_compte
UNION ALL
SELECT 'lignes budgétaires réimputées', nb_budget::TEXT FROM tmp_compte
UNION ALL
SELECT 'enfants rattachés à la canonique', nb_enfants::TEXT FROM tmp_compte
UNION ALL
SELECT 'doublons restants en base (0 attendu)',
       (SELECT COUNT(*) FROM (SELECT 1 FROM nomenclature_budgetaire
            WHERE deleted_at IS NULL AND exercice_id IS NOT NULL
            GROUP BY exercice_id, code, type HAVING COUNT(*) > 1) q)::TEXT
UNION ALL
SELECT 'index unique',
       (SELECT CASE WHEN COUNT(*) = 1 THEN 'créé (exercice, code, type) WHERE deleted_at IS NULL'
                    ELSE 'ABSENT' END
          FROM pg_class WHERE relname = 'nomenclature_exercice_code_type_active');

COMMIT;
