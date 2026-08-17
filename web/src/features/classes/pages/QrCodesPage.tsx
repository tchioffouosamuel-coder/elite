import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { QrCode } from 'lucide-react'
import { fetchClasses, type Classe } from '@/features/classes/api'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ClasseQrModal } from '@/features/classes/pages/ClasseQrModal'

/**
 * Un code QR par classe, à imprimer et afficher au mur de la salle : scanné
 * par un enseignant en début de cours, il ouvre directement « Ma journée »
 * sur le cours prévu à cette heure — sans qu'il ait à chercher la classe.
 * Cette page centralise l'accès à tous les codes plutôt que de les laisser
 * enfouis un par un dans chaque fiche classe.
 */
export function QrCodesPage() {
  const { t } = useTranslation()
  const [classeQr, setClasseQr] = useState<Classe | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })

  const colonnes: Colonne<Classe>[] = [
    {
      cle: 'nom',
      entete: t('classes.nom'),
      valeur: (c) => c.nom,
      cellule: (c) => <span className="font-semibold text-navy-900">{c.nom}</span>,
    },
    {
      cle: 'ecole',
      entete: t('classes.ecole'),
      valeur: (c) => c.school?.name,
      cellule: (c) => (c.school ? <Badge tone="purple">{c.school.name}</Badge> : '—'),
      masquerMobile: true,
    },
    {
      cle: 'niveau',
      entete: t('classes.niveau'),
      valeur: (c) => c.niveau?.name_fr,
      cellule: (c) => (c.niveau ? <Badge tone="gold">{c.niveau.name_fr}</Badge> : '—'),
      masquerMobile: true,
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (c) => (
        <Button
          variant="secondary"
          onClick={(e) => {
            e.stopPropagation()
            setClasseQr(c)
          }}
          disabled={!c.qr_token}
        >
          <QrCode className="h-4 w-4" />
          {t('classes.qr_voir')}
        </Button>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('classes.qr_title')} sousTitre={t('classes.qr_subtitle')} icon={QrCode} />

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
        />
      )}

      {classeQr && <ClasseQrModal classe={classeQr} onClose={() => setClasseQr(null)} />}
    </div>
  )
}
