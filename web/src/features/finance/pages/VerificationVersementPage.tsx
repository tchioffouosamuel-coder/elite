import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { ShieldCheck, ShieldX, Loader2, GraduationCap } from 'lucide-react'
import { fetchVerificationVersement, francs, type VerificationVersement } from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

const LIBELLES_MODE: Record<VerificationVersement['mode'], string> = {
  especes: 'Espèces',
  mobile_money: 'Mobile Money',
  virement: 'Virement',
  cheque: 'Chèque',
  depot_bancaire: 'Dépôt bancaire',
}

/**
 * Page publique ouverte en scannant le QR code imprimé sur un reçu de
 * versement : ne nécessite aucune connexion, un tiers externe doit pouvoir
 * vérifier l'authenticité depuis son téléphone. Ne montre que des
 * informations déjà visibles sur le reçu lui-même.
 */
export function VerificationVersementPage() {
  const { versementId, signature } = useParams<{ versementId: string; signature: string }>()
  const [versement, setVersement] = useState<VerificationVersement | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [chargement, setChargement] = useState(true)

  useEffect(() => {
    if (!versementId || !signature) {
      setErreur('Lien de vérification invalide.')
      setChargement(false)
      return
    }

    fetchVerificationVersement(Number(versementId), signature)
      .then(setVersement)
      .catch((err: ApiError) => setErreur(err.message || 'Ce lien ne correspond à aucun reçu authentique.'))
      .finally(() => setChargement(false))
  }, [versementId, signature])

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
            <p className="text-lg font-bold text-red-600">Reçu non authentifié</p>
            <p className="text-sm text-navy-400">{erreur}</p>
          </div>
        )}

        {!chargement && versement && (
          <div className="flex flex-col items-center gap-4 text-center">
            <span
              className={
                'flex h-14 w-14 items-center justify-center rounded-full ' +
                (versement.annule ? 'bg-red-50 text-red-500' : 'bg-green-50 text-green-600')
              }
            >
              {versement.annule ? <ShieldX className="h-7 w-7" /> : <ShieldCheck className="h-7 w-7" />}
            </span>
            <div>
              <p className="text-lg font-bold text-navy-900">
                {versement.annule ? 'Reçu annulé' : 'Reçu authentique'}
              </p>
              <p className="text-xs text-navy-400">{versement.annule ? 'Cancelled receipt' : 'Authentic receipt'}</p>
            </div>

            <div className="mt-2 w-full space-y-2 rounded-xl bg-cream-50 p-4 text-left text-sm">
              <Ligne label="Reçu N°" valeur={versement.numero_recu} />
              <Ligne label="Élève" valeur={versement.eleve.nom_complet} />
              {versement.eleve.matricule && <Ligne label="Matricule" valeur={versement.eleve.matricule} />}
              <Ligne label="École" valeur={versement.ecole} />
              {versement.classe && <Ligne label="Classe" valeur={versement.classe} />}
              <Ligne label="Montant" valeur={francs(versement.montant)} />
              <Ligne label="Date" valeur={new Date(versement.date_versement).toLocaleDateString('fr-FR')} />
              <Ligne label="Mode" valeur={LIBELLES_MODE[versement.mode]} />
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
