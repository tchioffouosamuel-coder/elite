import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ListChecks } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { fetchRemplissage } from '@/features/resultats/api'
import { fetchMesAffectations } from '@/features/progression/api'
import { useAuthStore } from '@/shared/store/authStore'
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
  const estEnseignant = useAuthStore((s) => s.user?.est_enseignant ?? false)
  const [classeId, setClasseId] = useState<number | ''>('')
  const [trimestreId, setTrimestreId] = useState<number | ''>('')

  // Un enseignant ne doit choisir que parmi les classes où il intervient
  // (titulaire ou matière affectée) — pas la liste complète de l'établissement,
  // qui n'aurait pour lui aucun sens et exposerait des classes hors de son périmètre.
  const { data: toutesLesClasses } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses(), enabled: !estEnseignant })
  const { data: mesAffectations } = useQuery({
    queryKey: ['mes-affectations'],
    queryFn: fetchMesAffectations,
    enabled: estEnseignant,
  })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })

  const classesEnseignant = useMemo(
    () => [...new Map((mesAffectations ?? []).map((a) => [a.classe_id, { id: a.classe_id, nom: a.classe }])).values()],
    [mesAffectations],
  )
  const classes = estEnseignant ? classesEnseignant : (toutesLesClasses ?? [])

  // Un seul rattachement : rien à choisir, la classe est déjà connue — le
  // select n'aurait qu'une option et n'apporterait rien.
  useEffect(() => {
    if (estEnseignant && classeId === '' && classesEnseignant.length === 1) {
      setClasseId(classesEnseignant[0].id)
    }
  }, [estEnseignant, classesEnseignant, classeId])

  const masquerSelectClasse = estEnseignant && classesEnseignant.length === 1
  const classeActive = classeId ? Number(classeId) : null
  const classeActiveNom = classes.find((c) => c.id === classeActive)?.nom

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

      <div className="flex flex-wrap items-end gap-3">
        {masquerSelectClasse ? (
          <div className="flex flex-col gap-1.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Classe</span>
            <span className="text-sm font-semibold text-navy-800">{classeActiveNom}</span>
          </div>
        ) : (
          <Select
            label="Classe"
            value={classeId}
            onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}
            className="max-w-xs"
          >
            <option value="">Sélectionner une classe…</option>
            {classes.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nom}
              </option>
            ))}
          </Select>
        )}

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

      {estEnseignant && classesEnseignant.length === 0 ? (
        <Card>
          <EmptyState label="Aucune matière ne vous est affectée pour le moment." />
        </Card>
      ) : !classeActive ? (
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
              <Th>Total volets</Th>
              <Th>Avancement</Th>
              <Th>Taux</Th>
            </tr>
          </Thead>
          <tbody>
            {data.matieres.map((ligne) => (
              <Tr key={ligne.classe_matiere_id}>
                <Td className="font-medium">{ligne.matiere}</Td>
                <Td>{ligne.enseignant ?? '—'}</Td>
                <Td className="text-center font-semibold">{ligne.volets ? ligne.volets.length : '—'}</Td>
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
