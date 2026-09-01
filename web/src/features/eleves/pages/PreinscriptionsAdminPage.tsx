import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardCheck, Check, X, Search, Pencil, Plus, Receipt, UserPlus } from 'lucide-react'
import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import { francs, fetchDossier, MODES, type ModePaiement } from '@/features/finance/api'
import { rechercheGlobaleEleves, type Eleve } from '@/features/eleves/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input, MontantInput, Select, Textarea } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
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
  const [searchParams, setSearchParams] = useSearchParams()
  const [statut, setStatut] = useState<Statut | ''>('en_attente')
  const [recherche, setRecherche] = useState('')
  const [detailId, setDetailId] = useState<number | null>(null)
  const [creationOuverte, setCreationOuverte] = useState(false)

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
            <Button type="button" onClick={() => setCreationOuverte(true)}>
              <Plus className="h-4 w-4" />
              Nouvelle préinscription
            </Button>
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

      {creationOuverte && (
        <CreerPreinscriptionModal
          onClose={() => setCreationOuverte(false)}
          onCreee={() => {
            setCreationOuverte(false)
            queryClient.invalidateQueries({ queryKey: ['preinscriptions-admin'] })
          }}
        />
      )}
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
    setEditionOuverte(true)
  }

  const enregistrerEdition = async () => {
    if (!p) return
    setTraitement(true)
    try {
      await http.put(`/preinscriptions/${id}`, {
        donnees_eleve: { ...p.donnees_eleve, ...champsEdites },
        // Portée volontairement limitée aux informations de l'élève : les
        // tuteurs proposés repartent inchangés, ce n'est pas ce qui motive
        // une correction avant validation.
        donnees_tuteurs: p.donnees_tuteurs,
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
          : "La fiche de l'élève sera mise à jour" + (p?.montant_verser ? ` et ${francs(p.montant_verser)} seront encaissés avec délivrance d'un reçu.` : '.'),
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

          {p.type === 'existant' && p.classe_actuelle && (
            <p className="rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-600">Classe actuelle (inchangée) : {p.classe_actuelle}</p>
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
              </div>
            )}
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

interface TuteurForm {
  nom_complet: string
  telephone: string
  email: string
  profession: string
  lien_parente: string
  is_principal: boolean
}

function tuteurDepuisEleve(t: Eleve['tuteurs'][number]): TuteurForm {
  const telephonePrincipal = t.telephones.find((tel) => tel.is_principal)?.numero ?? t.telephones[0]?.numero ?? t.telephone ?? ''
  return {
    nom_complet: t.nom_complet,
    telephone: telephonePrincipal,
    email: t.email ?? '',
    profession: t.profession ?? '',
    lien_parente: t.lien_parente ?? '',
    is_principal: t.is_principal,
  }
}

/**
 * Autocomplétion d'élève déjà scolarisé, pour la réinscription au guichet :
 * même principe que `TuteurNomAutocomplete` d'`EleveInscriptionPage`
 * (debounce 300ms, `rechercheGlobaleEleves`), mais choisir une suggestion ne
 * renseigne pas qu'un identifiant — tout le formulaire se recharge avec la
 * fiche actuelle de l'élève, prête à être complétée ou corrigée.
 */
function EleveAutocomplete({ onChoisir }: { onChoisir: (eleve: Eleve) => void }) {
  const [terme, setTerme] = useState('')
  const [termeDebounce, setTermeDebounce] = useState('')
  const [ouvert, setOuvert] = useState(false)

  useEffect(() => {
    const minuteur = setTimeout(() => setTermeDebounce(terme.trim()), 300)
    return () => clearTimeout(minuteur)
  }, [terme])

  const { data: suggestions, isFetching } = useQuery({
    queryKey: ['eleves-recherche-globale', termeDebounce],
    queryFn: () => rechercheGlobaleEleves(termeDebounce),
    enabled: ouvert && termeDebounce.length >= 2,
  })

  const afficherSuggestions = ouvert && termeDebounce.length >= 2 && ((suggestions?.length ?? 0) > 0 || isFetching)

  return (
    <div className="relative">
      <Input
        label="Nom de l'élève"
        placeholder="Commencez à taper le nom de l'élève…"
        icon={Search}
        autoComplete="off"
        value={terme}
        onChange={(e) => {
          setTerme(e.target.value)
          setOuvert(true)
        }}
        onFocus={() => setOuvert(true)}
        onBlur={() => setTimeout(() => setOuvert(false), 150)}
      />
      {afficherSuggestions && (
        <ul className="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-navy-100 bg-white py-1 shadow-lifted">
          {suggestions?.map((eleve) => (
            <li key={eleve.id}>
              <button
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => {
                  onChoisir(eleve)
                  setTerme(eleve.nom_complet)
                  setOuvert(false)
                }}
                className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm hover:bg-cream-100"
              >
                <span className="font-medium text-navy-900">{eleve.nom_complet}</span>
                <span className="text-xs text-navy-400">
                  {[eleve.matricule, eleve.classe?.nom].filter(Boolean).join(' · ') || 'Non affecté à une classe'}
                </span>
              </button>
            </li>
          ))}
          {isFetching && <li className="px-3 py-2 text-xs text-navy-300">Recherche…</li>}
        </ul>
      )}
    </div>
  )
}

/**
 * Préinscription saisie directement par l'admin pour un élève déjà connu du
 * système (réinscription au guichet) : contrairement à la file d'attente, la
 * demande est validée du même geste qu'elle est créée — élève et tuteurs mis
 * à jour, versement éventuel encaissé et reçu affiché immédiatement.
 */
function CreerPreinscriptionModal({ onClose, onCreee }: { onClose: () => void; onCreee: () => void }) {
  const [eleve, setEleve] = useState<Eleve | null>(null)
  const [champs, setChamps] = useState<Record<string, string>>({})
  const [tuteurs, setTuteurs] = useState<TuteurForm[]>([])
  const [montant, setMontant] = useState(0)
  const [mode, setMode] = useState<ModePaiement>('especes')
  const [reference, setReference] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [erreurMsg, setErreurMsg] = useState<string | null>(null)

  const choisirEleve = (choix: Eleve) => {
    setEleve(choix)
    setChamps(Object.fromEntries(CHAMPS_ELEVE.map(([cle]) => [cle, String((choix as unknown as Record<string, unknown>)[cle] ?? '')])))
    setTuteurs(choix.tuteurs.length > 0 ? choix.tuteurs.map(tuteurDepuisEleve) : [])
    setErreurMsg(null)
  }

  // Ce qui est réellement dû, pas seulement ce que le parent a annoncé — pour
  // que le montant à collecter reste visible même quand c'est quelqu'un
  // d'autre que le parent qui vient payer au guichet.
  const { data: dossier, isFetching: dossierEnChargement } = useQuery({
    queryKey: ['dossier-scolarite', eleve?.id],
    queryFn: () => fetchDossier(eleve!.id),
    enabled: !!eleve,
  })
  const montantNombre = montant || 0
  const resteApresPaiement = dossier ? dossier.reste_a_payer - montantNombre : null

  const majTuteur = (index: number, patch: Partial<TuteurForm>) => {
    setTuteurs((t) => t.map((tut, i) => (i === index ? { ...tut, ...patch } : tut)))
  }

  const enregistrer = async () => {
    if (!eleve) return
    setEnvoi(true)
    setErreurMsg(null)
    try {
      const { data } = await http.post<ApiResponse<PreinscriptionResume>>('/preinscriptions', {
        eleve_id: eleve.id,
        donnees_eleve: champs,
        donnees_tuteurs: tuteurs
          .filter((t) => t.nom_complet.trim() !== '')
          .map((t) => ({
            nom_complet: t.nom_complet,
            telephone: t.telephone || undefined,
            email: t.email || undefined,
            profession: t.profession || undefined,
            lien_parente: t.lien_parente || undefined,
            is_principal: t.is_principal,
          })),
        montant_verser: montantNombre > 0 ? montantNombre : undefined,
        mode_versement: montantNombre > 0 ? mode : undefined,
        reference_externe: reference || undefined,
      })

      succes('Préinscription enregistrée et validée.')
      if (data.data.versement_id) {
        ouvrirDocument(`/versements/${data.data.versement_id}/recu`)
      }
      onCreee()
    } catch (err) {
      setErreurMsg((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title="Nouvelle préinscription" onClose={onClose}>
      <div className="flex flex-col gap-4">
        {!eleve ? (
          <>
            <p className="rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-600">
              Recherchez l'élève à réinscrire : ses informations et ses tuteurs se rechargeront automatiquement, prêts à être
              complétés.
            </p>
            <EleveAutocomplete onChoisir={choisirEleve} />
          </>
        ) : (
          <>
            <div className="flex items-center justify-between rounded-lg bg-green-50 px-3 py-2">
              <div>
                <p className="text-sm font-semibold text-navy-900">{eleve.nom_complet}</p>
                <p className="text-xs text-navy-500">{[eleve.matricule, eleve.classe?.nom].filter(Boolean).join(' · ')}</p>
              </div>
              <button type="button" onClick={() => setEleve(null)} className="text-xs font-medium text-navy-500 hover:text-navy-800">
                Changer d'élève
              </button>
            </div>

            <div>
              <h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-navy-500">Informations de l'élève</h3>
              <div className="grid grid-cols-2 gap-2.5">
                {CHAMPS_ELEVE.map(([cle, libelle]) => (
                  <Input
                    key={cle}
                    label={libelle}
                    value={champs[cle] ?? ''}
                    onChange={(e) => setChamps((c) => ({ ...c, [cle]: e.target.value }))}
                  />
                ))}
              </div>
            </div>

            <div>
              <h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-navy-500">Tuteurs</h3>
              {tuteurs.length === 0 ? (
                <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                  Aucun tuteur enregistré pour cet élève. Ajoutez-en un depuis sa fiche avant de le réinscrire.
                </p>
              ) : (
                <div className="flex flex-col gap-3">
                  {tuteurs.map((t, i) => (
                    <div key={i} className="grid grid-cols-2 gap-2.5 rounded-lg bg-cream-50 p-3">
                      <Input label="Nom complet" value={t.nom_complet} onChange={(e) => majTuteur(i, { nom_complet: e.target.value })} />
                      <Input label="Téléphone" value={t.telephone} onChange={(e) => majTuteur(i, { telephone: e.target.value })} />
                      <Input label="Email" value={t.email} onChange={(e) => majTuteur(i, { email: e.target.value })} />
                      <Input label="Profession" value={t.profession} onChange={(e) => majTuteur(i, { profession: e.target.value })} />
                      <Input
                        label="Lien de parenté"
                        value={t.lien_parente}
                        onChange={(e) => majTuteur(i, { lien_parente: e.target.value })}
                      />
                      <label className="flex items-center gap-2 self-end pb-2 text-sm">
                        <input
                          type="checkbox"
                          checked={t.is_principal}
                          onChange={() => setTuteurs((ts) => ts.map((tut, j) => ({ ...tut, is_principal: j === i })))}
                          className="rounded border-navy-300"
                        />
                        <span className="text-navy-700">Tuteur principal</span>
                      </label>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div>
              <h3 className="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-navy-500">
                <Receipt className="h-3.5 w-3.5" />
                Versement (facultatif)
              </h3>

              {/* Le montant réellement dû, pas seulement ce que le parent a pu annoncer — indispensable
                  quand ce n'est pas lui qui vient payer, mais quelqu'un envoyé au guichet à sa place. */}
              {dossierEnChargement ? (
                <p className="text-xs text-navy-400">Calcul du montant dû…</p>
              ) : dossier ? (
                <dl className="mb-3 grid grid-cols-3 gap-2 rounded-xl bg-cream-100 p-3 text-center">
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
              ) : null}

              <div className="grid grid-cols-3 gap-2.5">
                <MontantInput label="Montant à encaisser" value={montant} onChange={setMontant} />
                <Select label="Mode" value={mode} onChange={(e) => setMode(e.target.value as ModePaiement)}>
                  {MODES.map((m) => (
                    <option key={m.valeur} value={m.valeur}>
                      {m.libelle}
                    </option>
                  ))}
                </Select>
                <Input label="Référence" value={reference} onChange={(e) => setReference(e.target.value)} />
              </div>

              {montantNombre > 0 && resteApresPaiement !== null && (
                <p className="mt-2 text-xs text-navy-500">
                  {resteApresPaiement > 0 ? (
                    <>Reste après ce versement : <span className="font-semibold">{francs(resteApresPaiement)}</span></>
                  ) : resteApresPaiement === 0 ? (
                    <span className="font-semibold text-green-600">Solde entièrement soldé.</span>
                  ) : (
                    <span className="font-semibold text-blue-600">Avance de {francs(-resteApresPaiement)}.</span>
                  )}
                  {' '}Un reçu sera généré et affiché à l'impression dès l'enregistrement.
                </p>
              )}
            </div>

            {erreurMsg && <p className="text-sm text-red-500">{erreurMsg}</p>}

            <div className="mt-2 flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={onClose} disabled={envoi}>
                Annuler
              </Button>
              <Button type="button" onClick={enregistrer} disabled={envoi || tuteurs.length === 0}>
                <UserPlus className="h-4 w-4" />
                Enregistrer et valider
              </Button>
            </div>
          </>
        )}
      </div>
    </Modal>
  )
}
