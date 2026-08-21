import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Plus,
  Eye,
  Pencil,
  Archive,
  RotateCcw,
  FileDown,
  FileSpreadsheet,
  FileText,
  Camera,
  Upload,
  UserRound,
  Trash2,
  ArrowRightLeft,
  Building2,
  Repeat,
  BarChart3,
} from 'lucide-react'
import { fetchEleves, archiveEleve, reactivateEleve, uploadElevePhoto, deleteEleve, batchDeleteEleves, type Eleve } from '@/features/eleves/api'
import { fetchClasses, fetchSchools } from '@/features/classes/api'
import { ouvrirBulletin } from '@/features/resultats/api'
import { telechargerFichier, ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ImportModal } from '@/shared/ui/ImportModal'
import { DropdownMenu, type DropdownMenuItem } from '@/shared/ui/DropdownMenu'
import { TransfererClasseModal } from '@/features/eleves/TransfererClasseModal'
import { TransfererEcoleModal } from '@/features/eleves/TransfererEcoleModal'
import { confirmer, succes } from '@/shared/lib/alertes'
import { Select } from '@/shared/ui/Select'

function PhotoCell({ eleve, canManage }: { eleve: { id: number; nom_complet: string; photo_url: string | null }; canManage: boolean }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const inputRef = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)

  const handleFile = async (file: File | undefined) => {
    if (!file) return
    setUploading(true)
    try {
      await uploadElevePhoto(eleve.id, file)
      queryClient.invalidateQueries({ queryKey: ['eleves'] })
    } finally {
      setUploading(false)
    }
  }

  return (
    <div className="relative h-10 w-10 flex-none">
      {eleve.photo_url ? (
        <img src={eleve.photo_url} alt={eleve.nom_complet} className="h-10 w-10 rounded-full object-cover ring-1 ring-navy-100" />
      ) : (
        <span className="flex h-10 w-10 items-center justify-center rounded-full bg-navy-700 text-xs font-bold text-cream-50">
          {eleve.nom_complet
            .split(' ')
            .map((p) => p[0])
            .slice(0, 2)
            .join('')
            .toUpperCase()}
        </span>
      )}
      {canManage && (
        <>
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation()
              inputRef.current?.click()
            }}
            disabled={uploading}
            title={t('eleves.photo_title')}
            className="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-gold-500 text-navy-900 shadow-soft hover:bg-gold-600"
          >
            <Camera className="h-3 w-3" />
          </button>
          <input
            ref={inputRef}
            type="file"
            accept="image/jpeg,image/jpg,image/png"
            className="hidden"
            onClick={(e) => e.stopPropagation()}
            onChange={(e) => handleFile(e.target.files?.[0])}
          />
        </>
      )}
    </div>
  )
}

