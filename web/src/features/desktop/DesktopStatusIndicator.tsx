import { useEffect, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Laptop, RefreshCw, ShieldCheck } from 'lucide-react'
import { fetchStatutSync, lancerSynchronisation, type StatutSyncEcole } from '@/features/desktop/api'
import { useDesktopUpdate } from '@/features/desktop/useDesktopUpdate'
import { Spinner } from '@/shared/ui/Feedback'
import { succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function formatDate(iso: string | null): string {
  if (!iso) return 'Jamais'
  return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function PointComplet({ complet }: { complet: boolean | null }) {
  if (complet === null) return <span className="h-2 w-2 flex-none rounded-full bg-navy-200" title="Non vérifié" />
  if (complet) return <span className="h-2 w-2 flex-none rounded-full bg-green-500" title="Complet" />
  return <span className="h-2 w-2 flex-none rounded-full bg-red-500" title="Incomplet" />
}

function SectionSynchronisation() {
  const queryClient = useQueryClient()
  const [verification, setVerification] = useState(false)
  const [synchronisationEnCours, setSynchronisationEnCours] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['desktop-statut-sync', verification],
    queryFn: () => fetchStatutSync(verification),
  })

  const verifierCompletude = () => {
    setVerification(true)
    queryClient.invalidateQueries({ queryKey: ['desktop-statut-sync'] })
  }

  const synchroniserMaintenant = async () => {
    setSynchronisationEnCours(true)
    try {
      await lancerSynchronisation()
      succes('Synchronisation terminée.')
      queryClient.invalidateQueries({ queryKey: ['desktop-statut-sync'] })
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setSynchronisationEnCours(false)
    }
  }

  return (
    <div className="flex flex-col gap-2 border-b border-navy-50 px-3.5 py-3">
      <h3 className="text-xs font-bold uppercase tracking-wide text-navy-500">Synchronisation</h3>

      {isLoading ? (
        <Spinner />
      ) : !data ? (
        <p className="text-xs text-red-500">Statut indisponible.</p>
      ) : (
        <>
          <dl className="grid grid-cols-2 gap-x-2 gap-y-1 text-xs text-navy-600">
            <dt className="text-navy-400">Dernier pull</dt>
            <dd className="text-right font-medium">{formatDate(data.dernier_pull_le)}</dd>
            <dt className="text-navy-400">Dernier push</dt>
            <dd className="text-right font-medium">{formatDate(data.dernier_push_le)}</dd>
            <dt className="text-navy-400">En attente d'envoi</dt>
            <dd className={`text-right font-medium ${data.en_attente_push > 0 ? 'text-gold-600' : ''}`}>{data.en_attente_push}</dd>
          </dl>

          {data.ecoles.length > 0 && (
            <ul className="flex flex-col gap-1 border-t border-navy-50 pt-2">
              {data.ecoles.map((e: StatutSyncEcole) => (
                <li key={e.school_id} className="flex items-center justify-between gap-2 text-xs text-navy-600">
                  <span className="flex min-w-0 items-center gap-1.5">
                    <PointComplet complet={e.complet} />
                    <span className="truncate">{e.nom ?? `École #${e.school_id}`}</span>
                  </span>
                  <span className="flex-none text-navy-400">{formatDate(e.dernier_pull_le)}</span>
                </li>
              ))}
            </ul>
          )}
        </>
      )}

      <div className="mt-1 flex gap-2">
        <button
          type="button"
          onClick={verifierCompletude}
          disabled={isLoading}
          className="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-navy-200 px-2 py-1.5 text-xs font-semibold text-navy-600 transition-colors hover:bg-cream-100 disabled:opacity-50"
        >
          <ShieldCheck className="h-3.5 w-3.5" />
          Vérifier la complétude
        </button>
        <button
          type="button"
          onClick={synchroniserMaintenant}
          disabled={synchronisationEnCours}
          className="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-navy-700 px-2 py-1.5 text-xs font-semibold text-cream-50 transition-colors hover:bg-navy-800 disabled:opacity-50"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${synchronisationEnCours ? 'animate-spin' : ''}`} />
          {synchronisationEnCours ? 'Synchronisation…' : 'Synchroniser maintenant'}
        </button>
      </div>
    </div>
  )
}

function SectionMiseAJour() {
  const { statut, verifier, redemarrerPourInstaller } = useDesktopUpdate()
  const [version, setVersion] = useState<string | null>(null)
  const [verificationEnCours, setVerificationEnCours] = useState(false)

  useEffect(() => {
    window.desktop?.getAppVersion().then(setVersion)
  }, [])

  const verifierMaintenant = async () => {
    setVerificationEnCours(true)
    try {
      await verifier()
    } finally {
      setVerificationEnCours(false)
    }
  }

  const libelle = (() => {
    if (!statut) return 'Vérification en cours…'
    switch (statut.etat) {
      case 'non-empaquete':
        return 'Mode développement — vérification indisponible.'
      case 'verification':
        return 'Vérification des mises à jour…'
      case 'a-jour':
        return 'Version à jour.'
      case 'disponible':
        return `Mise à jour disponible (v${statut.version}) — téléchargement en cours…`
      case 'telechargement':
        return `Téléchargement en cours… ${statut.pourcentage}%`
      case 'telechargee':
        return `Mise à jour prête (v${statut.version}).`
      case 'erreur':
        return `Échec de la vérification : ${statut.message}`
    }
  })()

  return (
    <div className="flex flex-col gap-2 px-3.5 py-3">
      <h3 className="text-xs font-bold uppercase tracking-wide text-navy-500">Mise à jour</h3>

      <p className={`text-xs ${statut?.etat === 'erreur' ? 'text-red-600' : 'text-navy-600'}`}>{libelle}</p>
      {version && <p className="text-[10px] text-navy-400">Version installée : {version}</p>}

      <div className="mt-1 flex gap-2">
        <button
          type="button"
          onClick={verifierMaintenant}
          disabled={verificationEnCours || statut?.etat === 'non-empaquete'}
          className="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-navy-200 px-2 py-1.5 text-xs font-semibold text-navy-600 transition-colors hover:bg-cream-100 disabled:opacity-50"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${verificationEnCours ? 'animate-spin' : ''}`} />
          Vérifier maintenant
        </button>
        {statut?.etat === 'telechargee' && (
          <button
            type="button"
            onClick={redemarrerPourInstaller}
            className="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-gold-600 px-2 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-gold-700"
          >
            Redémarrer maintenant
          </button>
        )}
      </div>
    </div>
  )
}

