import { useCallback, useEffect, useRef, useState } from 'react'
import { DatabaseZap, AlertTriangle, RefreshCw } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { libelleEntite } from '@/features/desktop/registreLabels'

interface EtatClonage {
  ecoleIndex: number
  ecoleTotal: number
  ecoleNom: string | null
  etape: number
  totalEtapes: number
  cleActuelle: string | null
  lignesCumulees: number
  erreurs: string[]
  enCours: boolean
  echec: boolean
}

const ETAT_INITIAL: EtatClonage = {
  ecoleIndex: 0,
  ecoleTotal: 1,
  ecoleNom: null,
  etape: 0,
  totalEtapes: 1,
  cleActuelle: null,
  lignesCumulees: 0,
  erreurs: [],
  enCours: true,
  echec: false,
}

function reduire(etat: EtatClonage, evenement: EvenementSyncProgress): EtatClonage {
  switch (evenement.type) {
    case 'debut':
      return { ...etat, ecoleTotal: Math.max(evenement.ecoles, 1), totalEtapes: Math.max(evenement.entites_par_ecole, 1) }
    case 'ecole_debut':
      return { ...etat, ecoleIndex: evenement.index, ecoleTotal: evenement.total, ecoleNom: evenement.nom, etape: 0, lignesCumulees: 0 }
    case 'entite_debut':
      return { ...etat, etape: evenement.etape, totalEtapes: evenement.total_etapes, cleActuelle: evenement.cle }
    case 'entite_progres':
      return { ...etat, lignesCumulees: evenement.lignes }
    case 'entite_fin':
      return { ...etat, etape: evenement.etape, totalEtapes: evenement.total_etapes, lignesCumulees: evenement.lignes }
    case 'ecole_erreur':
      return { ...etat, erreurs: [...etat.erreurs, evenement.message] }
    case 'ecole_fin':
    case 'clonage_initial_complet':
    case 'fin':
      return etat
    default:
      return etat
  }
}

/**
 * Modale plein écran, non fermable par un clic à l'extérieur, affichée tant
 * que le compte qui vient de se connecter (première liaison de ce poste, ou
 * reconnexion locale d'un clonage resté incomplet — cf. `LoginPage.tsx`) n'a
 * pas intégralement répliqué ses données. Aucune sortie possible sans un
 * clonage réussi : sur échec, seule une nouvelle tentative est proposée
 * (`sync:pull` reprend là où le curseur s'est arrêté, cf. `SyncPull.php`) —
 * ni bouton « continuer quand même », ni accès à l'application avec des
 * données partielles.
 */
