-- ============================================================================
-- RETRAIT DES LIGNES DE PRÉVISION DE RECETTES EN DOUBLON DE CODE + CONTRAINTE
-- ----------------------------------------------------------------------------
-- Le code de nomenclature est la clé métier d'une ligne de prévision. L'index
-- uniq_prevision_nomenclature_active porte sur nomenclature_id : il laisse donc
-- passer deux lignes du même code quand le code a été enregistré deux fois en
-- nomenclature — ce que produisaient les mouvements de collectif « créer une
-- nouvelle ligne » supprimés puis recréés (cas du 731207 à 1 400 000 000 FCFA).
--
-- Utilisation (production comme dev) :
--   psql "postgresql://USER:MOTDEPASSE@HOTE:5432/NOM_BASE" \
--        -v ON_ERROR_STOP=1 -f database/sql/dedup_lignes_recettes.sql
--
-- Tout tient dans UNE transaction :
--   * une ligne en doublon qui porte encore une trace — recette réelle
--     enregistrée, ou mouvement de collectif non annulé — lève une exception et
--     rien n'est écrit. Ces cas-là se règlent dans l'application
--     (« Réinitialisation des collectifs budgétaires ») ou à la main ;
--   * les doublons sont mis EN CORBEILLE (deleted_at), avec leurs 12 mois,
--     jamais effacés : les montants restent retrouvables.
--
-- Retour arrière : le rapport final liste les ids mis en corbeille, il suffit de
--   BEGIN;
--   UPDATE lignes_previsions_recettes  SET deleted_at = NULL WHERE id IN (<ids>);
--   UPDATE previsions_recettes_mensuelles SET deleted_at = NULL
--     WHERE ligne_prevision_recette_id IN (<ids>);
--   DROP INDEX IF EXISTS lignes_prev_recettes_prevision_code_active; COMMIT;
--
-- Équivalent Laravel, à préférer s'il tourne sur le serveur de prod (même effet,
-- avec le rapport ligne à ligne et l'option --fusionner) :
--   php artisan budget:dedup-lignes-recettes        -- aperçu, aucune écriture
--   php artisan budget:dedup-lignes-recettes --run  -- applique et crée l'index
--
-- À exécuter APRÈS database/sql/dedup_nomenclature.sql : tant que deux
-- nomenclatures portent le même code, la ligne conservée peut pointer sur l'une
-- ou l'autre et le doublon renaît à la prochaine saisie.
-- ============================================================================

\set ON_ERROR_STOP on

BEGIN;

-- 1. Trace portée par chaque ligne active ------------------------------------
--    Une ligne « vit » si des recettes réelles y sont rattachées ou si un
--    mouvement de collectif non annulé s'appuie encore dessus.
DROP TABLE IF EXISTS tmp_traces;
CREATE TEMP TABLE tmp_traces AS
SELECT l.id,
       l.prevision_recette_id,
       l.code_nomenclature,
       l.nomenclature_id,
       l.libelle_nomenclature,
       COALESCE(l.montant_prevu_initial, 0) AS initial,
       COALESCE(l.montant_rectifie, 0)      AS rectifie,
       (SELECT COUNT(*) FROM recettes_reelles r
          JOIN previsions_recettes_mensuelles m ON m.id = r.prevision_recette_mensuelle_id
         WHERE m.ligne_prevision_recette_id = l.id
           AND m.deleted_at IS NULL AND r.deleted_at IS NULL)  AS nb_recettes,
       (SELECT COUNT(*) FROM mouvements_collectifs mv
         WHERE (mv.ligne_recette_id = l.id OR mv.nouvelle_ligne_recette_id = l.id)
           AND (mv.statut IS NULL OR mv.statut NOT IN ('annule', 'annulee'))
           AND mv.date_annulation IS NULL)                      AS nb_mouvements
FROM lignes_previsions_recettes l
WHERE l.deleted_at IS NULL
  AND l.code_nomenclature IS NOT NULL;

-- 2. Groupes en doublon : la conservée est celle qui porte la trace,
--    sinon la plus ancienne (plus petit id).
DROP TABLE IF EXISTS tmp_groupes;
CREATE TEMP TABLE tmp_groupes AS
SELECT prevision_recette_id, code_nomenclature,
       COUNT(*) AS nb,
       (array_agg(id ORDER BY (nb_recettes > 0 OR nb_mouvements > 0) DESC, id ASC))[1] AS gardee_id
FROM tmp_traces
GROUP BY prevision_recette_id, code_nomenclature
HAVING COUNT(*) > 1;