/**
 * Panneau de statut du poste desktop : synchronisation avec le serveur
 * distant et mise à jour de l'application — n'a de sens que dans le client
 * Electron (cf. `window.desktop`, absent du site web classique).
 */
export function DesktopStatusIndicator() {
  const [ouvert, setOuvert] = useState(false)
  const { statut } = useDesktopUpdate()
  const { data } = useQuery({
    queryKey: ['desktop-statut-sync', false],
    queryFn: () => fetchStatutSync(false),
    refetchInterval: 60_000,
  })

  const alerte = (data?.en_attente_push ?? 0) > 0 || statut?.etat === 'telechargee' || statut?.etat === 'erreur'

  if (!window.desktop) return null

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOuvert((v) => !v)}
        className="relative flex h-9 w-9 flex-none items-center justify-center rounded-xl text-navy-500 transition-colors hover:bg-cream-100 hover:text-navy-800"
        aria-label="Statut du poste desktop"
        title="Statut du poste desktop"
      >
        <Laptop className="h-5 w-5" />
        {alerte && <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-gold-500" />}
      </button>

      {ouvert && (
        <>
          <div className="fixed inset-0 z-30" onClick={() => setOuvert(false)} />
          <div className="fixed inset-x-3 top-14 z-40 overflow-hidden rounded-xl border border-navy-100 bg-white shadow-lifted sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-80 sm:max-w-[90vw]">
            <SectionSynchronisation />
            <SectionMiseAJour />
          </div>
        </>
      )}
    </div>
  )
}
