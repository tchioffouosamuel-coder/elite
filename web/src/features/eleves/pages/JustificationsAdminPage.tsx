import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CalendarX, Search } from 'lucide-react'
import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Input, Select } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'

type Statut = 'en_attente' | 'appliquee'
type Motif = 'maladie' | 'scolarite' | 'permission'

interface JustificationResume {
  id: number
  statut: Statut
  motif: Motif
  date_debut: string
  date_fin: string
  tuteur: { id: number; nom_complet: string; telephone: string | null; email: string | null } | null
  eleve: { id: number; nom_complet: string; matricule: string | null; classe: string | null } | null
  created_at: string
}

interface JustificationDetail extends JustificationResume {
  description: string | null
}

async function fetchJustifications(statut: Statut | ''): Promise<JustificationResume[]> {
  const { data } = await http.get<ApiResponse<JustificationResume[]>>('/justifications', { params: { statut: statut || undefined } })
  return data.data
}

async function fetchJustification(id: number): Promise<JustificationDetail> {
  const { data } = await http.get<ApiResponse<JustificationDetail>>(`/justifications/${id}`)
  return data.data
}

const STATUT_TONE = { en_attente: 'gold', appliquee: 'green' } as const
const STATUT_LABEL = { en_attente: 'En attente', appliquee: 'Appliquée' } as const
const MOTIF_LABEL: Record<Motif, string> = { maladie: 'Maladie', scolarite: "Raison d'ordre scolaire", permission: 'Permission' }

/**
 * Justifications d'absence déposées par les parents, en lecture seule : le
 * statut passe automatiquement à « appliquée » quand l'enseignant fait
 * l'appel sur la période couverte, ce n'est pas une décision à prendre ici.
 */
export function JustificationsAdminPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [statut, setStatut] = useState<Statut | ''>('en_attente')
  const [recherche, setRecherche] = useState('')
  const [detailId, setDetailId] = useState<number | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['justifications-admin', statut],
    queryFn: () => fetchJustifications(statut),
  })

  // Ouvre directement le détail visé par le lien d'une notification (`/justifications?id=…`).
  useEffect(() => {
    const id = searchParams.get('id')
    if (id) {
      setDetailId(Number(id))
      searchParams.delete('id')
      setSearchParams(searchParams, { replace: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const donneesFiltrees = (data ?? []).filter((j) => {
    const q = recherche.trim().toLowerCase()
    if (!q) return true
    return [j.eleve?.nom_complet, j.eleve?.matricule, j.tuteur?.nom_complet, j.tuteur?.telephone, j.tuteur?.email]
      .filter(Boolean)
      .some((v) => String(v).toLowerCase().includes(q))
  })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Justifications d'absence"
        sousTitre="Absences justifiées par anticipation par les parents."
        icon={CalendarX}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Input
              value={recherche}
              onChange={(e) => setRecherche(e.target.value)}
              placeholder="Rechercher un élève, un tuteur…"
              className="w-56"
              icon={Search}
            />
            <Select value={statut} onChange={(e) => setStatut(e.target.value as Statut | '')} className="w-48">
              <option value="en_attente">En attente</option>
              <option value="appliquee">Appliquées</option>
              <option value="">Toutes</option>
            </Select>
          </div>
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState label="Aucune justification dans cet état." />
      ) : donneesFiltrees.length === 0 ? (
        <EmptyState label="Aucune justification ne correspond à cette recherche." />
      ) : (
        <div className="flex flex-col gap-3">
          {donneesFiltrees.map((j) => (
            <div key={j.id} onClick={() => setDetailId(j.id)} className="cursor-pointer">
              <Card className="transition-shadow hover:shadow-lifted">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-display text-base font-bold text-navy-900">{j.eleve?.nom_complet}</p>
                    <p className="mt-0.5 text-xs text-navy-400">
                      {j.tuteur?.nom_complet} · {MOTIF_LABEL[j.motif]} ·{' '}
                      {new Date(j.date_debut).toLocaleDateString('fr-FR')}
                      {j.date_fin !== j.date_debut ? ` – ${new Date(j.date_fin).toLocaleDateString('fr-FR')}` : ''}
                    </p>
                  </div>
                  <Badge tone={STATUT_TONE[j.statut]}>{STATUT_LABEL[j.statut]}</Badge>
                </div>
              </Card>
            </div>
          ))}
        </div>
      )}

      {detailId && <JustificationDetailModal id={detailId} onClose={() => setDetailId(null)} />}
    </div>
  )
}

function JustificationDetailModal({ id, onClose }: { id: number; onClose: () => void }) {
  const { data: j, isLoading } = useQuery({ queryKey: ['justification-admin', id], queryFn: () => fetchJustification(id) })

  return (
    <Modal title="Détail de la justification" onClose={onClose}>
      {isLoading || !j ? (
        <Spinner />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-display text-base font-bold text-navy-900">{j.eleve?.nom_complet}</p>
              <p className="text-xs text-navy-400">
                {j.eleve?.matricule} · {j.eleve?.classe} · Déposée par {j.tuteur?.nom_complet}
              </p>
            </div>
            <Badge tone={STATUT_TONE[j.statut]}>{STATUT_LABEL[j.statut]}</Badge>
          </div>

          <div className="grid grid-cols-2 gap-4 rounded-xl bg-cream-100 p-3 text-sm">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">Période</p>
              <p className="font-medium text-navy-800">
                {new Date(j.date_debut).toLocaleDateString('fr-FR')}
                {j.date_fin !== j.date_debut ? ` – ${new Date(j.date_fin).toLocaleDateString('fr-FR')}` : ''}
              </p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-navy-400">Motif</p>
              <p className="font-medium text-navy-800">{MOTIF_LABEL[j.motif]}</p>
            </div>
          </div>

          {j.description && (
            <div>
              <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-navy-400">Précisions</p>
              <p className="text-sm text-navy-700">{j.description}</p>
            </div>
          )}

          <p className="text-xs text-navy-400">
            {j.statut === 'appliquee'
              ? 'Rapprochée automatiquement du pointage réel : cette absence a été marquée justifiée.'
              : "En attente : sera appliquée automatiquement dès que l'enseignant fera l'appel sur cette période."}
          </p>
        </div>
      )}
    </Modal>
  )
}
