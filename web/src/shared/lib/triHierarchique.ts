type Accesseur<T> = (item: T) => string | number | null | undefined

/**
 * Compare deux valeurs de tri : nombres comparés numériquement, tout le
 * reste en texte français avec tri naturel des nombres inclus ; une valeur
 * absente retombe toujours en fin de liste, quel que soit le sens.
 */
function comparerValeurs(a: string | number | null | undefined, b: string | number | null | undefined): number {
  if (a == null && b == null) return 0
  if (a == null) return 1
  if (b == null) return -1
  if (typeof a === 'number' && typeof b === 'number') return a - b
  return String(a).localeCompare(String(b), 'fr', { numeric: true })
}

/**
 * Comparateur par défaut d'une table d'entités : regroupe les lignes par
 * école, puis sous-système, puis niveau, puis classe — l'ordre attendu dès
 * que plusieurs écoles/classes se mélangent dans une même liste. Chaque
 * dimension est optionnelle : une page sans sous-système omet simplement cet
 * accesseur plutôt que d'en fournir un qui ne renverrait rien.
 */
export function triEcoleSousSystemeNiveauClasse<T>(accesseurs: {
  ecole?: Accesseur<T>
  sousSysteme?: Accesseur<T>
  niveau?: Accesseur<T>
  classe?: Accesseur<T>
}): (a: T, b: T) => number {
  const dimensions = [accesseurs.ecole, accesseurs.sousSysteme, accesseurs.niveau, accesseurs.classe]
    .filter((accesseur): accesseur is Accesseur<T> => Boolean(accesseur))

  return (a, b) => {
    for (const accesseur of dimensions) {
      const resultat = comparerValeurs(accesseur(a), accesseur(b))
      if (resultat !== 0) return resultat
    }
    return 0
  }
}
