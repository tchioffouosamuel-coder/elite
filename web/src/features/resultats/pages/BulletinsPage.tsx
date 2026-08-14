import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { FileText, FileDown } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { ouvrirBulletinsClasse } from '@/features/resultats/api'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { estSecondaire } from '@/shared/lib/ecole'

/**
 * Édition des bulletins par classe : comme report_cards.php dans _smapp, on
 * choisit une classe et on obtient un seul PDF contenant le bulletin de chacun
 * de ses élèves.
 */
export function BulletinsPage() {
  const [trimestreId, setTrimestreId] = useState<number | ''>('')
  const [enCours, setEnCours] = useState<number | null>(null)
  // Le primaire et la maternelle n'ont pas de professeur principal : la classe
  // est tenue par son enseignant titulaire.
  const secondaire = estSecondaire()

  const { data: classes, isLoading } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })

  const editer = async (classeId: number) => {
    setEnCours(classeId)
    try {
      await ouvrirBulletinsClasse(classeId, trimestreId ? Number(trimestreId) : undefined)
    } finally {
      setEnCours(null)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center gap-3">
        <FileText className="h-6 w-6 text-gold-500" />
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">Bulletins de notes</h1>
      </div>

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

      {isLoading ? (
        <Spinner />
      ) : !classes?.length ? (
        <Card>
          <EmptyState label="Aucune classe." />
        </Card>
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>Classe</Th>
              <Th>Niveau</Th>
              <Th>Effectif</Th>
              <Th>{secondaire ? 'Professeur principal' : 'Enseignant'}</Th>
              <Th />
            </tr>
          </Thead>
          <tbody>
            {classes.map((classe) => (
              <Tr key={classe.id}>
                <Td className="font-semibold">{classe.nom}</Td>
                <Td>{classe.niveau?.name_fr ?? '—'}</Td>
                <Td>{classe.effectif ?? '—'}</Td>
                <Td>{(secondaire ? classe.professeur_principal : classe.titulaire)?.nom_complet ?? '—'}</Td>
                <Td>
                  <Button size="sm" variant="secondary" onClick={() => editer(classe.id)} disabled={enCours === classe.id}>
                    <FileDown className="h-4 w-4" />
                    {enCours === classe.id ? 'Génération…' : 'Éditer les bulletins'}
                  </Button>
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}
