import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { QrCode, AlertTriangle } from 'lucide-react'
import { resoudreQr } from '@/features/progression/api'
import { Spinner } from '@/shared/ui/Feedback'
import { Button } from '@/shared/ui/Button'
import type { ApiError } from '@/shared/types/api'

/**
 * Point d'arrivée du QR code affiché au mur de la salle. Le scan (par
 * l'appareil photo du téléphone, hors de l'application) n'est qu'un lien
 * profond : cette page résout le cours en cours et bascule directement sur
 * « Ma journée » pour cette affectation, sans ressaisie.
 */
export function QrScanPage() {
  const { token } = useParams<{ token: string }>()
  const navigate = useNavigate()
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (!token) return

    resoudreQr(token)
      .then((resolu) => {
        navigate('/ma-journee', { replace: true, state: { classeMatiereId: resolu.classe_matiere_id } })
      })
      .catch((e) => setErreur((e as ApiError).message))
  }, [token, navigate])

  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 text-center">
      {erreur ? (
        <>
          <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-500">
            <AlertTriangle className="h-6 w-6" />
          </span>
          <p className="max-w-sm text-sm font-medium text-navy-700">{erreur}</p>
          <Button variant="secondary" onClick={() => navigate('/ma-journee')}>
            Aller à « Ma journée »
          </Button>
        </>
      ) : (
        <>
          <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-navy-50 text-navy-500">
            <QrCode className="h-6 w-6" />
          </span>
          <p className="text-sm font-medium text-navy-600">Ouverture du cours…</p>
          <Spinner />
        </>
      )}
    </div>
  )
}
