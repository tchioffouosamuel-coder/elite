import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Banknote, Check, HandCoins, Plus, Undo2, Users, Wallet, X } from 'lucide-react'
import {
  accorderAvance,
  annulerAvance,
  fetchAvancesSalaire,
  fetchDemandesAvanceSalaire,
  fetchPlafondAvance,
  francs,
  rejeterDemandeAvance,
  rembourserAvance,
  validerDemandeAvance,
  type AvanceSalaire,
  type DemandeAvanceSalaire,
  type ModeRemboursement,
  type StatutAvance,
  type StatutDemandeAvance,
} from '@/features/finance/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card, StatCard } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { Tabs } from '@/shared/ui/Tabs'
import { Modal } from '@/shared/ui/Modal'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TONE_STATUT: Record<StatutAvance, 'green' | 'gold' | 'red' | 'neutral'> = {
  en_cours: 'gold',
  partielle: 'gold',
  remboursee: 'green',
  annulee: 'neutral',
}

const LIBELLES_STATUT: Record<StatutAvance, string> = {
  en_cours: 'En cours',
  partielle: 'Partielle',
  remboursee: 'Remboursée',
  annulee: 'Annulée',
}

const STATUTS: { valeur: StatutAvance | ''; libelle: string }[] = [
  { valeur: '', libelle: 'Tous les statuts' },
  { valeur: 'en_cours', libelle: 'En cours' },
  { valeur: 'partielle', libelle: 'Partielle' },
  { valeur: 'remboursee', libelle: 'Remboursée' },
  { valeur: 'annulee', libelle: 'Annulée' },
]

