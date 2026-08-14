import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Plus,
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
} from 'lucide-react'
import { fetchEleves, archiveEleve, reactivateEleve, uploadElevePhoto, deleteEleve, batchDeleteEleves, type Eleve } from '@/features/eleves/api'
import { ouvrirBulletin } from '@/features/resultats/api'
import { telechargerFichier } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ImportModal } from '@/shared/ui/ImportModal'
import { DropdownMenu } from '@/shared/ui/DropdownMenu'
import { TransfererClasseModal } from '@/features/eleves/TransfererClasseModal'
import { TransfererEcoleModal } from '@/features/eleves/TransfererEcoleModal'
import { confirmer, succes } from '@/shared/lib/alertes'

function PhotoCell({ eleve, canManage }: { eleve: { id: number; nom_complet: string; photo_url: string | null }; canManage: boolean }) {
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
            onClick={() => inputRef.current?.click()}
            disabled={uploading}
            title="Photo"
            className="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-gold-500 text-navy-900 shadow-soft hover:bg-gold-600"
          >
            <Camera className="h-3 w-3" />
          </button>
          <input
            ref={inputRef}
            type="file"
            accept="image/jpeg,image/jpg,image/png"
            className="hidden"
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

  // Recherche, tri et pagination sont assurés par DataTable côté client : on
  // charge donc l'effectif complet de l'établissement. Au-delà de ~2000 élèves
  // il faudra rebasculer la pagination côté API.
  const { data, isLoading, isError } = useQuery({
    queryKey: ['eleves'],
    queryFn: () => fetchEleves({ per_page: 1000 }),
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
      titre: `Supprimer ${ids.length} élève(s) ?`,
      message: 'Cette action est irréversible. Les données de ces élèves seront définitivement supprimées.',
      action: 'Supprimer',
    })
    if (!confirme) return

    try {
      await batchDeleteEleves(ids)
      setSelectedIds(new Set())
      invalidate()
      succes(`${ids.length} élève(s) supprimé(s).`)
    } catch (error) {
      console.error('Erreur lors de la suppression:', error)
    }
  }

  const handleDeleteSingle = async (eleve: Eleve) => {
    const confirme = await confirmer({
      titre: `Supprimer ${eleve.nom_complet} ?`,
      message: 'Cette action est irréversible. Les données de cet élève seront définitivement supprimées.',
      action: 'Supprimer',
    })
    if (!confirme) return

    try {
      await deleteEleve(eleve.id)
      invalidate()
      succes('Élève supprimé.')
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
          onChange={() => handleSelectAll(data?.items ?? [])}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ) : null,
      cellule: (e) => (
        <input
          type="checkbox"
          checked={selectedIds.has(e.id)}
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
      cellule: (e) => (
        <div className="flex items-center gap-1">
          {e.classe && (
            <>
              <button
                onClick={() => ouvrirBulletin(e.id)}
                title={t('resultats.bulletin')}
                className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 transition-colors hover:bg-cream-100"
              >
                <FileDown className="h-3.5 w-3.5" />
                PDF
              </button>
              <button
                onClick={() => telechargerFichier(`/eleves/${e.id}/attestation-scolarite`, undefined, 'attestation.docx')}
                title={t('export.attestation')}
                className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 transition-colors hover:bg-cream-100"
              >
                <FileText className="h-3.5 w-3.5" />
                Word
              </button>
            </>
          )}
          {can('eleves.manage') && (
            <button
              title={t('common.edit')}
              onClick={() => navigate(`/eleves/${e.id}/edit`)}
              className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
            >
              <Pencil className="h-4 w-4" />
            </button>
          )}
          {can('eleves.manage') &&
            (e.statut === 'actif' ? (
              <button
                title={t('common.archive')}
                onClick={async () => {
                  const confirme = await confirmer({
                    titre: `Archiver ${e.nom_complet} ?`,
                    message: "L'élève n'apparaîtra plus comme actif. La réactivation reste possible à tout moment.",
                    action: 'Archiver',
                  })
                  if (!confirme) return
                  await archiveEleve(e.id)
                  invalidate()
                  succes('Élève archivé.')
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
              >
                <Archive className="h-4 w-4" />
              </button>
            ) : (
              <button
                title={t('common.reactivate')}
                onClick={async () => {
                  await reactivateEleve(e.id)
                  invalidate()
                  succes('Élève réactivé.')
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-green-600"
              >
                <RotateCcw className="h-4 w-4" />
              </button>
            ))}
          {can('eleves.manage') && (
            <button
              title={t('common.delete')}
              onClick={() => handleDeleteSingle(e)}
              className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          )}
          {can('eleves.manage') && (
            <DropdownMenu
              title="Transférer"
              items={[
                { label: 'Changer de classe', icon: ArrowRightLeft, onClick: () => setTransfertClasseEleve(e) },
                ...(isSuperAdmin
                  ? [{ label: 'Transférer vers une autre école', icon: Building2, onClick: () => setTransfertEcoleEleve(e) }]
                  : []),
              ]}
            />
          )}
        </div>
      ),
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
                Supprimer ({selectedIds.size})
              </Button>
            )}
            <Button variant="secondary" onClick={() => telechargerFichier('/eleves/export', undefined, 'eleves.xlsx')}>
              <FileSpreadsheet className="h-4 w-4" />
              {t('export.excel')}
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
                Transferts en masse
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
          lignes={data.items}
          cleLigne={(e) => e.id}
          placeholderRecherche="Rechercher un nom, un matricule, une classe…"
          messageVide="Aucun élève pour cet établissement."
          largeurMin={900}
        />
      )}

      {showImport && (
        <ImportModal
          title={t('import.title')}
          url="/eleves/import"
          columns={['nom_complet', 'sexe (M/F)', 'matricule', 'classe', 'date_naissance', 'lieu_naissance', 'tuteur_nom_complet', 'tuteur_telephone']}
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
