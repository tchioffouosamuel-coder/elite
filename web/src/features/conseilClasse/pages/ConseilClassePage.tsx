import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, FileDown, ShieldAlert, HeartHandshake, Undo2, ChevronDown } from 'lucide-react'
import {
  fetchConseilClasse,
  definirSeuilConseil,
  definirDestinationConseil,
  exclureDecision,
  gracierDecision,
  annulerAjustementDecision,
  validerConseil,
  type DecisionConseil,
} from '@/features/conseilClasse/api'
import { fetchClasses } from '@/features/classes/api'
import { ouvrirDocument } from '@/shared/lib/download'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Conseil de classe de fin d'année : décisions par défaut (moyenne annuelle
 * vs seuil), ajustements motivés (exclusion, grâce), classe de destination
 * des admis, puis validation — irréversible, elle mute les fiches élèves,
 * archive la classe et permet de télécharger le PV. Sans rapport avec le PV
 * du conseil de fin de trimestre (simple constat, pas de décision) déjà
 * accessible depuis la fiche classe.
 */
export function ConseilClassePage() {
  const { classeId } = useParams<{ classeId: string }>()
  const id = Number(classeId)
  const queryClient = useQueryClient()

  const [seuilModalOuvert, setSeuilModalOuvert] = useState(false)
  const [motifModal, setMotifModal] = useState<{ decision: DecisionConseil; action: 'exclure' | 'gracier' } | null>(null)
  const [validationEnCours, setValidationEnCours] = useState(false)

  const { data: conseil, isLoading, isError } = useQuery({
    queryKey: ['conseil-classe', id],
    queryFn: () => fetchConseilClasse(id),
  })
  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: fetchClasses })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['conseil-classe', id] })

  if (isLoading) return <Spinner />
  if (isError || !conseil) return <ErrorState />

  const brouillon = conseil.statut === 'brouillon'
  const admis = conseil.decisions.filter((d) => d.decision_finale === 'admis')
  const redoublants = conseil.decisions.filter((d) => d.decision_finale === 'redouble')
  const exclus = conseil.decisions.filter((d) => d.decision_finale === 'exclu')

  const changerDestination = async (valeur: string) => {
    try {
      await definirDestinationConseil(conseil.id, valeur ? Number(valeur) : null)
      invalider()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const annulerAjustement = async (decision: DecisionConseil) => {
    try {
      await annulerAjustementDecision(decision.id)
      invalider()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const valider = async () => {
    const confirme = await confirmer({
      titre: 'Valider le conseil de classe ?',
      message: "Action irréversible : les classes changent, les redoublants sont marqués, les exclus sont archivés comme tels, et la classe est archivée pour cette année.",
      action: 'Valider',
      destructif: true,
    })
    if (!confirme) return

    setValidationEnCours(true)
    try {
      await validerConseil(conseil.id)
      invalider()
      succes('Conseil de classe validé.')
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setValidationEnCours(false)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to={`/classes/${conseil.classe.id}`} className="mb-2 flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          {conseil.classe.nom}
        </Link>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">Conseil de classe — {conseil.classe.nom}</h1>
            <p className="text-sm text-navy-400">{conseil.annee_scolaire.libelle}</p>
          </div>
          <div className="flex items-center gap-2">
            <Badge tone={brouillon ? 'gold' : 'green'}>{brouillon ? 'Brouillon' : 'Validé'}</Badge>
            <Button variant="secondary" onClick={() => ouvrirDocument(`/conseils-classe/${conseil.id}/pv`, undefined, undefined, 'PV du conseil')}>
              <FileDown className="h-4 w-4" />
              Télécharger le PV
            </Button>
          </div>
        </div>
      </div>

      <Card>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-1">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">Seuil de passage</span>
            <div className="flex items-center gap-2">
              <span className="text-lg font-bold text-navy-900">{conseil.seuil_moyenne}/20</span>
              {brouillon && (
                <button onClick={() => setSeuilModalOuvert(true)} className="text-xs font-semibold text-navy-500 underline hover:text-navy-700">
                  Modifier
                </button>
              )}
            </div>
            {conseil.motif_seuil && <p className="text-xs text-navy-400">{conseil.motif_seuil}</p>}
          </div>

          <div className="flex flex-col gap-1">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">Classe de destination des admis</span>
            {brouillon ? (
              <Select
                value={conseil.classe_destination?.id ?? ''}
                onChange={(e) => changerDestination(e.target.value)}
              >
                <option value="">Fin de cycle — les admis seront diplômés</option>
                {(classes ?? []).filter((c) => c.id !== conseil.classe.id).map((c) => (
                  <option key={c.id} value={c.id}>{c.nom}</option>
                ))}
              </Select>
            ) : (
              <span className="text-lg font-bold text-navy-900">{conseil.classe_destination?.nom ?? 'Fin de cycle (diplôme)'}</span>
            )}
          </div>
        </div>
      </Card>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <ListeDecisions
          titre={`Admis (${admis.length})`}
          tone="green"
          decisions={admis}
          brouillon={brouillon}
          onExclure={(d) => setMotifModal({ decision: d, action: 'exclure' })}
          onAnnuler={annulerAjustement}
        />
        <ListeDecisions
          titre={`Redoublants (${redoublants.length})`}
          tone="gold"
          decisions={redoublants}
          brouillon={brouillon}
          onExclure={(d) => setMotifModal({ decision: d, action: 'exclure' })}
          onGracier={(d) => setMotifModal({ decision: d, action: 'gracier' })}
          onAnnuler={annulerAjustement}
        />
      </div>

      {exclus.length > 0 && (
        <ListeDecisions titre={`Exclus (${exclus.length})`} tone="red" decisions={exclus} brouillon={brouillon} onAnnuler={annulerAjustement} />
      )}

      {brouillon && (
        <div className="flex justify-end">
          <Button onClick={valider} disabled={validationEnCours}>
            {validationEnCours ? 'Validation…' : 'Valider le conseil de classe'}
          </Button>
        </div>
      )}

      {seuilModalOuvert && (
        <SeuilModal
          conseilId={conseil.id}
          seuilActuel={conseil.seuil_moyenne}
          onClose={() => setSeuilModalOuvert(false)}
          onDone={() => {
            setSeuilModalOuvert(false)
            invalider()
          }}
        />
      )}

      {motifModal && (
        <MotifModal
          decision={motifModal.decision}
          action={motifModal.action}
          onClose={() => setMotifModal(null)}
          onDone={() => {
            setMotifModal(null)
            invalider()
          }}
        />
      )}
    </div>
  )
}

function ListeDecisions({
  titre,
  tone,
  decisions,
  brouillon,
  onExclure,
  onGracier,
  onAnnuler,
}: {
  titre: string
  tone: 'green' | 'gold' | 'red'
  decisions: DecisionConseil[]
  brouillon: boolean
  onExclure?: (d: DecisionConseil) => void
  onGracier?: (d: DecisionConseil) => void
  onAnnuler: (d: DecisionConseil) => void
}) {
  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <Badge tone={tone}>{titre}</Badge>
      </h2>
      {decisions.length === 0 ? (
        <p className="text-sm text-navy-400">Aucun élève.</p>
      ) : (
        <div className="flex flex-col divide-y divide-navy-50">
          {decisions.map((d) => (
            <LigneDecision key={d.id} decision={d} brouillon={brouillon} onExclure={onExclure} onGracier={onGracier} onAnnuler={onAnnuler} />
          ))}
        </div>
      )}
    </Card>
  )
}

function LigneDecision({
  decision,
  brouillon,
  onExclure,
  onGracier,
  onAnnuler,
}: {
  decision: DecisionConseil
  brouillon: boolean
  onExclure?: (d: DecisionConseil) => void
  onGracier?: (d: DecisionConseil) => void
  onAnnuler: (d: DecisionConseil) => void
}) {
  const [menuOuvert, setMenuOuvert] = useState(false)
  const ajuste = decision.gracie || decision.decision_finale === 'exclu'
  const peutGracier = onGracier && decision.decision_finale === 'redouble' && !decision.gracie

  return (
    <div className="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
      <div>
        <p className="text-sm font-medium text-navy-800">{decision.eleve.nom_complet}</p>
        <p className="text-xs text-navy-400">
          {decision.eleve.matricule ?? '—'} · {decision.moyenne_annuelle ?? '—'}/20
          {decision.gracie && <span className="ml-1 text-gold-600">· gracié</span>}
        </p>
        {decision.motif && <p className="text-xs text-navy-500">{decision.motif}</p>}
      </div>

      {brouillon && (onExclure || peutGracier || ajuste) && (
        <div className="relative">
          <button
            onClick={() => setMenuOuvert((v) => !v)}
            className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
          >
            Ajuster <ChevronDown className="h-3.5 w-3.5" />
          </button>
          {menuOuvert && (
            <div className="absolute right-0 z-10 mt-1 w-48 rounded-xl border border-navy-100 bg-white py-1 shadow-lifted">
              {ajuste ? (
                <button
                  onClick={() => {
                    setMenuOuvert(false)
                    onAnnuler(decision)
                  }}
                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-navy-600 hover:bg-cream-100"
                >
                  <Undo2 className="h-3.5 w-3.5" />
                  Annuler l'ajustement
                </button>
              ) : (
                <>
                  {peutGracier && onGracier && (
                    <button
                      onClick={() => {
                        setMenuOuvert(false)
                        onGracier(decision)
                      }}
                      className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-navy-600 hover:bg-cream-100"
                    >
                      <HeartHandshake className="h-3.5 w-3.5" />
                      Gracier
                    </button>
                  )}
                  {onExclure && (
                    <button
                      onClick={() => {
                        setMenuOuvert(false)
                        onExclure(decision)
                      }}
                      className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                      <ShieldAlert className="h-3.5 w-3.5" />
                      Exclure
                    </button>
                  )}
                </>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}

function SeuilModal({ conseilId, seuilActuel, onClose, onDone }: { conseilId: number; seuilActuel: number; onClose: () => void; onDone: () => void }) {
  const [seuil, setSeuil] = useState(String(seuilActuel))
  const [motif, setMotif] = useState('')
  const [envoi, setEnvoi] = useState(false)

  const soumettre = async () => {
    setEnvoi(true)
    try {
      await definirSeuilConseil(conseilId, Number(seuil), motif || undefined)
      succes('Seuil mis à jour.')
      onDone()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title="Modifier le seuil de passage" onClose={onClose}>
      <div className="flex flex-col gap-4">
        <Input label="Seuil (sur 20)" type="number" step="0.1" min={0} max={20} value={seuil} onChange={(e) => setSeuil(e.target.value)} />
        <Textarea
          label="Motif (requis si différent du seuil par défaut de l'école)"
          value={motif}
          onChange={(e) => setMotif(e.target.value)}
          placeholder="Ex. : classe à profil particulier, décision du conseil pédagogique…"
        />
        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>Annuler</Button>
          <Button onClick={soumettre} disabled={envoi}>{envoi ? 'Envoi…' : 'Enregistrer'}</Button>
        </div>
      </div>
    </Modal>
  )
}

function MotifModal({
  decision,
  action,
  onClose,
  onDone,
}: {
  decision: DecisionConseil
  action: 'exclure' | 'gracier'
  onClose: () => void
  onDone: () => void
}) {
  const [motif, setMotif] = useState('')
  const [envoi, setEnvoi] = useState(false)

  const soumettre = async () => {
    if (!motif.trim()) return
    setEnvoi(true)
    try {
      if (action === 'exclure') await exclureDecision(decision.id, motif.trim())
      else await gracierDecision(decision.id, motif.trim())
      succes(action === 'exclure' ? 'Élève exclu.' : 'Élève gracié.')
      onDone()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={action === 'exclure' ? `Exclure ${decision.eleve.nom_complet}` : `Gracier ${decision.eleve.nom_complet}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <Textarea
          label="Motif (obligatoire)"
          value={motif}
          onChange={(e) => setMotif(e.target.value)}
          placeholder={action === 'exclure' ? 'Motif de l\'exclusion…' : 'Motif de la grâce…'}
        />
        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>Annuler</Button>
          <Button onClick={soumettre} disabled={envoi || !motif.trim()}>{envoi ? 'Envoi…' : 'Confirmer'}</Button>
        </div>
      </div>
    </Modal>
  )
}
