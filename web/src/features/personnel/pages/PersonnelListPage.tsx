import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, KeyRound, Archive, RotateCcw, Trash2, FileSpreadsheet, FileText, Upload, Users } from 'lucide-react'
import {
  fetchPersonnels,
  archivePersonnel,
  reactivatePersonnel,
  deletePersonnel,
  batchDeletePersonnel,
  batchArchivePersonnel,
  type Personnel,
} from '@/features/personnel/api'
import { telechargerFichier, ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ImportModal } from '@/shared/ui/ImportModal'
import { CreateAccountModal } from '@/features/personnel/pages/CreateAccountModal'
import { confirmer, succes, erreur } from '@/shared/lib/alertes'
import { estSecondaire } from '@/shared/lib/ecole'
import type { ApiError } from '@/shared/types/api'

export function PersonnelListPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const navigate = useNavigate()
  const [showImport, setShowImport] = useState(false)
  const [accountFor, setAccountFor] = useState<number | null>(null)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  // Recherche, tri et pagination sont pris en charge par DataTable côté client :
  // on charge donc la liste entière plutôt que page par page. À l'échelle d'un
  // établissement (quelques centaines d'agents) la charge reste négligeable.
  const { data, isLoading, isError } = useQuery({
    queryKey: ['personnels'],
    queryFn: () => fetchPersonnels({ per_page: 500 }),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['personnels'] })

  const secondaire = estSecondaire()

  const handleToggleSelect = (id: number) => {
    const newSelected = new Set(selectedIds)
    if (newSelected.has(id)) {
      newSelected.delete(id)
    } else {
      newSelected.add(id)
    }
    setSelectedIds(newSelected)
  }

  const handleSelectAll = (personnels: Personnel[]) => {
    if (selectedIds.size === personnels.length && personnels.length > 0) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(personnels.map((p) => p.id)))
    }
  }

  const handleBatchArchive = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return

    const confirme = await confirmer({
      titre: `Archiver ${ids.length} membre(s) du personnel ?`,
      message: "Ils n'apparaîtront plus comme actifs. La réactivation reste possible à tout moment.",
      action: 'Archiver',
      destructif: false,
    })
    if (!confirme) return

    try {
      await batchArchivePersonnel(ids)
      setSelectedIds(new Set())
      invalidate()
      succes(`${ids.length} membre(s) du personnel archivé(s).`)
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const handleBatchDelete = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return

    const confirme = await confirmer({
      titre: `Supprimer ${ids.length} membre(s) du personnel ?`,
      message:
        "Cette action est irréversible. Leurs comptes de connexion seront également supprimés ; les affectations (titulaire, professeur principal, matières…) seront simplement libérées.",
      action: 'Supprimer',
    })
    if (!confirme) return

    try {
      const { deleted } = await batchDeletePersonnel(ids)
      setSelectedIds(new Set())
      invalidate()
      succes(`${deleted} membre(s) du personnel supprimé(s).`)
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const colonnes: Colonne<Personnel>[] = [
    ...(can('personnel.manage')
      ? [
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
            cellule: (p: Personnel) => (
              <input
                type="checkbox"
                checked={selectedIds.has(p.id)}
                onChange={() => handleToggleSelect(p.id)}
                className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
              />
            ),
          } satisfies Colonne<Personnel>,
        ]
      : []),
    {
      cle: 'nom',
      entete: t('personnel.nom_complet'),
      valeur: (p) => p.nom_complet,
      cellule: (p) => <span className="font-semibold text-navy-900">{p.nom_complet}</span>,
    },
    { cle: 'fonction', entete: t('personnel.fonction'), valeur: (p) => p.fonction, cellule: (p) => p.fonction },
    // Les départements n'existent qu'au secondaire : ailleurs la colonne
    // n'afficherait qu'une suite de tirets.
    ...(secondaire
      ? [
          {
            cle: 'departement',
            entete: t('personnel.departement'),
            valeur: (p: Personnel) => p.departement?.nom,
            cellule: (p: Personnel) => p.departement?.nom ?? '—',
            masquerMobile: true,
          },
        ]
      : []),
    {
      cle: 'telephone',
      entete: t('personnel.telephone'),
      valeur: (p) => p.telephone,
      cellule: (p) => p.telephone ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'statut',
      entete: t('personnel.statut'),
      valeur: (p) => p.statut,
      cellule: (p) => <Badge tone={p.statut === 'actif' ? 'green' : 'neutral'}>{t(`personnel.${p.statut}`)}</Badge>,
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (p) => (
        <div className="flex items-center gap-1">
          {can('personnel.manage') && (
            <button
              title={t('common.edit')}
              onClick={() => navigate(`/personnel/${p.id}/edit`)}
              className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
            >
              <Pencil className="h-4 w-4" />
            </button>
          )}
          {can('personnel.manage') && !p.a_un_compte && (
            <button
              title={t('personnel.create_account')}
              onClick={() => setAccountFor(p.id)}
              className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
            >
              <KeyRound className="h-4 w-4" />
            </button>
          )}
          {can('personnel.manage') &&
            (p.statut === 'actif' ? (
              <button
                title={t('common.archive')}
                onClick={async () => {
                  const confirme = await confirmer({
                    titre: `Archiver ${p.nom_complet} ?`,
                    message:
                      "Le compte n'apparaîtra plus dans les listes actives. L'historique est conservé et la réactivation reste possible.",
                    action: 'Archiver',
                  })
                  if (!confirme) return
                  await archivePersonnel(p.id)
                  invalidate()
                  succes('Personnel archivé.')
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
              >
                <Archive className="h-4 w-4" />
              </button>
            ) : (
              <button
                title={t('common.reactivate')}
                onClick={async () => {
                  await reactivatePersonnel(p.id)
                  invalidate()
                  succes('Personnel réactivé.')
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-green-600"
              >
                <RotateCcw className="h-4 w-4" />
              </button>
            ))}
          {can('personnel.manage') && (
            <button
              title={t('common.delete')}
              onClick={async () => {
                const confirme = await confirmer({
                  titre: `Supprimer ${p.nom_complet} ?`,
                  message:
                    "Cette action est irréversible. Son compte de connexion sera également supprimé ; les affectations (titulaire, professeur principal, matières…) seront simplement libérées.",
                  action: 'Supprimer',
                })
                if (!confirme) return
                try {
                  await deletePersonnel(p.id)
                  invalidate()
                  succes('Membre du personnel supprimé.')
                } catch (err) {
                  erreur((err as ApiError).message)
                }
              }}
              className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('personnel.title')}
        icon={Users}
        actions={
          <>
          <Button variant="secondary" onClick={() => ouvrirDocument('/personnels/fichier')}>
            <FileText className="h-4 w-4" />
            {t('personnel.fichier')}
          </Button>
          <Button variant="secondary" onClick={() => telechargerFichier('/personnels/export', undefined, 'personnel.xlsx')}>
            <FileSpreadsheet className="h-4 w-4" />
            {t('export.excel')}
          </Button>
          {can('personnel.manage') && (
            <Button variant="secondary" onClick={() => setShowImport(true)}>
              <Upload className="h-4 w-4" />
              {t('personnel.import')}
            </Button>
          )}
            {can('personnel.manage') && (
              <Button onClick={() => navigate('/personnel/nouveau')}>
                <Plus className="h-4 w-4" />
                {t('personnel.add')}
              </Button>
            )}
          </>
        }
      />

      {selectedIds.size > 0 && can('personnel.manage') && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="font-medium text-navy-900">{selectedIds.size} membre(s) du personnel sélectionné(s)</p>
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" onClick={handleBatchArchive}>
                <Archive className="h-4 w-4" />
                Archiver
              </Button>
              <Button variant="danger" onClick={handleBatchDelete}>
                <Trash2 className="h-4 w-4" />
                Supprimer
              </Button>
              <button
                onClick={() => setSelectedIds(new Set())}
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
          lignes={data.items}
          cleLigne={(p) => p.id}
          placeholderRecherche="Rechercher un nom, une fonction…"
          messageVide="Aucun personnel pour cet établissement."
          largeurMin={760}
        />
      )}

      {accountFor && (
        <CreateAccountModal
          personnelId={accountFor}
          onClose={() => setAccountFor(null)}
          onCreated={() => {
            setAccountFor(null)
            invalidate()
          }}
        />
      )}
      {showImport && (
        <ImportModal
          title={t('personnel.import')}
          url="/personnels/import"
          columns={[
            'Teachers name / NOMS DES ENSEIGNANTS',
            'Civilité (Mr/Mrs/Mlle)',
            'Matricules',
            'Numéro unique (CNI)',
            'N°CNPS',
            'Births',
            'Date Start',
            'Date end',
            'Date retraite',
            'Division of Origine',
            'Residence',
            'ORANGE',
            'MTN',
            'Married?',
            'NB children <21yrs',
            'Diplôme Prof',
            'Diplôme Academic',
            'Affectations / Duty post',
          ]}
          onClose={() => setShowImport(false)}
          onImported={invalidate}
        />
      )}
    </div>
  )
}
