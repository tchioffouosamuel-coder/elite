import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, FileDown, ChevronDown, ChevronUp, CalendarX, Gavel, Stethoscope } from 'lucide-react'
import { fetchArchiveClasse, type RosterLigne } from '@/features/archives/api'
import { ouvrirDocument } from '@/shared/lib/download'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

const DECISION_TONE: Record<string, 'green' | 'gold' | 'red' | 'blue'> = {
  admis: 'green',
  redouble: 'gold',
  exclu: 'red',
  diplome: 'blue',
}

const DECISION_LABEL: Record<string, string> = {
  admis: 'Admis',
  redouble: 'Redoublant',
  exclu: 'Exclu',
  diplome: 'Diplômé',
}

export function ArchiveClassePage() {
  const { anneeId, classeId } = useParams<{ anneeId: string; classeId: string }>()
  const anneeIdNum = Number(anneeId)
  const classeIdNum = Number(classeId)
  const [eleveOuvert, setEleveOuvert] = useState<number | null>(null)

  const { data: archive, isLoading, isError } = useQuery({
    queryKey: ['archive-classe', anneeIdNum, classeIdNum],
    queryFn: () => fetchArchiveClasse(anneeIdNum, classeIdNum),
  })

  if (isLoading) return <Spinner />
  if (isError || !archive) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to="/archives" className="mb-2 flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          Archives
        </Link>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{archive.classe_nom}</h1>
            <p className="text-sm text-navy-400">{archive.niveau_libelle ?? '—'} · {archive.effectif} élève{archive.effectif > 1 ? 's' : ''}</p>
          </div>
          <Button
            variant="secondary"
            onClick={() => ouvrirDocument(`/archives/annees/${anneeIdNum}/classes/${classeIdNum}/pv`, undefined, undefined, 'PV du conseil')}
          >
            <FileDown className="h-4 w-4" />
            Télécharger le PV
          </Button>
        </div>
      </div>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Élèves</h2>
        <div className="flex flex-col divide-y divide-navy-50">
          {archive.roster.map((eleve) => (
            <LigneEleve
              key={eleve.eleve_id}
              eleve={eleve}
              ouvert={eleveOuvert === eleve.eleve_id}
              onToggle={() => setEleveOuvert(eleveOuvert === eleve.eleve_id ? null : eleve.eleve_id)}
              absences={archive.absences[String(eleve.eleve_id)] ?? []}
              discipline={archive.discipline[String(eleve.eleve_id)] ?? []}
              infirmerie={archive.infirmerie[String(eleve.eleve_id)] ?? []}
              anneeId={anneeIdNum}
              classeId={classeIdNum}
            />
          ))}
        </div>
      </Card>
    </div>
  )
}

function LigneEleve({
  eleve,
  ouvert,
  onToggle,
  absences,
  discipline,
  infirmerie,
  anneeId,
  classeId,
}: {
  eleve: RosterLigne
  ouvert: boolean
  onToggle: () => void
  absences: { date: string | null; statut: string; justifie: boolean; remarque: string | null }[]
  discipline: { type: string; motif: string; date_sanction: string | null }[]
  infirmerie: { date_visite: string | null; raison: string | null }[]
  anneeId: number
  classeId: number
}) {
  return (
    <div className="py-2.5 first:pt-0 last:pb-0">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <button onClick={onToggle} className="flex min-w-0 items-center gap-2 text-left">
          {ouvert ? <ChevronUp className="h-4 w-4 flex-none text-navy-300" /> : <ChevronDown className="h-4 w-4 flex-none text-navy-300" />}
          <div>
            <p className="text-sm font-medium text-navy-800">{eleve.nom_complet}</p>
            <p className="text-xs text-navy-400">{eleve.matricule ?? '—'} · {eleve.moyenne_annuelle ?? '—'}/20</p>
          </div>
        </button>
        <div className="flex items-center gap-2">
          {eleve.decision && (
            <Badge tone={DECISION_TONE[eleve.decision]}>
              {DECISION_LABEL[eleve.decision]}
              {eleve.gracie ? ' (gracié)' : ''}
            </Badge>
          )}
          <Button
            size="sm"
            variant="secondary"
            onClick={() => ouvrirDocument(`/archives/annees/${anneeId}/classes/${classeId}/bulletin/${eleve.eleve_id}`, undefined, undefined, 'Bulletin')}
          >
            <FileDown className="h-3.5 w-3.5" />
            Bulletin
          </Button>
        </div>
      </div>

      {ouvert && (
        <div className="ml-6 mt-3 flex flex-col gap-3">
          {eleve.motif && <p className="text-xs text-navy-500">{eleve.motif}</p>}

          <div>
            <h3 className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-navy-400">
              <CalendarX className="h-3.5 w-3.5" />
              Absences ({absences.length})
            </h3>
            {absences.length === 0 ? (
              <p className="text-xs text-navy-400">Aucune absence relevée.</p>
            ) : (
              <div className="flex flex-col gap-1">
                {absences.map((a, i) => (
                  <p key={i} className="text-xs text-navy-600">
                    {a.date} — {a.statut === 'retard' ? 'Retard' : 'Absence'} {a.justifie ? '(justifiée)' : '(non justifiée)'}
                    {a.remarque ? ` · ${a.remarque}` : ''}
                  </p>
                ))}
              </div>
            )}
          </div>

          <div>
            <h3 className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-navy-400">
              <Gavel className="h-3.5 w-3.5" />
              Discipline ({discipline.length})
            </h3>
            {discipline.length === 0 ? (
              <p className="text-xs text-navy-400">Rien à signaler.</p>
            ) : (
              <div className="flex flex-col gap-1">
                {discipline.map((s, i) => (
                  <p key={i} className="text-xs text-navy-600">{s.date_sanction} — {s.type} : {s.motif}</p>
                ))}
              </div>
            )}
          </div>

          <div>
            <h3 className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-navy-400">
              <Stethoscope className="h-3.5 w-3.5" />
              Infirmerie ({infirmerie.length})
            </h3>
            {infirmerie.length === 0 ? (
              <p className="text-xs text-navy-400">Aucun passage relevé.</p>
            ) : (
              <div className="flex flex-col gap-1">
                {infirmerie.map((v, i) => (
                  <p key={i} className="text-xs text-navy-600">{v.date_visite} — {v.raison ?? '—'}</p>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
