import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardCheck, Check, X, Search, Pencil, Plus, Trash2 } from 'lucide-react'
import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import { francs, fetchDossier } from '@/features/finance/api'
import { fetchClasses, fetchNiveaux } from '@/features/classes/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import type { ApiError } from '@/shared/types/api'
import { completerTelephones, telephonesParDefaut, type TelephoneEntry } from '@/features/eleves/lib/telephones'
import { TelephonesEditor } from '@/features/eleves/components/TelephonesEditor'
import { ClasseNiveauPicker } from '@/features/eleves/components/ClasseNiveauPicker'

type Statut = 'en_attente' | 'validee' | 'rejetee'
type Type = 'existant' | 'nouveau'

export interface PreinscriptionResume {
  id: number
  type: Type
  statut: Statut
  tuteur: { id: number; nom_complet: string; telephone: string | null; email: string | null } | null
  eleve: { id: number; nom_complet: string; matricule: string | null } | null
  nom_propose: string | null
  montant_verser: number | null
  versement_id: number | null
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
  /** Classe choisie par l'admin pour la validation — `null` tant qu'il ne l'a pas corrigée. */
  classe_id: number | null
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
interface TuteurEditForm {
  nom_complet: string
  telephones: TelephoneEntry[]
  email: string
  profession: string
  lien_parente: string
  lieu_service: string
  is_principal: boolean
}

function tuteurEditFormDepuisDonnees(t: Record<string, unknown>): TuteurEditForm {
  const telephonesBrutes = Array.isArray(t.telephones) ? (t.telephones as { numero?: string; is_principal?: boolean }[]) : []
  const telephones =
    telephonesBrutes.length > 0
      ? telephonesBrutes.map((tel) => ({ numero: String(tel.numero ?? ''), is_principal: Boolean(tel.is_principal) }))
      : t.telephone
        ? [{ numero: String(t.telephone), is_principal: true }]
        : []
  return {
    nom_complet: String(t.nom_complet ?? ''),
    telephones: completerTelephones(telephones),
    email: String(t.email ?? ''),
    profession: String(t.profession ?? ''),
    lien_parente: String(t.lien_parente ?? ''),
    lieu_service: String(t.lieu_service ?? ''),
    is_principal: Boolean(t.is_principal),
  }
}

export const CHAMPS_ELEVE: [string, string][] = [
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
  const [searchParams, setSearchParams] = useSearchParams()
  const [statut, setStatut] = useState<Statut | ''>('en_attente')
  const [recherche, setRecherche] = useState('')
  const [detailId, setDetailId] = useState<number | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['preinscriptions-admin', statut],
    queryFn: () => fetchPreinscriptions(statut),
  })

  // Ouvre directement le détail visé par le lien d'une notification
  // (`/preinscriptions?id=…`) — sans quoi cliquer la notification amenait sur
  // la liste sans jamais désigner la demande à traiter.
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
    queryClient.invalidateQueries({ queryKey: ['preinscriptions-admin'] })
    setDetailId(null)
  }

  const donneesFiltrees = (data ?? []).filter((p) => {
    const q = recherche.trim().toLowerCase()
    if (!q) return true
    return [p.eleve?.nom_complet, p.nom_propose, p.eleve?.matricule, p.tuteur?.nom_complet, p.tuteur?.telephone, p.tuteur?.email]
      .filter(Boolean)
      .some((v) => String(v).toLowerCase().includes(q))
  })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Préinscriptions"
        sousTitre="Demandes déposées par les parents, en attente de validation."
        icon={ClipboardCheck}
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
            <Link to="/preinscriptions/nouvelle">
              <Button type="button">
                <Plus className="h-4 w-4" />
                Nouvelle préinscription
              </Button>
            </Link>
          </div>
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState label="Aucune préinscription dans cet état." />
      ) : donneesFiltrees.length === 0 ? (
        <EmptyState label="Aucune préinscription ne correspond à cette recherche." />
      ) : (
        <div className="flex flex-col gap-3">
          {donneesFiltrees.map((p) => (
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
  const queryClient = useQueryClient()
  const [motifRejet, setMotifRejet] = useState('')
  const [rejetOuvert, setRejetOuvert] = useState(false)
  const [traitement, setTraitement] = useState(false)
  const [editionOuverte, setEditionOuverte] = useState(false)
  const [champsEdites, setChampsEdites] = useState<Record<string, string>>({})
  const [classeIdEdite, setClasseIdEdite] = useState<number | null>(null)
  const [niveauIdEdite, setNiveauIdEdite] = useState<number | undefined>(undefined)
  const [tuteursEdites, setTuteursEdites] = useState<TuteurEditForm[]>([])

  const { data: classes } = useQuery({ queryKey: ['classes', 'select'], queryFn: () => fetchClasses() })
  const { data: niveaux } = useQuery({ queryKey: ['niveaux'], queryFn: () => fetchNiveaux() })

  const { data: p, isLoading } = useQuery({ queryKey: ['preinscription-admin', id], queryFn: () => fetchPreinscription(id) })

  // Le montant réellement dû, pas seulement ce que le parent a pu proposer :
  // utile quand ce n'est pas lui-même mais quelqu'un envoyé au guichet qui
  // vient régler, et qui doit savoir combien apporter.
  const { data: dossier } = useQuery({
    queryKey: ['dossier-scolarite', p?.eleve?.id],
    queryFn: () => fetchDossier(p!.eleve!.id),
    enabled: p?.type === 'existant' && !!p?.eleve?.id,
  })

  const ouvrirEdition = () => {
    setChampsEdites(
      Object.fromEntries(CHAMPS_ELEVE.map(([cle]) => [cle, String(p?.donnees_eleve[cle] ?? '')])),
    )
    // Nouvelle inscription : la classe proposée vit dans donnees_eleve.
    // Réinscription : dans classe_id, distinct de la classe actuelle de l'élève.
    const classeChoisie = p?.classe_id ?? (p?.donnees_eleve.classe_id as number | undefined) ?? null
    setClasseIdEdite(classeChoisie)
    setNiveauIdEdite(classeChoisie ? classes?.find((c) => c.id === classeChoisie)?.niveau_id : undefined)
    setTuteursEdites((p?.donnees_tuteurs ?? []).map(tuteurEditFormDepuisDonnees))
    setEditionOuverte(true)
  }

  const majTuteurEdite = (index: number, patch: Partial<TuteurEditForm>) => {
    setTuteursEdites((ts) => ts.map((t, i) => (i === index ? { ...t, ...patch } : t)))
  }

  const ajouterTuteurEdite = () => {
    setTuteursEdites((ts) => [
      ...ts,
      {
        nom_complet: '',
        telephones: telephonesParDefaut(),
        email: '',
        profession: '',
        lien_parente: '',
        lieu_service: '',
        is_principal: ts.length === 0,
      },
    ])
  }

  const supprimerTuteurEdite = (index: number) => {
    setTuteursEdites((ts) => ts.filter((_, i) => i !== index))
  }

  const enregistrerEdition = async () => {
    if (!p) return
    setTraitement(true)
    try {
      const donneesTuteurs = tuteursEdites
        .filter((t) => t.nom_complet.trim() !== '')
        .map((t) => ({
          nom_complet: t.nom_complet,
          telephones: t.telephones.filter((tel) => tel.numero.trim() !== ''),
          email: t.email || undefined,
          profession: t.profession || undefined,
          lien_parente: t.lien_parente || undefined,
          lieu_service: t.lieu_service || undefined,
          is_principal: t.is_principal,
        }))
      await http.put(`/preinscriptions/${id}`, {
        donnees_eleve: { ...p.donnees_eleve, ...champsEdites, classe_id: classeIdEdite },
        donnees_tuteurs: donneesTuteurs,
        classe_id: classeIdEdite,
      })
      succes('Informations mises à jour.')
      setEditionOuverte(false)
      queryClient.invalidateQueries({ queryKey: ['preinscription-admin', id] })
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setTraitement(false)
    }
  }

  const valider = async () => {
    const ok = await confirmer({
      titre: 'Valider cette préinscription ?',
      message:
        p?.type === 'nouveau'
          ? "L'élève sera créé avec les informations proposées."
          : "La fiche de l'élève sera mise à jour" +
            (p?.classe_id ? `, sa classe changée pour ${classes?.find((c) => c.id === p.classe_id)?.nom ?? 'la classe choisie'}` : '') +
            (p?.montant_verser ? ` et ${francs(p.montant_verser)} seront encaissés avec délivrance d'un reçu.` : '.'),
      action: 'Valider',
    })
    if (!ok) return

    setTraitement(true)
    try {
      const { data } = await http.post<ApiResponse<PreinscriptionResume>>(`/preinscriptions/${id}/valider`)
      succes('Préinscription validée.')
      if (data.data.versement_id) {
        ouvrirDocument(`/versements/${data.data.versement_id}/recu`)
      }
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

          {p.type === 'existant' && p.classe_actuelle && !editionOuverte && (
            <p className="rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-600">
              Classe actuelle : {p.classe_actuelle}
              {p.classe_id && classes && (
                <>
                  {' '}→ <b className="text-navy-800">{classes.find((c) => c.id === p.classe_id)?.nom ?? '—'}</b> à la validation
                </>
              )}
            </p>
          )}

          {p.note_admin && (
            <p className="rounded-lg bg-gold-50 px-3 py-2 text-xs text-navy-700">
              <b>Note du parent :</b> {p.note_admin}
            </p>
          )}

          <div>
            <div className="mb-2 flex items-center justify-between">
              <h3 className="text-xs font-bold uppercase tracking-wide text-navy-500">Informations proposées</h3>
              {p.statut === 'en_attente' && !editionOuverte && (
                <button
                  type="button"
                  onClick={ouvrirEdition}
                  className="flex items-center gap-1 text-xs font-medium text-navy-500 hover:text-navy-800"
                >
                  <Pencil className="h-3 w-3" />
                  Modifier
                </button>
              )}
            </div>

            {editionOuverte ? (
              <div className="flex flex-col gap-2.5">
                <div className="grid grid-cols-2 gap-2.5">
                  {CHAMPS_ELEVE.map(([cle, libelle]) => (
                    <Input
                      key={cle}
                      label={libelle}
                      value={champsEdites[cle] ?? ''}
                      onChange={(e) => setChampsEdites((c) => ({ ...c, [cle]: e.target.value }))}
                    />
                  ))}
                </div>
                <ClasseNiveauPicker
                  niveaux={niveaux}
                  classes={classes}
                  niveauId={niveauIdEdite}
                  onChangeNiveauId={(id) => {
                    setNiveauIdEdite(id)
                    setClasseIdEdite(null)
                  }}
                  classeId={classeIdEdite ?? undefined}
                  onChangeClasseId={(id) => setClasseIdEdite(id ?? null)}
                  niveauLabel="Niveau"
                  hint={
                    p.type === 'existant'
                      ? "Laissez le niveau vide pour conserver la classe actuelle de l'élève."
                      : "La classe pourra être modifiée ultérieurement dans les paramètres de l'élève."
                  }
                />
                <div>
                  <div className="mb-2 flex items-center justify-between">
                    <h4 className="text-xs font-bold uppercase tracking-wide text-navy-500">Tuteurs</h4>
                    <button
                      type="button"
                      onClick={ajouterTuteurEdite}
                      className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-navy-50 hover:text-navy-800"
                    >
                      <Plus className="h-3.5 w-3.5" />
                      Ajouter un tuteur
                    </button>
                  </div>

                  {tuteursEdites.length === 0 ? (
                    <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                      Aucun tuteur. Ajoutez-en un ci-dessus avant de valider.
                    </p>
                  ) : (
                    <div className="flex flex-col gap-3">
                      {tuteursEdites.map((t, i) => (
                        <div key={i} className="grid grid-cols-2 gap-2.5 rounded-lg bg-cream-50 p-3">
                          <Input label="Nom complet" value={t.nom_complet} onChange={(e) => majTuteurEdite(i, { nom_complet: e.target.value })} />
                          <Input label="Email" value={t.email} onChange={(e) => majTuteurEdite(i, { email: e.target.value })} />
                          <Input label="Profession" value={t.profession} onChange={(e) => majTuteurEdite(i, { profession: e.target.value })} />
                          <Input
                            label="Lien de parenté"
                            value={t.lien_parente}
                            onChange={(e) => majTuteurEdite(i, { lien_parente: e.target.value })}
                          />
                          <div className="col-span-2">
                            <TelephonesEditor telephones={t.telephones} onChange={(telephones) => majTuteurEdite(i, { telephones })} />
                          </div>
                          <div className="col-span-2 flex items-center justify-between border-t border-navy-100 pt-2">
                            <label className="flex items-center gap-2 text-sm">
                              <input
                                type="checkbox"
                                checked={t.is_principal}
                                onChange={() => setTuteursEdites((ts) => ts.map((tut, j) => ({ ...tut, is_principal: j === i })))}
                                className="rounded border-navy-300"
                              />
                              <span className="text-navy-700">Tuteur principal</span>
                            </label>
                            <button
                              type="button"
                              onClick={() => supprimerTuteurEdite(i)}
                              className="rounded-lg p-2 text-navy-400 hover:bg-red-100 hover:text-red-500"
                              title="Supprimer ce tuteur"
                            >
                              <Trash2 className="h-4 w-4" />
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
                <div className="flex justify-end gap-2">
                  <Button size="sm" variant="secondary" onClick={() => setEditionOuverte(false)} disabled={traitement}>
                    Annuler
                  </Button>
                  <Button size="sm" onClick={enregistrerEdition} disabled={traitement}>
                    Enregistrer
                  </Button>
                </div>
              </div>
            ) : (
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
                {p.type === 'nouveau' && (
                  <div>
                    <span className="text-navy-400">Classe : </span>
                    <span className="font-medium text-navy-800">
                      {classes?.find((c) => c.id === (p.classe_id ?? p.donnees_eleve.classe_id))?.nom ?? '—'}
                    </span>
                  </div>
                )}
              </div>
            )}
          </div>

          {!editionOuverte && (
            <div>
              <h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-navy-500">Tuteurs</h3>
              <div className="flex flex-col gap-2">
                {p.donnees_tuteurs.map((t, i) => {
                  const telephones = Array.isArray(t.telephones) ? (t.telephones as { numero?: string; is_principal?: boolean }[]) : []
                  const numeros =
                    telephones.length > 0
                      ? telephones.filter((tel) => tel.numero).map((tel) => `${tel.numero}${tel.is_principal ? ' ★' : ''}`).join(', ')
                      : t.telephone
                        ? String(t.telephone)
                        : null
                  return (
                    <div key={i} className="rounded-lg bg-cream-50 px-3 py-2 text-xs">
                      <p className="font-semibold text-navy-800">{String(t.nom_complet)}</p>
                      <p className="text-navy-500">
                        {[t.lien_parente, numeros, t.email, t.lieu_service].filter(Boolean).join(' · ') || '—'}
                      </p>
                    </div>
                  )
                })}
              </div>
            </div>
          )}

          {dossier && (
            <dl className="grid grid-cols-3 gap-2 rounded-xl bg-cream-100 p-3 text-center">
              <div>
                <dt className="text-[11px] uppercase tracking-wide text-navy-400">Total dû</dt>
                <dd className="text-sm font-bold tabular-nums text-navy-700">{francs(dossier.total_du)}</dd>
              </div>
              <div>
                <dt className="text-[11px] uppercase tracking-wide text-navy-400">Déjà versé</dt>
                <dd className="text-sm font-bold tabular-nums text-green-600">{francs(dossier.total_paye)}</dd>
              </div>
              <div>
                <dt className="text-[11px] uppercase tracking-wide text-navy-400">Reste à payer</dt>
                <dd className="text-sm font-bold tabular-nums text-red-500">{francs(dossier.reste_a_payer)}</dd>
              </div>
            </dl>
          )}

          {p.montant_verser ? (
            <p className="rounded-lg bg-green-50 px-3 py-2 text-xs text-green-800">
              Versement proposé : <b>{francs(p.montant_verser)}</b> ({p.mode_versement})
            </p>
          ) : null}

          {p.statut === 'en_attente' && !editionOuverte && (
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

