import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardCheck, Check, X } from 'lucide-react'
import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import { francs } from '@/features/finance/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Select, Textarea } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

type Statut = 'en_attente' | 'validee' | 'rejetee'
type Type = 'existant' | 'nouveau'

interface PreinscriptionResume {
  id: number
  type: Type
  statut: Statut
  tuteur: { id: number; nom_complet: string; telephone: string | null; email: string | null } | null
  eleve: { id: number; nom_complet: string; matricule: string | null } | null
  nom_propose: string | null
  montant_verser: number | null
  motif_rejet: string | null
  created_at: string
  traite_le: string | null
}

interface PreinscriptionDetail extends PreinscriptionResume {
  donnees_eleve: Record<string, unknown>
  donnees_tuteurs: Record<string, unknown>[]
  note_admin: string | null
  mode_versement: string | null
  classe_actuelle: string | null
}

async function fetchPreinscriptions(statut: Statut | ''): Promise<PreinscriptionResume[]> {
  const { data } = await http.get<ApiResponse<PreinscriptionResume[]>>('/preinscriptions', { params: { statut: statut || undefined } })
  return data.data
}

async function fetchPreinscription(id: number): Promise<PreinscriptionDetail> {
  const { data } = await http.get<ApiResponse<PreinscriptionDetail>>(`/preinscriptions/${id}`)
  return data.data
}

const STATUT_TONE = { en_attente: 'gold', validee: 'green', rejetee: 'red' } as const
const STATUT_LABEL = { en_attente: 'En attente', validee: 'Validée', rejetee: 'Rejetée' } as const
const CHAMPS_ELEVE: [string, string][] = [
  ['nom_complet', 'Nom complet'],
  ['sexe', 'Sexe'],
  ['date_naissance', 'Date de naissance'],
  ['lieu_naissance', 'Lieu de naissance'],
  ['adresse', 'Adresse'],
  ['numero_acte_naissance', "N° acte de naissance"],
  ['lieu_delivrance_acte', 'Lieu de délivrance'],
  ['officier_etat_civil', "Officier d'état civil"],
  ['groupe_sanguin', 'Groupe sanguin'],
  ['aptitude', 'Aptitude'],
  ['allergies', 'Allergies'],
  ['situation_sanitaire', 'Situation sanitaire'],
]

/** File d'attente des préinscriptions déposées par les parents — à examiner, valider ou rejeter. */
export function PreinscriptionsAdminPage() {
  const queryClient = useQueryClient()
  const [statut, setStatut] = useState<Statut | ''>('en_attente')
  const [detailId, setDetailId] = useState<number | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['preinscriptions-admin', statut],
    queryFn: () => fetchPreinscriptions(statut),
  })

  const invalider = () => {
    queryClient.invalidateQueries({ queryKey: ['preinscriptions-admin'] })
    setDetailId(null)
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Préinscriptions"
        sousTitre="Demandes déposées par les parents, en attente de validation."
        icon={ClipboardCheck}
        actions={
          <Select value={statut} onChange={(e) => setStatut(e.target.value as Statut | '')} className="w-48">
            <option value="en_attente">En attente</option>
            <option value="validee">Validées</option>
            <option value="rejetee">Rejetées</option>
            <option value="">Toutes</option>
          </Select>
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState label="Aucune préinscription dans cet état." />
      ) : (
        <div className="flex flex-col gap-3">
          {data.map((p) => (
            <div key={p.id} onClick={() => setDetailId(p.id)} className="cursor-pointer">
              <Card className="transition-shadow hover:shadow-lifted">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-display text-base font-bold text-navy-900">{p.eleve?.nom_complet || p.nom_propose}</p>
                    <p className="mt-0.5 text-xs text-navy-400">
                      {p.type === 'nouveau' ? 'Nouvelle inscription' : 'Révision de fiche'} · {p.tuteur?.nom_complet} (
                      {p.tuteur?.telephone || p.tuteur?.email}) · {new Date(p.created_at).toLocaleDateString('fr-FR')}
                    </p>
                    {p.montant_verser ? <p className="mt-1 text-xs text-navy-500">Versement proposé : {francs(p.montant_verser)}</p> : null}
                  </div>
                  <Badge tone={STATUT_TONE[p.statut]}>{STATUT_LABEL[p.statut]}</Badge>
                </div>
              </Card>
            </div>
          ))}
        </div>
      )}

      {detailId && <PreinscriptionDetailModal id={detailId} onClose={() => setDetailId(null)} onTraitee={invalider} />}
    </div>
  )
}

