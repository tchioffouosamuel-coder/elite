import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Copy, Download, Pencil, Plus, Search, Target, Trash2, Upload } from 'lucide-react'
import {
  fetchClasseMatieres,
  affecterMatiere,
  modifierAffectation,
  retirerMatiere,
  copierAffectations,
  fetchMatieres,
  type ClasseMatiere,
  type ClasseMatiereUpdatePayload,
} from '@/features/pedagogie/api'
import type { ClasseMatierePayload } from '@/features/pedagogie/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { fetchClasses, type Classe } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { ImportModal } from '@/shared/ui/ImportModal'
import { Modal } from '@/shared/ui/Modal'
import { AttribuerCompetencesModal } from '@/features/pedagogie/pages/AttribuerCompetencesModal'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import { telechargerFichier } from '@/shared/lib/download'
import { estSecondaire, type TypeEcole } from '@/shared/lib/ecole'
import type { ApiError } from '@/shared/types/api'

export function AffectationsTab({
  classeId,
  titulaireId,
  ecoleType,
}: {
  classeId: number
  titulaireId?: number | null
  ecoleType?: TypeEcole | null
}) {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [affectationEnEdition, setAffectationEnEdition] = useState<ClasseMatiere | null>(null)
  const [showCopyModal, setShowCopyModal] = useState(false)
  const [showImport, setShowImport] = useState(false)
  const [showCompetences, setShowCompetences] = useState(false)
  const [rechercheMatiere, setRechercheMatiere] = useState('')

  // Le primaire et la maternelle ne pondèrent pas les matières : la moyenne se
  // calcule sur les barèmes des volets, pas sur des coefficients. On garde la
  // valeur 1 côté payload (l'API l'exige) mais on ne la montre nulle part.
  const secondaire = estSecondaire(ecoleType)

  const { data: affectations, isLoading } = useQuery({
    queryKey: ['classe-matieres', classeId],
    queryFn: () => fetchClasseMatieres(classeId),
  })
  const { data: matieres } = useQuery({ queryKey: ['matieres'], queryFn: fetchMatieres })
  // Uniquement pour le select « Enseignant » du formulaire d'affectation, réservé
  // à qui peut gérer — un titulaire en lecture seule n'a pas le privilège
  // « Consulter le personnel » et n'a de toute façon pas accès à ce formulaire.
  const { data: personnels } = useQuery({
    queryKey: ['personnels', 'all'],
    queryFn: () => fetchPersonnels({ per_page: 100 }),
    enabled: can('pedagogie.manage'),
  })

  // Au primaire et en maternelle, le titulaire tient seul la classe : on
  // présélectionne son nom pour éviter de le resaisir à chaque matière.
  const { register, handleSubmit, reset } = useForm<ClasseMatierePayload>({
    defaultValues: { coefficient: 1, groupe: 1, personnel_id: !secondaire ? titulaireId : undefined },
  })

  const affectedIds = new Set(affectations?.map((a) => a.matiere.id))
  const matieresDisponibles = matieres?.filter((m) => !affectedIds.has(m.id)) ?? []
  const matieresDisponiblesFiltrees = matieresDisponibles.filter((matiere) =>
    matiere.nom.toLowerCase().includes(rechercheMatiere.trim().toLowerCase()),
  )
  const matieresById = new Map((matieres ?? []).map((m) => [m.id, m]))

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['classe-matieres', classeId] })

  const basculerSelection = (id: number) =>
    setSelectedIds((courant) => {
      const suivant = new Set(courant)
      suivant.has(id) ? suivant.delete(id) : suivant.add(id)
      return suivant
    })

  const selectionnerTout = () => {
    if (!affectations) return

    if (selectedIds.size === affectations.length && affectations.length > 0) {
      setSelectedIds(new Set())
      return
    }

    setSelectedIds(new Set(affectations.map((a) => a.id)))
  }

  const retirerAffectation = async (id: number, nom: string) => {
    if (!(await confirmerSuppression(`l'affectation de ${nom}`, 'Les notes déjà saisies pour cette matière seront également retirées du bulletin.'))) return

    await retirerMatiere(id)
    setSelectedIds((courant) => {
      const suivant = new Set(courant)
      suivant.delete(id)
      return suivant
    })
    invalidate()
    succes('Affectation retirée.')
  }

  const supprimerSelection = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return

    const ok = await confirmerSuppression(`${ids.length} affectation(s)`)
    if (!ok) return

    try {
      await Promise.all(ids.map((id) => retirerMatiere(id)))
      setSelectedIds(new Set())
      invalidate()
      succes(`${ids.length} affectation(s) retirée(s).`)
    } catch {
      erreur('Impossible de supprimer les affectations sélectionnées.')
    }
  }

  const onSubmit = async (values: ClasseMatierePayload) => {
    await affecterMatiere(classeId, {
      ...values,
      matiere_id: Number(values.matiere_id),
      personnel_id: values.personnel_id ? Number(values.personnel_id) : null,
      coefficient: secondaire ? Number(values.coefficient) : 1,
      quota_horaire: values.quota_horaire ? Number(values.quota_horaire) : null,
      groupe: Number(values.groupe) || 1,
    })
    succes('Matière affectée à la classe.')

    reset()
    setShowForm(false)
    invalidate()
  }

  const colonnes: Colonne<ClasseMatiere>[] =
    affectations
      ? [
        ...(can('pedagogie.manage')
          ? [
            {
              cle: 'selection',
              entete: (
                <div className="flex justify-center">
                  <input
                    type="checkbox"
                    checked={affectations.length > 0 && selectedIds.size === affectations.length}
                    onChange={selectionnerTout}
                    className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
                    aria-label="Tout sélectionner"
                  />
                </div>
              ),
              cellule: (a: ClasseMatiere) => (
                <div className="flex justify-center">
                  <input
                    type="checkbox"
                    checked={selectedIds.has(a.id)}
                    onChange={() => basculerSelection(a.id)}
                    className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
                    aria-label={`Sélectionner ${a.matiere.nom}`}
                  />
                </div>
              ),
              className: 'w-12',
            },
          ]
          : []),
        {
          cle: 'matiere',
          entete: t('matieres.title'),
          valeur: (a) => a.matiere.nom,
          cellule: (a) => <span className="font-medium text-navy-900">{a.matiere.nom}</span>,
        },
        ...(secondaire
          ? [
            {
              cle: 'enseignant',
              entete: t('pedagogie.enseignant'),
              valeur: (a: ClasseMatiere) => a.enseignant?.nom_complet ?? '',
              cellule: (a: ClasseMatiere) => a.enseignant?.nom_complet ?? '—',
            },
            {
              cle: 'coefficient',
              entete: t('pedagogie.coefficient'),
              valeur: (a: ClasseMatiere) => a.coefficient,
              cellule: (a: ClasseMatiere) => a.coefficient,
            },
          ]
          : [
            // Le titulaire enseigne déjà seul sa classe : plutôt que de
            // réafficher son propre nom, on montre à quelle compétence la
            // matière se rattache — c'est elle qui sera notée au bulletin.
            {
              cle: 'competence',
              entete: t('competences.singulier'),
              valeur: (a: ClasseMatiere) => matieresById.get(a.matiere.id)?.competence?.label_fr ?? '',
              cellule: (a: ClasseMatiere) => {
                const competence = matieresById.get(a.matiere.id)?.competence

                return competence ? (
                  <span className="font-medium text-navy-700">{competence.label_fr}</span>
                ) : (
                  <span className="text-xs text-gold-600">{t('competences.non_rattachee')}</span>
                )
              },
            },
            {
              cle: 'bareme_competence',
              entete: t('matieres.notation'),
              valeur: (a: ClasseMatiere) => matieresById.get(a.matiere.id)?.competence?.notation ?? 0,
              cellule: (a: ClasseMatiere) => {
                const competence = matieresById.get(a.matiere.id)?.competence
                return competence ? `/${competence.notation}` : '—'
              },
              masquerMobile: true,
            },
          ]),
        {
          cle: 'quota_horaire',
          entete: t('pedagogie.quota_horaire'),
          valeur: (a) => a.quota_horaire ?? 0,
          cellule: (a) => a.quota_horaire ?? '—',
        },
        ...(can('pedagogie.manage')
          ? [
            {
              cle: 'actions',
              entete: t('common.actions'),
              cellule: (a: ClasseMatiere) => (
                <div className="flex items-center gap-1">
                  <button
                    onClick={(e) => {
                      e.stopPropagation()
                      setAffectationEnEdition(a)
                    }}
                    className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700"
                  >
                    <Pencil className="h-4 w-4" />
                  </button>
                  <button
                    onClick={(e) => {
                      e.stopPropagation()
                      retirerAffectation(a.id, a.matiere.nom)
                    }}
                    className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              ),
            },
          ]
          : []),
      ]
      : []

  if (isLoading) return <Spinner />

  return (
    <div className="flex flex-col gap-4">
      {can('pedagogie.manage') && (
        <div className="flex justify-end gap-2">
          {selectedIds.size > 0 && (
            <>
              <Button size="sm" variant="secondary" onClick={() => setShowCopyModal(true)}>
                <Copy className="h-4 w-4" />
                Copier vers une classe ({selectedIds.size})
              </Button>
              <Button size="sm" variant="danger" onClick={supprimerSelection}>
                <Trash2 className="h-4 w-4" />
                Supprimer ({selectedIds.size})
              </Button>
            </>
          )}
          {/* Au primaire on attribue des compétences, pas des matières : leurs
              matières s'installent d'office. Le formulaire matière par matière
              reste celui du secondaire, où la matière est l'unité notée. */}
          {secondaire ? (
            <Button size="sm" onClick={() => setShowForm((v) => !v)}>
              <Plus className="h-4 w-4" />
              {t('pedagogie.affecter')}
            </Button>
          ) : (
            <Button size="sm" onClick={() => setShowCompetences(true)}>
              <Target className="h-4 w-4" />
              {t('competences.attribuer')}
            </Button>
          )}
          <Button size="sm" variant="secondary" onClick={() => telechargerFichier('/matieres/export', { classe_id: classeId }, 'matieres.xlsx')}>
            <Download className="h-4 w-4" />
            {t('export.excel')}
          </Button>
          <Button size="sm" variant="secondary" onClick={() => setShowImport(true)}>
            <Upload className="h-4 w-4" />
            Importer une affectation
          </Button>
        </div>
      )}

      {showForm && secondaire && (
        <form onSubmit={handleSubmit(onSubmit)} className="grid grid-cols-2 gap-3 rounded-xl border border-navy-100 bg-white p-4 sm:grid-cols-4">
          <div className="col-span-2 flex flex-col gap-1.5 sm:col-span-4">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-300" />
              <Input
                aria-label="Rechercher une matière"
                placeholder="Rechercher une matière…"
                value={rechercheMatiere}
                onChange={(event) => setRechercheMatiere(event.target.value)}
                className="pl-9"
              />
            </div>
            <Select label={t('matieres.title')} {...register('matiere_id', { required: true })}>
              <option value="">—</option>
              {matieresDisponiblesFiltrees.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.nom}
                </option>
              ))}
            </Select>
          </div>
          <Select label={t('pedagogie.enseignant')} {...register('personnel_id')}>
            <option value="">—</option>
            {personnels?.map((p) => (
              <option key={p.id} value={p.id}>
                {p.nom_complet}
              </option>
            ))}
          </Select>
          {secondaire && (
            <Input label={t('pedagogie.coefficient')} type="number" step="0.5" {...register('coefficient', { required: true })} />
          )}
          <Input label={t('pedagogie.quota_horaire')} type="number" {...register('quota_horaire')} />
          <div className="col-span-2 flex items-end gap-2 sm:col-span-4">
            <Button type="submit" size="sm">
              {t('common.save')}
            </Button>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => {
                setShowForm(false)
              }}
            >
              {t('common.cancel')}
            </Button>
          </div>
        </form>
      )}

      {!affectations || affectations.length === 0 ? (
        <EmptyState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={affectations}
          cleLigne={(a) => a.id}
          placeholderRecherche={secondaire ? 'Rechercher une matière ou un enseignant…' : 'Rechercher une matière…'}
          messageVide="Aucune affectation pour cette classe."
          parPage={10}
          outils={
            selectedIds.size > 0 && can('pedagogie.manage') ? (
              <div className="flex items-center gap-2">
                <Button variant="secondary" size="sm" onClick={() => setShowCopyModal(true)}>
                  <Copy className="h-4 w-4" />
                  Copier vers une classe ({selectedIds.size})
                </Button>
                <Button variant="danger" size="sm" onClick={supprimerSelection}>
                  <Trash2 className="h-4 w-4" />
                  Supprimer ({selectedIds.size})
                </Button>
              </div>
            ) : undefined
          }
        />
      )}

      {showCompetences && (
        <AttribuerCompetencesModal
          classeId={classeId}
          titulaireId={titulaireId}
          onClose={() => setShowCompetences(false)}
          onAttribuees={() => {
            setShowCompetences(false)
            invalidate()
            queryClient.invalidateQueries({ queryKey: ['classe-competences', classeId] })
          }}
        />
      )}

      {showImport && (
        <ImportModal
          title="Importer les affectations Excel"
          url="/matieres/import"
          columns={['nom', 'enseignant', 'quota_horaire']}
          extraFields={{
            classe_id: classeId,
            cycle: secondaire ? 'secondaire' : 'primaire',
          }}
          onClose={() => setShowImport(false)}
          onImported={() => {
            setShowImport(false)
            invalidate()
          }}
        />
      )}

      {affectationEnEdition && (
        <EditAffectationModal
          affectation={affectationEnEdition}
          secondaire={secondaire}
          onClose={() => setAffectationEnEdition(null)}
          onSaved={() => {
            setAffectationEnEdition(null)
            invalidate()
          }}
        />
      )}

      {showCopyModal && (
        <CopierVersClasseModal
          classeId={classeId}
          affectationIds={[...selectedIds]}
          onClose={() => setShowCopyModal(false)}
          onCopied={() => {
            setShowCopyModal(false)
            setSelectedIds(new Set())
          }}
        />
      )}
    </div>
  )
}

