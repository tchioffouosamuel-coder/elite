import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Building2, ClipboardList, Pencil, Plus, School, ShieldCheck, Trash2, Users } from 'lucide-react'
import {
  creerAssuranceScolaire,
  definirApee,
  definirConseilEcole,
  definirMontantPercu,
  fetchApee,
  fetchAssurancesScolaires,
  fetchBudgetFonctionnement,
  fetchConseilEcole,
  modifierAssuranceScolaire,
  supprimerAssuranceScolaire,
  type AssuranceScolaire,
  type AssuranceScolairePayload,
  type Apee,
  type ConseilEcole,
  type LigneBudgetFonctionnement,
} from '@/features/finance/rentreeApi'
import { fetchAnneesScolaires } from '@/features/session/api'
import { francs, type RubriqueBudgetFonctionnement } from '@/features/finance/api'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, MontantInput } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { useAuthStore } from '@/shared/store/authStore'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const LIBELLES_RUBRIQUE: Record<RubriqueBudgetFonctionnement, string> = {
  primes_rendement: 'Primes de rendement',
  projet_ecole: "Projet d'école",
  fenassco: 'FENASSCO',
  fonctionnement: 'Fonctionnement',
  evaluation: 'Évaluation',
}

export function RentreeScolairePage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const { data: annees } = useQuery({ queryKey: ['annees-scolaires'], queryFn: fetchAnneesScolaires })
  const anneeActive = annees?.find((a) => a.is_active) ?? annees?.[0]

  const { data: budget, isLoading: chargeBudget } = useQuery({
    queryKey: ['budget-fonctionnement', anneeActive?.id],
    queryFn: () => fetchBudgetFonctionnement(anneeActive?.id),
    enabled: !!anneeActive,
  })
  const { data: assurances, isLoading: chargeAssurances } = useQuery({
    queryKey: ['assurances-scolaires', anneeActive?.id],
    queryFn: () => fetchAssurancesScolaires(anneeActive?.id),
    enabled: !!anneeActive,
  })
  const { data: conseil } = useQuery({
    queryKey: ['conseil-ecole', anneeActive?.id],
    queryFn: () => fetchConseilEcole(anneeActive?.id),
    enabled: !!anneeActive,
  })
  const { data: apee } = useQuery({
    queryKey: ['apee', anneeActive?.id],
    queryFn: () => fetchApee(anneeActive?.id),
    enabled: !!anneeActive,
  })

  const [rubriqueEnEdition, setRubriqueEnEdition] = useState<RubriqueBudgetFonctionnement | null>(null)
  const [assuranceEnEdition, setAssuranceEnEdition] = useState<AssuranceScolaire | null>(null)
  const [showAssuranceForm, setShowAssuranceForm] = useState(false)

  const invalidateBudget = () => queryClient.invalidateQueries({ queryKey: ['budget-fonctionnement'] })
  const invalidateAssurances = () => queryClient.invalidateQueries({ queryKey: ['assurances-scolaires'] })

  const supprimerUneAssurance = async (assurance: AssuranceScolaire) => {
    const confirme = await confirmerSuppression(t('rentree.confirm_delete', { nom: assurance.libelle }))
    if (!confirme) return
    try {
      await supprimerAssuranceScolaire(assurance.id)
      succes(t('rentree.deleted'))
      invalidateAssurances()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const colonnesBudget: Colonne<LigneBudgetFonctionnement>[] = [
    { cle: 'rubrique', entete: t('rentree.rubrique_col'), valeur: (l) => LIBELLES_RUBRIQUE[l.rubrique], cellule: (l) => <span className="font-semibold text-navy-900">{LIBELLES_RUBRIQUE[l.rubrique]}</span> },
    { cle: 'percu', entete: t('rentree.montant_percu_col'), valeur: (l) => l.montant_percu, cellule: (l) => <span className="tabular-nums">{francs(l.montant_percu)}</span> },
    { cle: 'depense', entete: t('rentree.montant_depense_col'), valeur: (l) => l.montant_depense, cellule: (l) => <span className="tabular-nums">{francs(l.montant_depense)}</span> },
    {
      cle: 'reste',
      entete: t('rentree.reste_col'),
      valeur: (l) => l.reste,
      cellule: (l) => <span className={`tabular-nums font-semibold ${l.reste < 0 ? 'text-red-600' : 'text-green-600'}`}>{francs(l.reste)}</span>,
    },
    ...(can('finance.manage')
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (l: LigneBudgetFonctionnement) => (
              <button
                title={t('common.edit')}
                onClick={() => setRubriqueEnEdition(l.rubrique)}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
              >
                <Pencil className="h-4 w-4" />
              </button>
            ),
          } satisfies Colonne<LigneBudgetFonctionnement>,
        ]
      : []),
  ]

  const colonnesAssurances: Colonne<AssuranceScolaire>[] = [
    { cle: 'libelle', entete: t('rentree.niveau_col'), valeur: (a) => a.libelle, cellule: (a) => <span className="font-semibold text-navy-900">{a.libelle}</span> },
    { cle: 'effectif', entete: t('rentree.effectif_col'), valeur: (a) => a.effectif, cellule: (a) => <span className="tabular-nums">{a.effectif}</span> },
    { cle: 'assureur', entete: t('rentree.assureur_col'), valeur: (a) => a.nom_assureur, cellule: (a) => a.nom_assureur ?? '—' },
    { cle: 'police', entete: t('rentree.police_col'), valeur: (a) => a.numero_police, cellule: (a) => a.numero_police ?? '—' },
    ...(can('finance.manage')
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (a: AssuranceScolaire) => (
              <div className="flex items-center gap-1">
                <button
                  title={t('common.edit')}
                  onClick={() => {
                    setAssuranceEnEdition(a)
                    setShowAssuranceForm(true)
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                >
                  <Pencil className="h-4 w-4" />
                </button>
                <button
                  title={t('common.delete')}
                  onClick={() => supprimerUneAssurance(a)}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ),
          } satisfies Colonne<AssuranceScolaire>,
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('rentree.title')}
        sousTitre={t('rentree.subtitle')}
        icon={ClipboardList}
      />

      {!anneeActive ? (
        <Spinner />
      ) : (
        <>
          <Card>
            <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
              <Building2 className="h-4 w-4" />
              {t('rentree.section_budget')}
            </h2>
            {chargeBudget ? <Spinner /> : <DataTable colonnes={colonnesBudget} lignes={budget ?? []} cleLigne={(l) => l.rubrique} messageVide="—" largeurMin={560} />}
          </Card>

          <Card>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
                <ShieldCheck className="h-4 w-4" />
                {t('rentree.section_assurances')}
              </h2>
              {can('finance.manage') && (
                <Button
                  onClick={() => {
                    setAssuranceEnEdition(null)
                    setShowAssuranceForm(true)
                  }}
                >
                  <Plus className="h-4 w-4" />
                  {t('rentree.add')}
                </Button>
              )}
            </div>
            {chargeAssurances ? (
              <Spinner />
            ) : (
              <DataTable colonnes={colonnesAssurances} lignes={assurances ?? []} cleLigne={(a) => a.id} messageVide={t('rentree.empty_assurances')} largeurMin={560} />
            )}
          </Card>

          <div className="grid gap-5 lg:grid-cols-2">
            <Card>
              <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
                <Users className="h-4 w-4" />
                {t('rentree.section_conseil')}
              </h2>
              {conseil && <ConseilEcoleForm conseil={conseil} anneeScolaireId={anneeActive.id} peutModifier={can('finance.manage')} />}
            </Card>

            <Card>
              <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
                <School className="h-4 w-4" />
                {t('rentree.section_apee')}
              </h2>
              {apee && <ApeeForm apee={apee} anneeScolaireId={anneeActive.id} peutModifier={can('finance.manage')} />}
            </Card>
          </div>
        </>
      )}

      {rubriqueEnEdition && anneeActive && (
        <MontantPercuModal
          rubrique={rubriqueEnEdition}
          ligne={budget?.find((l) => l.rubrique === rubriqueEnEdition) ?? null}
          anneeScolaireId={anneeActive.id}
          onClose={() => setRubriqueEnEdition(null)}
          onSaved={() => {
            setRubriqueEnEdition(null)
            invalidateBudget()
          }}
        />
      )}

      {showAssuranceForm && anneeActive && (
        <AssuranceFormModal
          assurance={assuranceEnEdition}
          anneeScolaireId={anneeActive.id}
          onClose={() => setShowAssuranceForm(false)}
          onSaved={() => {
            setShowAssuranceForm(false)
            setAssuranceEnEdition(null)
            invalidateAssurances()
          }}
        />
      )}
    </div>
  )
}

