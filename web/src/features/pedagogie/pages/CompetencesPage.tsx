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
  batchDeleteCompetences,
  type Competence,
  type CompetencePayload,
} from '@/features/pedagogie/api'
import { fetchSchools } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner } from '@/shared/ui/Feedback'
import { triEcoleSousSystemeNiveauClasse } from '@/shared/lib/triHierarchique'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Référentiel des compétences évaluées du primaire et de la maternelle.
 *
 * La compétence est l'unité que le bulletin note. Elle dit ce que l'on évalue ;
 * les matières listées sous chacune en sont le contenu enseigné, et suivent la
 * compétence quand on l'attribue à une classe. Le barème et la répartition des
 * volets, eux, se règlent sur cette attribution — ils varient d'une classe à
 * l'autre (cf. AffectationsTab).
 */
export function CompetencesPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [formOuvert, setFormOuvert] = useState(false)
  const [enEdition, setEnEdition] = useState<Competence | null>(null)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [aSupprimerAvecMotDePasse, setASupprimerAvecMotDePasse] = useState<{ ids: number[]; label: string } | null>(null)

  const { data: competences, isLoading } = useQuery({ queryKey: ['competences'], queryFn: fetchCompetences })

  const invalider = () => {
    queryClient.invalidateQueries({ queryKey: ['competences'] })
    queryClient.invalidateQueries({ queryKey: ['matieres'] })
  }

  const handleToggleSelect = (id: number) => {
    setSelectedIds((actuels) => {
      const suivants = new Set(actuels)
      if (suivants.has(id)) suivants.delete(id)
      else suivants.add(id)
      return suivants
    })
  }

  const handleSelectAll = (lignes: Competence[]) => {
    if (selectedIds.size === lignes.length && lignes.length > 0) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(lignes.map((c) => c.id)))
    }
  }

  const handleBatchDelete = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return

    const ok = await confirmerSuppression(`${ids.length} compétence(s)`)
    if (!ok) return

    try {
      const { supprimees } = await batchDeleteCompetences(ids)
      setSelectedIds(new Set())
      invalider()
      succes(`${supprimees} compétence(s) supprimée(s).`)
    } catch (err) {
      const apiErr = err as ApiError
      // 409 : une ou plusieurs compétences portent déjà des notes — l'API
      // demande une confirmation par mot de passe plutôt que de bloquer.
      if (apiErr.status === 409) {
        setASupprimerAvecMotDePasse({ ids, label: `${ids.length} compétence(s)` })
        return
      }
      erreur(apiErr.message)
    }
  }

  const triCompetences = triEcoleSousSystemeNiveauClasse<Competence>({
    ecole: (c) => c.school?.name,
  })

  const colonnes: Colonne<Competence>[] = [
    ...(can('competences.delete')
      ? [
        {
          cle: 'selection',
          entete: competences ? (
            <input
              type="checkbox"
              checked={selectedIds.size === competences.length && competences.length > 0}
              onChange={() => handleSelectAll(competences)}
              className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
            />
          ) : null,
          cellule: (c: Competence) => (
            <input
              type="checkbox"
              checked={selectedIds.has(c.id)}
              onChange={() => handleToggleSelect(c.id)}
              className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
            />
          ),
        } satisfies Colonne<Competence>,
      ]
      : []),
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
    {
      cle: 'statut',
      entete: t('competences.statut'),
      valeur: (c) => c.statut,
      cellule: (c) => (
        <Badge tone={c.statut === 'actif' ? 'green' : 'neutral'}>
          {c.statut === 'actif' ? t('competences.actif') : t('competences.inactif')}
        </Badge>
      ),
      masquerMobile: true,
    },
    ...(can('competences.update|competences.delete')
      ? [
        {
          cle: 'actions',
          entete: t('common.actions'),
          sticky: 'right',
          cellule: (c: Competence) => (
            <div className="flex items-center gap-1">
              {can('competences.update') && (
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
              )}
              {can('competences.delete') && (
                <button
                  title={t('common.delete')}
                  onClick={async () => {
                    if (!(await confirmerSuppression(c.label_fr))) return
                    try {
                      await supprimerCompetence(c.id)
                      invalider()
                      succes(t('competences.supprimee'))
                    } catch (err) {
                      const apiErr = err as ApiError
                      // 409 : cette compétence porte déjà des notes — l'API
                      // demande une confirmation par mot de passe plutôt que de
                      // bloquer la suppression.
                      if (apiErr.status === 409) {
                        setASupprimerAvecMotDePasse({ ids: [c.id], label: c.label_fr })
                        return
                      }
                      erreur(apiErr.message)
                    }
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              )}
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
          can('competences.create|competences.import|competences.delete') && (
            <>
              <ImportExportBar
                titreImport={t('competences.title')}
                importUrl="/competences/import"
                peutImporter={can('competences.import')}
                exportUrl="/competences/export"
                modeleUrl="/competences/modele"
                // Une ligne par compétence ET par classe : le barème appartient
                // au couple (cf. CompetenceImport côté API).
                colonnes={['Competence', 'Competence (EN)', 'Abreviation', 'Ordre', 'Classe', 'Notation', 'Oral', 'Ecrit', 'Savoir-etre', 'Pratique']}
                nomFichier="competences"
                onImported={() => queryClient.invalidateQueries({ queryKey: ['competences'] })}
              />
              {selectedIds.size > 0 && can('competences.delete') && (
                <Button variant="danger" onClick={handleBatchDelete}>
                  <Trash2 className="h-4 w-4" />
                  {t('common.delete')} ({selectedIds.size})
                </Button>
              )}
              {can('competences.create') && (
                <Button
                  onClick={() => {
                    setEnEdition(null)
                    setFormOuvert(true)
                  }}
                >
                  <Plus className="h-4 w-4" />
                  {t('competences.ajouter')}
                </Button>
              )}
            </>
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
          triDefaut={triCompetences}
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

      {aSupprimerAvecMotDePasse && (
        <SupprimerCompetenceNoteeModal
          ids={aSupprimerAvecMotDePasse.ids}
          label={aSupprimerAvecMotDePasse.label}
          onClose={() => setASupprimerAvecMotDePasse(null)}
          onDeleted={() => {
            setASupprimerAvecMotDePasse(null)
            setSelectedIds(new Set())
            invalider()
          }}
        />
      )}
    </div>
  )
}

/**
 * Confirmation par mot de passe avant de supprimer une compétence (ou un lot)
 * déjà notée : l'API a répondu 409 à une première tentative sans mot de
 * passe. La suppression emporte les matières, les attributions aux classes
 * et toutes les notes déjà saisies — d'où l'étape supplémentaire, sur le
 * même principe que le retrait d'une compétence d'une classe.
 */
function SupprimerCompetenceNoteeModal({
  ids,
  label,
  onClose,
  onDeleted,
}: {
  ids: number[]
  label: string
  onClose: () => void
  onDeleted: () => void
}) {
  const [motDePasse, setMotDePasse] = useState('')
  const [serverError, setServerError] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  const confirmer = async () => {
    if (!motDePasse) {
      setServerError('Votre mot de passe est requis.')
      return
    }

    setEnvoi(true)
    setServerError(null)

    try {
      if (ids.length === 1) {
        await supprimerCompetence(ids[0], motDePasse)
      } else {
        await batchDeleteCompetences(ids, motDePasse)
      }
      succes('Compétence supprimée, avec ses matières, ses attributions et ses notes.')
      onDeleted()
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={`Supprimer — ${label}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <p className="text-sm text-navy-600">
          Des notes ont déjà été saisies pour {ids.length > 1 ? 'ces compétences' : 'cette compétence'}. Confirmez
          votre mot de passe pour {ids.length > 1 ? 'les' : 'la'} supprimer définitivement, avec leurs matières, leurs
          attributions aux classes et toutes leurs notes — cette action est irréversible.
        </p>

        <Input
          type="password"
          label="Votre mot de passe"
          value={motDePasse}
          onChange={(e) => setMotDePasse(e.target.value)}
          autoFocus
          autoComplete="current-password"
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              void confirmer()
            }
          }}
        />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="button" variant="danger" onClick={confirmer} disabled={envoi}>
            {envoi ? 'Suppression…' : 'Supprimer définitivement'}
          </Button>
        </div>
      </div>
    </Modal>
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
  const { data: toutesLesEcoles } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })
  // Les compétences n'existent qu'au primaire et en maternelle : le secondaire
  // note ses matières directement, avec un coefficient (cf. l'en-tête de ce
  // fichier).
  const schools = toutesLesEcoles?.filter((ecole) => ecole.type !== 'secondaire')

  const { register, handleSubmit, formState: { isSubmitting, errors } } = useForm<CompetencePayload>({
    defaultValues: competence
      ? {
        label_fr: competence.label_fr,
        label_en: competence.label_en ?? '',
        abbreviation: competence.abbreviation ?? '',
        ordre: competence.ordre,
        statut: competence.statut,
      }
      : { ordre: 0, statut: 'actif' },
  })

  const onSubmit = async (values: CompetencePayload) => {
    setServerError(null)
    try {
      const payload: CompetencePayload = {
        ...values,
        ordre: values.ordre ? Number(values.ordre) : 0,
        school_id: values.school_id ? Number(values.school_id) : null,
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

        {/* Une compétence déjà notée refuse la suppression (cf. CompetenceController::destroy)
            — c'est ce sélecteur qui offre l'alternative que le message d'erreur suggère. */}
        <Select label={t('competences.statut')} {...register('statut')}>
          <option value="actif">{t('competences.actif')}</option>
          <option value="inactif">{t('competences.inactif')}</option>
        </Select>

        {/*
          Le barème et la répartition des volets ne se règlent plus ici : ils
          appartiennent à l'attribution de la compétence à une classe, où ils
          peuvent différer d'une classe à l'autre
          (cf. AttribuerCompetencesModal, et ClasseCompetence côté API).
        */}
        <p className="rounded-xl border border-navy-100 bg-cream-50/60 p-3 text-xs text-navy-500">
          {t('competences.bareme_par_classe')}
        </p>

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
