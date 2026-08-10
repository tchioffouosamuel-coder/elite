import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ListChecks } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { fetchRemplissage } from '@/features/resultats/api'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'

/** Vert au-delà de ce taux, rouge en dessous de la moitié — repère visuel de _smapp. */
function couleurTaux(taux: number): string {
  if (taux >= 90) return 'bg-green-500'
  if (taux >= 50) return 'bg-gold-500'
  return 'bg-red-500'
}

export function RemplissagePage() {
  const [classeId, setClasseId] = useState<number | ''>('')
  const [trimestreId, setTrimestreId] = useState<number | ''>('')

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })

  const classeActive = classeId ? Number(classeId) : null

  const { data, isLoading } = useQuery({
    queryKey: ['remplissage', classeActive, trimestreId],
    queryFn: () => fetchRemplissage(classeActive!, trimestreId ? Number(trimestreId) : undefined),
    enabled: classeActive !== null,
  })

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center gap-3">
        <ListChecks className="h-6 w-6 text-gold-500" />
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">
          État de remplissage des notes
        </h1>
      </div>

      <div className="flex flex-wrap gap-3">
        <Select
          label="Classe"
          value={classeId}
          onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}
          className="max-w-xs"
        >
          <option value="">Sélectionner une classe…</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </Select>

        <Select
          label="Trimestre"
          value={trimestreId}
          onChange={(e) => setTrimestreId(e.target.value ? Number(e.target.value) : '')}
          className="max-w-xs"
        >
          <option value="">Trimestre actif</option>
          {trimestres?.map((t) => (
            <option key={t.id} value={t.id}>
              {t.libelle}
            </option>
          ))}
        </Select>
      </div>

      {!classeActive ? (
        <Card>
          <EmptyState label="Choisissez une classe pour suivre la saisie des notes." />
        </Card>
      ) : isLoading ? (
        <Spinner />
      ) : !data?.matieres.length ? (
        <Card>
          <EmptyState label="Aucune matière affectée à cette classe." />
        </Card>
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>Matière</Th>
              <Th>Enseignant</Th>
              <Th>Avancement</Th>
              <Th>Taux</Th>
            </tr>
          </Thead>
          <tbody>
            {data.matieres.map((ligne) => (
              <Tr key={ligne.classe_matiere_id}>
                <Td className="font-medium">{ligne.matiere}</Td>
                <Td>{ligne.enseignant ?? '—'}</Td>
                <Td>
                  <div className="h-2 w-40 overflow-hidden rounded-full bg-navy-100">
                    <div
                      className={`h-full rounded-full ${couleurTaux(ligne.taux)}`}
                      style={{ width: `${Math.min(ligne.taux, 100)}%` }}
                    />
                  </div>
                </Td>
                <Td className="font-semibold">{ligne.taux.toFixed(1)} %</Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}