function EditAffectationModal({
  affectation,
  secondaire,
  onClose,
  onSaved,
}: {
  affectation: ClasseMatiere
  secondaire: boolean
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const [serverError, setServerError] = useState<string | null>(null)
  const { data: personnels } = useQuery({
    queryKey: ['personnels', 'all'],
    queryFn: () => fetchPersonnels({ per_page: 100 }),
    enabled: can('pedagogie.manage'),
  })

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<ClasseMatiereUpdatePayload>({
    defaultValues: {
      personnel_id: affectation.enseignant?.id ?? undefined,
      coefficient: affectation.coefficient,
      quota_horaire: affectation.quota_horaire ?? undefined,
    },
  })

  const onSubmit = async (values: ClasseMatiereUpdatePayload) => {
    setServerError(null)
    try {
      await modifierAffectation(affectation.id, {
        personnel_id: values.personnel_id ? Number(values.personnel_id) : null,
        coefficient: secondaire ? Number(values.coefficient) : undefined,
        quota_horaire: values.quota_horaire ? Number(values.quota_horaire) : null,
      })
      succes('Affectation mise à jour.')
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={`Modifier — ${affectation.matiere.nom}`} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select label={t('pedagogie.enseignant')} error={errors.personnel_id?.message} {...register('personnel_id')}>
          <option value="">—</option>
          {personnels?.map((p) => (
            <option key={p.id} value={p.id}>
              {p.nom_complet}
            </option>
          ))}
        </Select>

        {secondaire && (
          <Input
            label={t('pedagogie.coefficient')}
            type="number"
            step="0.5"
            error={errors.coefficient?.message}
            {...register('coefficient', { required: true })}
          />
        )}

        <Input label={t('pedagogie.quota_horaire')} type="number" {...register('quota_horaire')} />

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

/**
 * Duplique les affectations sélectionnées vers une ou plusieurs autres
 * classes — le trio matière/enseignant/coefficient n'est plus à ressaisir
 * classe par classe. Une matière déjà affectée dans la classe visée est
 * simplement ignorée, jamais écrasée.
 *
 * L'enseignant fait exception, et l'écran le dit avant qu'on valide : au
 * primaire et en maternelle, c'est le titulaire de la classe d'arrivée qui
 * reprend la matière, pas celui de la classe copiée (cf. `enseignantPour`,
 * côté API). Chaque classe proposée affiche donc l'enseignant qu'elle
 * retiendra réellement.
 */
function CopierVersClasseModal({
  classeId,
  affectationIds,
  onClose,
  onCopied,
}: {
  classeId: number
  affectationIds: number[]
  onClose: () => void
  onCopied: () => void
}) {
  const { t } = useTranslation()
  const [recherche, setRecherche] = useState('')
  const [cibleIds, setCibleIds] = useState<Set<number>>(new Set())
  const [envoi, setEnvoi] = useState(false)

  const { data: classes, isLoading } = useQuery({ queryKey: ['classes', 'copier-affectations'], queryFn: () => fetchClasses() })

  // La classe d'origine n'a rien à faire dans ses propres cibles.
  const classesDisponibles = (classes ?? []).filter((c) => c.id !== classeId)
  const classesFiltrees = recherche
    ? classesDisponibles.filter((c) => c.nom.toLowerCase().includes(recherche.toLowerCase()))
    : classesDisponibles

  // Le titulaire ne s'impose qu'au primaire et en maternelle : au secondaire,
  // la matière suit son professeur spécialiste.
  const suitLeTitulaire = (classe: Classe) => !estSecondaire(classe.school?.type)
  const regleTitulaireVisible = classesDisponibles.some(suitLeTitulaire)

  const basculerCible = (id: number) =>
    setCibleIds((courant) => {
      const suivant = new Set(courant)
      suivant.has(id) ? suivant.delete(id) : suivant.add(id)
      return suivant
    })

  const copier = async () => {
    if (cibleIds.size === 0) {
      erreur('Choisissez au moins une classe.')
      return
    }

    setEnvoi(true)
    try {
      const { copiees, ignorees } = await copierAffectations({
        affectation_ids: affectationIds,
        classe_ids: [...cibleIds],
      })
      succes(
        ignorees > 0
          ? `${copiees} affectation(s) copiée(s), ${ignorees} déjà présente(s) ignorée(s).`
          : `${copiees} affectation(s) copiée(s).`,
      )
      onCopied()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={`Copier vers une classe (${affectationIds.length})`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Input
          label="Rechercher une classe"
          value={recherche}
          onChange={(e) => setRecherche(e.target.value)}
          autoFocus
        />

        {regleTitulaireVisible && (
          <p className="rounded-xl border border-navy-100 bg-cream-50 px-3.5 py-2.5 text-xs text-navy-500">
            Au primaire et en maternelle, la matière copiée revient au titulaire de la classe d'arrivée — pas à
            l'enseignant de la classe copiée.
          </p>
        )}

        {isLoading ? (
          <Spinner />
        ) : classesFiltrees.length === 0 ? (
          <p className="rounded-xl border border-navy-100 bg-cream-50 px-3.5 py-2.5 text-sm text-navy-400">
            Aucune classe trouvée.
          </p>
        ) : (
          <div className="flex max-h-64 flex-col divide-y divide-navy-50 overflow-y-auto rounded-xl border border-navy-100">
            {classesFiltrees.map((c) => (
              <label key={c.id} className="flex cursor-pointer items-start gap-2.5 px-3 py-2 text-sm hover:bg-cream-50">
                <input
                  type="checkbox"
                  checked={cibleIds.has(c.id)}
                  onChange={() => basculerCible(c.id)}
                  className="mt-0.5 h-4 w-4 flex-none rounded border-navy-300 text-gold-600 focus:ring-gold-500"
                />
                <span className="flex min-w-0 flex-col">
                  <span className="text-navy-800">{c.nom}</span>
                  {suitLeTitulaire(c) &&
                    (c.titulaire ? (
                      <span className="truncate text-xs text-navy-400">
                        Enseignant : {c.titulaire.nom_complet}
                      </span>
                    ) : (
                      <span className="text-xs text-gold-600">Sans titulaire — enseignant laissé vide</span>
                    ))}
                </span>
              </label>
            ))}
          </div>
        )}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={copier} disabled={envoi || cibleIds.size === 0}>
            <Copy className="h-4 w-4" />
            {envoi ? '…' : `Copier vers ${cibleIds.size || ''} classe(s)`}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
