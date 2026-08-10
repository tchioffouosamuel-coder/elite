import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, Clock, FileDown, ShieldAlert, Users } from 'lucide-react'
import {
  LIBELLES_SANCTION,
  fetchStatsDisciplinaires,
  ouvrirStatsDisciplinairesPdf,
  type StatsDisciplinairesClasse,
} from '@/features/statistiques/api'
import { Button } from '@/shared/ui/Button'
import { Card, StatCard } from '@/shared/ui/Card'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { erreur } from '@/shared/lib/alertes'

/**
 * Code couleur d'assiduité repris de _smapp (`getAbsenceColorClass`) :
 * vert en dessous de 10 h, orange jusqu'à 30 h, rouge au-delà.
 */
function tonAbsence(heures: number): string {
  if (heures > 30) return 'text-red-600'
  if (heures >= 10) return 'text-gold-600'
  return 'text-green-600'
}

export function StatsDisciplinairesPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['stats-disciplinaires'],
    queryFn: () => fetchStatsDisciplinaires(),
  })

  const colonnes: Colonne<StatsDisciplinairesClasse>[] = [
    {
      cle: 'classe',
      entete: 'Classe',
      valeur: (c) => c.classe.nom,
      cellule: (c) => <span className="font-semibold text-navy-900">{c.classe.nom}</span>,
    },
    { cle: 'effectif', entete: 'Effectif', valeur: (c) => c.bilan.effectif, cellule: (c) => c.bilan.effectif },
    {
      cle: 'sanctions',
      entete: 'Sanctions',
      valeur: (c) => c.total_sanctions,
      cellule: (c) => c.total_sanctions,
    },
    {
      cle: 'sanctionnes',
      entete: 'Élèves sanctionnés',
      valeur: (c) => c.eleves_sanctionnes,
      cellule: (c) => c.eleves_sanctionnes,
      masquerMobile: true,
    },
    {
      cle: 'hnj',
      entete: 'Heures non justifiées',
      valeur: (c) => c.bilan.total_hnj,
      cellule: (c) => (
        <span className={`font-semibold tabular-nums ${tonAbsence(c.bilan.total_hnj)}`}>
          {c.bilan.total_hnj.toFixed(1)} h
        </span>
      ),
    },
    {
      cle: 'moyenne',
      entete: 'Moyenne / élève',
      valeur: (c) => c.bilan.moyenne_hnj,
      cellule: (c) => `${c.bilan.moyenne_hnj.toFixed(1)} h`,
      masquerMobile: true,
    },
    {
      cle: 'plus_absent',
      entete: 'Élève le plus absent',
      valeur: (c) => c.bilan.eleve_plus_absent?.nom_complet,
      cellule: (c) =>
        c.bilan.eleve_plus_absent ? (
          <span className="text-navy-500">
            {c.bilan.eleve_plus_absent.nom_complet} ({c.bilan.eleve_plus_absent.heures_non_justifiees.toFixed(1)} h)
          </span>
        ) : (
          '—'
        ),
      masquerMobile: true,
    },
  ]

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  const c = data.consolide
  const moyenne = c.effectif > 0 ? c.heures_non_justifiees / c.effectif : 0
  const types = Object.keys(LIBELLES_SANCTION)

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Statistiques disciplinaires"
        sousTitre={`${data.trimestre.libelle} · ${data.classes.length} classe(s)`}
        icon={ShieldAlert}
        actions={
          <Button
            variant="secondary"
            onClick={() => ouvrirStatsDisciplinairesPdf().catch(() => erreur('Génération du PDF impossible.'))}
          >
            <FileDown className="h-4 w-4" />
            Document PDF
          </Button>
        }
      />

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Effectif" value={c.effectif} icon={Users} accent="navy" />
        <StatCard
          label="Élèves sanctionnés"
          value={c.eleves_sanctionnes}
          icon={AlertTriangle}
          accent="red"
          hint={`${c.total_sanctions} sanction(s)`}
        />
        <StatCard
          label="Heures non justifiées"
          value={`${c.heures_non_justifiees.toFixed(1)} h`}
          icon={Clock}
          accent="gold"
        />
        <StatCard label="Moyenne / élève" value={`${moyenne.toFixed(1)} h`} icon={Clock} accent="navy" />
      </div>

      <Card className="flex flex-col gap-3">
        <h2 className="font-display text-base font-bold text-navy-900">Répartition des sanctions</h2>
        <Table minWidth={420}>
          <Thead>
            <tr>
              <Th>Type de sanction</Th>
              <Th>Nombre</Th>
              <Th>Part</Th>
            </tr>
          </Thead>
          <tbody>
            {types.map((type) => {
              const nombre = c.sanctions_par_type[type] ?? 0
              const part = c.total_sanctions > 0 ? (nombre / c.total_sanctions) * 100 : 0

              return (
                <Tr key={type}>
                  <Td className="text-left font-medium">{LIBELLES_SANCTION[type]}</Td>
                  <Td>{nombre}</Td>
                  <Td>{part.toFixed(1)} %</Td>
                </Tr>
              )
            })}
            <Tr className="bg-cream-100/70 font-semibold">
              <Td className="text-left">Total</Td>
              <Td>{c.total_sanctions}</Td>
              <Td>—</Td>
            </Tr>
          </tbody>
        </Table>
      </Card>

      <div className="flex flex-col gap-3">
        <h2 className="font-display text-base font-bold text-navy-900">Comparatif des classes</h2>
        <DataTable
          colonnes={colonnes}
          lignes={data.classes}
          cleLigne={(c) => c.classe.id}
          placeholderRecherche="Rechercher une classe…"
          messageVide="Aucune classe pour cet établissement."
          largeurMin={900}
        />
      </div>
    </div>
  )
}
