import { useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useEffect, useState } from 'react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { fetchNiveaux, fetchAnneesScolaires, createClasse, updateClasse, fetchSousSystemes, fetchSchools, type Classe, type ClassePayload } from '@/features/classes/api'
import { fetchEcole } from '@/features/settings/api'
import { fetchNiveauxScolaires } from '@/features/primaire/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { estSecondaire, utiliseNiveaux } from '@/shared/lib/ecole'
import type { ApiError } from '@/shared/types/api'

export function ClasseFormModal({
  classe,
  onClose,
  onCreated,
}: {
  classe?: Classe
  onClose: () => void
  onCreated: () => void
}) {
  const { t } = useTranslation()
  const { data: niveaux } = useQuery({ queryKey: ['niveaux'], queryFn: fetchNiveaux })
  const { data: ecole } = useQuery({ queryKey: ['ecole'], queryFn: fetchEcole })
  const { data: annees } = useQuery({ queryKey: ['annees-scolaires'], queryFn: fetchAnneesScolaires })
  const { data: sousSystemes } = useQuery({ queryKey: ['sous-systemes'], queryFn: fetchSousSystemes })
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
  const niveauxEcole = ecole ? niveaux?.filter((n) => n.school_id === ecole.id) : niveaux
  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  // Le titulaire unique vaut pour le primaire comme pour la maternelle ; le
  // degré d'enseignement (SIL, CP…) ne concerne que le primaire, la maternelle
  // ne connaissant pas cette notion.
  const secondaire = estSecondaire()
  const avecNiveaux = utiliseNiveaux()
  const { data: niveauxScolaires } = useQuery({
    queryKey: ['niveaux-scolaires'],
    queryFn: fetchNiveauxScolaires,
    enabled: avecNiveaux,
  })
  const { data: personnels } = useQuery({
    queryKey: ['personnels', { page: 1, per_page: 100 }],
    queryFn: () => fetchPersonnels({ per_page: 100 }),
    enabled: !secondaire,
  })

  const activeAnnee = annees?.find((a) => a.is_active) ?? annees?.[0]

  const buildDefaultValues = (): Partial<ClassePayload> =>
    classe
      ? {
        nom: classe.nom,
        sigle: classe.sigle ?? '',
        niveau_classe: classe.niveau_classe ?? '',
        niveau_id: classe.niveau_id,
        annee_scolaire_id: classe.annee_scolaire_id,
        niveau_scolaire_id: classe.niveau_scolaire_id ?? undefined,
        sous_systeme_id: classe.sous_systeme_id ?? undefined,
        school_id: classe.school_id ?? undefined,
        titulaire_id: classe.titulaire_id ?? undefined,
        filiere: classe.filiere ?? '',
        code_examen: classe.code_examen ?? '',
        capacite: classe.capacite ?? undefined,
      }
      : { annee_scolaire_id: activeAnnee?.id }

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ClassePayload>({ defaultValues: buildDefaultValues() })

  // Les options des selects (écoles, niveaux, titulaire…) viennent de requêtes
  // encore en vol au premier rendu : un <select> ne peut présélectionner une
  // <option> qui n'existe pas encore dans le DOM. On resynchronise le
  // formulaire dès que chaque source de données arrive.
  useEffect(() => {
    if (classe) reset(buildDefaultValues())
  }, [classe, niveaux, annees, sousSystemes, schools, niveauxScolaires, personnels])

  const onSubmit = async (values: ClassePayload) => {
    setServerError(null)
    setSubmitting(true)
    try {
      const payload = {
        ...values,
        niveau_id: Number(values.niveau_id),
        annee_scolaire_id: Number(values.annee_scolaire_id),
        capacite: values.capacite ? Number(values.capacite) : null,
        niveau_scolaire_id: values.niveau_scolaire_id ? Number(values.niveau_scolaire_id) : null,
        sous_systeme_id: values.sous_systeme_id ? Number(values.sous_systeme_id) : null,
        school_id: values.school_id ? Number(values.school_id) : null,
        titulaire_id: values.titulaire_id ? Number(values.titulaire_id) : null,
      }
      if (classe) {
        await updateClasse(classe.id, payload)
      } else {
        await createClasse(payload)
      }
      onCreated()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={classe ? t('classes.edit') : t('classes.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input label={t('classes.nom')} error={errors.nom?.message} {...register('nom', { required: true })} />

        <Input label="Sigle" placeholder="ex: GS-A, CE1-B" {...register('sigle')} />

        <Select label="École" {...register('school_id')}>
          <option value="">—</option>
          {schools?.map((s) => (
            <option key={s.id} value={String(s.id)}>
              {s.name}
            </option>
          ))}
        </Select>

        <Select label={t('classes.niveau')} error={errors.niveau_id?.message} {...register('niveau_id', { required: true })}>
          <option value="">—</option>
          {niveauxEcole?.map((n) => (
            <option key={n.id} value={n.id}>
              {n.name_fr}
            </option>
          ))}
        </Select>

        <Select
          label="Année scolaire"
          error={errors.annee_scolaire_id?.message}
          {...register('annee_scolaire_id', { required: true })}
        >
          {annees?.map((a) => (
            <option key={a.id} value={a.id}>
              {a.libelle}
            </option>
          ))}
        </Select>

        <Select label="Sous-système" {...register('sous_systeme_id')}>
          <option value="">—</option>
          {sousSystemes?.map((s) => (
            <option key={s.id} value={String(s.id)}>
              {s.nom} ({s.code})
            </option>
          ))}
        </Select>

        {avecNiveaux && (
          <Select label={t('niveaux.title')} {...register('niveau_scolaire_id')}>
            <option value="">—</option>
            {niveauxScolaires?.map((n) => (
              <option key={n.id} value={n.id}>
                {n.code} — {n.libelle}
              </option>
            ))}
          </Select>
        )}

        {!secondaire && (
          <>
            <Select label={t('classes.titulaire')} {...register('titulaire_id')}>
              <option value="">—</option>
              {personnels?.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.nom_complet}
                </option>
              ))}
            </Select>
          </>
        )}

        <div className="grid grid-cols-2 gap-3">
          {secondaire && <Input label={t('classes.filiere')} {...register('filiere')} />}
          {/* Renseigner ce code inscrit la classe au module Photos DECC & OBC
              et l'imprime sous chaque photo de candidat. */}
          <Input
            label={t('classes.code_examen')}
            placeholder="BEPC, Probatoire, BAC, CEP…"
            {...register('code_examen')}
          />
          <Input label={t('classes.capacite')} type="number" {...register('capacite')} />
        </div>

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={submitting}>
            {t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
