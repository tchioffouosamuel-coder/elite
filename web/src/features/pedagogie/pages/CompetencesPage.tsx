import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Target, Trash2 } from 'lucide-react'
import {
  fetchCompetences,
  creerCompetence,
  modifierCompetence,
  supprimerCompetence,
  type Competence,
  type CompetencePayload,
} from '@/features/pedagogie/api'
import { fetchSchools } from '@/features/classes/api'
import { LIBELLES_COMPOSANTES, type Composante } from '@/features/primaire/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/** Volets systématiques, plus « pratique » si la compétence l'évalue. */
function voletsActifs(evaluePratique: boolean): Composante[] {
  return evaluePratique ? ['oral', 'ecrit', 'savoir_etre', 'pratique'] : ['oral', 'ecrit', 'savoir_etre']
}

/**
 * Référentiel des compétences évaluées du primaire et de la maternelle.
 *
 * La compétence est l'unité que le bulletin note : elle porte le barème et sa
 * répartition entre les volets. Les matières listées sous chacune en sont le
 * contenu enseigné, et suivent la compétence quand on l'attribue à une classe.
 */
export function CompetencesPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [formOuvert, setFormOuvert] = useState(false)
  const [enEdition, setEnEdition] = useState<Competence | null>(null)

  const { data: competences, isLoading } = useQuery({ queryKey: ['competences'], queryFn: fetchCompetences })

  const invalider = () => {
    queryClient.invalidateQueries({ queryKey: ['competences'] })
    queryClient.invalidateQueries({ queryKey: ['matieres'] })
  }

  const colonnes: Colonne<Competence>[] = [
    {
      cle: 'label',
      entete: t('competences.libelle'),
      valeur: (c) => c.label_fr,
      cellule: (c) => (
        <span className="flex flex-col">
          <span className="font-semibold text-navy-900">{c.label_fr}</span>
          {c.label_en && <span className="text-xs text-navy-400">{c.label_en}</span>}
        </span>
      ),
    },
    {
      cle: 'notation',
      entete: t('matieres.notation'),
      valeur: (c) => c.notation ?? -1,
      cellule: (c) =>
        c.notation != null ? (
          <span className="font-semibold tabular-nums">/ {c.notation}</span>
        ) : (
          <span className="text-xs text-navy-400">{t('competences.par_appreciation')}</span>
        ),
    },
    {
      cle: 'volets',
      entete: t('matieres.repartition_volets'),
      valeur: (c) => c.volets.length,
      cellule: (c) => (
        <span className="text-xs text-navy-600">
          {c.notation != null
            ? c.volets
              .map((volet) => `${LIBELLES_COMPOSANTES[volet as Composante] ?? volet} /${c.repartition_volets[volet] ?? 0}`)
              .join(' · ')
            : c.volets.map((volet) => LIBELLES_COMPOSANTES[volet as Composante] ?? volet).join(' · ')}
        </span>
      ),
      masquerMobile: true,
    },
    {
      cle: 'matieres',
      entete: t('competences.contenu'),
      valeur: (c) => c.matieres_count ?? 0,
      cellule: (c) =>
        (c.matieres?.length ?? 0) === 0 ? (
          <span className="text-xs text-gold-600">{t('competences.sans_matiere')}</span>
        ) : (
          <span
            className="text-xs text-navy-600"
            title={c.matieres?.map((matiere) => matiere.nom).join(' · ')}
          >
            {c.matieres?.map((matiere) => matiere.nom).join(' · ')}
          </span>
        ),
      masquerMobile: true,
    },
    {
      cle: 'classes',
      entete: t('competences.attribuee_dans'),
      valeur: (c) => c.classes_count ?? 0,
      cellule: (c) => <Badge tone={(c.classes_count ?? 0) > 0 ? 'green' : 'neutral'}>{c.classes_count ?? 0}</Badge>,
      masquerMobile: true,
    },
    ...(can('pedagogie.manage')
      ? [
        {
          cle: 'actions',
          entete: t('common.actions'),
          sticky: 'right',
          cellule: (c: Competence) => (
            <div className="flex items-center gap-1">
              <button
                title={t('common.edit')}
                onClick={() => {
                  setEnEdition(c)
                  setFormOuvert(true)
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
              >
                <Pencil className="h-4 w-4" />
              </button>
              <button
                title={t('common.delete')}
                onClick={async () => {
                  if (!(await confirmerSuppression(c.label_fr))) return
                  try {
                    await supprimerCompetence(c.id)
                    invalider()
                    succes(t('competences.supprimee'))
                  } catch (err) {
                    erreur((err as ApiError).message)
                  }
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ),
        } satisfies Colonne<Competence>,
      ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('competences.title')}
        sousTitre={t('competences.subtitle')}
        icon={Target}
        actions={
          can('pedagogie.manage') && (
            <Button
              onClick={() => {
                setEnEdition(null)
                setFormOuvert(true)
              }}
            >
              <Plus className="h-4 w-4" />
              {t('competences.ajouter')}
            </Button>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={competences ?? []}
          cleLigne={(c) => c.id}
          placeholderRecherche={t('competences.recherche')}
          messageVide={t('competences.vide')}
          largeurMin={860}
        />
      )}

      {formOuvert && (
        <CompetenceFormModal
          competence={enEdition}
          onClose={() => {
            setFormOuvert(false)
            setEnEdition(null)
          }}
          onSaved={() => {
            setFormOuvert(false)
            setEnEdition(null)
            invalider()
          }}
        />
      )}
    </div>
  )
}

function CompetenceFormModal({
  competence,
  onClose,
  onSaved,
}: {
  competence: Competence | null
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const { data: toutesLesEcoles } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
  // Les compétences n'existent qu'au primaire et en maternelle : le secondaire
  // note ses matières directement, avec un coefficient (cf. l'en-tête de ce
  // fichier).
  const schools = toutesLesEcoles?.filter((ecole) => ecole.type !== 'secondaire')

  const { register, handleSubmit, watch, formState: { isSubmitting, errors } } = useForm<CompetencePayload>({
    defaultValues: competence
      ? {
        label_fr: competence.label_fr,
        label_en: competence.label_en ?? '',
        abbreviation: competence.abbreviation ?? '',
        notation: competence.notation,
        evalue_pratique: competence.evalue_pratique,
        repartition_volets: competence.repartition_volets,
        ordre: competence.ordre,
      }
      : { notation: 20, evalue_pratique: false, ordre: 0 },
  })

  // La maternelle évalue par appréciation (un visage coché), pas par barème :
  // ni la notation, ni la répartition des volets en points ne s'y appliquent
  // (cf. StoreCompetenceRequest::parAppreciation côté API). Quand le
  // sélecteur d'école est masqué (un seul établissement accessible), on
  // retombe sur celui-là.
  const schoolIdSaisi = watch('school_id')
  const ecoleSelectionnee =
    competence?.school ??
    schools?.find((ecole) => ecole.id === Number(schoolIdSaisi)) ??
    (schools?.length === 1 ? schools[0] : undefined)
  const estMaternelle = ecoleSelectionnee?.type === 'maternelle'

  const notationSaisie = Number(watch('notation')) || 0
  const evaluePratique = !!watch('evalue_pratique')
  const volets = voletsActifs(evaluePratique)
  const repartitionSaisie = watch('repartition_volets')
  const somme = volets.reduce((total, volet) => total + (Number(repartitionSaisie?.[volet]) || 0), 0)
  // Purement indicatif : chaque volet est facultatif, celui qu'on laisse vide
  // compte pour 0 point (cf. Competence::repartitionVolets côté API) — la
  // somme n'a donc plus à égaler le barème pour enregistrer.
  const repartitionEquilibree = notationSaisie > 0 && Math.abs(somme - notationSaisie) < 0.01

  const onSubmit = async (values: CompetencePayload) => {
    setServerError(null)
    try {
      const payload: CompetencePayload = {
        ...values,
        ordre: values.ordre ? Number(values.ordre) : 0,
        school_id: values.school_id ? Number(values.school_id) : null,
        ...(estMaternelle
          ? { notation: null, repartition_volets: null }
          : {
            notation: Number(values.notation),
            repartition_volets: Object.fromEntries(
              volets.map((volet) => [volet, Number(values.repartition_volets?.[volet]) || 0]),
            ),
          }),
      }

      if (competence) {
        await modifierCompetence(competence.id, payload)
        succes(t('competences.modifiee'))
      } else {
        await creerCompetence(payload)
        succes(t('competences.creee'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={competence ? t('competences.modifier') : t('competences.ajouter')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        {!competence && (schools?.length ?? 0) > 1 && (
          <Select
            label={`${t('classes.ecole')} *`}
            error={errors.school_id?.message}
            {...register('school_id', { required: "L'école est requise." })}
          >
            <option value="">—</option>
            {schools?.map((school) => (
              <option key={school.id} value={school.id}>
                {school.name}
              </option>
            ))}
          </Select>
        )}

        <Input label={t('competences.libelle')} {...register('label_fr', { required: true })} />
        <Input label={t('competences.libelle_en')} {...register('label_en')} />

        <div className="grid grid-cols-2 gap-3">
          <Input label={t('matieres.abbreviation')} {...register('abbreviation')} />
          <Input type="number" min={0} max={999} label={t('competences.ordre')} {...register('ordre')} />
        </div>

        {!estMaternelle && (
          <Input
            type="number"
            min={5}
            max={100}
            label={t('matieres.notation')}
            {...register('notation', { required: true })}
          />
        )}

        <label className="flex items-center gap-2 text-sm text-navy-700">
          <input type="checkbox" className="h-4 w-4 rounded border-navy-300" {...register('evalue_pratique')} />
          {t('matieres.evalue_pratique')}
        </label>

        {/*
          La maternelle évalue par appréciation (un visage coché par volet),
          pas par barème réparti en points : ni la notation ni cette
          répartition ne s'y appliquent (cf. StoreCompetenceRequest côté API).
        */}
        {!estMaternelle && (
          <div className="flex flex-col gap-2 rounded-xl border border-navy-100 bg-cream-50/60 p-3">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
              {t('matieres.repartition_volets')}
            </span>
            <div className="grid grid-cols-2 gap-3">
              {volets.map((volet) => (
                <Input
                  key={volet}
                  type="number"
                  min={0}
                  step={0.5}
                  label={LIBELLES_COMPOSANTES[volet]}
                  {...register(`repartition_volets.${volet}`, { min: 0 })}
                />
              ))}
            </div>
            <span className={`text-xs font-medium ${repartitionEquilibree ? 'text-green-600' : 'text-navy-400'}`}>
              {t('matieres.repartition_somme', { somme, notation: notationSaisie })}
            </span>
          </div>
        )}

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
