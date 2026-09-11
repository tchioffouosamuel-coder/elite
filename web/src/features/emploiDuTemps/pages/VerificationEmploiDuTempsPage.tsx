import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { CalendarClock, Loader2, ShieldCheck, ShieldX } from 'lucide-react'
import { fetchVerificationEmploiDuTemps, type VerificationEmploiDuTemps } from '@/features/emploiDuTemps/api'
import type { ApiError } from '@/shared/types/api'

export function VerificationEmploiDuTempsPage() {
  const { classeId, anneeId, signature } = useParams<{ classeId: string; anneeId: string; signature: string }>()
  const [document, setDocument] = useState<VerificationEmploiDuTemps | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!classeId || !anneeId || !signature) {
      setError('Lien de vérification invalide / Invalid verification link.')
      return
    }
    fetchVerificationEmploiDuTemps(Number(classeId), Number(anneeId), signature)
      .then(setDocument)
      .catch((err: ApiError) => setError(err.message || 'Document non authentifié / Unauthenticated document.'))
  }, [classeId, anneeId, signature])

  return (
    <div className="flex min-h-svh items-center justify-center bg-cream-50 p-6">
      <div className="w-full max-w-md rounded-2xl border border-navy-100 bg-white p-8 text-center shadow-lg">
        <CalendarClock className="mx-auto mb-4 h-8 w-8 text-gold-500" />
        {!document && !error && <Loader2 className="mx-auto h-6 w-6 animate-spin text-navy-400" />}
        {error && (
          <div className="flex flex-col items-center gap-3">
            <ShieldX className="h-12 w-12 text-red-500" />
            <h1 className="text-lg font-bold text-red-600">Document non authentifié / Invalid document</h1>
            <p className="text-sm text-navy-400">{error}</p>
          </div>
        )}
        {document && (
          <div className="flex flex-col items-center gap-3">
            <ShieldCheck className="h-12 w-12 text-green-600" />
            <h1 className="text-lg font-bold text-navy-900">Emploi du temps authentique / Authentic timetable</h1>
            <div className="mt-2 w-full space-y-2 rounded-xl bg-cream-50 p-4 text-left text-sm">
              <p><span className="text-navy-400">École / School :</span> {document.ecole || '—'}</p>
              <p><span className="text-navy-400">Classe / Class :</span> {document.classe}</p>
              <p><span className="text-navy-400">Année / Academic year :</span> {document.annee_scolaire}</p>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
