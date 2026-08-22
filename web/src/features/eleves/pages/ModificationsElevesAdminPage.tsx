import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { UserCog, Check, X, Search } from 'lucide-react'
import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

type Statut = 'en_attente' | 'validee' | 'rejetee'

interface ModificationResume {
  id: number
  statut: Statut
  tuteur: { id: number; nom_complet: string; telephone: string | null; email: string | null } | null
  eleve: { id: number; nom_complet: string; matricule: string | null } | null
  motif_rejet: string | null
  created_at: string
  traite_le: string | null
}

interface ModificationDetail extends ModificationResume {
  donnees: Record<string, string>
  valeurs_actuelles: Record<string, string | null>
}

async function fetchModifications(statut: Statut | ''): Promise<ModificationResume[]> {
  const { data } = await http.get<ApiResponse<ModificationResume[]>>('/modifications-eleves', { params: { statut: statut || undefined } })
  return data.data
}

async function fetchModification(id: number): Promise<ModificationDetail> {
  const { data } = await http.get<ApiResponse<ModificationDetail>>(`/modifications-eleves/${id}`)
  return data.data
}

const STATUT_TONE = { en_attente: 'gold', validee: 'green', rejetee: 'red' } as const
const STATUT_LABEL = { en_attente: 'En attente', validee: 'Validée', rejetee: 'Rejetée' } as const
const LIBELLES_CHAMPS: Record<string, string> = {
  nom_complet: 'Nom complet',
  sexe: 'Sexe',
  date_naissance: 'Date de naissance',
  lieu_naissance: 'Lieu de naissance',
  adresse: 'Adresse',
  numero_acte_naissance: "N° acte de naissance",
  lieu_delivrance_acte: 'Lieu de délivrance',
  officier_etat_civil: "Officier d'état civil",
  groupe_sanguin: 'Groupe sanguin',
  situation_sanitaire: 'Situation sanitaire',
  aptitude: 'Aptitude',
  allergies: 'Allergies',
}

