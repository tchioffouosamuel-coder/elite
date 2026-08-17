import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useParams } from 'react-router-dom'
import { clsx } from 'clsx'
import { ArrowLeft, Check, ChevronDown, ClipboardCheck } from 'lucide-react'
import { enregistrerAppel, fetchAppel, MOTIFS, type LigneAppel, type MotifAbsence } from '@/features/emploiDuTemps/api'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { ErrorState, Spinner } from '@/shared/ui/Feedback'
import { PageHeader } from '@/shared/ui/PageHeader'
import { erreur, succes } from '@/shared/lib/alertes'

export function AppelPage() {
  const { id } = useParams<{ id: string }>()
  const seanceId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data, isLoading, isError } = useQuery({
    queryKey: ['appel', seanceId],
    queryFn: () => fetchAppel(seanceId),
    enabled: Number.isFinite(seanceId),
  })

  const [lignes, setLignes] = useState<LigneAppel[]>([])

  useEffect(() => {
    if (data) setLignes(data.lignes)
  }, [data])

  const enregistrement = useMutation({
    mutationFn: () =>
      enregistrerAppel(
        seanceId,
        lignes.map((l) => ({ eleve_id: l.eleve_id, statut: l.statut, motif: l.motif, remarque: l.remarque })),
      ),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['seances', data?.seance.classe_id] })
      queryClient.invalidateQueries({ queryKey: ['appel', seanceId] })
      succes(`Appel enregistré (${res.enregistres} élève(s)).`)
      navigate('/seances')
    },
    onError: (e: { message?: string }) => erreur(e.message ?? "Enregistrement de l'appel impossible."),
  })

  const marquerPresent = (eleveId: number) =>
    setLignes((actuel) => actuel.map((l) => (l.eleve_id === eleveId ? { ...l, statut: 'present', motif: null } : l)))

  const marquerAbsent = (eleveId: number, motif: MotifAbsence) =>
    setLignes((actuel) => actuel.map((l) => (l.eleve_id === eleveId ? { ...l, statut: 'absent', motif } : l)))

  if (isLoading || !data) return <Spinner />
  if (isError) return <ErrorState />

  const absents = lignes.filter((l) => l.statut === 'absent').length

  return (
    <div className="flex flex-col gap-5">
      <div>
        <button
          onClick={() => navigate('/seances')}
          className="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
        >
          <ArrowLeft className="h-4 w-4" />
          Retour aux séances
        </button>
        <PageHeader
          titre={`Appel — ${data.seance.matiere ?? ''}`}
          sousTitre={`${new Date(data.seance.date_seance).toLocaleDateString('fr-FR')} · ${data.seance.heure_debut}–${data.seance.heure_fin}`}
          icon={ClipboardCheck}
        />
      </div>

      <Card className="p-4">
        <div className="mb-3 flex items-center justify-between">
          <p className="text-sm text-navy-500">
            {lignes.length} élève(s) · {lignes.length - absents} présent(s) · {absents} absent(s)
          </p>
        </div>

        <div className="flex flex-col divide-y divide-navy-50">
          {lignes.map((ligne) => {
            const present = ligne.statut === 'present'

            return (
              <div key={ligne.eleve_id} className="flex flex-wrap items-center justify-between gap-3 py-2.5">
                <span className={clsx('min-w-0 flex-1 truncate text-sm', present ? 'text-navy-800' : 'font-semibold text-red-600')}>
                  {ligne.nom_complet}
                </span>

                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => marquerPresent(ligne.eleve_id)}
                    className={clsx(
                      'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                      present ? 'bg-green-500 text-white shadow-sm' : 'bg-navy-50 text-navy-400 hover:bg-navy-100',
                    )}
                  >
                    {present && <Check className="h-3.5 w-3.5" />}
                    Présent
                  </button>

                  <details className="group relative">
                    <summary
                      className={clsx(
                        'flex list-none cursor-pointer items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors select-none',
                        !present ? 'bg-red-500 text-white shadow-sm' : 'bg-navy-50 text-navy-400 hover:bg-navy-100',
                      )}
                    >
                      {!present ? `Absent — ${MOTIFS[ligne.motif ?? 'inconnu']}` : 'Absent'}
                      <ChevronDown className="h-3.5 w-3.5" />
                    </summary>

                    <div className="absolute right-0 z-10 mt-1 w-40 overflow-hidden rounded-xl border border-navy-100 bg-white py-1 shadow-lifted">
                      {Object.entries(MOTIFS).map(([cle, libelle]) => (
                        <button
                          key={cle}
                          type="button"
                          onClick={(e) => {
                            marquerAbsent(ligne.eleve_id, cle as MotifAbsence)
                            e.currentTarget.closest('details')?.removeAttribute('open')
                          }}
                          className="block w-full px-3 py-1.5 text-left text-xs text-navy-700 hover:bg-cream-100"
                        >
                          {libelle}
                        </button>
                      ))}
                    </div>
                  </details>
                </div>
              </div>
            )
          })}
        </div>
      </Card>

      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={() => navigate('/seances')}>
          Annuler
        </Button>
        <Button onClick={() => enregistrement.mutate()} disabled={enregistrement.isPending || lignes.length === 0}>
          Enregistrer l'appel
        </Button>
      </div>
    </div>
  )
}
