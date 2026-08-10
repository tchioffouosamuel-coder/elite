import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { FileDown, FileSpreadsheet } from 'lucide-react'
import { fetchRemplissage, fetchClassement, ouvrirBulletin } from '@/features/resultats/api'
import { telechargerFichier } from '@/shared/lib/download'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Badge } from '@/shared/ui/Badge'
import { Spinner } from '@/shared/ui/Feedback'

const MENTION_LABEL: Record<string, { fr: string; en: string; tone: 'green' | 'gold' | 'red' }> = {
  felicitations: { fr: 'Félicitations', en: 'With honors', tone: 'green' },
  encouragements: { fr: 'Encouragements', en: 'Encouraged', tone: 'green' },
  avertissement_travail: { fr: 'Avert. travail', en: 'Academic warning', tone: 'gold' },
  blame_travail: { fr: 'Blâme travail', en: 'Academic reprimand', tone: 'red' },
}

export function ResultatsTab({ classeId }: { classeId: number }) {
  const { t, i18n } = useTranslation()
  const isFr = i18n.language === 'fr'

  const { data: remplissage, isLoading: loadingRemplissage } = useQuery({
    queryKey: ['remplissage', classeId],
    queryFn: () => fetchRemplissage(classeId),
  })
  const { data: classement, isLoading: loadingClassement } = useQuery({
    queryKey: ['classement', classeId],
    queryFn: () => fetchClassement(classeId),
  })

  return (
    <div className="flex flex-col gap-6">
      <Card>
        <h2 className="mb-4 font-display text-base font-bold tracking-tight text-navy-800">{t('resultats.remplissage')}</h2>
        {loadingRemplissage ? (
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
        )}
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
          <Table>
            <Thead>
              <tr>
                <Th>{t('resultats.rang')}</Th>
                <Th>{t('eleves.nom')}</Th>
                <Th>{t('resultats.moyenne')}</Th>
                <Th>{t('resultats.cote')}</Th>
                <Th>{t('resultats.mention')}</Th>
                <Th>{t('resultats.bulletin')}</Th>
              </tr>
            </Thead>
            <tbody>
              {classement?.eleves.map((e) => (
                <Tr key={e.eleve_id}>
                  <Td className="font-semibold">{e.rang ?? '—'}</Td>
                  <Td className="font-medium">{e.nom_complet}</Td>
                  <Td>{e.moyenne !== null ? e.moyenne.toFixed(2) : '—'}</Td>
                  <Td>
                    <Badge tone={e.cote?.startsWith('A') ? 'green' : e.cote === 'D' ? 'red' : 'gold'}>{e.cote}</Badge>
                  </Td>
                  <Td>
                    {e.mention && MENTION_LABEL[e.mention] && (
                      <Badge tone={MENTION_LABEL[e.mention].tone}>
                        {isFr ? MENTION_LABEL[e.mention].fr : MENTION_LABEL[e.mention].en}
                      </Badge>
                    )}
                  </Td>
                  <Td>
                    <button
                      onClick={() => ouvrirBulletin(e.eleve_id)}
                      className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
                    >
                      <FileDown className="h-3.5 w-3.5" />
                      PDF
                    </button>
                  </Td>
                </Tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>
    </div>
  )
}
