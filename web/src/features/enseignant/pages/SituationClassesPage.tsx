import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Bus, Wallet } from 'lucide-react'
import clsx from 'clsx'
import { fetchMesInsolvables, fetchMesElevesBus, type EleveSituation } from '@/features/enseignant/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'

type Onglet = 'insolvables' | 'bus'

/** Regroupe les élèves par classe, dans l'ordre déjà trié par l'API. */
function parClasse(eleves: EleveSituation[]): [string, EleveSituation[]][] {
  const groupes = new Map<string, EleveSituation[]>()
  for (const e of eleves) {
    const cle = e.classe ?? '—'
    groupes.set(cle, [...(groupes.get(cle) ?? []), e])
  }
  return [...groupes.entries()]
}

/**
 * Ce qu'un enseignant voit des finances de ses classes : qui est insolvable,
 * qui prend le bus — des noms, jamais de montants (cf.
 * SituationEnseignantController). Pendant web de « Finances de mes classes »
 * de l'app mobile.
 */
export function SituationClassesPage() {
  const { t } = useTranslation()
  const [onglet, setOnglet] = useState<Onglet>('insolvables')

  const insolvables = useQuery({ queryKey: ['enseignant-insolvables'], queryFn: fetchMesInsolvables })
  const bus = useQuery({ queryKey: ['enseignant-bus'], queryFn: fetchMesElevesBus })
  const requete = onglet === 'insolvables' ? insolvables : bus
  const groupes = useMemo(() => parClasse(requete.data ?? []), [requete.data])

  const onglets: { id: Onglet; libelle: string; icone: typeof Wallet; total?: number }[] = [
    { id: 'insolvables', libelle: t('situationClasses.insolvables'), icone: Wallet, total: insolvables.data?.length },
    { id: 'bus', libelle: t('situationClasses.bus'), icone: Bus, total: bus.data?.length },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('situationClasses.title')} sousTitre={t('situationClasses.subtitle')} icon={Wallet} />

      <div role="tablist" className="inline-flex w-fit rounded-xl border border-navy-100 bg-white p-1">
        {onglets.map(({ id, libelle, icone: Icone, total }) => (
          <button
            key={id}
            type="button"
            role="tab"
            aria-selected={onglet === id}
            onClick={() => setOnglet(id)}
            className={clsx(
              'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-colors',
              onglet === id ? 'bg-navy-700 text-white' : 'text-navy-600 hover:bg-navy-50',
            )}
          >
            <Icone className="h-4 w-4" aria-hidden />
            {libelle}
            {total !== undefined && <span className="tabular-nums opacity-80">({total})</span>}
          </button>
        ))}
      </div>

      {requete.isLoading ? (
        <Spinner />
      ) : requete.isError ? (
        <ErrorState />
      ) : groupes.length === 0 ? (
        <EmptyState label={onglet === 'insolvables' ? t('situationClasses.noInsolvables') : t('situationClasses.noBus')} />
      ) : (
        groupes.map(([classe, eleves]) => (
          <Card key={classe}>
            <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-navy-500">
              {classe} <span className="font-normal normal-case text-navy-400">({eleves.length})</span>
            </h2>
            <Table>
              <Thead>
                <tr>
                  <Th>{t('situationClasses.student')}</Th>
                  <Th>{t('situationClasses.matricule')}</Th>
                  {onglet === 'bus' && <Th>{t('situationClasses.route')}</Th>}
                  {onglet === 'bus' && <Th>{t('situationClasses.stop')}</Th>}
                </tr>
              </Thead>
              <tbody>
                {eleves.map((e) => (
                  <Tr key={e.id}>
                    <Td className="font-medium">{e.nom_complet}</Td>
                    <Td className="tabular-nums text-navy-500">{e.matricule ?? '—'}</Td>
                    {onglet === 'bus' && <Td>{e.trajet ?? '—'}</Td>}
                    {onglet === 'bus' && <Td>{e.arret ?? '—'}</Td>}
                  </Tr>
                ))}
              </tbody>
            </Table>
          </Card>
        ))
      )}
    </div>
  )
}
