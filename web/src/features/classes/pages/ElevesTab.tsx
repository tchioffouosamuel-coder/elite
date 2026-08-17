import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { FileDown, FileText, FileSpreadsheet } from 'lucide-react'
import { fetchEleves, type Eleve } from '@/features/eleves/api'
import { ouvrirBulletin } from '@/features/resultats/api'
import { telechargerFichier, ouvrirDocument } from '@/shared/lib/download'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

export function ElevesTab({ classeId }: { classeId: number }) {
  const { t } = useTranslation()

  const { data, isLoading, isError } = useQuery({
    queryKey: ['eleves', { classe_id: classeId }],
    queryFn: () => fetchEleves({ classe_id: classeId, per_page: 1000 }),
  })

  const colonnes: Colonne<Eleve>[] = [
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
        <Badge tone={e.sexe === 'F' ? 'gold' : 'neutral'}>{e.sexe === 'F' ? t('eleves.feminin') : t('eleves.masculin')}</Badge>
      ),
    },
    {
      cle: 'tuteur',
      entete: t('eleves.tuteur'),
      valeur: (e) => e.tuteurs?.[0]?.nom_complet,
      cellule: (e) => (
        <span className="text-navy-500">{e.tuteurs?.[0] ? `${e.tuteurs[0].nom_complet} · ${e.tuteurs[0].telephone ?? '—'}` : '—'}</span>
      ),
      masquerMobile: true,
    },
    {
      cle: 'documents',
      entete: t('common.actions'),
      cellule: (e) => (
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
      ),
    },
  ]

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  return (
    <DataTable
      colonnes={colonnes}
      lignes={data.items}
      cleLigne={(e) => e.id}
      placeholderRecherche={t('classes.eleves_tab_search_placeholder')}
      messageVide={t('classes.eleves_tab_empty')}
      largeurMin={760}
      outils={
        <>
          <Button variant="secondary" size="sm" onClick={() => ouvrirDocument(`/classes/${classeId}/eleves/pdf`)}>
            <FileDown className="h-4 w-4" />
            PDF
          </Button>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => telechargerFichier(`/classes/${classeId}/eleves/word`, undefined, 'liste-eleves.docx')}
          >
            <FileText className="h-4 w-4" />
            Word
          </Button>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => telechargerFichier('/eleves/export', { classe_id: classeId }, 'liste-eleves.xlsx')}
          >
            <FileSpreadsheet className="h-4 w-4" />
            Excel
          </Button>
        </>
      }
    />
  )
}
