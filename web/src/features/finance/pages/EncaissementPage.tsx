import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Controller, useForm } from 'react-hook-form'
import { ArrowLeft, BadgePercent, Ban, ChevronDown, ChevronUp, Clock, Eye, Pencil, Printer, Receipt, Wallet } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Input, MontantInput, Select, useMontantSaisie } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { CanauxNotificationField, type CanalNotification } from '@/shared/ui/CanauxNotificationField'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { imprimerDocument, ouvrirDocument } from '@/shared/lib/download'
import { annulerVersement, creerRemise, encaisser, fetchDettesAnterieures, fetchDossier, fetchRemises, francs, ventilerAutomatiquement, MODES, type DossierScolarite, type LigneVentilation, type ModePaiement } from '@/features/finance/api'
import { SectionMoratoires } from '@/features/finance/GestionInsolvableModal'
import { GestionRemiseModal } from '@/features/finance/GestionRemiseModal'
import { GestionDetteAnterieureModal } from '@/features/finance/GestionDetteAnterieureModal'
import { CorrigerVersementModal } from '@/features/finance/CorrigerVersementModal'
import { useAuthStore } from '@/shared/store/authStore'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  montant: number
  mode: ModePaiement
  date_versement: string
  reference_externe: string
  note: string
}

type SortieRecu = 'preview' | 'print'
type Versement = NonNullable<DossierScolarite['versements']>[number]

