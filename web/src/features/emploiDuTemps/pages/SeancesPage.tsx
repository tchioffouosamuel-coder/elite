import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardCheck, UserCheck } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import {
  enregistrerAppel,
  fetchAppel,
  fetchSeances,
  type LigneAppel,
  type Seance,
} from '@/features/emploiDuTemps/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'

const STATUTS: { valeur: LigneAppel['statut']; libelle: string }[] = [
  { valeur: 'present', libelle: 'Présent' },
  { valeur: 'absent', libelle: 'Absent' },
  { valeur: 'retard', libelle: 'Retard' },
  { valeur: 'renvoye', libelle: 'Renvoyé' },
]

export function SeancesPage() {
  const can = useAuthStore((s) => s.can)
  const [classeId, setClasseId] = useState<number | ''>('')
  const [seanceAppel, setSeanceAppel] = useState<Seance | null>(null)

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const classeActive = classeId ? Number(classeId) : null

  const { data: seances, isLoading } = useQuery({
    queryKey: ['seances', classeActive],
    queryFn: () => fetchSeances(classeActive!),
    enabled: classeActive !== null,
  })

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center gap-3">
        <ClipboardCheck className="h-6 w-6 text-gold-500" />
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">Séances &amp; appel</h1>
      </div>

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

      {!classeActive ? (
        <Card>
          <EmptyState label="Choisissez une classe pour afficher ses séances." />
        </Card>
      ) : isLoading ? (
        <Spinner />
      ) : !seances?.length ? (
        <Card>
          <EmptyState label="Aucune séance. Générez-les depuis l'emploi du temps de la classe." />
        </Card>
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>Date</Th>
              <Th>Horaire</Th>
              <Th>Matière</Th>
              <Th>Enseignant</Th>
              <Th>Statut</Th>
              <Th>Absents</Th>
              <Th />
            </tr>
          </Thead>
          <tbody>
            {seances.map((seance) => (
              <Tr key={seance.id}>
                <Td className="font-medium">{new Date(seance.date_seance).toLocaleDateString('fr-FR')}</Td>
                <Td>
                  {seance.heure_debut}–{seance.heure_fin}
                </Td>
                <Td className="font-medium">{seance.matiere}</Td>
                <Td>{seance.enseignant ?? '—'}</Td>
                <Td>
                  <Badge tone={seance.statut === 'effectuee' ? 'green' : seance.statut === 'annulee' ? 'red' : 'neutral'}>
                    {seance.statut === 'effectuee' ? 'Effectuée' : seance.statut === 'annulee' ? 'Annulée' : 'Prévue'}
                  </Badge>
                </Td>
                <Td>{seance.absents > 0 ? <Badge tone="red">{seance.absents}</Badge> : '—'}</Td>
                <Td>
                  {can('appel.manage') && (
                    <Button size="sm" variant="secondary" onClick={() => setSeanceAppel(seance)}>
                      <UserCheck className="h-4 w-4" />
                      Faire l'appel
                    </Button>
                  )}
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}

      {seanceAppel && (
        <AppelModal seance={seanceAppel} classeId={classeActive!} onClose={() => setSeanceAppel(null)} />
      )}
    </div>
  )
}

function AppelModal({ seance, classeId, onClose }: { seance: Seance; classeId: number; onClose: () => void }) {
  const queryClient = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['appel', seance.id], queryFn: () => fetchAppel(seance.id) })
  const [lignes, setLignes] = useState<LigneAppel[]>([])

  useEffect(() => {
    if (data) setLignes(data.lignes)
  }, [data])

  const enregistrement = useMutation({
    mutationFn: () =>
      enregistrerAppel(
        seance.id,
        lignes.map((l) => ({
          eleve_id: l.eleve_id,
          statut: l.statut,
          justifie: l.justifie,
          remarque: l.remarque,
        })),
      ),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['seances', classeId] })
      queryClient.invalidateQueries({ queryKey: ['appel', seance.id] })
      onClose()
    },
  })

  const modifier = (eleveId: number, champs: Partial<LigneAppel>) =>
    setLignes((actuel) => actuel.map((l) => (l.eleve_id === eleveId ? { ...l, ...champs } : l)))

  const absents = lignes.filter((l) => l.statut === 'absent' || l.statut === 'renvoye').length

  return (
    <Modal title={`Appel — ${seance.matiere} du ${new Date(seance.date_seance).toLocaleDateString('fr-FR')}`} onClose={onClose}>
      {isLoading ? (
        <Spinner />
      ) : (
        <div className="flex flex-col gap-4">
          <p className="text-sm text-navy-500">
            {lignes.length} élève(s) · {absents} absent(s). Ne modifiez que les élèves concernés.
          </p>

          <ul className="flex max-h-96 flex-col gap-2 overflow-y-auto">
            {lignes.map((ligne) => (
              <li key={ligne.eleve_id} className="rounded-xl border border-navy-100 px-3 py-2">
                <div className="flex items-center justify-between gap-3">
                  <span className="min-w-0 flex-1 truncate text-sm font-medium text-navy-800">{ligne.nom_complet}</span>
                  <select
                    value={ligne.statut}
                    onChange={(e) => modifier(ligne.eleve_id, { statut: e.target.value as LigneAppel['statut'] })}
                    className="rounded-lg border border-navy-200 px-2 py-1 text-xs font-semibold text-navy-700"
                  >
                    {STATUTS.map((s) => (
                      <option key={s.valeur} value={s.valeur}>
                        {s.libelle}
                      </option>
                    ))}
                  </select>
                </div>
                {(ligne.statut === 'absent' || ligne.statut === 'renvoye') && (
                  <label className="mt-1.5 flex items-center gap-2 text-xs text-navy-500">
                    <input
                      type="checkbox"
                      checked={ligne.justifie}
                      onChange={(e) => modifier(ligne.eleve_id, { justifie: e.target.checked })}
                    />
                    Absence justifiée
                  </label>
                )}
              </li>
            ))}
          </ul>

          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={onClose}>
              Annuler
            </Button>
            <Button onClick={() => enregistrement.mutate()} disabled={enregistrement.isPending || lignes.length === 0}>
              Enregistrer l'appel
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}
