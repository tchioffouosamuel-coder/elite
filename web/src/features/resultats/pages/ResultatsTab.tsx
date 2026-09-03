import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { ChevronDown, FileDown, FileSpreadsheet } from 'lucide-react'
import { clsx } from 'clsx'
import { fetchRemplissage, fetchClassement, ouvrirBulletin, type Classement } from '@/features/resultats/api'
import { telechargerFichier } from '@/shared/lib/download'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Badge } from '@/shared/ui/Badge'
import { Spinner } from '@/shared/ui/Feedback'
import { useAuthStore } from '@/shared/store/authStore'

const MENTION_LABEL: Record<string, { fr: string; en: string; tone: 'green' | 'gold' | 'red' }> = {
  felicitations: { fr: 'Félicitations', en: 'With honors', tone: 'green' },
  encouragements: { fr: 'Encouragements', en: 'Encouraged', tone: 'green' },
  avertissement_travail: { fr: 'Avert. travail', en: 'Academic warning', tone: 'gold' },
  blame_travail: { fr: 'Blâme travail', en: 'Academic reprimand', tone: 'red' },
}

export function ResultatsTab({ classeId }: { classeId: number }) {
  const { t, i18n } = useTranslation()
  const isFr = i18n.language === 'fr'
  const estEnseignant = useAuthStore((s) => s.user?.est_enseignant ?? false)
  const [remplissageOuvert, setRemplissageOuvert] = useState(false)

  const { data: remplissage, isLoading: loadingRemplissage } = useQuery({
    queryKey: ['remplissage', classeId],
    queryFn: () => fetchRemplissage(classeId),
  })
  const { data: classement, isLoading: loadingClassement } = useQuery({
    queryKey: ['classement', classeId],
    queryFn: () => fetchClassement(classeId),
  })

  const colonnesClassement: Colonne<Classement['eleves'][number]>[] = [
    {
      cle: 'rang',
      entete: t('resultats.rang'),
      valeur: (e) => e.rang,
      cellule: (e) => <span className="font-semibold">{e.rang ?? '—'}</span>,
    },
    {
      cle: 'nom',
      entete: t('eleves.nom_complet'),
      valeur: (e) => e.nom_complet,
      cellule: (e) => <span className="font-medium">{e.nom_complet}</span>,
    },
    {
      cle: 'moyenne',
      entete: t('resultats.moyenne'),
      valeur: (e) => e.moyenne,
      cellule: (e) => (e.moyenne !== null ? e.moyenne.toFixed(2) : '—'),
    },
    {
      cle: 'cote',
      entete: t('resultats.cote'),
      valeur: (e) => e.cote,
      cellule: (e) => <Badge tone={e.cote?.startsWith('A') ? 'green' : e.cote === 'D' ? 'red' : 'gold'}>{e.cote}</Badge>,
    },
    {
      cle: 'mention',
      entete: t('resultats.mention'),
      cellule: (e) =>
        e.mention && MENTION_LABEL[e.mention] ? (
          <Badge tone={MENTION_LABEL[e.mention].tone}>{isFr ? MENTION_LABEL[e.mention].fr : MENTION_LABEL[e.mention].en}</Badge>
        ) : null,
    },
    ...(!estEnseignant
      ? [
        {
          cle: 'bulletin',
          entete: t('resultats.bulletin'),
          cellule: (e: Classement['eleves'][number]) => (
            <button
              onClick={() => ouvrirBulletin(e.eleve_id)}
              className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
            >
              <FileDown className="h-3.5 w-3.5" />
              PDF
            </button>
          ),
        } satisfies Colonne<Classement['eleves'][number]>,
      ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-6">
      <Card>
        <button
          type="button"
          onClick={() => setRemplissageOuvert((v) => !v)}
          aria-expanded={remplissageOuvert}
          className={clsx('flex w-full items-center justify-between gap-2 text-left', remplissageOuvert && 'mb-4')}
        >
          <h2 className="font-display text-base font-bold tracking-tight text-navy-800">{t('resultats.remplissage')}</h2>
          <ChevronDown className={clsx('h-4 w-4 flex-none text-navy-400 transition-transform', remplissageOuvert && 'rotate-180')} />
        </button>
        {remplissageOuvert &&
          (loadingRemplissage ? (
            <Spinner />
          ) : (
            <div className="flex flex-col gap-2.5">
              {remplissage?.matieres.map((m) => (
                <div key={m.classe_matiere_id} className="flex items-center gap-3">
                  <span className="w-40 flex-none truncate text-sm text-navy-600">{m.matiere}</span>
                  <div className="h-2 flex-1 rounded-full bg-cream-100">
                    <div
                      className={`h-2 rounded-full ${m.taux >= 80 ? 'bg-green-500' : m.taux >= 50 ? 'bg-gold-500' : 'bg-red-500'}`}
                      style={{ width: `${m.taux}%` }}
                    />
                  </div>
                  <span className="w-12 flex-none text-right text-sm font-semibold tabular-nums text-navy-700">{m.taux}%</span>
                </div>
              ))}
            </div>
          ))}
      </Card>

      <Card>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="font-display text-base font-bold tracking-tight text-navy-800">{t('resultats.classement')}</h2>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => telechargerFichier(`/classes/${classeId}/classement/export`, undefined, 'classement.xlsx')}
          >
            <FileSpreadsheet className="h-3.5 w-3.5" />
            {t('export.excel')}
          </Button>
        </div>
        {loadingClassement ? (
          <Spinner />
        ) : (
          <DataTable
            colonnes={colonnesClassement}
            lignes={classement?.eleves ?? []}
            cleLigne={(e) => e.eleve_id}
            placeholderRecherche={t('resultats.search_eleve')}
            messageVide={t('resultats.empty_classement')}
          />
        )}
      </Card>
    </div>
  )
}