export function AvancesSalairePage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [statut, setStatut] = useState<StatutAvance | ''>('')
  const [onglet, setOnglet] = useState<'avances' | 'demandes'>('avances')
  const [showForm, setShowForm] = useState(false)
  const [avanceARembourser, setAvanceARembourser] = useState<AvanceSalaire | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['avances-salaire', statut],
    queryFn: () => fetchAvancesSalaire({ statut: statut || undefined }),
  })

  // Compteur de l'onglet. Même clé que la liste filtrée sur « en attente » :
  // React Query mutualise la requête au lieu d'en lancer une seconde.
  const { data: enAttente } = useQuery({
    queryKey: ['demandes-avance-salaire', 'en_attente'],
    queryFn: () => fetchDemandesAvanceSalaire('en_attente'),
    enabled: can('finance.paie'),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['avances-salaire'] })

  const annuler = async (avance: AvanceSalaire) => {
    const ok = await confirmer({
      titre: `Annuler l'avance de ${avance.personnel.nom_complet} ?`,
      message: `${francs(avance.montant)} ne seront plus dus par l'employé. Cette avance n'a encore reçu aucun remboursement.`,
      action: "Annuler l'avance",
    })
    if (!ok) return

    try {
      await annulerAvance(avance.id, 'Annulation depuis la gestion des avances')
      succes('Avance annulée.')
      invalidate()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    }
  }

  const colonnes: Colonne<AvanceSalaire>[] = [
    {
      cle: 'personnel',
      entete: 'Employé',
      valeur: (a) => `${a.personnel.nom_complet} ${a.personnel.matricule ?? ''}`,
      cellule: (a) => (
        <div className="min-w-0">
          <div className="truncate font-semibold text-navy-900">{a.personnel.nom_complet}</div>
          <div className="text-xs text-navy-400">{a.personnel.fonction ?? '—'}</div>
        </div>
      ),
    },
    {
      cle: 'school',
      entete: t('classes.ecole'),
      valeur: (a) => a.school?.name,
      cellule: (a) => <span className="text-navy-600">{a.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'montant',
      entete: 'Montant',
      valeur: (a) => a.montant,
      cellule: (a) => <span className="tabular-nums font-semibold">{francs(a.montant)}</span>,
    },
    {
      cle: 'echeancier',
      entete: 'Échéancier',
      valeur: (a) => a.mensualite ?? 0,
      cellule: (a) =>
        a.nombre_mois ? (
          <div className="min-w-0">
            <div className="tabular-nums font-semibold text-navy-800">{francs(a.mensualite ?? 0)}/mois</div>
            <div className="text-xs text-navy-400">sur {a.nombre_mois} mois</div>
          </div>
        ) : (
          // Les avances accordées avant l'échéancier n'en portent pas : le
          // remboursement y reste libre, saisi au fil de l'eau.
          <span className="text-navy-300">—</span>
        ),
      masquerMobile: true,
    },
    {
      cle: 'date',
      entete: 'Date',
      valeur: (a) => a.date_avance,
      cellule: (a) => new Date(a.date_avance).toLocaleDateString('fr-FR'),
      masquerMobile: true,
    },
    {
      cle: 'rembourse',
      entete: 'Remboursé',
      valeur: (a) => a.montant_rembourse,
      cellule: (a) => <span className="tabular-nums text-green-600">{francs(a.montant_rembourse)}</span>,
    },
    {
      cle: 'solde',
      entete: 'Reste',
      valeur: (a) => a.solde,
      cellule: (a) => (
        <span className={a.solde > 0 ? 'font-semibold tabular-nums text-red-500' : 'tabular-nums text-navy-300'}>
          {a.solde > 0 ? francs(a.solde) : '—'}
        </span>
      ),
    },
    {
      cle: 'statut',
      entete: 'Statut',
      valeur: (a) => a.statut,
      cellule: (a) => <Badge tone={TONE_STATUT[a.statut]}>{LIBELLES_STATUT[a.statut]}</Badge>,
    },
    ...(can('finance.paie')
      ? [
          {
            cle: 'actions',
            entete: '',
            cellule: (a: AvanceSalaire) => (
              <div className="flex justify-end gap-1.5">
                {!a.annule && a.solde > 0 && (
                  <Button size="sm" onClick={() => setAvanceARembourser(a)}>
                    <Undo2 className="h-3.5 w-3.5" />
                    Rembourser
                  </Button>
                )}
                {!a.annule && a.montant_rembourse === 0 && (
                  <Button size="sm" variant="danger" onClick={() => annuler(a)}>
                    Annuler
                  </Button>
                )}
              </div>
            ),
          } satisfies Colonne<AvanceSalaire>,
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Avances sur salaire"
        sousTitre="Suivi des avances accordées au personnel et de leurs remboursements."
        icon={HandCoins}
        actions={
          can('finance.paie') && (
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              Accorder une avance
            </Button>
          )
        }
      />

      {data && (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard label="Employés concernés" value={data.totaux.effectif} icon={Users} accent="navy" />
          <StatCard label="Total accordé" value={francs(data.totaux.total_accorde)} icon={Banknote} accent="gold" />
          <StatCard label="Total remboursé" value={francs(data.totaux.total_rembourse)} icon={Wallet} accent="green" />
          <StatCard label="Reste à recouvrer" value={francs(data.totaux.total_restant)} icon={HandCoins} accent="red" />
        </div>
      )}

      {/* Les demandes du personnel n'atteignent le registre qu'une fois
          validées : deux états de la même matière, deux onglets. */}
      {can('finance.paie') && (
        <Tabs
          active={onglet}
          onChange={(cle) => setOnglet(cle as 'avances' | 'demandes')}
          tabs={[
            { key: 'avances', label: 'Avances accordées' },
            {
              key: 'demandes',
              label: enAttente?.length ? `Demandes (${enAttente.length})` : 'Demandes',
            },
          ]}
        />
      )}

      {onglet === 'demandes' && can('finance.paie') ? (
        <DemandesAvanceSection onTraitee={invalidate} />
      ) : isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data?.avances ?? []}
          cleLigne={(a) => a.id}
          placeholderRecherche={t('finance.search_avance')}
          messageVide={t('finance.empty_avance')}
          largeurMin={900}
          outils={
            <Select value={statut} onChange={(e) => setStatut(e.target.value as StatutAvance | '')}>
              {STATUTS.map((s) => (
                <option key={s.valeur} value={s.valeur}>
                  {s.libelle}
                </option>
              ))}
            </Select>
          }
        />
      )}

      {showForm && (
        <AccorderAvanceModal
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}

      {avanceARembourser && (
        <RembourserAvanceModal
          avance={avanceARembourser}
          onClose={() => setAvanceARembourser(null)}
          onSaved={() => {
            setAvanceARembourser(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}

const TONE_DEMANDE: Record<StatutDemandeAvance, 'green' | 'gold' | 'red'> = {
  en_attente: 'gold',
  validee: 'green',
  rejetee: 'red',
}

const LIBELLES_DEMANDE: Record<StatutDemandeAvance, string> = {
  en_attente: 'En attente',
  validee: 'Validée',
  rejetee: 'Rejetée',
}

/**
 * File d'attente des demandes soumises par le personnel depuis « Mes avances ».
 * Valider crée l'avance réelle avec son échéancier ; rejeter la clôt avec un
 * motif que l'employé retrouve dans son espace.
 */
function DemandesAvanceSection({ onTraitee }: { onTraitee: () => void }) {
  const queryClient = useQueryClient()
  const [statut, setStatut] = useState<StatutDemandeAvance | ''>('en_attente')
  const [demandeARejeter, setDemandeARejeter] = useState<DemandeAvanceSalaire | null>(null)
  const [traitementId, setTraitementId] = useState<number | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['demandes-avance-salaire', statut || 'toutes'],
    queryFn: () => fetchDemandesAvanceSalaire(statut),
  })

  const invalider = () => {
    queryClient.invalidateQueries({ queryKey: ['demandes-avance-salaire'] })
    // Une validation crée une avance : le registre et les totaux changent aussi.
    onTraitee()
  }

  const valider = async (d: DemandeAvanceSalaire) => {
    const ok = await confirmer({
      titre: `Accorder l'avance à ${d.personnel?.nom_complet} ?`,
      message: `${francs(d.montant)} seront accordés, remboursables en ${d.nombre_mois} mois à ${francs(d.mensualite)} par mois.`,
      action: 'Valider la demande',
    })
    if (!ok) return

    setTraitementId(d.id)
    try {
      await validerDemandeAvance(d.id)
      succes('Demande validée, avance accordée.')
      invalider()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setTraitementId(null)
    }
  }

  if (isLoading) return <Spinner />

  return (
    <div className="flex flex-col gap-3">
      <div className="flex justify-end">
        <Select value={statut} onChange={(e) => setStatut(e.target.value as StatutDemandeAvance | '')} className="w-48">
          <option value="en_attente">En attente</option>
          <option value="validee">Validées</option>
          <option value="rejetee">Rejetées</option>
          <option value="">Toutes</option>
        </Select>
      </div>

      {!data?.length ? (
        <EmptyState label="Aucune demande dans cet état." />
      ) : (
        data.map((d) => {
          // Le serveur refuse déjà l'échéancier hors plafond ; l'afficher ici
          // évite à l'administrateur de valider pour rien.
          const horsPlafond = d.plafond_mensualite !== null && d.mensualite > d.plafond_mensualite

          return (
            <Card key={d.id}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="font-display text-base font-bold text-navy-900">{d.personnel?.nom_complet ?? '—'}</p>
                  <p className="mt-0.5 text-xs text-navy-400">
                    {d.personnel?.fonction ?? '—'} · demandée le {new Date(d.created_at).toLocaleDateString('fr-FR')}
                  </p>
                </div>
                <Badge tone={TONE_DEMANDE[d.statut]}>{LIBELLES_DEMANDE[d.statut]}</Badge>
              </div>

              <div className="mt-3 grid gap-3 sm:grid-cols-3">
                <div>
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-navy-400">Montant demandé</p>
                  <p className="tabular-nums font-semibold text-navy-900">{francs(d.montant)}</p>
                </div>
                <div>
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-navy-400">Échéancier</p>
                  <p className="tabular-nums font-semibold text-navy-900">
                    {d.nombre_mois} mois · {francs(d.mensualite)}/mois
                  </p>
                </div>
                <div>
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-navy-400">Plafond (50% du brut)</p>
                  <p className={horsPlafond ? 'tabular-nums font-semibold text-red-600' : 'tabular-nums text-navy-600'}>
                    {d.plafond_mensualite === null ? 'Rémunération non renseignée' : `${francs(d.plafond_mensualite)}/mois`}
                  </p>
                </div>
              </div>

              {d.motif && <p className="mt-3 rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-600">{d.motif}</p>}

              {horsPlafond && (
                <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                  La mensualité dépasse 50% du salaire brut : la validation sera refusée tant que l'employé n'aura pas
                  allongé la durée ou réduit le montant.
                </p>
              )}

              {d.statut === 'rejetee' && d.motif_rejet && (
                <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                  <b>Motif du rejet :</b> {d.motif_rejet}
                </p>
              )}

              {d.statut === 'en_attente' && (
                <div className="mt-3 flex justify-end gap-2">
                  <Button variant="secondary" onClick={() => setDemandeARejeter(d)} disabled={traitementId === d.id}>
                    <X className="h-4 w-4" />
                    Rejeter
                  </Button>
                  <Button onClick={() => valider(d)} disabled={traitementId === d.id}>
                    <Check className="h-4 w-4" />
                    Valider
                  </Button>
                </div>
              )}
            </Card>
          )
        })
      )}

      {demandeARejeter && (
        <RejeterDemandeModal
          demande={demandeARejeter}
          onClose={() => setDemandeARejeter(null)}
          onRejetee={() => {
            setDemandeARejeter(null)
            invalider()
          }}
        />
      )}
    </div>
  )
}

function RejeterDemandeModal({
  demande,
  onClose,
  onRejetee,
}: {
  demande: DemandeAvanceSalaire
  onClose: () => void
  onRejetee: () => void
}) {
  const [motif, setMotif] = useState('')
  const [enCours, setEnCours] = useState(false)

  const rejeter = async () => {
    if (motif.trim().length < 3) return
    setEnCours(true)
    try {
      await rejeterDemandeAvance(demande.id, motif.trim())
      succes('Demande rejetée.')
      onRejetee()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  return (
    <Modal title={`Rejeter la demande — ${demande.personnel?.nom_complet ?? ''}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <p className="rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
          Le motif sera visible par l'employé dans son espace « Mes avances ».
        </p>

        <Textarea label="Motif du rejet" value={motif} onChange={(e) => setMotif(e.target.value)} />

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button variant="danger" onClick={rejeter} disabled={enCours || motif.trim().length < 3}>
            Confirmer le rejet
          </Button>
        </div>
      </div>
    </Modal>
  )
}

interface FormAccorder {
  personnel_id: number
  montant: number
  nombre_mois: number
  date_avance: string
  motif: string
}

function AccorderAvanceModal({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const [serverError, setServerError] = useState<string | null>(null)
  const { data: personnels } = useQuery({ queryKey: ['personnels', 'avances'], queryFn: () => fetchPersonnels({ per_page: 500 }) })

  const {
    register,
    handleSubmit,
    watch,
    formState: { isSubmitting, errors },
  } = useForm<FormAccorder>({ defaultValues: { date_avance: new Date().toISOString().slice(0, 10), nombre_mois: 1 } })

  const personnelId = Number(watch('personnel_id')) || 0
  const montant = Number(watch('montant')) || 0
  const nombreMois = Number(watch('nombre_mois')) || 1
  const mensualite = nombreMois > 0 ? Math.ceil(montant / nombreMois) : 0

  // Le plafond dépend de l'agent choisi : on le charge dès la sélection pour
  // que l'échéancier se corrige dans le formulaire, pas après un refus 422.
  const { data: plafond } = useQuery({
    queryKey: ['avance-plafond', personnelId],
    queryFn: () => fetchPlafondAvance(personnelId),
    enabled: personnelId > 0,
  })

  const horsPlafond = plafond?.plafond_mensualite != null && mensualite > plafond.plafond_mensualite

  const onSubmit = async (values: FormAccorder) => {
    setServerError(null)
    try {
      await accorderAvance({
        personnel_id: Number(values.personnel_id),
        montant: Number(values.montant),
        nombre_mois: Number(values.nombre_mois),
        date_avance: values.date_avance,
        motif: values.motif || null,
      })
      succes('Avance accordée.')
      onSaved()
    } catch (e) {
      setServerError((e as ApiError).message)
    }
  }

  return (
    <Modal title="Accorder une avance" onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select label="Employé" error={errors.personnel_id?.message} {...register('personnel_id', { required: 'Choisissez un employé.' })}>
          <option value="">—</option>
          {personnels?.map((p) => (
            <option key={p.id} value={p.id}>
              {p.nom_complet}
            </option>
          ))}
        </Select>

        <div className="grid grid-cols-2 gap-3">
          <Input
            label="Montant (F CFA)"
            type="number"
            min={1}
            error={errors.montant?.message}
            {...register('montant', { required: 'Saisissez le montant.', min: { value: 1, message: 'Le montant doit être supérieur à zéro.' } })}
          />
          <Input
            label="Nombre de mois"
            type="number"
            min={1}
            max={36}
            error={errors.nombre_mois?.message}
            {...register('nombre_mois', {
              required: 'Requis.',
              min: { value: 1, message: 'Au moins 1 mois.' },
              max: { value: 36, message: '36 mois maximum.' },
            })}
          />
        </div>

        {/* Calendrier de remboursement : ce qui sera retenu chaque mois, et la
            borne des 50% du brut que la retenue ne peut franchir. */}
        {personnelId > 0 && plafond?.plafond_mensualite == null && (
          <p className="rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-800">
            Aucune rémunération n'est enregistrée pour cet employé : le plafond de remboursement ne peut pas être calculé
            et l'avance sera refusée.
          </p>
        )}

        {montant > 0 && nombreMois > 0 && (
          <div
            className={
              horsPlafond
                ? 'rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700'
                : 'rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-500'
            }
          >
            <p>
              Retenue mensuelle sur salaire :{' '}
              <span className={horsPlafond ? 'font-semibold' : 'font-semibold text-navy-800'}>{francs(mensualite)}</span>{' '}
              pendant {nombreMois} mois.
            </p>
            {plafond?.plafond_mensualite != null && (
              <p className="mt-0.5">
                Salaire brut {francs(plafond.salaire_brut ?? 0)} — plafond 50% :{' '}
                <span className="font-semibold">{francs(plafond.plafond_mensualite)}</span>/mois.
              </p>
            )}
            {horsPlafond && (
              <p className="mt-0.5 font-semibold">
                Au-delà du plafond : allongez la durée ou réduisez le montant.
              </p>
            )}
          </div>
        )}

        <Input label="Date" type="date" {...register('date_avance', { required: true })} />
        <Input label="Motif" placeholder="Facultatif" {...register('motif')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting || horsPlafond}>
            Accorder
          </Button>
        </div>
      </form>
    </Modal>
  )
}

interface FormRembourser {
  montant: number
  date_remboursement: string
  mode: ModeRemboursement
  note: string
}

function RembourserAvanceModal({
  avance,
  onClose,
  onSaved,
}: {
  avance: AvanceSalaire
  onClose: () => void
  onSaved: () => void
}) {
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<FormRembourser>({
    defaultValues: { montant: avance.solde, date_remboursement: new Date().toISOString().slice(0, 10), mode: 'retenue_salaire' },
  })

  const onSubmit = async (values: FormRembourser) => {
    setServerError(null)
    try {
      await rembourserAvance(avance.id, {
        montant: Number(values.montant),
        date_remboursement: values.date_remboursement,
        mode: values.mode,
        note: values.note || null,
      })
      succes('Remboursement enregistré.')
      onSaved()
    } catch (e) {
      setServerError((e as ApiError).message)
    }
  }

  return (
    <Modal title={`Rembourser — ${avance.personnel.nom_complet}`} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <p className="rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
          Solde restant : <span className="font-semibold text-navy-800">{francs(avance.solde)}</span>
        </p>

        <Input
          label="Montant remboursé (F CFA)"
          type="number"
          min={1}
          max={avance.solde}
          error={errors.montant?.message}
          {...register('montant', {
            required: 'Saisissez le montant.',
            min: { value: 1, message: 'Le montant doit être supérieur à zéro.' },
            max: { value: avance.solde, message: 'Ce montant dépasse le solde restant.' },
          })}
        />

        <Select label="Mode" {...register('mode')}>
          <option value="retenue_salaire">Retenue sur salaire</option>
          <option value="versement_direct">Versement direct</option>
        </Select>

        <Input label="Date" type="date" {...register('date_remboursement', { required: true })} />
        <Input label="Note" placeholder="Facultatif" {...register('note')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            Enregistrer
          </Button>
        </div>
      </form>
    </Modal>
  )
}
