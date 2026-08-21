import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { createSanction } from '@/features/discipline/api'
import type { SanctionPayload, TypeSanction } from '@/features/discipline/api'
import { fetchEleves } from '@/features/eleves/api'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, Textarea, FieldWrapper } from '@/shared/ui/Field'
import type { ApiError } from '@/shared/types/api'

// Une exclusion temporaire n'a de sens qu'avec une durée à borner ; les autres
// types n'ont rien à saisir dans ce champ.
const TYPES_AVEC_DUREE: TypeSanction[] = ['exclusion_temporaire']

/**
 * Prononcer une sanction. Ouvert depuis le registre disciplinaire, où l'élève
 * reste à choisir, mais aussi depuis la fiche d'un élève : dans ce cas `eleve`
 * est fourni, le sélecteur cède la place au nom déjà connu, et l'utilisateur
 * n'a plus à retrouver son élève dans une liste de plusieurs centaines.
 */
export function SanctionFormModal({
  eleve,
  onClose,
  onCreated,
}: {
  eleve?: { id: number; nom_complet: string; classe?: { nom: string } | null }
  onClose: () => void
  onCreated: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  // Inutile de charger tout l'effectif quand l'élève est déjà connu.
  const { data: eleves } = useQuery({
    queryKey: ['eleves', 'all'],
    queryFn: () => fetchEleves({ per_page: 200 }),
    enabled: !eleve,
  })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]

  const {
    register,
    handleSubmit,
    watch,
    formState: { isSubmitting, errors },
  } = useForm<SanctionPayload>({
    defaultValues: {
      type: 'avertissement',
      date_sanction: new Date().toISOString().slice(0, 10),
      impacte_bulletin: false,
      ...(eleve ? { eleve_id: eleve.id } : {}),
    },
  })

  const typeChoisi = watch('type')

  const onSubmit = async (values: SanctionPayload) => {
    setServerError(null)
    try {
      await createSanction({
        ...values,
        eleve_id: eleve ? eleve.id : Number(values.eleve_id),
        trimestre_id: trimestreActif ? trimestreActif.id : Number(values.trimestre_id),
        duree_jours: values.duree_jours ? Number(values.duree_jours) : null,
        impacte_bulletin: Boolean(values.impacte_bulletin),
      })
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={t('discipline.add_sanction')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        {eleve ? (
          <FieldWrapper label={t('eleves.title')}>
            <p className="rounded-xl border border-navy-100 bg-cream-50 px-3.5 py-2.5 text-sm font-semibold text-navy-800">
              {eleve.nom_complet}
              {eleve.classe?.nom && <span className="font-normal text-navy-400"> — {eleve.classe.nom}</span>}
            </p>
          </FieldWrapper>
        ) : (
          <Select label={t('eleves.title')} error={errors.eleve_id?.message} {...register('eleve_id', { required: true })}>
            <option value="">—</option>
            {eleves?.items.map((e) => (
              <option key={e.id} value={e.id}>
                {e.nom_complet} — {e.classe?.nom}
              </option>
            ))}
          </Select>
        )}
        <Select label={t('discipline.type')} {...register('type', { required: true })}>
          <option value="avertissement">{t('discipline.type_avertissement')}</option>
          <option value="blame">{t('discipline.type_blame')}</option>
          <option value="corvee">{t('discipline.type_corvee')}</option>
          <option value="exclusion_temporaire">{t('discipline.type_exclusion_temporaire')}</option>
          <option value="exclusion_definitive">{t('discipline.type_exclusion_definitive')}</option>
          <option value="autre">{t('discipline.type_autre')}</option>
        </Select>
        <div className="grid grid-cols-2 gap-3">
          {TYPES_AVEC_DUREE.includes(typeChoisi) && (
            <Input
              label={t('discipline.duree_jours')}
              type="number"
              min={1}
              error={errors.duree_jours?.message}
              {...register('duree_jours', { required: TYPES_AVEC_DUREE.includes(typeChoisi) })}
            />
          )}
          <Input label={t('discipline.date')} type="date" {...register('date_sanction', { required: true })} />
        </div>
        <Textarea
          label={t('discipline.motif')}
          error={errors.motif?.message}
          {...register('motif', { required: true, minLength: { value: 10, message: t('discipline.motif_min_length') } })}
        />
        <Textarea label={t('discipline.commentaire')} rows={2} {...register('commentaire')} />
        <label className="flex cursor-pointer items-center gap-2 text-sm text-navy-700">
          <input type="checkbox" className="h-4 w-4 rounded border-navy-300" {...register('impacte_bulletin')} />
          {t('discipline.impacte_bulletin')}
        </label>

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
