-- ---------------------------------------------------------------------------
-- Nombre de familles dans le système
-- ---------------------------------------------------------------------------
--
-- Il n'existe pas de table « familles » : une famille se reconstitue depuis le
-- lien élève ↔ tuteur. Deux élèves sont d'une même famille dès qu'ils partagent
-- un tuteur — directement, ou de proche en proche : l'aîné rattaché au père,
-- le cadet à la mère, et les deux parents joignables au même numéro.
--
-- Cette transitivité n'est pas un raffinement théorique. Sans elle, le compte
-- monte à 741 au lieu de 677 sur la base actuelle, soit 9 % de familles
-- fantômes — précisément les fratries que les fiches ne relient pas
-- directement.
--
-- Trois étapes : fusionner les fiches tuteur qui désignent la même personne,
-- étiqueter chaque élève, puis propager l'étiquette la plus petite jusqu'à
-- stabilisation.
--
-- Tables de travail ordinaires et non TEMPORARY : MySQL interdit de citer deux
-- fois une même table temporaire dans une requête, ce que la propagation fait
-- nécessairement (elle joint les élèves à leurs frères et sœurs).
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS tmp_foyer;
DROP TABLE IF EXISTS tmp_famille;
DROP TABLE IF EXISTS tmp_propagation;

-- 1. Les fiches tuteur qui partagent un numéro de téléphone sont une seule
--    personne : le fichier porte une fiche par enfant pour un même parent.
--    Sans numéro, la fiche reste seule — on ne fusionne pas sur le nom, trop
--    d'homonymes réels dans un établissement.
CREATE TABLE tmp_foyer AS
SELECT
    id AS tuteur_id,
    CASE
        WHEN REGEXP_REPLACE(COALESCE(telephone, ''), '[^0-9]', '') = '' THEN id
        ELSE MIN(id) OVER (PARTITION BY REGEXP_REPLACE(telephone, '[^0-9]', ''))
    END AS foyer_id
FROM tuteurs;

ALTER TABLE tmp_foyer ADD PRIMARY KEY (tuteur_id), ADD INDEX (foyer_id);

-- 2. Étiquette de départ de chaque élève : le plus petit foyer qui le rattache.
CREATE TABLE tmp_famille AS
SELECT et.eleve_id, MIN(f.foyer_id) AS groupe
FROM eleve_tuteur et
JOIN tmp_foyer f ON f.tuteur_id = et.tuteur_id
GROUP BY et.eleve_id;

ALTER TABLE tmp_famille ADD PRIMARY KEY (eleve_id), ADD INDEX (groupe);

-- 3. Propagation. Chaque tour aligne un élève sur la plus petite étiquette de
--    sa fratrie ; la plus longue chaîne observée tient en trois tours, cinq
--    laissent de la marge. Un tour qui ne change rien est sans effet, il n'y a
--    donc aucun risque à en faire un de trop.
--
--    Répéter le bloc suivant 5 fois. ------------------------------------------

CREATE TABLE tmp_propagation AS
SELECT fam.eleve_id, MIN(voisin.groupe) AS groupe
FROM tmp_famille fam
JOIN eleve_tuteur et      ON et.eleve_id = fam.eleve_id
JOIN tmp_foyer f          ON f.tuteur_id = et.tuteur_id
JOIN tmp_foyer f2         ON f2.foyer_id = f.foyer_id
JOIN eleve_tuteur et2     ON et2.tuteur_id = f2.tuteur_id
JOIN tmp_famille voisin   ON voisin.eleve_id = et2.eleve_id
GROUP BY fam.eleve_id;

UPDATE tmp_famille fam
JOIN tmp_propagation p ON p.eleve_id = fam.eleve_id
SET fam.groupe = p.groupe
WHERE p.groupe < fam.groupe;

DROP TABLE tmp_propagation;

--    Fin du bloc à répéter. ---------------------------------------------------

-- 4. Le résultat.
SELECT COUNT(DISTINCT groupe) AS familles FROM tmp_famille;

-- Répartition des fratries, pour contrôler la vraisemblance du compte.
SELECT enfants, COUNT(*) AS familles
FROM (SELECT groupe, COUNT(*) AS enfants FROM tmp_famille GROUP BY groupe) x
GROUP BY enfants
ORDER BY enfants;

-- Familles par école. Un élève transféré peut relier deux écoles du complexe :
-- la famille est alors comptée dans chacune, et la somme dépasse le total.
SELECT s.name AS ecole, COUNT(DISTINCT fam.groupe) AS familles
FROM tmp_famille fam
JOIN eleves e ON e.id = fam.eleve_id
JOIN schools s ON s.id = e.school_id
GROUP BY s.id, s.name
ORDER BY familles DESC;

DROP TABLE IF EXISTS tmp_foyer;
DROP TABLE IF EXISTS tmp_famille;
