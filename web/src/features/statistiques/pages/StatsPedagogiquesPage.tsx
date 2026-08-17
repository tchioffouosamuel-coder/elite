import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { BarChart3, FileDown, GraduationCap, Percent, Trophy, Users } from 'lucide-react'
import {
  BANDES,
  LIBELLES_CATEGORIE,
  fetchStatsPedagogiques,
  ouvrirStatsPedagogiquesPdf,
  type CategoriePedagogique,
  type CleCategorie,
  type StatsPedagogiquesClasse,
} from '@/features/statistiques/api'
import { Button } from '@/shared/ui/Button'
import { Card, StatCard } from '@/shared/ui/Card'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { erreur } from '@/shared/lib/alertes'

const CATEGORIES = Object.keys(LIBELLES_CATEGORIE) as CleCategorie[]

/** Barre de comparaison des taux, en lecture directe. */
function Jauge({ valeur }: { valeur: number }) {
  return (
    <div className="flex items-center gap-2">
      <span className="h-2 min-w-24 flex-1 overflow-hidden rounded-full bg-navy-50">
        <span
          className="block h-full rounded-full bg-linear-to-r from-gold-300 to-gold-500"
          style={{ width: `${Math.max(1, Math.min(100, valeur))}%` }}
        />
      </span>
      <span className="w-14 flex-none text-right text-xs font-semibold tabular-nums text-navy-600">
        {valeur.toFixed(1)} %
      </span>
    </div>
  )
}

export function StatsPedagogiquesPage() {
  const { t } = useTranslation()
  const { data, isLoading, isError } = useQuery({
    queryKey: ['stats-pedagogiques'],
    queryFn: () => fetchStatsPedagogiques(),
  })

  const colonnes: Colonne<StatsPedagogiquesClasse>[] = [
    {
      cle: 'classe',
      entete: 'Classe',
      valeur: (c) => c.classe.nom,
      cellule: (c) => <span className="font-semibold text-navy-900">{c.classe.nom}</span>,
    },
    {
      cle: 'effectif',
      entete: 'Effectif',
      valeur: (c) => c.categories.total.effectif,
      cellule: (c) => c.categories.total.effectif,
    },
    {
      cle: 'admis',
      entete: 'Admis',
      valeur: (c) => c.categories.total.admis,
      cellule: (c) => c.categories.total.admis,
    },
    {
      cle: 'reussite',
      entete: 'Taux de réussite',
      valeur: (c) => c.categories.total.taux_reussite,
      cellule: (c) => <Jauge valeur={c.categories.total.taux_reussite} />,
      className: 'min-w-52',
    },
    {
      cle: 'moyenne',
      entete: 'Moyenne de classe',
      valeur: (c) => c.moyenne_classe,
      cellule: (c) => (
        <span className="font-semibold tabular-nums">{c.moyenne_classe?.toFixed(2) ?? '—'}</span>
      ),
    },
    {
      cle: 'honneur',
      entete: "Tableau d'honneur",
      valeur: (c) => c.categories.total.honneur,
      cellule: (c) => c.categories.total.honneur,
      masquerMobile: true,
    },
  ]

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  const total = data.consolide.total

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Statistiques pédagogiques"
        sousTitre={`${data.trimestre.libelle} · ${data.classes.length} classe(s)`}
        icon={BarChart3}
        actions={
          <Button
            variant="secondary"
            onClick={() => ouvrirStatsPedagogiquesPdf().catch(() => erreur('Génération du PDF impossible.'))}
          >
            <FileDown className="h-4 w-4" />
            Document PDF
          </Button>
        }
      />

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Effectif" value={total.effectif} icon={Users} accent="navy" />
        <StatCard label="Élèves notés" value={total.notes} icon={GraduationCap} accent="gold" />
        <StatCard
          label="Taux de réussite"
          value={`${total.taux_reussite.toFixed(1)} %`}
          icon={Percent}
          accent="green"
          hint={`${total.admis} admis`}
        />
        <StatCard label="Tableau d'honneur" value={total.honneur} icon={Trophy} accent="gold" />
      </div>

      <Card className="flex flex-col gap-3">
        <h2 className="font-display text-base font-bold text-navy-900">Consolidé par catégorie</h2>
        {/* Les taux sont rapportés à l'effectif de la catégorie, pas au nombre
            d'élèves notés — un élève sans note pèse dans le dénominateur. */}
        <Table minWidth={720}>
          <Thead>
            <tr>
              <Th>Catégorie</Th>
              <Th>Effectif</Th>
              <Th>Notés</Th>
              <Th>Admis</Th>
              <Th>Réussite</Th>
              <Th>Participation</Th>
              <Th>Honneur</Th>
            </tr>
          </Thead>
          <tbody>
            {CATEGORIES.map((cle) => {
              const c: CategoriePedagogique = data.consolide[cle]
              return (
                <Tr key={cle} className={cle === 'total' ? 'bg-cream-100/70 font-semibold' : undefined}>
                  <Td className="text-left font-medium">{LIBELLES_CATEGORIE[cle]}</Td>
                  <Td>{c.effectif}</Td>
                  <Td>{c.notes}</Td>
                  <Td>{c.admis}</Td>
                  <Td>{c.taux_reussite.toFixed(2)} %</Td>
                  <Td>{c.taux_participation.toFixed(2)} %</Td>
                  <Td>{c.honneur}</Td>
                </Tr>
              )
            })}
          </tbody>
        </Table>
      </Card>

      <Card className="flex flex-col gap-3">
        <h2 className="font-display text-base font-bold text-navy-900">Répartition par tranche de moyenne</h2>
        <Table minWidth={560}>
          <Thead>
            <tr>
              <Th>Catégorie</Th>
              {BANDES.map((b) => (
                <Th key={b.cle}>{b.libelle}</Th>
              ))}
            </tr>
          </Thead>
          <tbody>
            {CATEGORIES.map((cle) => (
              <Tr key={cle} className={cle === 'total' ? 'bg-cream-100/70 font-semibold' : undefined}>
                <Td className="text-left font-medium">{LIBELLES_CATEGORIE[cle]}</Td>
                {BANDES.map((b) => (
                  <Td key={b.cle}>{data.consolide[cle][b.cle]}</Td>
                ))}
              </Tr>
            ))}
          </tbody>
        </Table>
      </Card>

      <div className="flex flex-col gap-3">
        <h2 className="font-display text-base font-bold text-navy-900">Comparatif des classes</h2>
        <DataTable
          colonnes={colonnes}
          lignes={data.classes}
          cleLigne={(c) => c.classe.id}
          placeholderRecherche={t('classes.search_placeholder')}
          messageVide={t('classes.empty')}
          largeurMin={820}
        />
      </div>
    </div>
  )
}
