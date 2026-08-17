import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { FileDown } from 'lucide-react'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { fetchAbsences, sauvegarderAbsences, fetchBilanDisciplinaire, type AbsenceCellule } from '@/features/discipline/api'
import { estSecondaire } from '@/shared/lib/ecole'
import { ouvrirDocument } from '@/shared/lib/download'
import { Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner } from '@/shared/ui/Feedback'

export function AbsencesTab({ classeId }: { classeId: number }) {
  const { t } = useTranslation()
  // Au primaire et en maternelle les journées se déduisent des appels : la
  // grille n'y est qu'un compte rendu, il n'y a rien à saisir ni à envoyer.
  const secondaire = estSecondaire()
  const suffixe = secondaire ? 'h' : ' j'

  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]
  const [trimestreId, setTrimestreId] = useState<number | ''>('')

  useEffect(() => {
    if (!trimestreId && trimestreActif) setTrimestreId(trimestreActif.id)
  }, [trimestreActif, trimestreId])

  const { data: grille, isLoading, refetch } = useQuery({
    queryKey: ['absences', classeId, trimestreId],
    queryFn: () => fetchAbsences(classeId, Number(trimestreId)),
    enabled: !!trimestreId,
  })

  const { data: bilan } = useQuery({
    queryKey: ['bilan-disciplinaire', classeId, trimestreId],
    queryFn: () => fetchBilanDisciplinaire(classeId, Number(trimestreId)),
    enabled: !!trimestreId,
  })

  const [valeurs, setValeurs] = useState<Record<number, { hj: string; hnj: string }>>({})
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    if (grille) {
      setValeurs(
        Object.fromEntries(grille.map((g) => [g.eleve_id, { hj: String(g.justifiees), hnj: String(g.non_justifiees) }])),
      )
    }
  }, [grille])

  const handleSave = async () => {
    if (!trimestreId) return
    setSubmitting(true)
    try {
      const absences = Object.entries(valeurs).map(([eleveId, v]) => ({
        eleve_id: Number(eleveId),
        heures_justifiees: Number(v.hj || 0),
        heures_non_justifiees: Number(v.hnj || 0),
      }))
      await sauvegarderAbsences(classeId, Number(trimestreId), absences)
      refetch()
    } finally {
      setSubmitting(false)
    }
  }

  const colonnes: Colonne<AbsenceCellule>[] = [
    {
      cle: 'nom',
      entete: t('eleves.nom_complet'),
      valeur: (row) => row.nom_complet,
      cellule: (row) => <span className="font-medium">{row.nom_complet}</span>,
    },
    {
      cle: 'justifiees',
      entete: t(secondaire ? 'discipline.heures_justifiees' : 'discipline.jours_justifies'),
      valeur: (row) => (row.calculee ? row.justifiees : Number(valeurs[row.eleve_id]?.hj ?? 0)),
      cellule: (row) =>
        row.calculee ? (
          row.justifiees
        ) : (
          <input
            type="number"
            min={0}
            step={0.5}
            value={valeurs[row.eleve_id]?.hj ?? ''}
            onChange={(e) => setValeurs((v) => ({ ...v, [row.eleve_id]: { ...v[row.eleve_id], hj: e.target.value } }))}
            className="w-20 rounded-lg border border-navy-200 px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
          />
        ),
    },
    {
      cle: 'non_justifiees',
      entete: t(secondaire ? 'discipline.heures_non_justifiees' : 'discipline.jours_non_justifies'),
      valeur: (row) => (row.calculee ? row.non_justifiees : Number(valeurs[row.eleve_id]?.hnj ?? 0)),
      cellule: (row) =>
        row.calculee ? (
          <span className={row.non_justifiees > 0 ? 'font-semibold text-navy-900' : undefined}>{row.non_justifiees}</span>
        ) : (
          <input
            type="number"
            min={0}
            step={0.5}
            value={valeurs[row.eleve_id]?.hnj ?? ''}
            onChange={(e) => setValeurs((v) => ({ ...v, [row.eleve_id]: { ...v[row.eleve_id], hnj: e.target.value } }))}
            className="w-20 rounded-lg border border-navy-200 px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
          />
        ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-end justify-between gap-3">
        <Select label={t('notes.trimestre')} value={trimestreId} onChange={(e) => setTrimestreId(e.target.value ? Number(e.target.value) : '')} className="max-w-xs">
          {trimestres?.map((tr) => (
            <option key={tr.id} value={tr.id}>
              {tr.libelle}
            </option>
          ))}
        </Select>
        {trimestreId && (
          <Button
            variant="secondary"
            size="sm"
            onClick={() => ouvrirDocument(`/classes/${classeId}/bilan-disciplinaire/pdf`, { trimestre_id: Number(trimestreId) })}
          >
            <FileDown className="h-3.5 w-3.5" />
            {t('export.pdf')}
          </Button>
        )}
      </div>

      {bilan && (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <Card><span className="text-xs font-semibold uppercase text-navy-400">{t(secondaire ? 'discipline.total_hnj' : 'discipline.total_jnj')}</span><p className="font-display text-xl font-bold text-navy-800">{bilan.total_hnj}{suffixe}</p></Card>
          <Card><span className="text-xs font-semibold uppercase text-navy-400">{t(secondaire ? 'discipline.moyenne_hnj' : 'discipline.moyenne_jnj')}</span><p className="font-display text-xl font-bold text-navy-800">{bilan.moyenne_hnj}{suffixe}</p></Card>
          <Card><span className="text-xs font-semibold uppercase text-navy-400">{t('discipline.plus_absent')}</span><p className="text-sm font-semibold text-navy-800">{bilan.eleve_plus_absent?.nom_complet ?? '—'}</p></Card>
          <Card><span className="text-xs font-semibold uppercase text-navy-400">{t('common.total')}</span><p className="font-display text-xl font-bold text-navy-800">{bilan.effectif}</p></Card>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : (
        <>
          <DataTable
            colonnes={colonnes}
            lignes={grille ?? []}
            cleLigne={(row) => row.eleve_id}
            placeholderRecherche={t('discipline.search_eleve_placeholder')}
            messageVide={t('discipline.empty_eleves_classe')}
            largeurMin={520}
            // La saisie des heures (secondaire) doit garder toutes les lignes
            // visibles à la fois : paginer forcerait à changer de page pour
            // remplir chaque élève avant d'enregistrer.
            parPage={secondaire ? 0 : 15}
          />
          {secondaire ? (
            <div>
              <Button onClick={handleSave} disabled={submitting}>
                {t('common.save')}
              </Button>
            </div>
          ) : (
            <p className="text-xs text-navy-400">{t('discipline.jours_calcules')}</p>
          )}
        </>
      )}
    </div>
  )
}
