import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { CalendarDays, UserX } from 'lucide-react'
import clsx from 'clsx'
import { fetchAbsentsDuJour, type EleveAbsentDuJour } from '@/features/dashboard/api'
import { Card } from '@/shared/ui/Card'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

const aujourdhui = () => new Date().toISOString().slice(0, 10)

function enClair(date: string): string {
  const jour = new Date(`${date}T00:00:00`)
  if (Number.isNaN(jour.getTime())) return date
  return jour.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
}

/**
 * Deux chiffres plutôt qu'un : le nombre d'élèves ayant manqué au moins un
 * appel dans la journée, et parmi eux ceux qui n'ont pas paru du tout. Une
 * absence d'une heure et une journée entière n'appellent pas la même
 * réaction, et la seconde se perdrait dans le total.
 */
function LigneEleve({ eleve }: { eleve: EleveAbsentDuJour }) {
  return (
    <li className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 border-b border-navy-50 py-2.5 last:border-b-0">
      <div className="min-w-0">
        <p className="truncate text-sm font-semibold text-navy-800">{eleve.nom_complet}</p>
        <p className="text-xs text-navy-400">
          {[eleve.classe, eleve.matricule].filter(Boolean).join(' · ') || '—'}
        </p>
      </div>
      <div className="flex flex-none flex-col items-end gap-1">
        {eleve.journee_complete ? (
          <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600">
            Toute la journée
          </span>
        ) : (
          <span className="text-xs font-semibold tabular-nums text-amber-600">
            {eleve.absences} / {eleve.appels} appel{eleve.appels > 1 ? 's' : ''}
          </span>
        )}
        {eleve.creneaux.length > 0 && (
          <p className="text-right text-[0.6875rem] text-navy-400">
            {eleve.creneaux
              .map((c) => [c.heure, c.matiere].filter(Boolean).join(' '))
              .join(' · ')}
          </p>
        )}
      </div>
    </li>
  )
}

export function CarteAbsentsDuJour() {
  const [ouverte, setOuverte] = useState(false)
  const [date, setDate] = useState(aujourdhui())

  const { data, isLoading, isError } = useQuery({
    queryKey: ['dashboard-absents-du-jour', date],
    queryFn: () => fetchAbsentsDuJour(date),
    staleTime: 60_000,
  })

  const estAujourdhui = date === aujourdhui()
  const valeur = (v: number | undefined) => (isLoading ? '…' : isError || v === undefined ? '—' : v)

  return (
    <>
      <Card>
        <button
          type="button"
          onClick={() => setOuverte(true)}
          className="flex w-full items-center justify-between gap-4 rounded-xl text-left transition-colors hover:bg-cream-50"
          aria-haspopup="dialog"
        >
          <div className="min-w-0">
            <h2 className="font-display text-base font-bold tracking-tight text-navy-800">
              Absents {estAujourdhui ? "aujourd'hui" : `le ${enClair(date)}`}
            </h2>
            <p className="mt-1 text-xs text-navy-400">
              {isLoading || isError
                ? 'Voir la liste'
                : `dont ${data?.journee_complete ?? 0} toute la journée · voir la liste`}
            </p>
          </div>
          <div className="flex flex-none items-center gap-2">
            <UserX
              className={clsx('h-5 w-5', (data?.total ?? 0) > 0 ? 'text-red-500' : 'text-navy-300')}
              aria-hidden
            />
            <span className="text-3xl font-extrabold tabular-nums text-navy-800">{valeur(data?.total)}</span>
          </div>
        </button>
      </Card>

      {ouverte && (
        <Modal
          title={`Absents · ${enClair(date)}`}
          taille="lg"
          onClose={() => setOuverte(false)}
        >
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <label className="flex items-center gap-2 text-sm text-navy-600">
              <CalendarDays className="h-4 w-4 text-navy-400" aria-hidden />
              <span className="sr-only sm:not-sr-only">Jour</span>
              <input
                type="date"
                value={date}
                max={aujourdhui()}
                onChange={(e) => setDate(e.target.value || aujourdhui())}
                className="rounded-xl border border-navy-100 px-3 py-1.5 text-sm text-navy-800 focus:border-navy-300 focus:outline-none"
              />
            </label>
            {!isLoading && !isError && data && (
              <p className="text-sm text-navy-500">
                <strong className="text-navy-800">{data.total}</strong> élève{data.total > 1 ? 's' : ''}
                {data.journee_complete > 0 && (
                  <>
                    {' · '}
                    <strong className="text-red-600">{data.journee_complete}</strong> toute la journée
                  </>
                )}
              </p>
            )}
          </div>

          {isLoading ? (
            <Spinner />
          ) : isError ? (
            <ErrorState />
          ) : !data || data.eleves.length === 0 ? (
            <p className="py-8 text-center text-sm text-navy-400">
              Aucune absence relevée ce jour-là.
              <span className="mt-1 block text-xs">
                Seuls les cours dont l'appel a été fait sont comptés.
              </span>
            </p>
          ) : (
            <ul className="divide-y divide-navy-50">
              {data.eleves.map((eleve) => (
                <LigneEleve key={eleve.eleve_id} eleve={eleve} />
              ))}
            </ul>
          )}
        </Modal>
      )}
    </>
  )
}