DROP TABLE IF EXISTS tmp_doublons;
CREATE TEMP TABLE tmp_doublons AS
SELECT t.*
FROM tmp_traces t
JOIN tmp_groupes g ON g.prevision_recette_id = t.prevision_recette_id
                  AND g.code_nomenclature    = t.code_nomenclature
WHERE t.id <> g.gardee_id;

-- 3. Rapport d'ouverture ------------------------------------------------------
SELECT g.prevision_recette_id AS prev,
       g.code_nomenclature     AS code,
       g.nb                    AS lignes,
       g.gardee_id             AS conservee,
       (SELECT string_agg('n°' || d.id || ' (' || d.rectifie || ')', ', ' ORDER BY d.id)
          FROM tmp_doublons d
         WHERE d.prevision_recette_id = g.prevision_recette_id
           AND d.code_nomenclature = g.code_nomenclature) AS a_retirer
FROM tmp_groupes g
ORDER BY g.prevision_recette_id, g.code_nomenclature;

-- 4. Arrêt sur tout ce qui porte encore une trace -----------------------------
DO $$
DECLARE
    r RECORD;
    blocs INT := 0;
    liste TEXT := '';
BEGIN
    FOR r IN SELECT id, prevision_recette_id, code_nomenclature, nb_recettes, nb_mouvements
               FROM tmp_doublons
              WHERE nb_recettes > 0 OR nb_mouvements > 0
              ORDER BY prevision_recette_id, code_nomenclature, id LOOP
        blocs := blocs + 1;
        liste := liste || format(
            'ligne n°%s (prévision %s, code %s : %s recette(s) réelle(s), %s mouvement(s) actif(s)) ; ',
            r.id, r.prevision_recette_id, r.code_nomenclature, r.nb_recettes, r.nb_mouvements);
    END LOOP;

    IF blocs > 0 THEN
        RAISE EXCEPTION 'ARRÊT : % doublon(s) portent encore une somme enregistrée — %',
            blocs, liste
            USING HINT = 'Réimputez les recettes, ou réinitialisez le collectif concerné depuis l''application, puis relancez.';
    END IF;
END
$$;

-- 5. Corbeille : la ligne et ses 12 mois -------------------------------------
UPDATE previsions_recettes_mensuelles m
   SET deleted_at = now(), updated_at = now()
  FROM tmp_doublons d
 WHERE m.ligne_prevision_recette_id = d.id
   AND m.deleted_at IS NULL;

UPDATE lignes_previsions_recettes l
   SET deleted_at = now(), updated_at = now()
  FROM tmp_doublons d
 WHERE l.id = d.id
   AND l.deleted_at IS NULL;

-- 6. Recalcul des lignes restantes des prévisions touchées --------------------
UPDATE lignes_previsions_recettes l
   SET montant_recouvre = COALESCE((
         SELECT SUM(m.montant_recouvre) FROM previsions_recettes_mensuelles m
          WHERE m.ligne_prevision_recette_id = l.id AND m.deleted_at IS NULL), 0)
  FROM (SELECT DISTINCT prevision_recette_id FROM tmp_groupes) g
 WHERE l.prevision_recette_id = g.prevision_recette_id
   AND l.deleted_at IS NULL;

UPDATE lignes_previsions_recettes
   SET ecart = montant_recouvre - montant_rectifie,
       taux_recouvrement = CASE WHEN montant_rectifie = 0 THEN 0
                                ELSE montant_recouvre / montant_rectifie * 100 END
 WHERE deleted_at IS NULL
   AND prevision_recette_id IN (SELECT DISTINCT prevision_recette_id FROM tmp_groupes);

-- 7. Contrainte ---------------------------------------------------------------
CREATE UNIQUE INDEX IF NOT EXISTS lignes_prev_recettes_prevision_code_active
    ON lignes_previsions_recettes (prevision_recette_id, code_nomenclature)
    WHERE deleted_at IS NULL;

-- 8. Rapport de clôture -------------------------------------------------------
SELECT (SELECT COUNT(*) FROM tmp_groupes)  AS groupes_traites,
       (SELECT COUNT(*) FROM tmp_doublons) AS lignes_mises_en_corbeille,
       (SELECT COALESCE(SUM(rectifie), 0) FROM tmp_doublons) AS montant_retire,
       (SELECT string_agg(id::text, ',' ORDER BY id) FROM tmp_doublons) AS ids_a_restaurer,
       (SELECT COUNT(*) FROM (
            SELECT 1 FROM lignes_previsions_recettes
             WHERE deleted_at IS NULL AND code_nomenclature IS NOT NULL
             GROUP BY prevision_recette_id, code_nomenclature
            HAVING COUNT(*) > 1) q) AS doublons_restants;

COMMIT;
