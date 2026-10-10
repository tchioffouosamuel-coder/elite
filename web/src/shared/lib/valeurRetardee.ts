import { useEffect, useState } from "react";

/**
 * Valeur qui ne se propage qu'une fois la saisie retombée — un champ de
 * recherche branché sur une requête serveur relançait sinon un appel (et un
 * rechargement de tableau) à chaque caractère frappé, ce qui rend la frappe
 * saccadée et fait clignoter la liste sous les yeux de celui qui cherche.
 *
 * L'état affiché dans l'input reste la valeur immédiate : seule la valeur
 * *consommée* (clé de requête, filtre) passe par ici, pour que la frappe
 * demeure instantanée.
 */
export function useValeurRetardee<T>(valeur: T, delaiMs = 400): T {
  const [retardee, setRetardee] = useState(valeur);

  useEffect(() => {
    const minuteur = window.setTimeout(() => setRetardee(valeur), delaiMs);

    return () => window.clearTimeout(minuteur);
  }, [valeur, delaiMs]);

  return retardee;
}
