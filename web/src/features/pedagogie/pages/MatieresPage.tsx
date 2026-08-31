import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { BookOpen, Download, ListChecks, Pencil, Plus, School, Trash2, Upload } from 'lucide-react'
import { useState } from 'react'
import {
  fetchMatieres,
  fetchMatiereClasses,
  fetchCompetences,
  deleteMatiere,
  batchDeleteMatieres,
  batchCompetenceMatieres,
} from '@/features/pedagogie/api'
import { fetchSchools } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Select } from '@/shared/ui/Select'
import { Select as FieldSelect } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { ImportModal } from '@/shared/ui/ImportModal'
import { telechargerFichier } from '@/shared/lib/download'
import { Modal } from '@/shared/ui/Modal'
import { estSecondaire } from '@/shared/lib/ecole'
import { confirmerSuppression, succes, erreur } from '@/shared/lib/alertes'
import type { Matiere } from '@/features/pedagogie/api'
import type { ApiError } from '@/shared/types/api'

/*
 * Colonnes attendues par l'import, identiques quel que soit le cycle (cf.
 * App\Imports\MatiereImport) : seul l'établissement visé change. Le modèle
 * minimal ne contient que les libellés et l'abréviation ; les colonnes
 * d'affectation restent acceptées par l'API.
 */
const COLONNES_MATIERES = [
  'nom',
  'nom_en',
  'abreviation',
]