/** Date + heure lisibles, pour l'audit « qui a enregistré / annulé ce versement ». */
function formatDateHeure(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatDate(iso: string): string {
  return new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR')
}

/**
 * Encaissement au comptoir, en page dédiée plutôt qu'en modale : la
 * répartition par rubrique a besoin de place, et un reçu qui s'imprime juste
 * après mérite un écran à lui, pas une boîte qu'on referme dans la foulée.
 *
 * Un élève sans dossier en porte un projeté (`id` nul côté liste) : cette
 * page l'ouvre elle-même au premier accès plutôt qu'à l'affichage de la
 * caisse, qui ne doit pas créer un dossier pour chaque famille qui n'a rien
 * versé.
 */
export function EncaissementPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { eleveId } = useParams<{ eleveId: string }>()
  // L'écran d'où l'on vient : la caisse par défaut, mais la fiche de l'élève
  // quand l'encaissement a été lancé depuis elle — y renvoyer l'utilisateur
  // lui évite de rechercher son élève une deuxième fois.
  const { state } = useLocation() as { state?: { retour?: string } }
  const urlRetour = state?.retour ?? '/caisse'
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)
  const [allocations, setAllocations] = useState<number[]>([])
  const [allocationsModifiees, setAllocationsModifiees] = useState(false)
  const [moratoireOuvert, setMoratoireOuvert] = useState(false)
  const [canaux, setCanaux] = useState<CanalNotification[]>(['interne'])
  const [sortieRecu, setSortieRecu] = useState<SortieRecu>('preview')
  const [remise, setRemise] = useState<number | null>(null)
  const [motifRemise, setMotifRemise] = useState('')
  const [remiseEnCours, setRemiseEnCours] = useState(false)
  const [gestionRemises, setGestionRemises] = useState(false)
  const [gestionDette, setGestionDette] = useState(false)
  const [versementACorriger, setVersementACorriger] = useState<Versement | null>(null)
  const [annulationEnCours, setAnnulationEnCours] = useState<number | null>(null)
  const queryClient = useQueryClient()
  const can = useAuthStore((s) => s.can)
  const peutAccorderRemise = can('remises.create')
  const peutGererRemises = peutAccorderRemise || can('remises.update') || can('remises.delete')
  const peutGererDette = can('dettes_anterieures.create') || can('dettes_anterieures.update') || can('dettes_anterieures.delete') || can('dettes_anterieures.oublier')

  const {
    data: dossier,
    isLoading,
    isError,
  } = useQuery({
    queryKey: ['dossier-scolarite', Number(eleveId)],
    queryFn: () => fetchDossier(Number(eleveId)),
    enabled: !!eleveId,
  })
  const rubriques = useMemo(() => dossier?.rubriques ?? [], [dossier?.rubriques])

  // `values` (pas `defaultValues`) : le dossier arrive après le premier rendu
  // (fetch async), et seul `values` resynchronise le formulaire une fois la
  // donnée là — `defaultValues` figerait le montant à `undefined`.
  const {
    register,
    handleSubmit,
    watch,
    control,
    formState: { errors },
  } = useForm<FormValues>({
    values: dossier
      ? {
        montant: dossier.reste_a_payer || (undefined as unknown as number),
        mode: 'especes',
        date_versement: new Date().toISOString().slice(0, 10),
        reference_externe: '',
        note: '',
      }
      : undefined,
  })

  const montant = Number(watch('montant') || 0)
  const resteApres = (dossier?.reste_a_payer ?? 0) - montant
  const versements = (dossier?.versements ?? []).slice().sort((a, b) => new Date(b.date_versement + 'T' + (b.heure_versement ?? '00:00:00')).getTime() - new Date(a.date_versement + 'T' + (a.heure_versement ?? '00:00:00')).getTime())
  const { data: remises, isLoading: remisesEnChargement, isError: erreurRemises } = useQuery({
    queryKey: ['remises', dossier?.eleve.id],
    queryFn: () => fetchRemises(dossier!.eleve.id),
    enabled: !!dossier,
  })
  const { data: dettes, isLoading: dettesEnChargement, isError: erreurDettes } = useQuery({
    queryKey: ['dettes-anterieures', dossier?.eleve.id],
    queryFn: () => fetchDettesAnterieures(dossier!.eleve.id),
    enabled: !!dossier,
  })

  // La répartition suggérée suit le montant saisi tant que l'utilisateur n'a
  // pas encore touché aux champs — dès qu'il en modifie un, on ne l'écrase
  // plus pour ne pas effacer son ajustement.
  useEffect(() => {
    if (allocationsModifiees || rubriques.length === 0) return
    setAllocations(ventilerAutomatiquement(rubriques, montant))
  }, [montant, rubriques, allocationsModifiees])

  const totalAlloue = useMemo(() => allocations.reduce((s, v) => s + (v || 0), 0), [allocations])
  const ventilationValide = rubriques.length === 0 || (montant > 0 && totalAlloue === montant)

  const retour = () => navigate(urlRetour)

  const rafraichirDossier = async () => {
    setAllocationsModifiees(false)
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['dossier-scolarite', Number(eleveId)] }),
      queryClient.invalidateQueries({ queryKey: ['scolarite-situation'] }),
      queryClient.invalidateQueries({ queryKey: ['finance-dettes-anterieures'] }),
      queryClient.invalidateQueries({ queryKey: ['finance-insolvables'] }),
    ])
  }

  const annulerPaiement = async (versement: Versement) => {
    if (!dossier || annulationEnCours !== null) return
    const ok = await confirmer({
      titre: `Annuler le reçu ${versement.numero_recu} ?`,
      message: `${francs(versement.montant)} seront retirés du total versé de ${dossier.eleve.nom_complet}. Le reçu restera au registre, marqué annulé.`,
      action: 'Annuler le reçu',
    })
    if (!ok) return
    setAnnulationEnCours(versement.id)
    try {
      await annulerVersement(versement.id, "Annulation depuis la page d'encaissement")
      succes('Reçu annulé.')
      await rafraichirDossier()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setAnnulationEnCours(null)
    }
  }

  /**
   * La remise s'applique tout de suite, avant l'encaissement : le total dû,
   * le reste et la répartition par rubrique viennent du serveur, et le reçu
   * doit porter les montants d'après remise. Le dossier rechargé recale le
   * montant proposé sur le nouveau reste.
   */
  const accorderRemise = async () => {
    if (!dossier || !remise || remiseEnCours) return

    setRemiseEnCours(true)
    try {
      await creerRemise(dossier.eleve.id, { montant: remise, motif: motifRemise.trim() || undefined })
      succes(`Remise de ${francs(remise)} accordée.`)
      setRemise(null)
      setMotifRemise('')
      await rafraichirDossier()
      queryClient.invalidateQueries({ queryKey: ['remises', dossier.eleve.id] })
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    } finally {
      setRemiseEnCours(false)
    }
  }

  const onSubmit = async (valeurs: FormValues) => {
    if (!dossier) return
    setServerError(null)

    if (!ventilationValide) {
      setServerError(`La répartition (${francs(totalAlloue)}) doit correspondre au montant reçu (${francs(montant)}).`)
      return
    }

    setSubmitting(true)
    try {
      const lignes: LigneVentilation[] | undefined =
        rubriques.length > 0
          ? rubriques
            .map((r, i) => ({
              affectation: r.cle,
              dossier_frais_annexe_id: r.dossier_frais_annexe_id,
              libelle: r.libelle,
              montant: allocations[i] || 0,
            }))
            .filter((l) => l.montant > 0)
          : undefined

      const { versement_id, numero_recu } = await encaisser(dossier.id, {
        montant: Number(valeurs.montant),
        mode: valeurs.mode,
        date_versement: valeurs.date_versement || undefined,
        reference_externe: valeurs.reference_externe || undefined,
        note: valeurs.note || undefined,
        lignes,
        canaux,
      })

      succes(t('finance.receipt_recorded', { numero: numero_recu }))
      const urlRecu = `/versements/${versement_id}/recu`
      if (sortieRecu === 'print') {
        await imprimerDocument(urlRecu)
      } else {
        await ouvrirDocument(urlRecu)
      }
      retour()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) setServerError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  if (isError) return <ErrorState />
  if (isLoading || !dossier) return <Spinner />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <button
          onClick={retour}
          className="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
        >
          <ArrowLeft className="h-4 w-4" />
          {state?.retour ? t('common.back') : 'Retour à la caisse'}
        </button>
        <PageHeader titre={`Encaisser — ${dossier.eleve.nom_complet}`} icon={Wallet} />
      </div>

      <Card className="max-w-2xl p-5">
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <dl className="grid grid-cols-2 gap-2 rounded-xl bg-cream-100 p-3 text-center sm:grid-cols-4">
            {[
              ['Total dû', francs(dossier.total_du), 'text-navy-700'],
              ['Déjà versé', francs(dossier.total_paye), 'text-green-600'],
              ['Dette antérieure restante', francs(dossier.dette_anterieure_restante), 'text-red-500'],
              [t('eleves.financier.reste_scolarite_a_payer'), francs(dossier.reste_scolarite_a_payer), 'text-red-500'],
            ].map(([libelle, valeur, couleur]) => (
              <div key={libelle}>
                <dt className="text-[0.6875rem] uppercase tracking-wide text-navy-400">{libelle}</dt>
                <dd className={`text-sm font-bold tabular-nums ${couleur}`}>{valeur}</dd>
              </div>
            ))}
          </dl>

          {(peutGererRemises || peutGererDette) && (
            <div className="flex flex-wrap gap-2">
              {peutGererRemises && (
                <Button type="button" size="sm" variant="secondary" onClick={() => setGestionRemises(true)}>
                  <BadgePercent className="h-4 w-4" />
                  Gérer les remises
                </Button>
              )}
              {peutGererDette && (
                <Button type="button" size="sm" variant="secondary" onClick={() => setGestionDette(true)}>
                  <Pencil className="h-4 w-4" />
                  Gérer la dette antérieure
                </Button>
              )}
            </div>
          )}

          {versements.length > 0 && (
            <div className="rounded-xl border border-navy-100 bg-white p-3">
              <div className="mb-2 flex items-center justify-between gap-2">
                <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Historique des versements</span>
                <span className="text-[0.6875rem] text-navy-400">{versements.length} reçu{versements.length > 1 ? 's' : ''}</span>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full min-w-[31.25rem] text-left text-xs">
                  <thead className="bg-cream-50 text-[0.625rem] font-semibold uppercase tracking-wide text-navy-400">
                    <tr>
                      <th className="px-2 py-2">Date</th>
                      <th className="px-2 py-2">Heure</th>
                      <th className="px-2 py-2 text-right">Montant</th>
                      <th className="px-2 py-2">Mode</th>
                      <th className="px-2 py-2">Reçu</th>
                      <th className="px-2 py-2 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-navy-100">
                    {versements.map((v) => {
                      const date = v.date_versement ? new Date(`${v.date_versement}T${v.heure_versement ?? '00:00:00'}`) : null
                      return (
                        <tr key={v.id} className={`align-middle text-navy-700 ${v.annule ? 'opacity-60' : ''}`}>
                          <td className="px-2 py-2">{date ? date.toLocaleDateString('fr-FR') : '—'}</td>
                          <td className="px-2 py-2">{v.heure_versement ? v.heure_versement.slice(0, 5) : '—'}</td>
                          <td className={`whitespace-nowrap px-2 py-2 text-right font-semibold tabular-nums ${v.annule ? 'text-navy-400 line-through' : 'text-green-600'}`}>
                            {francs(v.montant)}
                          </td>
                          <td className="px-2 py-2">{v.mode}</td>
                          <td className="px-2 py-2 font-medium text-navy-800">
                            <div className="flex flex-col gap-0.5">
                              <span>
                                {v.numero_recu}
                                {v.annule && <span className="ml-1.5 text-xs font-semibold text-red-500">(annulé)</span>}
                              </span>
                              <span className="text-[0.625rem] font-normal text-navy-400">
                                Enregistré par {v.cree_par} le {formatDateHeure(v.created_at)}
                              </span>
                              {v.annule_par && (
                                <span className="text-[0.625rem] font-semibold text-red-500">
                                  Annulé par {v.annule_par} le {formatDateHeure(v.annule_le)}
                                  {v.motif_annulation ? ` — ${v.motif_annulation}` : ''}
                                </span>
                              )}
                            </div>
                          </td>
                          <td className="px-2 py-2">
                            <div className="flex justify-end gap-1">
                              <button type="button" title={`Afficher le reçu ${v.numero_recu}`} onClick={() => ouvrirDocument(`/versements/${v.id}/recu`)} className="rounded-lg p-2 text-navy-500 hover:bg-cream-100 hover:text-navy-800">
                                <Receipt className="h-4 w-4" />
                              </button>
                              {!v.annule && can('finance.annuler') && can('finance.encaisser') && (
                                <button type="button" title={`Modifier le paiement ${v.numero_recu}`} disabled={annulationEnCours !== null} onClick={() => setVersementACorriger(v)} className="rounded-lg p-2 text-navy-500 hover:bg-cream-100 hover:text-navy-800 disabled:opacity-50">
                                  <Pencil className="h-4 w-4" />
                                </button>
                              )}
                              {!v.annule && can('finance.annuler') && (
                                <button type="button" title={`Annuler le paiement ${v.numero_recu}`} disabled={annulationEnCours !== null} onClick={() => annulerPaiement(v)} className="rounded-lg p-2 text-red-500 hover:bg-red-50 disabled:opacity-50">
                                  <Ban className="h-4 w-4" />
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          <div className="rounded-xl border border-navy-100 bg-white p-3">
            <div className="mb-2 flex items-center justify-between gap-2">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Historique des remises</span>
              <span className="text-[0.6875rem] text-navy-400">
                {remisesEnChargement ? 'Chargement…' : `${remises?.length ?? 0} remise${remises?.length === 1 ? '' : 's'}`}
              </span>
            </div>
            {erreurRemises ? (
              <ErrorState />
            ) : remisesEnChargement ? (
              <Spinner />
            ) : remises?.length ? (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[27.5rem] text-left text-xs">
                  <thead className="bg-cream-50 text-[0.625rem] font-semibold uppercase tracking-wide text-navy-400">
                    <tr>
                      <th className="px-2 py-2">Date</th>
                      <th className="px-2 py-2 text-right">Montant</th>
                      <th className="px-2 py-2">Année scolaire</th>
                      <th className="px-2 py-2">Motif</th>
                      <th className="px-2 py-2">Accordée par</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-navy-100">
                    {remises.map((r) => (
                      <tr key={r.id} className="align-middle text-navy-700">
                        <td className="whitespace-nowrap px-2 py-2">{r.created_at ? formatDate(r.created_at) : '—'}</td>
                        <td className="whitespace-nowrap px-2 py-2 text-right font-semibold tabular-nums text-violet-600">{francs(r.montant)}</td>
                        <td className="px-2 py-2">{r.annee_scolaire ?? 'Année active'}</td>
                        <td className="px-2 py-2">{r.motif || '—'}</td>
                        <td className="px-2 py-2">{r.accorde_par || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="py-2 text-xs text-navy-400">Aucune remise enregistrée.</p>
            )}
          </div>

          <div className="rounded-xl border border-navy-100 bg-white p-3">
            <div className="mb-2 flex items-center justify-between gap-2">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Historique des dettes antérieures</span>
              <span className="text-[0.6875rem] text-navy-400">
                {dettesEnChargement ? 'Chargement…' : `${dettes?.length ?? 0} dette${dettes?.length === 1 ? '' : 's'}`}
              </span>
            </div>
            {erreurDettes ? (
              <ErrorState />
            ) : dettesEnChargement ? (
              <Spinner />
            ) : dettes?.length ? (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[27.5rem] text-left text-xs">
                  <thead className="bg-cream-50 text-[0.625rem] font-semibold uppercase tracking-wide text-navy-400">
                    <tr>
                      <th className="px-2 py-2">Date</th>
                      <th className="px-2 py-2 text-right">Montant</th>
                      <th className="px-2 py-2">Motif</th>
                      <th className="px-2 py-2">Situation</th>
                      <th className="px-2 py-2">Enregistrée par</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-navy-100">
                    {dettes.map((d) => (
                      <tr key={d.id} className="align-middle text-navy-700">
                        <td className="whitespace-nowrap px-2 py-2">{d.created_at ? formatDate(d.created_at) : '—'}</td>
                        <td className="whitespace-nowrap px-2 py-2 text-right font-semibold tabular-nums text-red-500">{francs(d.montant)}</td>
                        <td className="px-2 py-2">{d.motif || '—'}</td>
                        <td className="px-2 py-2">{d.imputee ? 'Reprise dans le dossier' : 'À reprendre'}</td>
                        <td className="px-2 py-2">{d.accorde_par || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="py-2 text-xs text-navy-400">Aucune dette antérieure enregistrée.</p>
            )}
          </div>

          {peutAccorderRemise && (
            <div
              className="flex flex-col gap-2 rounded-xl border border-navy-100 bg-white p-3"
              // Entrée dans ces champs accorde la remise : sans cela, elle
              // validerait le formulaire et encaisserait avant la remise.
              onKeyDown={(e) => {
                if (e.key !== 'Enter') return
                e.preventDefault()
                void accorderRemise()
              }}
            >
              <div className="flex items-center justify-between gap-2">
                <span className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-navy-500">
                  <BadgePercent className="h-3.5 w-3.5" />
                  Remise
                </span>
                {dossier.remise > 0 && (
                  <span className="text-[0.6875rem] text-navy-400">
                    Déjà accordée : <span className="font-semibold text-navy-600">{francs(dossier.remise)}</span>
                  </span>
                )}
              </div>
              <div className="grid items-end gap-2 sm:grid-cols-[minmax(0,10rem)_minmax(0,1fr)_auto]">
                <MontantInput label="Montant (F CFA)" placeholder="0" value={remise} onChange={setRemise} />
                <Input label="Motif" placeholder="Facultatif" value={motifRemise} onChange={(e) => setMotifRemise(e.target.value)} />
                <Button type="button" variant="secondary" onClick={accorderRemise} disabled={!remise || remiseEnCours}>
                  {remiseEnCours ? 'Application…' : 'Accorder'}
                </Button>
              </div>
              {!!remise && remise > dossier.reste_scolarite_a_payer && (
                <p className="text-xs font-semibold text-red-500">
                  La remise dépasse le reste de scolarité à payer ({francs(dossier.reste_scolarite_a_payer)}).
                </p>
              )}
            </div>
          )}

          <Controller
            name="montant"
            control={control}
            rules={{
              required: 'Saisissez le montant reçu.',
              min: { value: 1, message: 'Le montant doit être supérieur à zéro.' },
            }}
            render={({ field }) => (
              <MontantInput
                label="Montant reçu (F CFA)"
                autoFocus
                error={errors.montant?.message}
                value={field.value}
                onChange={field.onChange}
                onBlur={field.onBlur}
              />
            )}
          />

          {montant > 0 && (
            <p className="-mt-2 text-xs text-navy-500">
              {resteApres > 0 ? (
                <>Restera à payer : <span className="font-semibold">{francs(resteApres)}</span></>
              ) : resteApres === 0 ? (
                <span className="font-semibold text-green-600">Le dossier sera soldé.</span>
              ) : (
                <span className="font-semibold text-blue-600">
                  Trop-perçu de {francs(-resteApres)} — enregistré en avance.
                </span>
              )}
            </p>
          )}

          {rubriques.length > 0 && (
            <div className="flex flex-col gap-2">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
                Répartition par rubrique
              </span>

              <div className="overflow-x-auto rounded-xl border border-navy-100">
                <table className="w-full min-w-[27.5rem] text-xs">
                  <thead className="bg-cream-50 text-[0.625rem] font-semibold uppercase tracking-wide text-navy-400">
                    <tr>
                      <th className="px-2.5 py-2 text-left">Rubrique</th>
                      <th className="px-2.5 py-2 text-right">Payé</th>
                      <th className="px-2.5 py-2 text-right">Reste</th>
                      <th className="px-2.5 py-2 text-right">Montant alloué</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-navy-50">
                    {rubriques.map((r, i) => (
                      <tr key={`${r.cle}:${r.dossier_frais_annexe_id ?? ''}`}>
                        <td className="px-2.5 py-1.5 font-medium text-navy-800">{r.libelle}</td>
                        <td className="px-2.5 py-1.5 text-right tabular-nums text-green-600">{francs(r.montant_paye)}</td>
                        <td className="px-2.5 py-1.5 text-right tabular-nums text-red-500">{francs(r.reste)}</td>
                        <td className="px-2.5 py-1.5 text-right">
                          <MontantAlloueInput
                            valeur={allocations[i] ?? 0}
                            onChange={(v) => {
                              setAllocationsModifiees(true)
                              setAllocations((a) => {
                                const suivant = [...a]
                                suivant[i] = v
                                return suivant
                              })
                            }}
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot>
                    <tr className="border-t border-navy-100 bg-cream-50/70 font-semibold">
                      <td className="px-2.5 py-1.5 text-navy-700">Total réparti</td>
                      <td />
                      <td />
                      <td className={`px-2.5 py-1.5 text-right tabular-nums ${totalAlloue === montant ? 'text-green-600' : 'text-red-500'}`}>
                        {francs(totalAlloue)}
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>

              {totalAlloue !== montant && (
                <p className="text-xs font-semibold text-red-500">
                  La répartition ({francs(totalAlloue)}) doit correspondre au montant reçu.
                </p>
              )}
            </div>
          )}

          <div className="grid gap-3 sm:grid-cols-2">
            <Select label="Mode de paiement" {...register('mode')}>
              {MODES.map((m) => (
                <option key={m.valeur} value={m.valeur}>
                  {m.libelle}
                </option>
              ))}
            </Select>
            <Input label="Date" type="date" {...register('date_versement')} />
          </div>

          <Input
            label="Référence (Mobile Money, chèque…)"
            placeholder="Facultatif"
            {...register('reference_externe')}
          />
          <Input label="Note" placeholder="Facultatif" {...register('note')} />

          <CanauxNotificationField value={canaux} onChange={setCanaux} />

          <div className="flex flex-col gap-2">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Sortie du reçu</span>
            <div className="grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setSortieRecu('preview')}
                className={`flex min-h-[4.5rem] items-center gap-3 rounded-xl border px-3 py-2 text-left text-sm transition-colors ${sortieRecu === 'preview'
                    ? 'border-navy-700 bg-navy-50 text-navy-900 ring-2 ring-navy-100'
                    : 'border-navy-100 bg-white text-navy-600 hover:border-navy-200'
                  }`}
              >
                <Eye className="h-5 w-5 flex-none" />
                <span>
                  <span className="block font-semibold">Afficher l'aperçu</span>
                  <span className="block text-xs text-navy-400">Ouvrir le reçu avant impression</span>
                </span>
              </button>
              <button
                type="button"
                onClick={() => setSortieRecu('print')}
                className={`flex min-h-[4.5rem] items-center gap-3 rounded-xl border px-3 py-2 text-left text-sm transition-colors ${sortieRecu === 'print'
                    ? 'border-navy-700 bg-navy-50 text-navy-900 ring-2 ring-navy-100'
                    : 'border-navy-100 bg-white text-navy-600 hover:border-navy-200'
                  }`}
              >
                <Printer className="h-5 w-5 flex-none" />
                <span>
                  <span className="block font-semibold">Imprimer directement</span>
                  <span className="block text-xs text-navy-400">Lancer la boîte d'impression</span>
                </span>
              </button>
            </div>
          </div>

          <p className="flex items-start gap-2 rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
            <Receipt className="mt-0.5 h-3.5 w-3.5 flex-none" />
            {sortieRecu === 'print'
              ? "Le reçu sera envoyé directement à l'impression après l'enregistrement."
              : "Le reçu s'ouvrira automatiquement à l'enregistrement, prêt à imprimer."}
          </p>

          {serverError && <p className="text-sm text-red-500">{serverError}</p>}

          <div className="mt-1 flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={retour}>
              Annuler
            </Button>
            <Button type="submit" disabled={submitting || !ventilationValide}>
              <Wallet className="h-4 w-4" />
              {submitting ? 'Enregistrement…' : 'Encaisser'}
            </Button>
          </div>
        </form>
      </Card>

      <Card className="max-w-2xl p-5">
        <button
          type="button"
          onClick={() => setMoratoireOuvert((v) => !v)}
          className="flex w-full items-center justify-between text-sm font-semibold text-navy-700"
        >
          <span className="flex items-center gap-2">
            <Clock className="h-4 w-4" />
            Moratoire
          </span>
          {moratoireOuvert ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
        </button>
        {moratoireOuvert && (
          <div className="mt-4">
            <SectionMoratoires eleveId={dossier.eleve.id} onChange={() => { }} />
          </div>
        )}
      </Card>

      {gestionRemises && (
        <GestionRemiseModal eleveId={dossier.eleve.id} eleveNom={dossier.eleve.nom_complet} onClose={() => setGestionRemises(false)} onChange={rafraichirDossier} />
      )}
      {gestionDette && (
        <GestionDetteAnterieureModal eleveId={dossier.eleve.id} eleveNom={dossier.eleve.nom_complet} resteDette={dossier.dette_anterieure_restante} onClose={() => setGestionDette(false)} onChange={rafraichirDossier} />
      )}
      {versementACorriger && (
        <CorrigerVersementModal versement={versementACorriger} onClose={() => setVersementACorriger(null)} onCorrige={() => { setVersementACorriger(null); void rafraichirDossier() }} />
      )}
    </div>
  )
}

/** Montant alloué à une rubrique dans le tableau de répartition : milliers groupés, en composant à part pour que chaque ligne porte son propre état de saisie. */
function MontantAlloueInput({ valeur, onChange }: { valeur: number; onChange: (valeur: number) => void }) {
  const champ = useMontantSaisie(valeur, onChange)
  return (
    <input
      {...champ}
      className="w-24 rounded-lg border border-navy-200 px-1.5 py-1 text-right text-xs tabular-nums shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
    />
  )
}
