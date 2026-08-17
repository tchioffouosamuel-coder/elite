import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { clsx } from 'clsx'
import { Plus, Pencil, Trash2, School, Upload, AlertTriangle } from 'lucide-react'
import { fetchClasses, deleteClasse, fetchSousSystemes, fetchSchools, bulkUpdateClasses, fetchNiveaux, type Classe } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ImportModal } from '@/shared/ui/ImportModal'
import { Select } from '@/shared/ui/Select'
import { ClasseFormModal } from '@/features/classes/pages/ClasseFormModal'
import { estSecondaire } from '@/shared/lib/ecole'
import { confirmerSuppression, succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

export function ClassesListPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [showForm, setShowForm] = useState(false)
  const [showImport, setShowImport] = useState(false)
  const [editingClasse, setEditingClasse] = useState<Classe | null>(null)
  const [selectedClasses, setSelectedClasses] = useState<Set<number>>(new Set())
  const secondaire = estSecondaire()

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['classes'] })

  const { data, isLoading, isError } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const classesPleines = (data ?? []).filter((c) => c.capacite != null && (c.effectif ?? 0) > c.capacite)
  const { data: sousSystemes = [] } = useQuery({
    queryKey: ['sous-systemes'],
    queryFn: fetchSousSystemes,
  })
  const { data: schools = [] } = useQuery({
    queryKey: ['schools'],
    queryFn: fetchSchools,
  })
  const { data: niveaux = [] } = useQuery({
    queryKey: ['niveaux'],
    queryFn: fetchNiveaux,
  })

  const getSousSystemeNom = (classe: Classe) => {
    if (classe.sous_systeme?.nom) return classe.sous_systeme.nom
    if (!classe.sous_systeme_id) return '—'

    return sousSystemes.find((item) => item.id === classe.sous_systeme_id)?.nom ?? '—'
  }

  const toggleSelection = (classeId: number) => {
    const newSelection = new Set(selectedClasses)
    if (newSelection.has(classeId)) {
      newSelection.delete(classeId)
    } else {
      newSelection.add(classeId)
    }
    setSelectedClasses(newSelection)
  }

  const toggleSelectAll = () => {
    if (selectedClasses.size === data?.length) {
      setSelectedClasses(new Set())
    } else {
      setSelectedClasses(new Set(data?.map((c) => c.id) ?? []))
    }
  }

  const handleBulkUpdate = async (updates: { school_id?: number | null; sous_systeme_id?: number | null; niveau_id?: number | null }) => {
    try {
      await bulkUpdateClasses(Array.from(selectedClasses), updates)
      setSelectedClasses(new Set())
      invalidate()
      succes(t('classes.bulk_updated', { count: selectedClasses.size }))
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const colonnes: Colonne<Classe>[] = [
    ...(can('classes.manage')
      ? [
        {
          cle: 'checkbox',
          entete: (
            <input
              type="checkbox"
              checked={selectedClasses.size === data?.length && data?.length > 0}
              onChange={toggleSelectAll}
              onClick={(e) => e.stopPropagation()}
              className="rounded border-gray-300"
            />
          ),
          cellule: (c) => (
            <input
              type="checkbox"
              checked={selectedClasses.has(c.id)}
              onChange={(e) => {
                e.stopPropagation()
                toggleSelection(c.id)
              }}
              onClick={(e) => e.stopPropagation()}
              className="rounded border-gray-300"
            />
          ),
          largeur: '50px',
        } satisfies Colonne<Classe>,
      ]
      : []),
    {
      cle: 'nom',
      entete: t('classes.nom'),
      valeur: (c) => c.nom,
      cellule: (c) => <span className="font-semibold text-navy-900">{c.nom}</span>,
    },
    {
      cle: 'sigle',
      entete: 'Sigle',
      valeur: (c) => c.sigle,
      cellule: (c) => (c.sigle ? <span className="font-mono font-semibold text-gold-600">{c.sigle}</span> : '—'),
    },
    {
      cle: 'sous_systeme',
      entete: 'Sous-système',
      valeur: (c) => getSousSystemeNom(c),
      cellule: (c) => (getSousSystemeNom(c) === '—' ? '—' : <Badge tone="blue">{getSousSystemeNom(c)}</Badge>),
      masquerMobile: true,
    },
    {
      cle: 'effectif',
      entete: t('classes.effectif'),
      valeur: (c) => c.effectif ?? 0,
      cellule: (c) => {
        const depasse = c.capacite != null && (c.effectif ?? 0) > c.capacite
        return (
          <span className="inline-flex items-center gap-1.5 tabular-nums">
            <span>
              <span className={clsx('font-semibold', depasse && 'text-red-600')}>{c.effectif ?? 0}</span>
              {c.capacite != null && <span className={depasse ? 'text-red-400' : 'text-navy-300'}> / {c.capacite}</span>}
            </span>
            {depasse && (
              <span title={`Capacité dépassée : ${c.effectif ?? 0} élèves pour ${c.capacite} places`}>
                <AlertTriangle className="h-3.5 w-3.5 flex-none text-red-500" />
              </span>
            )}
          </span>
        )
      },
    },
    // Au primaire et en maternelle, la classe est tenue par un enseignant
    // unique (le titulaire) ; le professeur principal est une fonction du
    // secondaire, et la colonne resterait vide.
    secondaire
      ? {
        cle: 'professeur_principal',
        entete: t('classes.professeur_principal'),
        valeur: (c) => c.professeur_principal?.nom_complet,
        cellule: (c) => c.professeur_principal?.nom_complet ?? '—',
        masquerMobile: true,
      }
      : {
        cle: 'titulaire',
        entete: t('classes.titulaire'),
        valeur: (c) => c.titulaire?.nom_complet,
        cellule: (c) => c.titulaire?.nom_complet ?? '—',
        masquerMobile: true,
      },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (c) =>
        can('classes.manage') && (
          <div className="flex items-center gap-1">
            <button
              title={t('common.edit')}
              onClick={(e) => {
                e.stopPropagation()
                setEditingClasse(c)
              }}
              className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
            >
              <Pencil className="h-4 w-4" />
            </button>
            <button
              title={t('common.delete')}
              onClick={async (e) => {
                e.stopPropagation()
                const confirme = await confirmerSuppression(
                  t('classes.delete_confirm_quoi', { nom: c.nom }),
                  t('classes.delete_confirm_precision'),
                )
                if (!confirme) return
                try {
                  await deleteClasse(c.id)
                  invalidate()
                  succes(t('classes.deleted'))
                } catch (err) {
                  erreur((err as ApiError).message)
                }
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
        titre={t('classes.title')}
        icon={School}
        actions={
          can('classes.manage') && (
            <>
              <Button variant="secondary" onClick={() => setShowImport(true)}>
                <Upload className="h-4 w-4" />
                {t('import.title')}
              </Button>
              <Button onClick={() => setShowForm(true)}>
                <Plus className="h-4 w-4" />
                {t('classes.add')}
              </Button>
            </>
          )
        }
      />

      {classesPleines.length > 0 && (
        <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4">
          <AlertTriangle className="mt-0.5 h-5 w-5 flex-none text-red-500" />
          <div>
            <p className="font-medium text-navy-900">
              {classesPleines.length > 1
                ? `${classesPleines.length} classes ont dépassé leur capacité maximale`
                : '1 classe a dépassé sa capacité maximale'}
            </p>
            <p className="text-sm text-red-700">{classesPleines.map((c) => `${c.nom} (${c.effectif ?? 0}/${c.capacite})`).join(', ')}</p>
          </div>
        </div>
      )}

      {selectedClasses.size > 0 && can('classes.manage') && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <div className="flex items-center justify-between gap-4">
            <p className="font-medium text-navy-900">{selectedClasses.size} classe(s) sélectionnée(s)</p>
            <div className="flex flex-wrap gap-2">
              <div className="w-48">
                <Select
                  options={schools.map((s) => ({ value: s.id, label: s.name }))}
                  placeholder="Définir l'école"
                  onChange={(option) => {
                    if (option) handleBulkUpdate({ school_id: option.value as number })
                  }}
                  isSearchable
                  isClearable={false}
                />
              </div>

              <div className="w-56">
                <Select
                  options={sousSystemes.map((s) => ({ value: s.id, label: `${s.nom} (${s.code})` }))}
                  placeholder="Définir le sous-système"
                  onChange={(option) => {
                    if (option) handleBulkUpdate({ sous_systeme_id: option.value as number })
                  }}
                  isSearchable
                  isClearable={false}
                />
              </div>

              <div className="w-48">
                <Select
                  options={niveaux.map((n) => ({ value: n.id, label: n.name_fr }))}
                  placeholder="Définir le niveau"
                  onChange={(option) => {
                    if (option) handleBulkUpdate({ niveau_id: option.value as number })
                  }}
                  isSearchable
                  isClearable={false}
                />
              </div>

              <button
                onClick={() => setSelectedClasses(new Set())}
                className="rounded-lg px-4 py-2 text-sm font-medium text-navy-600 hover:bg-navy-50 whitespace-nowrap"
              >
                Annuler
              </button>
            </div>
          </div>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data}
          cleLigne={(c) => c.id}
          placeholderRecherche={t('classes.search_placeholder')}
          messageVide={t('classes.empty')}
          onLigneClick={(c) => navigate(`/classes/${c.id}`)}
        />
      )}

      {showForm && (
        <ClasseFormModal
          onClose={() => setShowForm(false)}
          onCreated={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}
      {editingClasse && (
        <ClasseFormModal
          classe={editingClasse}
          onClose={() => setEditingClasse(null)}
          onCreated={() => {
            setEditingClasse(null)
            invalidate()
          }}
        />
      )}

      {showImport && (
        <ImportModal
          title={t('import.title')}
          url="/classes/import"
          columns={['nom', 'sigle', 'capacite']}
          onClose={() => setShowImport(false)}
          onImported={invalidate}
        />
      )}
    </div>
  )
}