/** File d'attente des révisions d'identité/santé proposées par les parents — à examiner, valider ou rejeter. */
export function ModificationsElevesAdminPage() {
  const queryClient = useQueryClient()
  const [searchParams, setSearchParams] = useSearchParams()
  const [statut, setStatut] = useState<Statut | ''>('en_attente')
  const [recherche, setRecherche] = useState('')
  const [detailId, setDetailId] = useState<number | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['modifications-eleves-admin', statut],
    queryFn: () => fetchModifications(statut),
  })

  // Ouvre directement le détail visé par le lien d'une notification
  // (`/modifications-eleves?id=…`).
  useEffect(() => {
    const id = searchParams.get('id')
    if (id) {
      setDetailId(Number(id))
      searchParams.delete('id')
      setSearchParams(searchParams, { replace: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const invalider = () => {
    queryClient.invalidateQueries({ queryKey: ['modifications-eleves-admin'] })
    setDetailId(null)
  }

  const donneesFiltrees = (data ?? []).filter((m) => {
    const q = recherche.trim().toLowerCase()
    if (!q) return true
    return [m.eleve?.nom_complet, m.eleve?.matricule, m.tuteur?.nom_complet, m.tuteur?.telephone, m.tuteur?.email]
      .filter(Boolean)
      .some((v) => String(v).toLowerCase().includes(q))
  })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Modifications de fiches"
        sousTitre="Révisions d'identité et de situation sanitaire proposées par les parents."
        icon={UserCog}
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
              <option value="validee">Validées</option>
              <option value="rejetee">Rejetées</option>
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
        <EmptyState label="Aucune demande dans cet état." />
      ) : donneesFiltrees.length === 0 ? (
        <EmptyState label="Aucune demande ne correspond à cette recherche." />
      ) : (
        <div className="flex flex-col gap-3">
          {donneesFiltrees.map((m) => (
            <div key={m.id} onClick={() => setDetailId(m.id)} className="cursor-pointer">
              <Card className="transition-shadow hover:shadow-lifted">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-display text-base font-bold text-navy-900">{m.eleve?.nom_complet}</p>
                    <p className="mt-0.5 text-xs text-navy-400">
                      {m.tuteur?.nom_complet} ({m.tuteur?.telephone || m.tuteur?.email}) ·{' '}
                      {new Date(m.created_at).toLocaleDateString('fr-FR')}
                    </p>
                  </div>
                  <Badge tone={STATUT_TONE[m.statut]}>{STATUT_LABEL[m.statut]}</Badge>
                </div>
              </Card>
            </div>
          ))}
        </div>
      )}

      {detailId && <ModificationDetailModal id={detailId} onClose={() => setDetailId(null)} onTraitee={invalider} />}
    </div>
  )
}

function ModificationDetailModal({ id, onClose, onTraitee }: { id: number; onClose: () => void; onTraitee: () => void }) {
  const [motifRejet, setMotifRejet] = useState('')
  const [rejetOuvert, setRejetOuvert] = useState(false)
  const [traitement, setTraitement] = useState(false)

  const { data: m, isLoading } = useQuery({ queryKey: ['modification-eleve-admin', id], queryFn: () => fetchModification(id) })

  const valider = async () => {
    const ok = await confirmer({
      titre: 'Valider cette modification ?',
      message: "La fiche de l'élève sera mise à jour avec les informations proposées.",
      action: 'Valider',
    })
    if (!ok) return

    setTraitement(true)
    try {
      await http.post(`/modifications-eleves/${id}/valider`)
      succes('Modification validée et appliquée.')
      onTraitee()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setTraitement(false)
    }
  }

  const rejeter = async () => {
    if (motifRejet.trim().length < 3) return
    setTraitement(true)
    try {
      await http.post(`/modifications-eleves/${id}/rejeter`, { motif: motifRejet })
      succes('Modification rejetée.')
      onTraitee()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setTraitement(false)
    }
  }

  return (
    <Modal title="Détail de la modification" onClose={onClose}>
      {isLoading || !m ? (
        <Spinner />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-display text-base font-bold text-navy-900">{m.eleve?.nom_complet}</p>
              <p className="text-xs text-navy-400">Proposé par {m.tuteur?.nom_complet}</p>
            </div>
            <Badge tone={STATUT_TONE[m.statut]}>{STATUT_LABEL[m.statut]}</Badge>
          </div>

          <div className="overflow-hidden rounded-xl border border-navy-100">
            <table className="w-full text-xs">
              <thead className="bg-cream-50 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                <tr>
                  <th className="px-2.5 py-2 text-left">Champ</th>
                  <th className="px-2.5 py-2 text-left">Valeur actuelle</th>
                  <th className="px-2.5 py-2 text-left">Valeur proposée</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-navy-50">
                {Object.entries(m.donnees).map(([cle, valeur]) => (
                  <tr key={cle}>
                    <td className="px-2.5 py-1.5 font-medium text-navy-800">{LIBELLES_CHAMPS[cle] || cle}</td>
                    <td className="px-2.5 py-1.5 text-navy-400">{m.valeurs_actuelles[cle] || '—'}</td>
                    <td className="px-2.5 py-1.5 font-semibold text-navy-900">{valeur || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {m.statut === 'en_attente' && (
            <>
              {rejetOuvert ? (
                <div className="flex flex-col gap-2">
                  <Textarea label="Motif du rejet" value={motifRejet} onChange={(e) => setMotifRejet(e.target.value)} />
                  <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={() => setRejetOuvert(false)}>
                      Annuler
                    </Button>
                    <Button variant="danger" onClick={rejeter} disabled={traitement || motifRejet.trim().length < 3}>
                      Confirmer le rejet
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="flex justify-end gap-2">
                  <Button variant="secondary" onClick={() => setRejetOuvert(true)} disabled={traitement}>
                    <X className="h-4 w-4" />
                    Rejeter
                  </Button>
                  <Button onClick={valider} disabled={traitement}>
                    <Check className="h-4 w-4" />
                    Valider
                  </Button>
                </div>
              )}
            </>
          )}

          {m.statut === 'rejetee' && m.motif_rejet && (
            <p className="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
              <b>Motif du rejet :</b> {m.motif_rejet}
            </p>
          )}
        </div>
      )}
    </Modal>
  )
}
