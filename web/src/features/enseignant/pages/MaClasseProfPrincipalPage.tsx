import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { School, Download, ShieldAlert, ClipboardList, Trophy } from 'lucide-react'
import { fetchMaClasseProfPrincipal, type AffectationClasse } from '@/features/enseignant/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { modifierAffectation, fetchTrimestres } from '@/features/pedagogie/api'
import { fetchSanctions, fetchBilanDisciplinaire, ouvrirBilanDisciplinairePdf, type TypeSanction, type StatutSanction } from '@/features/discipline/api'
import { fetchClassement } from '@/features/resultats/api'
import { useAuthStore } from '@/shared/store/authStore'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card, StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Select } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TYPE_TONE: Record<TypeSanction, 'gold' | 'red' | 'neutral' | 'blue'> = {
  avertissement: 'blue',
  blame: 'gold',
  corvee: 'gold',
  exclusion_temporaire: 'red',
  exclusion_definitive: 'red',
  autre: 'neutral',
}
const STATUT_TONE: Record<StatutSanction, 'gold' | 'green' | 'neutral'> = {
  en_attente: 'gold',
  confirmee: 'green',
  annulee: 'neutral',
}

function Jauge({ taux }: { taux: number | null }) {
  if (taux === null) return <span className="text-xs text-navy-300">—</span>
  return (
    <div className="flex items-center gap-2">
      <div className="h-2 w-24 overflow-hidden rounded-full bg-navy-100">
        <div className="h-full rounded-full bg-gold-500" style={{ width: `${taux}%` }} />
      </div>
      <span className="text-xs font-semibold tabular-nums text-navy-600">{taux}%</span>
    </div>
  )
}

