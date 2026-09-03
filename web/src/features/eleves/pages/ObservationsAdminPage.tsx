import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { MessageSquare, Search, Send, ShieldCheck, ShieldAlert } from 'lucide-react'
import { http } from '@/shared/lib/http'
import type { ApiResponse, ApiError } from '@/shared/types/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Input, Textarea } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'

interface FilResume {
  eleve: { id: number; nom_complet: string; matricule: string | null } | null
  dernier_message: string
  derniere_origine: 'parent' | 'ecole'
  total: number
  derniere_date: string
}

interface Message {
  id: number
  contenu: string
  auteur: string | null
  origine: 'parent' | 'ecole'
  date: string
}

interface FilDetail {
  eleve: { id: number; nom_complet: string; matricule: string | null }
  observations: Message[]
}

async function fetchFils(): Promise<FilResume[]> {
  const { data } = await http.get<ApiResponse<FilResume[]>>('/observations')
  return data.data
}

async function fetchFil(eleveId: number): Promise<FilDetail> {
  const { data } = await http.get<ApiResponse<FilDetail>>(`/observations/${eleveId}`)
  return data.data
}

async function repondre(eleveId: number, contenu: string): Promise<Message> {
  const { data } = await http.post<ApiResponse<Message>>(`/observations/${eleveId}`, { contenu })
  return data.data
}

/**
 * Fils d'observations sur les élèves, alimentés des deux côtés (parent et
 * établissement) — un fil par élève, pas une liste plate de messages : cf.
 * Observation côté API.
 */
export function ObservationsAdminPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [recherche, setRecherche] = useState('')
  const [eleveId, setEleveId] = useState<number | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['observations-admin'], queryFn: fetchFils })

  // Ouvre directement le fil visé par le lien d'une notification (`/observations?id=…`).
  useEffect(() => {
    const id = searchParams.get('id')
    if (id) {
      setEleveId(Number(id))
      searchParams.delete('id')
      setSearchParams(searchParams, { replace: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const filsFiltres = (data ?? []).filter((f) => {
    const q = recherche.trim().toLowerCase()
    if (!q) return true
    return [f.eleve?.nom_complet, f.eleve?.matricule].filter(Boolean).some((v) => String(v).toLowerCase().includes(q))
  })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Observations"
        sousTitre="Fils d'échange avec les familles, par élève."
        icon={MessageSquare}
        actions={
          <Input
            value={recherche}
            onChange={(e) => setRecherche(e.target.value)}
            placeholder="Rechercher un élève…"
            className="w-56"
            icon={Search}
          />
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState label="Aucune observation pour l'instant." />
      ) : filsFiltres.length === 0 ? (
        <EmptyState label="Aucun élève ne correspond à cette recherche." />
      ) : (
        <div className="flex flex-col gap-3">
          {filsFiltres.map((f) => (
            <div key={f.eleve?.id} onClick={() => setEleveId(f.eleve!.id)} className="cursor-pointer">
              <Card className="transition-shadow hover:shadow-lifted">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <p className="font-display text-base font-bold text-navy-900">{f.eleve?.nom_complet}</p>
                    <p className="mt-0.5 truncate text-xs text-navy-400">{f.dernier_message}</p>
                  </div>
                  <div className="flex flex-none flex-col items-end gap-1">
                    <Badge tone={f.derniere_origine === 'ecole' ? 'blue' : 'gold'}>
                      {f.derniere_origine === 'ecole' ? "L'établissement" : 'Parent'}
                    </Badge>
                    <span className="text-xs text-navy-400">{f.total} message{f.total > 1 ? 's' : ''}</span>
                  </div>
                </div>
              </Card>
            </div>
          ))}
        </div>
      )}

      {eleveId && <FilModal eleveId={eleveId} onClose={() => setEleveId(null)} />}
    </div>
  )
}

function FilModal({ eleveId, onClose }: { eleveId: number; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [contenu, setContenu] = useState('')
  const [envoi, setEnvoi] = useState(false)

  const { data: fil, isLoading } = useQuery({ queryKey: ['observation-admin', eleveId], queryFn: () => fetchFil(eleveId) })

  const envoyer = async () => {
    if (!contenu.trim()) return
    setEnvoi(true)
    try {
      await repondre(eleveId, contenu.trim())
      setContenu('')
      queryClient.invalidateQueries({ queryKey: ['observation-admin', eleveId] })
      queryClient.invalidateQueries({ queryKey: ['observations-admin'] })
      succes('Réponse envoyée.')
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={fil ? `Fil — ${fil.eleve.nom_complet}` : 'Fil d\'observations'} onClose={onClose}>
      {isLoading || !fil ? (
        <Spinner />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="flex flex-col divide-y divide-navy-50">
            {fil.observations.map((o) => (
              <div key={o.id} className="flex flex-col gap-1.5 py-3 first:pt-0 last:pb-0">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge tone={o.origine === 'ecole' ? 'blue' : 'gold'}>
                    {o.origine === 'ecole' ? <ShieldCheck className="h-3 w-3" /> : <ShieldAlert className="h-3 w-3" />}
                    {o.origine === 'ecole' ? "L'établissement" : o.auteur}
                  </Badge>
                  <span className="text-xs text-navy-400">{new Date(o.date).toLocaleString('fr-FR')}</span>
                </div>
                <p className="text-sm leading-relaxed text-navy-700">{o.contenu}</p>
              </div>
            ))}
          </div>

          <div className="flex flex-col gap-2.5 border-t border-navy-50 pt-4 sm:flex-row sm:items-end">
            <div className="flex-1">
              <Textarea
                value={contenu}
                onChange={(e) => setContenu(e.target.value)}
                placeholder="Répondre à la famille…"
                rows={3}
              />
            </div>
            <Button onClick={envoyer} disabled={envoi || !contenu.trim()} className="w-full sm:w-auto sm:flex-none">
              <Send className="h-4 w-4" />
              Envoyer
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}
