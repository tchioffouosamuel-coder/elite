import { useState } from 'react'
import { useTranslation } from 'react-i18next'
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
import { Select } from '@/shared/ui/Field'
import { erreur } from '@/shared/lib/alertes'
import { estSecondaire } from '@/shared/lib/ecole'
import { useAuthStore } from '@/shared/store/authStore'

/**
 * Code couleur d'assiduité repris de _smapp (`getAbsenceColorClass`) :
 * vert en dessous du premier seuil, orange jusqu'au second, rouge au-delà.
 * Au primaire et en maternelle l'absence se compte en journées : 2 et 6 jours
 * y valent la semaine de classe que représentent les 10 h et 30 h du secondaire.
 */
function tonAbsence(valeur: number, enJours: boolean): string {
  const [seuilMoyen, seuilHaut] = enJours ? [2, 6] : [10, 30]
  if (valeur > seuilHaut) return 'text-red-600'
  if (valeur >= seuilMoyen) return 'text-gold-600'
  return 'text-green-600'
}

export function StatsDisciplinairesPage() {
  const { t } = useTranslation()
  const user = useAuthStore((s) => s.user)
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)

  // Ces statistiques ne se calculent que pour une seule école : même en mode
  // "toutes les écoles", il en faut une précise. Filtre local, pour ne pas
  // changer l'école active globale de l'application.
  const ecolesAccessibles = user?.ecoles_accessibles ?? []
  const afficherFiltreEcole = !!user?.is_super_admin && ecolesAccessibles.length >= 2
  const [ecoleFiltreId, setEcoleFiltreId] = useState<number | null>(
    activeSchoolId ?? user?.school_id ?? ecolesAccessibles[0]?.id ?? null,
  )
  const ecoleFiltre = ecolesAccessibles.find((e) => e.id === ecoleFiltreId)

  const secondaire = estSecondaire(ecoleFiltre?.type)
  const enJours = !secondaire
  const suffixe = enJours ? ' j' : ' h'
  // Une journée est un entier : afficher « 1,0 j » laisserait croire à une demi-journée.
  const decimales = enJours ? 0 : 1
  const libelleJust = enJours ? 'Jours justifiés' : 'Heures justifiées'
  const libelleNonJust = enJours ? 'Jours non justifiés' : 'Heures non justifiées'
  const { data, isLoading, isError } = useQuery({
    queryKey: ['stats-disciplinaires', ecoleFiltreId],
    queryFn: () => fetchStatsDisciplinaires(undefined, ecoleFiltreId),
    enabled: ecoleFiltreId != null,
  })

  const colonnes: Colonne<StatsDisciplinairesClasse>[] = [
    {
      cle: 'classe',
      entete: 'Classe',
      valeur: (c) => c.classe.nom,
      cellule: (c) => <span className="font-semibold text-navy-900">{c.classe.nom}</span>,
    },
    { cle: 'effectif', entete: 'Effectif', valeur: (c) => c.bilan.effectif, cellule: (c) => c.bilan.effectif },
    // Seul le secondaire prononce des sanctions : ailleurs, ces deux colonnes
    // n'afficheraient que des zéros et masqueraient l'assiduité, qui est la
    // vraie matière du bilan disciplinaire de ces cycles.
    ...(secondaire
      ? [
        {
          cle: 'sanctions',
          entete: 'Sanctions',
          valeur: (c: StatsDisciplinairesClasse) => c.total_sanctions,
          cellule: (c: StatsDisciplinairesClasse) => c.total_sanctions,
        },
        {
          cle: 'sanctionnes',
          entete: 'Élèves sanctionnés',
          valeur: (c: StatsDisciplinairesClasse) => c.eleves_sanctionnes,
          cellule: (c: StatsDisciplinairesClasse) => c.eleves_sanctionnes,
          masquerMobile: true,
        },
      ]
      : []),
    {
      cle: 'hnj',
      entete: libelleNonJust,
      valeur: (c) => c.bilan.total_hnj,
      cellule: (c) => (
        <span className={`font-semibold tabular-nums ${tonAbsence(c.bilan.total_hnj ?? 0, enJours)}`}>
          {(c.bilan.total_hnj ?? 0).toFixed(decimales)}{suffixe}
        </span>
      ),
    },
    {
      cle: 'moyenne',
      entete: 'Moyenne / élève',
      valeur: (c) => c.bilan.moyenne_hnj,
      cellule: (c) => `${(c.bilan.moyenne_hnj ?? 0).toFixed(1)}${suffixe}`,
      masquerMobile: true,
    },
    {
      cle: 'plus_absent',
      entete: 'Élève le plus absent',
      valeur: (c) => c.bilan.eleve_plus_absent?.nom_complet,
      cellule: (c) =>
        c.bilan.eleve_plus_absent ? (
          <span className="text-navy-500">
            {c.bilan.eleve_plus_absent.nom_complet} (
            {c.bilan.eleve_plus_absent.heures_non_justifiees.toFixed(decimales)}
            {suffixe})
          </span>
        ) : (
          '—'
        ),
      masquerMobile: true,
    },
  ]

  if (!ecoleFiltreId) return <ErrorState message="Aucun établissement accessible." />
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
            onClick={() =>
              ouvrirStatsDisciplinairesPdf(undefined, ecoleFiltreId).catch(() => erreur('Génération du PDF impossible.'))
            }
          >
            <FileDown className="h-4 w-4" />
            Document PDF
          </Button>
        }
      />

      {afficherFiltreEcole && (
        <div className="w-64">
          <Select
            label="École"
            value={ecoleFiltreId ?? ''}
            onChange={(e) => setEcoleFiltreId(e.target.value ? Number(e.target.value) : null)}
          >
            {ecolesAccessibles.map((ecole) => (
              <option key={ecole.id} value={ecole.id}>
                {ecole.name}
              </option>
            ))}
          </Select>
        </div>
      )}

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Effectif" value={c.effectif} icon={Users} accent="navy" />
        {secondaire ? (
          <StatCard
            label="Élèves sanctionnés"
            value={c.eleves_sanctionnes}
            icon={AlertTriangle}
            accent="red"
            hint={`${c.total_sanctions} sanction(s)`}
          />
        ) : (
          <StatCard
            label={libelleJust}
            value={`${(c.heures_justifiees ?? 0).toFixed(decimales)}${suffixe}`}
            icon={Clock}
            accent="green"
          />
        )}
        <StatCard
          label={libelleNonJust}
          value={`${(c.heures_non_justifiees ?? 0).toFixed(decimales)}${suffixe}`}
          icon={Clock}
          accent="gold"
        />
        <StatCard label="Moyenne / élève" value={`${moyenne.toFixed(1)}${suffixe}`} icon={Clock} accent="navy" />
      </div>

      {secondaire && (
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
                const nombre = c.sanctions_par_type?.[type] ?? 0
                const totalSanctions = c.total_sanctions ?? 0
                const part = totalSanctions > 0 ? (nombre / totalSanctions) * 100 : 0

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
      )}

      <div className="flex flex-col gap-3">
        <h2 className="font-display text-base font-bold text-navy-900">Comparatif des classes</h2>
        <DataTable
          colonnes={colonnes}
          lignes={data.classes}
          cleLigne={(c) => c.classe.id}
          placeholderRecherche={t('classes.search_placeholder')}
          messageVide={t('classes.empty')}
          largeurMin={900}
        />
      </div>
    </div>
  )
}
