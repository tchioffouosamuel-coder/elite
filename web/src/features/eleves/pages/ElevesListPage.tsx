import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, FileDown, FileSpreadsheet, FileText, Camera, Upload, UserRound } from 'lucide-react'
import { fetchEleves, uploadElevePhoto, type Eleve } from '@/features/eleves/api'
import { ouvrirBulletin } from '@/features/resultats/api'
import { telechargerFichier } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ImportModal } from '@/shared/ui/ImportModal'
import { EleveFormModal } from '@/features/eleves/pages/EleveFormModal'

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
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const [showForm, setShowForm] = useState(false)
  const [showImport, setShowImport] = useState(false)

  // Recherche, tri et pagination sont assurés par DataTable côté client : on
  // charge donc l'effectif complet de l'établissement. Au-delà de ~2000 élèves
  // il faudra rebasculer la pagination côté API.
  const { data, isLoading, isError } = useQuery({
    queryKey: ['eleves'],
    queryFn: () => fetchEleves({ per_page: 1000 }),
  })

  const colonnes: Colonne<Eleve>[] = [
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
      entete: t('eleves.nom'),
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
      cle: 'documents',
      entete: t('common.actions'),
      cellule: (e) =>
        e.classe ? (
          <div className="flex items-center gap-1">
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
          </div>
        ) : (
          '—'
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
              <Button onClick={() => setShowForm(true)}>
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

      {showForm && (
        <EleveFormModal
          onClose={() => setShowForm(false)}
          onCreated={() => {
            setShowForm(false)
            queryClient.invalidateQueries({ queryKey: ['eleves'] })
            queryClient.invalidateQueries({ queryKey: ['classes'] })
          }}
        />
      )}
      {showImport && (
        <ImportModal
          title={t('import.title')}
          url="/eleves/import"
          columns={['nom', 'prenom', 'sexe (M/F)', 'matricule', 'classe', 'date_naissance', 'lieu_naissance', 'tuteur_nom', 'tuteur_prenom', 'tuteur_telephone']}
          onClose={() => setShowImport(false)}
          onImported={() => {
            queryClient.invalidateQueries({ queryKey: ['eleves'] })
            queryClient.invalidateQueries({ queryKey: ['classes'] })
          }}
        />
      )}
    </div>
  )
}
