import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Banknote, HandCoins, Plus, Undo2, Users, Wallet } from 'lucide-react'
import {
  accorderAvance,
  annulerAvance,
  fetchAvancesSalaire,
  francs,
  rembourserAvance,
  type AvanceSalaire,
  type ModeRemboursement,
  type StatutAvance,
} from '@/features/finance/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { StatCard } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
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
  const [showForm, setShowForm] = useState(false)
  const [avanceARembourser, setAvanceARembourser] = useState<AvanceSalaire | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['avances-salaire', statut],
    queryFn: () => fetchAvancesSalaire({ statut: statut || undefined }),
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

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data?.avances ?? []}
          cleLigne={(a) => a.id}
          placeholderRecherche={t('finance.search_avance')}
          messageVide={t('finance.empty_avance')}
          largeurMin={780}
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

interface FormAccorder {
  personnel_id: number
  montant: number
  date_avance: string
  motif: string
}

function AccorderAvanceModal({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const [serverError, setServerError] = useState<string | null>(null)
  const { data: personnels } = useQuery({ queryKey: ['personnels', 'avances'], queryFn: () => fetchPersonnels({ per_page: 500 }) })

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<FormAccorder>({ defaultValues: { date_avance: new Date().toISOString().slice(0, 10) } })

  const onSubmit = async (values: FormAccorder) => {
    setServerError(null)
    try {
      await accorderAvance({
        personnel_id: Number(values.personnel_id),
        montant: Number(values.montant),
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

        <Input
          label="Montant (F CFA)"
          type="number"
          min={1}
          error={errors.montant?.message}
          {...register('montant', { required: 'Saisissez le montant.', min: { value: 1, message: 'Le montant doit être supérieur à zéro.' } })}
        />

        <Input label="Date" type="date" {...register('date_avance', { required: true })} />
        <Input label="Motif" placeholder="Facultatif" {...register('motif')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting}>
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