function MontantPercuModal({
  rubrique,
  ligne,
  anneeScolaireId,
  onClose,
  onSaved,
}: {
  rubrique: RubriqueBudgetFonctionnement
  ligne: LigneBudgetFonctionnement | null
  anneeScolaireId: number
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [montant, setMontant] = useState(ligne?.montant_percu ?? 0)
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const enregistrer = async () => {
    setSubmitting(true)
    setServerError(null)
    try {
      await definirMontantPercu(rubrique, anneeScolaireId, Number(montant))
      succes(t('rentree.updated'))
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={LIBELLES_RUBRIQUE[rubrique]} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <MontantInput
          label={t('rentree.montant_percu_col')}
          value={montant}
          onChange={setMontant}
        />
        {serverError && <p className="text-sm text-red-500">{serverError}</p>}
        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={submitting} onClick={enregistrer}>
            {t('common.save')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function AssuranceFormModal({
  assurance,
  anneeScolaireId,
  onClose,
  onSaved,
}: {
  assurance: AssuranceScolaire | null
  anneeScolaireId: number
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<AssuranceScolairePayload>({
    defaultValues: assurance
      ? { libelle: assurance.libelle, effectif: assurance.effectif, nom_assureur: assurance.nom_assureur ?? '', numero_police: assurance.numero_police ?? '' }
      : { annee_scolaire_id: anneeScolaireId, effectif: 0 },
  })

  const onSubmit = async (values: AssuranceScolairePayload) => {
    setServerError(null)
    const payload: AssuranceScolairePayload = { ...values, annee_scolaire_id: anneeScolaireId, effectif: Number(values.effectif) }

    try {
      if (assurance) {
        await modifierAssuranceScolaire(assurance.id, payload)
        succes(t('rentree.updated'))
      } else {
        await creerAssuranceScolaire(payload)
        succes(t('rentree.created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={assurance ? t('rentree.edit_title') : t('rentree.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('rentree.niveau_col')}
          placeholder="Niveau 1, Niveau 2…"
          error={errors.libelle?.message}
          {...register('libelle', { required: t('bus.field_required') as string })}
        />
        <Input
          label={t('rentree.effectif_col')}
          type="number"
          min={0}
          error={errors.effectif?.message}
          {...register('effectif', { required: true, min: 0 })}
        />
        <div className="grid grid-cols-2 gap-3">
          <Input label={t('rentree.assureur_col')} {...register('nom_assureur')} />
          <Input label={t('rentree.police_col')} {...register('numero_police')} />
        </div>
        {serverError && <p className="text-sm text-red-500">{serverError}</p>}
        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function ConseilEcoleForm({ conseil, anneeScolaireId, peutModifier }: { conseil: ConseilEcole; anneeScolaireId: number; peutModifier: boolean }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [submitting, setSubmitting] = useState(false)

  const { register, handleSubmit, reset } = useForm<ConseilEcole>({ defaultValues: conseil })

  useEffect(() => reset(conseil), [conseil, reset])

  const onSubmit = async (values: ConseilEcole) => {
    setSubmitting(true)
    try {
      await definirConseilEcole(anneeScolaireId, values)
      succes(t('rentree.updated'))
      queryClient.invalidateQueries({ queryKey: ['conseil-ecole'] })
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-3">
      <label className="flex items-center gap-2 text-sm text-navy-700">
        <input type="checkbox" className="h-4 w-4 rounded border-navy-300" disabled={!peutModifier} {...register('existe')} />
        {t('rentree.existe_label')}
      </label>
      <div className="grid grid-cols-2 gap-3">
        <Input label={t('rentree.date_ag_label')} type="date" disabled={!peutModifier} {...register('date_ag_elective')} />
        <Input label={t('rentree.duree_mandat_label')} placeholder="02ans" disabled={!peutModifier} {...register('duree_mandat')} />
      </div>
      <Input label={t('rentree.president_label')} disabled={!peutModifier} {...register('president_nom')} />
      <div className="grid grid-cols-2 gap-3">
        <Input label={t('rentree.president_fonction_label')} disabled={!peutModifier} {...register('president_fonction')} />
        <Input label={t('rentree.president_telephone_label')} disabled={!peutModifier} {...register('president_telephone')} />
      </div>
      {peutModifier && (
        <Button type="submit" disabled={submitting} className="self-end">
          {t('common.save')}
        </Button>
      )}
    </form>
  )
}

function ApeeForm({ apee, anneeScolaireId, peutModifier }: { apee: Apee; anneeScolaireId: number; peutModifier: boolean }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [submitting, setSubmitting] = useState(false)

  const { register, handleSubmit, reset, watch, setValue } = useForm<Apee>({ defaultValues: apee })

  useEffect(() => reset(apee), [apee, reset])

  const onSubmit = async (values: Apee) => {
    setSubmitting(true)
    try {
      await definirApee(anneeScolaireId, {
        ...values,
        taux_par_eleve: values.taux_par_eleve ? Number(values.taux_par_eleve) : null,
        montant_percu: Number(values.montant_percu) || 0,
        montant_depense: Number(values.montant_depense) || 0,
      })
      succes(t('rentree.updated'))
      queryClient.invalidateQueries({ queryKey: ['apee'] })
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-3">
      <label className="flex items-center gap-2 text-sm text-navy-700">
        <input type="checkbox" className="h-4 w-4 rounded border-navy-300" disabled={!peutModifier} {...register('legalisee')} />
        {t('rentree.legalisee_label')}
      </label>
      <Input label={t('rentree.president_label')} disabled={!peutModifier} {...register('president_nom')} />
      <div className="grid grid-cols-2 gap-3">
        <MontantInput
          label={t('rentree.montant_percu_col')}
          disabled={!peutModifier}
          value={watch('montant_percu')}
          onChange={(v) => setValue('montant_percu', v)}
        />
        <MontantInput
          label={t('rentree.montant_depense_col')}
          disabled={!peutModifier}
          value={watch('montant_depense')}
          onChange={(v) => setValue('montant_depense', v)}
        />
      </div>
      {apee.id && (
        <Badge tone={apee.montant_restant >= 0 ? 'green' : 'red'}>
          {t('rentree.montant_restant_label')} : {francs(apee.montant_restant)}
        </Badge>
      )}
      {peutModifier && (
        <Button type="submit" disabled={submitting} className="self-end">
          {t('common.save')}
        </Button>
      )}
    </form>
  )
}
