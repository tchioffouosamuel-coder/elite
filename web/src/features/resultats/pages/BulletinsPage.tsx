import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { FileText, FileDown, Gavel } from 'lucide-react'
import { fetchClasses, type Classe } from '@/features/classes/api'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { ouvrirBulletinsClasse, ouvrirPvConseilClasse } from '@/features/resultats/api'
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
 *
 * En mode agrégé (super admin, "Toutes les écoles"), la liste mélange des
 * classes de types différents : le moteur de bulletin (secondaire/primaire)
 * et la disponibilité du PV se décident donc par ligne, sur l'école de
 * CHAQUE classe — jamais sur une école active globale qui n'existe pas ici.
 */
export function BulletinsPage() {
  const [trimestreId, setTrimestreId] = useState<number | ''>('')
  const [enCours, setEnCours] = useState<{ classeId: number; type: 'bulletins' | 'pv' } | null>(null)

  const { data: classes, isLoading } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })

  const editer = async (classe: Classe) => {
    setEnCours({ classeId: classe.id, type: 'bulletins' })
    try {
      await ouvrirBulletinsClasse(classe.id, trimestreId ? Number(trimestreId) : undefined, classe.school?.type)
    } finally {
      setEnCours(null)
    }
  }

  const editerPv = async (classeId: number) => {
    setEnCours({ classeId, type: 'pv' })
    try {
      await ouvrirPvConseilClasse(classeId, trimestreId ? Number(trimestreId) : undefined)
    } finally {
      setEnCours(null)
    }
  }

  // Une seule colonne pour les deux libellés (secondaire/primaire) : la
  // liste peut mélanger des classes de types différents en mode agrégé, un
  // en-tête unique ne peut donc pas trancher pour toutes les lignes.
  const libelleResponsable = 'Responsable'

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
              <Th>{libelleResponsable}</Th>
              <Th />
            </tr>
          </Thead>
          <tbody>
            {classes.map((classe) => {
              const classeSecondaire = estSecondaire(classe.school?.type)

              return (
                <Tr key={classe.id}>
                  <Td className="font-semibold">{classe.nom}</Td>
                  <Td>{classe.niveau?.name_fr ?? '—'}</Td>
                  <Td>{classe.effectif ?? '—'}</Td>
                  <Td>{(classeSecondaire ? classe.professeur_principal : classe.titulaire)?.nom_complet ?? '—'}</Td>
                  <Td>
                    <div className="flex items-center gap-2">
                      <Button
                        size="sm"
                        variant="secondary"
                        onClick={() => editer(classe)}
                        disabled={enCours?.classeId === classe.id}
                      >
                        <FileDown className="h-4 w-4" />
                        {enCours?.classeId === classe.id && enCours.type === 'bulletins' ? 'Génération…' : 'Éditer les bulletins'}
                      </Button>
                      {classeSecondaire && (
                        <Button
                          size="sm"
                          variant="secondary"
                          onClick={() => editerPv(classe.id)}
                          disabled={enCours?.classeId === classe.id}
                        >
                          <Gavel className="h-4 w-4" />
                          {enCours?.classeId === classe.id && enCours.type === 'pv' ? 'Génération…' : 'PV du conseil'}
                        </Button>
                      )}
                    </div>
                  </Td>
                </Tr>
              )
            })}
          </tbody>
        </Table>
      )}
    </div>
  )
}