export function MatieresPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [showImport, setShowImport] = useState(false)
  const [exportEnCours, setExportEnCours] = useState(false)
  const [matiereClasses, setMatiereClasses] = useState<Matiere | null>(null)
  const [schoolFilter, setSchoolFilter] = useState<number | null>(null)
  const [showCompetenceEnMasse, setShowCompetenceEnMasse] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['matieres'], queryFn: fetchMatieres })
  const { data: schools = [] } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
  const matieresFiltrees = schoolFilter === null
    ? data ?? []
    : (data ?? []).filter((matiere) => (matiere.school_id ?? matiere.school?.id) === schoolFilter)

  /**
   * École unique de ce type dans le complexe, s'il y en a exactement une — le
   * cas courant d'un établissement par cycle. Sert à faire correspondre le
   * cycle choisi à l'école visée sans que l'utilisateur ait à le redire.
   */
  const ecoleParType = (type: 'secondaire' | 'primaire' | 'maternelle') => {
    const correspondantes = schools.filter((school) => school.type === type)
    return correspondantes.length === 1 ? correspondantes[0] : null
  }

  const cibleCycle = (valeur: string) => {
    if (valeur !== 'secondaire' && valeur !== 'primaire' && valeur !== 'maternelle') return
    const ecole = ecoleParType(valeur)
    if (ecole) setSchoolFilter(ecole.id)
  }

  // Le secondaire classe ses matières par département.
  const secondaire = estSecondaire()
  const typeEcoleActive = useAuthStore((s) => s.activeSchool()?.type)
  const cycleDefaut = typeEcoleActive ?? 'secondaire'

  const handleExport = async () => {
    setExportEnCours(true)
    try {
      await telechargerFichier('/matieres/export', undefined, 'matieres.xlsx')
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setExportEnCours(false)
    }
  }

  const handleDelete = async (matiere: Matiere) => {
    const ok = await confirmerSuppression(`la matière ${matiere.nom}`)
    if (!ok) return

    try {
      await deleteMatiere(matiere.id)
      queryClient.invalidateQueries({ queryKey: ['matieres'] })
      succes('Matière supprimée.')
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const handleToggleSelect = (id: number) => {
    const newSelected = new Set(selectedIds)
    if (newSelected.has(id)) {
      newSelected.delete(id)
    } else {
      newSelected.add(id)
    }
    setSelectedIds(newSelected)
  }

  const handleSelectAll = (matieres: Matiere[]) => {
    if (selectedIds.size === matieres.length && matieres.length > 0) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(matieres.map((m) => m.id)))
    }
  }

  const handleBatchDelete = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return

    const ok = await confirmerSuppression(`${ids.length} matière(s)`)
    if (!ok) return

    try {
      await batchDeleteMatieres(ids)
      setSelectedIds(new Set())
      queryClient.invalidateQueries({ queryKey: ['matieres'] })
      succes(`${ids.length} matière(s) supprimée(s).`)
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const colonnes: Colonne<Matiere>[] = [
    {
      cle: 'selection',
      entete: data ? (
        <input
          type="checkbox"
          checked={selectedIds.size === data.length && data.length > 0}
          onChange={() => handleSelectAll(data)}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ) : null,
      cellule: (m) => (
        <input
          type="checkbox"
          checked={selectedIds.has(m.id)}
          onChange={() => handleToggleSelect(m.id)}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ),
    },
    {
      cle: 'nom',
      entete: t('matieres.nom'),
      valeur: (m) => m.nom,
      cellule: (m) => <span className="font-semibold text-navy-900">{m.nom}</span>,
    },
    {
      cle: 'abbreviation',
      entete: t('matieres.abbreviation'),
      valeur: (m) => m.abbreviation,
      cellule: (m) => m.abbreviation ?? '—',
    },
    {
      cle: 'school',
      entete: t('classes.ecole'),
      valeur: (m) => m.school?.name,
      cellule: (m) => <span className="text-navy-600">{m.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    ...(secondaire
      ? [
        {
          cle: 'departement',
          entete: t('personnel.departement'),
          valeur: (m: Matiere) => m.departement?.nom,
          cellule: (m: Matiere) => m.departement?.nom ?? '—',
          masquerMobile: true,
        },
      ]
      : [
        // La matière ne porte plus de barème : il appartient à la compétence
        // qu'elle sert, et c'est cette compétence que le bulletin note.
        {
          cle: 'competence',
          entete: t('competences.singulier'),
          valeur: (m: Matiere) => m.competence?.label_fr ?? '',
          cellule: (m: Matiere) =>
            m.competence ? (
              <span className="font-medium text-navy-700">{m.competence.label_fr}</span>
            ) : (
              <span className="text-xs text-gold-600">{t('competences.non_rattachee')}</span>
            ),
        },
        {
          cle: 'bareme_competence',
          entete: t('matieres.notation'),
          valeur: (m: Matiere) => m.competence?.notation ?? 0,
          cellule: (m: Matiere) => (m.competence ? `/ ${m.competence.notation}` : '—'),
          masquerMobile: true,
        },
      ]),
    {
      cle: 'enseignee',
      entete: t('matieres.enseignee_dans'),
      valeur: (m) => m.classes_count ?? 0,
      cellule: (m) =>
        m.classes_count ? (
          <button
            onClick={(e) => {
              e.stopPropagation()
              setMatiereClasses(m)
            }}
            className="font-medium text-navy-600 hover:text-gold-600 hover:underline"
          >
            {t('matieres.enseignee_dans_count', { count: m.classes_count })}
          </button>
        ) : (
          <span className="text-navy-300">{t('matieres.enseignee_dans_aucune')}</span>
        ),
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (m) =>
        can('pedagogie.manage') && (
          <div className="flex items-center gap-1">
            <button
              title={t('common.edit')}
              onClick={(e) => {
                e.stopPropagation()
                navigate(`/matieres/${m.id}/edit`)
              }}
              className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
            >
              <Pencil className="h-4 w-4" />
            </button>
            <button
              title={t('common.delete')}
              onClick={(e) => {
                e.stopPropagation()
                handleDelete(m)
              }}
              className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          </div>
        ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('matieres.title')}
        icon={BookOpen}
        actions={
          <>
            {selectedIds.size > 0 && can('pedagogie.manage') && !secondaire && (
              <Button variant="secondary" onClick={() => setShowCompetenceEnMasse(true)}>
                <ListChecks className="h-4 w-4" />
                {t('competences.attribuer_en_masse', { count: selectedIds.size })}
              </Button>
            )}
            {selectedIds.size > 0 && can('pedagogie.manage') && (
              <Button variant="danger" onClick={handleBatchDelete}>
                <Trash2 className="h-4 w-4" />
                Supprimer ({selectedIds.size})
              </Button>
            )}
            <Button variant="secondary" onClick={handleExport} disabled={exportEnCours}>
              <Download className="h-4 w-4" />
              {t('export.excel')}
            </Button>
            {can('pedagogie.manage') && (
              <Button
                variant="secondary"
                onClick={() => {
                  cibleCycle(cycleDefaut)
                  setShowImport(true)
                }}
              >
                <Upload className="h-4 w-4" />
                {t('import.title')}
              </Button>
            )}
            {can('pedagogie.manage') && (
              <Button onClick={() => navigate('/matieres/nouvelle')}>
                <Plus className="h-4 w-4" />
                {t('matieres.add')}
              </Button>
            )}
          </>
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={matieresFiltrees}
          cleLigne={(m) => m.id}
          placeholderRecherche={t('matieres.search_placeholder')}
          messageVide={t('matieres.empty')}
          outils={schools.length > 1 ? (
            <div className="w-full sm:w-56">
              <Select
                options={schools.map((school) => ({ value: school.id, label: school.name }))}
                value={schoolFilter === null ? null : schools
                  .filter((school) => school.id === schoolFilter)
                  .map((school) => ({ value: school.id, label: school.name }))[0] ?? null}
                placeholder={t('classes.all_schools_placeholder')}
                onChange={(option) => setSchoolFilter(option ? Number(option.value) : null)}
                isSearchable={false}
                isClearable
              />
            </div>
          ) : undefined}
        />
      )}

      {showImport && (
        <ImportModal
          title={t('import.title')}
          url="/matieres/import"
          columns={COLONNES_MATIERES}
          /*
           * Même école que le filtre de la liste : en mode « Toutes les
           * écoles » (aucune sélectionnée), l'API refuse l'import plutôt que
           * de deviner — deviner avait justement écrit sous la mauvaise école
           * en production, un import qui se disait réussi mais dont les
           * lignes restaient introuvables dans la liste.
           */
          extraFields={schoolFilter ? { school_id: schoolFilter } : undefined}
          /*
           * Le cycle est déclaré, pas déduit : rien dans le fichier (un nom,
           * une abréviation) ne dit vers quelle école du complexe il part.
           * Primaire et maternelle sont deux choix distincts — pas un
           * « Primaire / maternelle » ambigu — précisément pour que l'école
           * visée soit déclarée, pas devinée : chacun choisit sa propre école
           * dans le complexe (cf. `cibleCycle`). Les colonnes lues sont les
           * mêmes pour les trois : seule l'école cible change.
           */
          choix={{
            nom: 'cycle',
            label: t('matieres.import_cycle'),
            defaut: cycleDefaut,
            options: [
              { valeur: 'secondaire', libelle: t('matieres.cycle_secondaire'), colonnes: COLONNES_MATIERES },
              { valeur: 'primaire', libelle: t('matieres.cycle_primaire'), colonnes: COLONNES_MATIERES },
              { valeur: 'maternelle', libelle: t('matieres.cycle_maternelle'), colonnes: COLONNES_MATIERES },
            ],
          }}
          onChoixChange={cibleCycle}
          note={
            schoolFilter ? (
              <p className="text-xs text-navy-500">
                {t('matieres.import_ecole_visee')}{' '}
                <span className="font-semibold text-navy-700">
                  {schools.find((school) => school.id === schoolFilter)?.name}
                </span>
              </p>
            ) : (
              <p className="text-xs text-gold-600">{t('matieres.import_choisir_ecole')}</p>
            )
          }
          onClose={() => setShowImport(false)}
          onImported={() => queryClient.invalidateQueries({ queryKey: ['matieres'] })}
        />
      )}

      {matiereClasses && (
        <ClassesMatiereModal matiere={matiereClasses} onClose={() => setMatiereClasses(null)} />
      )}

      {showCompetenceEnMasse && (
        <CompetenceEnMasseModal
          ids={Array.from(selectedIds)}
          onClose={() => setShowCompetenceEnMasse(false)}
          onDone={() => {
            setShowCompetenceEnMasse(false)
            setSelectedIds(new Set())
            queryClient.invalidateQueries({ queryKey: ['matieres'] })
          }}
        />
      )}
    </div>
  )
}

/**
 * Rattache (ou détache) une même compétence à toutes les matières
 * sélectionnées — la saisie matière par matière devient vite fastidieuse dès
 * qu'un même bloc de compétence en couvre plusieurs.
 */
function CompetenceEnMasseModal({
  ids,
  onClose,
  onDone,
}: {
  ids: number[]
  onClose: () => void
  onDone: () => void
}) {
  const { t } = useTranslation()
  const [competenceId, setCompetenceId] = useState<number | '' | 'aucune'>('')
  const [envoi, setEnvoi] = useState(false)

  const { data: competences, isLoading } = useQuery({
    queryKey: ['competences', 'attribution-en-masse'],
    queryFn: fetchCompetences,
  })

  const valider = async () => {
    if (competenceId === '') return

    setEnvoi(true)
    try {
      const { modifiees, installees } = await batchCompetenceMatieres(
        ids,
        competenceId === 'aucune' ? null : Number(competenceId),
      )
      succes(
        installees > 0
          ? `${modifiees} matière(s) mise(s) à jour, installée(s) dans ${installees} classe(s).`
          : `${modifiees} matière(s) mise(s) à jour.`,
      )
      onDone()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={t('competences.attribuer_en_masse', { count: ids.length })} onClose={onClose}>
      <div className="flex flex-col gap-4">
        {isLoading ? (
          <Spinner />
        ) : (
          <FieldSelect
            label={t('competences.singulier')}
            value={competenceId}
            onChange={(e) => {
              const brut = e.target.value
              setCompetenceId(brut === '' ? '' : brut === 'aucune' ? 'aucune' : Number(brut))
            }}
          >
            <option value="">—</option>
            <option value="aucune">{t('competences.non_rattachee')} (détacher)</option>
            {competences?.map((competence) => (
              <option key={competence.id} value={competence.id}>
                {competence.label_fr}
              </option>
            ))}
          </FieldSelect>
        )}

        <div className="mt-1 flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button disabled={competenceId === '' || envoi} onClick={valider}>
            {t('common.save')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function ClassesMatiereModal({ matiere, onClose }: { matiere: Matiere; onClose: () => void }) {
  const { t } = useTranslation()
  const secondaire = estSecondaire()
  const { data, isLoading } = useQuery({
    queryKey: ['matiere-classes', matiere.id],
    queryFn: () => fetchMatiereClasses(matiere.id),
  })

  return (
    <Modal title={t('matieres.enseignee_dans_titre', { nom: matiere.nom })} onClose={onClose}>
      {isLoading ? (
        <Spinner />
      ) : !data || data.length === 0 ? (
        <EmptyState label={t('matieres.enseignee_dans_aucune')} />
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[420px] text-sm">
            <thead>
              <tr className="border-b border-navy-100 text-left text-xs font-semibold uppercase tracking-wide text-navy-400">
                <th className="py-2">{t('classes.title')}</th>
                <th className="py-2">{t('pedagogie.enseignant')}</th>
                {secondaire && <th className="py-2">{t('pedagogie.coefficient')}</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-navy-50">
              {data.map((ligne) => (
                <tr key={ligne.classe_matiere_id}>
                  <td className="py-2.5">
                    <span className="flex items-center gap-2 font-semibold text-navy-900">
                      <School className="h-4 w-4 text-navy-400" />
                      {ligne.classe?.nom ?? '—'}
                    </span>
                  </td>
                  <td className="py-2.5 text-navy-600">{ligne.enseignant?.nom_complet ?? '—'}</td>
                  {secondaire && <td className="py-2.5 tabular-nums text-navy-600">{ligne.coefficient}</td>}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Modal>
  )
}