export function PremiereSynchronisationModal({ onTermine, onAnnuler }: { onTermine: () => void; onAnnuler: () => void }) {
  const { i18n } = useTranslation()
  const locale = i18n.language.startsWith('en') ? 'en' : 'fr'
  const [etat, setEtat] = useState<EtatClonage>(ETAT_INITIAL)
  const [tentative, setTentative] = useState(0)
  const tentativeEnCours = useRef(false)

  const lancer = useCallback(() => {
    // Un seul `runInitialSync()` en vol à la fois — `StrictMode` invoque les
    // effets deux fois en développement, et un double-clic sur « Réessayer »
    // ne doit pas non plus en cumuler deux : le second échouerait
    // immédiatement (`syncEnCours` déjà vrai côté `main.cjs`), affichant un
    // faux échec alors que le premier clonage tourne toujours.
    if (tentativeEnCours.current) return
    tentativeEnCours.current = true

    setEtat((etatPrecedent) => ({ ...ETAT_INITIAL, erreurs: etatPrecedent.erreurs }))

    window.desktop
      ?.runInitialSync()
      .then((resultat) => {
        setEtat((etatPrecedent) => ({ ...etatPrecedent, enCours: false, echec: !resultat.succes }))
      })
      .catch((erreur: Error) => {
        setEtat((etatPrecedent) => ({
          ...etatPrecedent,
          enCours: false,
          echec: true,
          erreurs: [...etatPrecedent.erreurs, erreur.message],
        }))
      })
      .finally(() => {
        tentativeEnCours.current = false
      })
  }, [])

  useEffect(() => {
    const desactiverEcoute = window.desktop?.onSyncProgress((evenement) => {
      setEtat((etatPrecedent) => reduire(etatPrecedent, evenement))
    })

    lancer()

    return () => desactiverEcoute?.()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tentative])

  useEffect(() => {
    if (!etat.enCours && !etat.echec) {
      const delai = setTimeout(onTermine, 700)
      return () => clearTimeout(delai)
    }
  }, [etat.enCours, etat.echec, onTermine])

  const progresEcole = etat.totalEtapes > 0 ? etat.etape / etat.totalEtapes : 0
  const progresGlobal = etat.ecoleTotal > 0
    ? ((etat.ecoleIndex - 1 + progresEcole) / etat.ecoleTotal) * 100
    : 0
  const pourcentage = Math.min(100, Math.max(0, Math.round(progresGlobal)))
  const termineAvecSucces = !etat.enCours && !etat.echec

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-navy-900/80 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-2xl border border-navy-100/70 bg-white p-8 shadow-lifted">
        <div className="flex items-center gap-3">
          <span className="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-navy-800 text-cream-50">
            {etat.echec ? <AlertTriangle className="h-5 w-5 text-gold-300" /> : <DatabaseZap className="h-5 w-5" />}
          </span>
          <div>
            <h2 className="font-display text-lg font-bold text-navy-900">
              {etat.echec ? 'Clonage interrompu' : termineAvecSucces ? 'Clonage terminé' : 'Préparation du poste hors-ligne'}
            </h2>
            <p className="text-xs text-navy-400">
              {etat.echec
                ? 'La connexion reste indisponible tant que toutes vos données ne sont pas récupérées.'
                : termineAvecSucces
                  ? 'Toutes vos données sont maintenant disponibles hors-ligne.'
                  : 'Premier lancement : téléchargement complet de vos données. Ne fermez pas l’application.'}
            </p>
          </div>
        </div>

        {etat.ecoleTotal > 1 && (
          <p className="mt-5 text-xs font-semibold uppercase tracking-wide text-navy-400">
            École {etat.ecoleIndex || 1} / {etat.ecoleTotal}
            {etat.ecoleNom ? ` — ${etat.ecoleNom}` : ''}
          </p>
        )}

        <div className="mt-2">
          <div className="h-2.5 w-full overflow-hidden rounded-full bg-navy-50">
            <div
              className={`h-full rounded-full transition-[width] duration-300 ${etat.echec ? 'bg-gold-500' : 'bg-green-500'}`}
              style={{ width: `${termineAvecSucces ? 100 : pourcentage}%` }}
            />
          </div>
          <div className="mt-2 flex items-center justify-between text-xs text-navy-500">
            <span className="truncate">
              {termineAvecSucces
                ? 'Terminé'
                : etat.cleActuelle
                  ? `${libelleEntite(etat.cleActuelle, locale)}…`
                  : 'Connexion au serveur…'}
            </span>
            <span className="flex-none font-semibold text-navy-700">{termineAvecSucces ? '100%' : `${pourcentage}%`}</span>
          </div>
          {etat.enCours && etat.lignesCumulees > 0 && (
            <p className="mt-1 text-[11px] text-navy-400">{etat.lignesCumulees} ligne(s) reçue(s) pour cette table…</p>
          )}
        </div>

        {etat.erreurs.length > 0 && (
          <p className="mt-4 rounded-lg bg-gold-50 px-3 py-2 text-xs font-medium text-gold-700">
            {etat.erreurs[etat.erreurs.length - 1]}
          </p>
        )}

        {etat.echec && (
          <div className="mt-5 flex flex-col gap-2">
            <button
              type="button"
              onClick={() => setTentative((n) => n + 1)}
              className="flex w-full items-center justify-center gap-1.5 rounded-lg bg-navy-700 px-4 py-2.5 text-sm font-semibold text-cream-50 transition-colors hover:bg-navy-800"
            >
              <RefreshCw className="h-4 w-4" />
              Réessayer
            </button>
            <button
              type="button"
              onClick={onAnnuler}
              className="w-full rounded-lg px-4 py-2 text-xs font-semibold text-navy-400 transition-colors hover:text-navy-600"
            >
              Utiliser un autre compte
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
