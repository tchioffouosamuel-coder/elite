import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, KeyRound, Archive, RotateCcw, FileSpreadsheet, FileText, Upload, Users } from 'lucide-react'
import { fetchPersonnels, archivePersonnel, reactivatePersonnel, type Personnel } from '@/features/personnel/api'
import { telechargerFichier, ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ImportModal } from '@/shared/ui/ImportModal'
import { PersonnelFormModal } from '@/features/personnel/pages/PersonnelFormModal'
import { CreateAccountModal } from '@/features/personnel/pages/CreateAccountModal'
import { confirmer, succes } from '@/shared/lib/alertes'
import { estSecondaire } from '@/shared/lib/ecole'

export function PersonnelListPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const [showForm, setShowForm] = useState(false)
  const [editingPersonnel, setEditingPersonnel] = useState<Personnel | null>(null)
  const [showImport, setShowImport] = useState(false)
  const [accountFor, setAccountFor] = useState<number | null>(null)

  // Recherche, tri et pagination sont pris en charge par DataTable côté client :
  // on charge donc la liste entière plutôt que page par page. À l'échelle d'un
  // établissement (quelques centaines d'agents) la charge reste négligeable.
  const { data, isLoading, isError } = useQuery({
    queryKey: ['personnels'],
    queryFn: () => fetchPersonnels({ per_page: 500 }),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['personnels'] })

  const secondaire = estSecondaire()

  const colonnes: Colonne<Personnel>[] = [
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
              onClick={() => setEditingPersonnel(p)}
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
              <Button onClick={() => setShowForm(true)}>
                <Plus className="h-4 w-4" />
                {t('personnel.add')}
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
          cleLigne={(p) => p.id}
          placeholderRecherche="Rechercher un nom, une fonction…"
          messageVide="Aucun personnel pour cet établissement."
          largeurMin={760}
        />
      )}

      {showForm && (
        <PersonnelFormModal
          onClose={() => setShowForm(false)}
          onCreated={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}
      {editingPersonnel && (
        <PersonnelFormModal
          personnel={editingPersonnel}
          onClose={() => setEditingPersonnel(null)}
          onCreated={() => {
            setEditingPersonnel(null)
            invalidate()
          }}
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
          columns={['nom_complet', 'fonction', 'matricule', 'telephone', 'email', 'date_embauche']}
          onClose={() => setShowImport(false)}
          onImported={invalidate}
        />
      )}
    </div>
  )
}
