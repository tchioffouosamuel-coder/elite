import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { ShieldCheck, ShieldX, Loader2, GraduationCap } from 'lucide-react'
import { fetchVerificationBulletin, type VerificationBulletin } from '@/features/resultats/api'
import type { ApiError } from '@/shared/types/api'

/**
 * Page publique ouverte en scannant le QR code imprimé sur un bulletin : ne
 * nécessite aucune connexion, un tiers externe (employeur, autre
 * établissement) doit pouvoir vérifier l'authenticité depuis son téléphone.
 * Ne montre que des informations déjà visibles sur le bulletin lui-même.
 */
export function VerificationBulletinPage() {
  const { eleveId, trimestreId, signature } = useParams<{
    eleveId: string
    trimestreId: string
    signature: string
  }>()
  const [bulletin, setBulletin] = useState<VerificationBulletin | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [chargement, setChargement] = useState(true)

  useEffect(() => {
    if (!eleveId || !trimestreId || !signature) {
      setErreur('Lien de vérification invalide.')
      setChargement(false)
      return
    }

    fetchVerificationBulletin(Number(eleveId), Number(trimestreId), signature)
      .then(setBulletin)
      .catch((err: ApiError) =>
        setErreur(err.message || "Ce lien ne correspond à aucun bulletin authentique."),
      )
      .finally(() => setChargement(false))
  }, [eleveId, trimestreId, signature])

  return (
    <div className="flex min-h-svh items-center justify-center bg-cream-50 p-6">
      <div className="w-full max-w-md rounded-2xl border border-navy-100 bg-white p-8 shadow-lg">
        <div className="mb-6 flex items-center justify-center gap-2 text-navy-800">
          <GraduationCap className="h-6 w-6 text-gold-500" />
          <span className="text-sm font-semibold uppercase tracking-wide">Fondation Elites</span>
        </div>

        {chargement && (
          <div className="flex flex-col items-center gap-3 py-10 text-navy-400">
            <Loader2 className="h-6 w-6 animate-spin" />
            <span className="text-sm">Vérification en cours…</span>
          </div>
        )}

        {!chargement && erreur && (
          <div className="flex flex-col items-center gap-3 py-6 text-center">
            <span className="flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-500">
              <ShieldX className="h-7 w-7" />
            </span>
            <p className="text-lg font-bold text-red-600">Bulletin non authentifié</p>
            <p className="text-sm text-navy-400">{erreur}</p>
          </div>
        )}

        {!chargement && bulletin && (
          <div className="flex flex-col items-center gap-4 text-center">
            <span className="flex h-14 w-14 items-center justify-center rounded-full bg-green-50 text-green-600">
              <ShieldCheck className="h-7 w-7" />
            </span>
            <div>
              <p className="text-lg font-bold text-navy-900">Bulletin authentique</p>
              <p className="text-xs text-navy-400">Authentic report card</p>
            </div>

            <div className="mt-2 w-full space-y-2 rounded-xl bg-cream-50 p-4 text-left text-sm">
              <Ligne label="Élève" valeur={bulletin.eleve.nom_complet} />
              {bulletin.eleve.matricule && <Ligne label="Matricule" valeur={bulletin.eleve.matricule} />}
              <Ligne label="École" valeur={bulletin.ecole} />
              <Ligne label="Classe" valeur={bulletin.classe} />
              <Ligne label="Trimestre" valeur={bulletin.trimestre} />
              {bulletin.annee_scolaire && <Ligne label="Année scolaire" valeur={bulletin.annee_scolaire} />}
              <Ligne
                label="Moyenne générale"
                valeur={bulletin.moyenne_generale !== null ? `${bulletin.moyenne_generale.toFixed(2)} / 20` : '—'}
              />
              <Ligne
                label="Rang"
                valeur={bulletin.rang !== null ? `${bulletin.rang} / ${bulletin.effectif}` : '—'}
              />
              <Ligne label="Cote" valeur={bulletin.cote} />
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

function Ligne({ label, valeur }: { label: string; valeur: string }) {
  return (
    <div className="flex items-center justify-between gap-3 border-b border-navy-100 pb-2 last:border-0 last:pb-0">
      <span className="text-navy-400">{label}</span>
      <span className="font-semibold text-navy-900">{valeur}</span>
    </div>
  )
}