/** Vue du professeur principal sur sa classe : affectations, sanctions, absences et résultats — lecture seule sauf les affectations. */
export function MaClasseProfPrincipalPage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const typeEcole = useAuthStore((s) => s.activeSchool()?.type)
  const [enModification, setEnModification] = useState<number | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['ma-classe-prof-principal'], queryFn: fetchMaClasseProfPrincipal })
  const classeId = data?.classe.id

  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]

  const { data: personnels } = useQuery({ queryKey: ['personnels'], queryFn: () => fetchPersonnels() })
  const enseignants = personnels?.filter((p) => p.fonction?.toLowerCase().includes('enseignant')) ?? []

  const { data: sanctions } = useQuery({
    queryKey: ['prof-principal-sanctions', classeId],
    queryFn: () => fetchSanctions({ classe_id: classeId }),
    enabled: !!classeId,
  })

  const { data: bilanAbsences } = useQuery({
    queryKey: ['prof-principal-absences', classeId, trimestreActif?.id],
    queryFn: () => fetchBilanDisciplinaire(classeId!, trimestreActif!.id),
    enabled: !!classeId && !!trimestreActif,
  })

  const { data: classement } = useQuery({
    queryKey: ['prof-principal-classement', classeId, trimestreActif?.id],
    queryFn: () => fetchClassement(classeId!, trimestreActif?.id, typeEcole),
    enabled: !!classeId,
  })

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  const telechargerBilanDisciplinaire = async () => {
    if (!trimestreActif) return
    try {
      await ouvrirBilanDisciplinairePdf(data.classe.id, trimestreActif.id)
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  const changerEnseignant = async (affectation: AffectationClasse, personnelId: number | null) => {
    try {
      await modifierAffectation(affectation.classe_matiere_id, { personnel_id: personnelId })
      succes('Affectation mise à jour.')
      setEnModification(null)
      queryClient.invalidateQueries({ queryKey: ['ma-classe-prof-principal'] })
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={data.classe.nom}
        sousTitre="Ma classe — affectations, discipline et résultats."
        icon={School}
        actions={
          <Button variant="secondary" onClick={telechargerBilanDisciplinaire} disabled={!trimestreActif}>
            <Download className="h-4 w-4" />
            Bilan disciplinaire
          </Button>
        }
      />

      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <StatCard label="Matières" value={data.affectations.length} icon={ClipboardList} accent="navy" />
        <StatCard
          label="Remplissage moyen"
          value={data.taux_remplissage_moyen === null ? '—' : `${data.taux_remplissage_moyen}%`}
          accent="gold"
        />
        <StatCard label="Sanctions" value={sanctions?.length ?? 0} icon={ShieldAlert} accent="red" />
        <StatCard
          label="Moy. HNJ"
          value={bilanAbsences ? bilanAbsences.moyenne_hnj : '—'}
          accent="navy"
        />
      </div>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Affectations</h2>
        {data.affectations.length === 0 ? (
          <EmptyState label="Aucune matière affectée à cette classe." />
        ) : (
          <Table>
            <Thead>
              <tr>
                <Th>{t('matieres.title')}</Th>
                <Th>{t('personnel.enseignant')}</Th>
                <Th>Remplissage</Th>
              </tr>
            </Thead>
            <tbody>
              {data.affectations.map((affectation) => (
                <Tr key={affectation.classe_matiere_id}>
                  <Td className="font-medium">{affectation.matiere}</Td>
                  <Td>
                    {enModification === affectation.classe_matiere_id ? (
                      <Select
                        defaultValue={affectation.personnel_id ?? ''}
                        onChange={(e) => changerEnseignant(affectation, e.target.value ? Number(e.target.value) : null)}
                        className="min-w-48"
                      >
                        <option value="">—</option>
                        {enseignants.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.nom_complet}
                          </option>
                        ))}
                      </Select>
                    ) : (
                      <button
                        onClick={() => setEnModification(affectation.classe_matiere_id)}
                        className="text-sm text-navy-700 underline decoration-navy-200 underline-offset-2 hover:text-navy-900"
                      >
                        {affectation.enseignant ?? 'Non affecté'}
                      </button>
                    )}
                  </Td>
                  <Td>
                    <Jauge taux={affectation.taux_remplissage} />
                  </Td>
                </Tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Sanctions</h2>
        {!sanctions || sanctions.length === 0 ? (
          <EmptyState label="Aucune sanction enregistrée pour cette classe." />
        ) : (
          <div className="flex flex-col divide-y divide-navy-50">
            {sanctions.map((s) => (
              <div key={s.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div>
                  <p className="text-sm font-semibold text-navy-800">{s.eleve.nom_complet}</p>
                  <p className="text-xs text-navy-400">
                    {s.motif} · {new Date(s.date_sanction).toLocaleDateString('fr-FR')}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <Badge tone={TYPE_TONE[s.type]}>{s.type}</Badge>
                  <Badge tone={STATUT_TONE[s.statut]}>{s.statut}</Badge>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Absences</h2>
        {!bilanAbsences ? (
          <EmptyState label="Aucune donnée d'absence pour l'instant." />
        ) : (
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div className="flex flex-col gap-0.5">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">Total</span>
              <span className="text-sm font-medium text-navy-800">{bilanAbsences.total_hnj}</span>
            </div>
            <div className="flex flex-col gap-0.5">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">Moyenne</span>
              <span className="text-sm font-medium text-navy-800">{bilanAbsences.moyenne_hnj}</span>
            </div>
            <div className="flex flex-col gap-0.5">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">Élève le plus absent</span>
              <span className="text-sm font-medium text-navy-800">{bilanAbsences.eleve_plus_absent?.nom_complet ?? '—'}</span>
            </div>
          </div>
        )}
      </Card>

      <Card>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-sm font-bold uppercase tracking-wide text-navy-500">Résultats</h2>
          <Trophy className="h-4 w-4 text-gold-500" />
        </div>
        {!classement || classement.eleves.length === 0 ? (
          <EmptyState label="Aucun résultat disponible pour l'instant." />
        ) : (
          <Table>
            <Thead>
              <tr>
                <Th>{t('eleves.nom_complet')}</Th>
                <Th>Moyenne</Th>
                <Th>Rang</Th>
                <Th>Mention</Th>
              </tr>
            </Thead>
            <tbody>
              {classement.eleves.map((e) => (
                <Tr key={e.eleve_id}>
                  <Td className="font-medium">{e.nom_complet}</Td>
                  <Td className="tabular-nums">{e.moyenne?.toFixed(2) ?? '—'}</Td>
                  <Td className="tabular-nums">{e.rang ?? '—'}</Td>
                  <Td>{e.mention ?? '—'}</Td>
                </Tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>
    </div>
  )
}