function PreinscriptionDetailModal({ id, onClose, onTraitee }: { id: number; onClose: () => void; onTraitee: () => void }) {
  const [motifRejet, setMotifRejet] = useState('')
  const [rejetOuvert, setRejetOuvert] = useState(false)
  const [traitement, setTraitement] = useState(false)

  const { data: p, isLoading } = useQuery({ queryKey: ['preinscription-admin', id], queryFn: () => fetchPreinscription(id) })

  const valider = async () => {
    const ok = await confirmer({
      titre: 'Valider cette préinscription ?',
      message:
        p?.type === 'nouveau'
          ? "L'élève sera créé avec les informations proposées."
          : "La fiche de l'élève sera mise à jour" + (p?.montant_verser ? ` et ${francs(p.montant_verser)} seront encaissés avec délivrance d'un reçu.` : '.'),
      action: 'Valider',
    })
    if (!ok) return

    setTraitement(true)
    try {
      await http.post(`/preinscriptions/${id}/valider`)
      succes('Préinscription validée.')
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
      await http.post(`/preinscriptions/${id}/rejeter`, { motif: motifRejet })
      succes('Préinscription rejetée.')
      onTraitee()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setTraitement(false)
    }
  }

  return (
    <Modal title="Détail de la préinscription" onClose={onClose}>
      {isLoading || !p ? (
        <Spinner />
      ) : (
        <div className="flex flex-col gap-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-display text-base font-bold text-navy-900">{p.eleve?.nom_complet || p.nom_propose}</p>
              <p className="text-xs text-navy-400">{p.type === 'nouveau' ? 'Nouvelle inscription' : 'Révision de fiche existante'}</p>
            </div>
            <Badge tone={STATUT_TONE[p.statut]}>{STATUT_LABEL[p.statut]}</Badge>
          </div>

          {p.type === 'existant' && p.classe_actuelle && (
            <p className="rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-600">Classe actuelle (inchangée) : {p.classe_actuelle}</p>
          )}

          {p.note_admin && (
            <p className="rounded-lg bg-gold-50 px-3 py-2 text-xs text-navy-700">
              <b>Note du parent :</b> {p.note_admin}
            </p>
          )}

          <div>
            <h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-navy-500">Informations proposées</h3>
            <div className="grid grid-cols-2 gap-2 text-xs">
              {CHAMPS_ELEVE.map(([cle, libelle]) => {
                const valeur = p.donnees_eleve[cle]
                if (!valeur) return null
                return (
                  <div key={cle}>
                    <span className="text-navy-400">{libelle} : </span>
                    <span className="font-medium text-navy-800">{String(valeur)}</span>
                  </div>
                )
              })}
            </div>
          </div>

          <div>
            <h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-navy-500">Tuteurs</h3>
            <div className="flex flex-col gap-2">
              {p.donnees_tuteurs.map((t, i) => (
                <div key={i} className="rounded-lg bg-cream-50 px-3 py-2 text-xs">
                  <p className="font-semibold text-navy-800">{String(t.nom_complet)}</p>
                  <p className="text-navy-500">
                    {[t.lien_parente, t.telephone, t.email, t.lieu_service].filter(Boolean).join(' · ') || '—'}
                  </p>
                </div>
              ))}
            </div>
          </div>

          {p.montant_verser ? (
            <p className="rounded-lg bg-green-50 px-3 py-2 text-xs text-green-800">
              Versement proposé : <b>{francs(p.montant_verser)}</b> ({p.mode_versement})
            </p>
          ) : null}

          {p.statut === 'en_attente' && (
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
        </div>
      )}
    </Modal>
  )
}