export function ElevesListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const can = useAuthStore((s) => s.can)
  const isSuperAdmin = useAuthStore((s) => s.user?.is_super_admin ?? false)
  const queryClient = useQueryClient()
  const [showImport, setShowImport] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [transfertClasseEleve, setTransfertClasseEleve] = useState<Eleve | null>(null)
  const [transfertEcoleEleve, setTransfertEcoleEleve] = useState<Eleve | null>(null)
  // La fiche classe renvoie ici avec `?classe=` : arriver sur la liste déjà
  // filtrée évite de rechercher la classe une seconde fois dans le sélecteur.
  const [searchParams] = useSearchParams()
  const [schoolFilter, setSchoolFilter] = useState<number | null>(null)
  const [classeFilter, setClasseFilter] = useState<number | null>(
    Number(searchParams.get('classe')) || null,
  )

  // Recherche, tri et pagination sont assurés par DataTable côté client : on
  // charge donc l'effectif complet de l'établissement. Au-delà de ~2000 élèves
  // il faudra rebasculer la pagination côté API.
  const { data, isLoading, isError } = useQuery({
    queryKey: ['eleves'],
    queryFn: () => fetchEleves({ per_page: 1000 }),
  })
  const { data: schools = [] } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
  const { data: classes = [] } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })

  const classesDisponibles = schoolFilter === null
    ? classes
    : classes.filter((classe) => (classe.school_id ?? classe.school?.id) === schoolFilter)
  const elevesFiltres = (data?.items ?? []).filter((eleve) => {
    const ecoleCorrespond = schoolFilter === null || eleve.school_id === schoolFilter
    const classeCorrespond = classeFilter === null || eleve.classe?.id === classeFilter
    return ecoleCorrespond && classeCorrespond
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['eleves'] })
    queryClient.invalidateQueries({ queryKey: ['classes'] })
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

  const handleSelectAll = (eleves: Eleve[]) => {
    if (selectedIds.size === eleves.length && eleves.length > 0) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(eleves.map((e) => e.id)))
    }
  }

  const handleBatchDelete = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return

    const confirme = await confirmer({
      titre: t('eleves.delete_batch_title', { count: ids.length }),
      message: t('eleves.delete_batch_message'),
      action: t('common.delete'),
    })
    if (!confirme) return

    try {
      await batchDeleteEleves(ids)
      setSelectedIds(new Set())
      invalidate()
      succes(t('eleves.batch_deleted', { count: ids.length }))
    } catch (error) {
      console.error('Erreur lors de la suppression:', error)
    }
  }

  const handleDeleteSingle = async (eleve: Eleve) => {
    const confirme = await confirmer({
      titre: t('eleves.delete_title', { nom: eleve.nom_complet }),
      message: t('eleves.delete_message'),
      action: t('common.delete'),
    })
    if (!confirme) return

    try {
      await deleteEleve(eleve.id)
      invalidate()
      succes(t('eleves.deleted'))
    } catch (error) {
      console.error('Erreur lors de la suppression:', error)
    }
  }

  const colonnes: Colonne<Eleve>[] = [
    {
      cle: 'selection',
      entete: data?.items ? (
        <input
          type="checkbox"
          checked={selectedIds.size === data.items.length && data.items.length > 0}
          onClick={(event) => event.stopPropagation()}
          onChange={() => handleSelectAll(data?.items ?? [])}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ) : null,
      cellule: (e) => (
        <input
          type="checkbox"
          checked={selectedIds.has(e.id)}
          onClick={(event) => event.stopPropagation()}
          onChange={() => handleToggleSelect(e.id)}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ),
    },
    {
      cle: 'photo',
      entete: '',
      cellule: (e) => <PhotoCell eleve={e} canManage={can('eleves.manage')} />,
    },
    {
      cle: 'matricule',
      entete: t('eleves.matricule'),
      valeur: (e) => e.matricule,
      cellule: (e) => <span className="font-mono text-xs">{e.matricule ?? '—'}</span>,
    },
    {
      cle: 'nom',
      entete: t('eleves.nom_complet'),
      valeur: (e) => e.nom_complet,
      cellule: (e) => <span className="font-semibold text-navy-900">{e.nom_complet}</span>,
    },
    {
      cle: 'sexe',
      entete: t('eleves.sexe'),
      valeur: (e) => e.sexe,
      cellule: (e) => (
        <Badge tone={e.sexe === 'F' ? 'gold' : 'neutral'}>
          {e.sexe === 'F' ? t('eleves.feminin') : t('eleves.masculin')}
        </Badge>
      ),
    },
    {
      cle: 'classe',
      entete: t('eleves.classe'),
      valeur: (e) => e.classe?.nom,
      cellule: (e) => e.classe?.nom ?? '—',
    },
    {
      cle: 'ecole',
      entete: t('classes.ecole'),
      valeur: (e) => e.school?.name,
      cellule: (e) => e.school?.name ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'tuteur',
      entete: t('eleves.tuteur'),
      valeur: (e) => e.tuteurs?.[0]?.nom_complet,
      cellule: (e) => (
        <span className="text-navy-500">
          {e.tuteurs?.[0] ? `${e.tuteurs[0].nom_complet} · ${e.tuteurs[0].telephone ?? '—'}` : '—'}
        </span>
      ),
      masquerMobile: true,
    },
    {
      cle: 'statut',
      entete: t('eleves.statut'),
      valeur: (e) => e.statut,
      cellule: (e) => <Badge tone={e.statut === 'actif' ? 'green' : 'neutral'}>{t(`eleves.${e.statut}`)}</Badge>,
      masquerMobile: true,
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (e) => {
        const menuItems: DropdownMenuItem[] = [
          ...(e.classe
            ? ([
              { label: t('resultats.bulletin'), icon: FileDown, onClick: () => ouvrirBulletin(e.id) },
              {
                label: t('export.attestation'),
                icon: FileText,
                onClick: () => telechargerFichier(`/eleves/${e.id}/attestation-scolarite`, undefined, 'attestation.docx'),
              },
            ] satisfies DropdownMenuItem[])
            : []),
          ...(can('eleves.manage')
            ? ([
              { label: t('eleves.changer_classe'), icon: ArrowRightLeft, onClick: () => setTransfertClasseEleve(e) },
              ...(isSuperAdmin
                ? [{ label: t('eleves.transferer_ecole'), icon: Building2, onClick: () => setTransfertEcoleEleve(e) }]
                : []),
              e.statut === 'actif'
                ? {
                  label: t('common.archive'),
                  icon: Archive,
                  onClick: async () => {
                    const confirme = await confirmer({
                      titre: t('eleves.archive_title', { nom: e.nom_complet }),
                      message: t('eleves.archive_message'),
                      action: t('common.archive'),
                    })
                    if (!confirme) return
                    await archiveEleve(e.id)
                    invalidate()
                    succes(t('eleves.archived'))
                  },
                }
                : {
                  label: t('common.reactivate'),
                  icon: RotateCcw,
                  onClick: async () => {
                    await reactivateEleve(e.id)
                    invalidate()
                    succes(t('eleves.reactivated'))
                  },
                },
              { label: t('common.delete'), icon: Trash2, onClick: () => handleDeleteSingle(e), danger: true },
            ] satisfies DropdownMenuItem[])
            : []),
        ]

        return (
          <div className="flex items-center gap-1">
            <button
              title={t('common.view')}
              onClick={(event) => {
                event.stopPropagation()
                navigate(`/eleves/${e.id}`)
              }}
              className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
            >
              <Eye className="h-4 w-4" />
            </button>
            {can('eleves.manage') && (
              <button
                title={t('common.edit')}
                onClick={(event) => {
                  event.stopPropagation()
                  navigate(`/eleves/${e.id}/edit`)
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
              >
                <Pencil className="h-4 w-4" />
              </button>
            )}
            {menuItems.length > 0 && <DropdownMenu items={menuItems} />}
          </div>
        )
      },
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('eleves.title')}
        icon={UserRound}
        actions={
          <>
            {selectedIds.size > 0 && can('eleves.manage') && (
              <Button variant="danger" onClick={handleBatchDelete}>
                <Trash2 className="h-4 w-4" />
                {t('eleves.delete_selection', { count: selectedIds.size })}
              </Button>
            )}
            <Button variant="secondary" onClick={() => telechargerFichier('/eleves/export', undefined, 'eleves.xlsx')}>
              <FileSpreadsheet className="h-4 w-4" />
              {t('export.excel')}
            </Button>
            <Button
              variant="secondary"
              onClick={() => ouvrirDocument('/eleves/pdf', schoolFilter ? { school_id: schoolFilter } : undefined)}
            >
              <FileDown className="h-4 w-4" />
              Liste par école
            </Button>
            <Button variant="secondary" onClick={() => navigate('/eleves/rapports')}>
              <BarChart3 className="h-4 w-4" />
              Rapports
            </Button>
            {can('eleves.manage') && (
              <Button variant="secondary" onClick={() => setShowImport(true)}>
                <Upload className="h-4 w-4" />
                {t('import.title')}
              </Button>
            )}
            {can('eleves.manage') && (
              <Button variant="secondary" onClick={() => navigate('/eleves/transferts')}>
                <Repeat className="h-4 w-4" />
                {t('nav.transferts')}
              </Button>
            )}
            {can('eleves.manage') && (
              <Button onClick={() => navigate('/eleves/nouveau')}>
                <Plus className="h-4 w-4" />
                {t('eleves.add')}
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
          lignes={elevesFiltres}
          cleLigne={(e) => e.id}
          onLigneClick={(e) => navigate(`/eleves/${e.id}`)}
          placeholderRecherche={t('eleves.search_placeholder')}
          messageVide={t('eleves.empty')}
          outils={schools.length > 1 || classes.length > 0 ? (
            <>
              {schools.length > 1 && (
                <div className="w-full sm:w-52">
                  <Select
                    options={schools.map((school) => ({ value: school.id, label: school.name }))}
                    value={schoolFilter === null ? null : schools
                      .filter((school) => school.id === schoolFilter)
                      .map((school) => ({ value: school.id, label: school.name }))[0] ?? null}
                    placeholder={t('nav.toutesLesEcoles')}
                    onChange={(option) => {
                      setSchoolFilter(option ? Number(option.value) : null)
                      setClasseFilter(null)
                    }}
                    isSearchable={false}
                    isClearable
                  />
                </div>
              )}
              <div className="w-full sm:w-52">
                <Select
                  options={classesDisponibles.map((classe) => ({ value: classe.id, label: classe.nom }))}
                  value={classeFilter === null ? null : classesDisponibles
                    .filter((classe) => classe.id === classeFilter)
                    .map((classe) => ({ value: classe.id, label: classe.nom }))[0] ?? null}
                  placeholder={t('eleves.toutes_classes')}
                  onChange={(option) => setClasseFilter(option ? Number(option.value) : null)}
                  isSearchable
                  isClearable
                />
              </div>
            </>
          ) : undefined}
          largeurMin={900}
        />
      )}

      {showImport && (
        <ImportModal
          title={t('import.title')}
          url="/eleves/import"
          columns={[
            'IDEleves (matricule)',
            'nom_eleves',
            'sexe_eleves (M/F)',
            'ddn_eleves (AAAAMMJJ)',
            'lieu_naiss',
            'nationalité',
            'numero_acte_naissance',
            'Nom_classe',
            'niveau_classe',
            'categorie_ecole',
            'etat_eleves (Actif/Inactif)',
            'redoublant / refugies / deplace_interne (OUI/NON)',
            'nom_parents',
            'tel_pere',
            'fonction_pere',
            'nom_mere',
            'tel_mere',
            'fonction_mere',
            'tel_autre',
            'adresse_parent',
          ]}
          onClose={() => setShowImport(false)}
          onImported={invalidate}
        />
      )}

      {transfertClasseEleve && (
        <TransfererClasseModal
          eleve={transfertClasseEleve}
          onClose={() => setTransfertClasseEleve(null)}
          onDone={() => {
            setTransfertClasseEleve(null)
            invalidate()
          }}
        />
      )}

      {transfertEcoleEleve && (
        <TransfererEcoleModal
          eleve={transfertEcoleEleve}
          onClose={() => setTransfertEcoleEleve(null)}
          onDone={() => {
            setTransfertEcoleEleve(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}
