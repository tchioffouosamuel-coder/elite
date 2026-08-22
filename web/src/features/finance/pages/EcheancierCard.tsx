import { useEffect, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarClock, Info, Plus, Trash2 } from 'lucide-react'
import {
  fetchTranchesScolarite,
  remplacerTranchesScolarite,
  francs,
  type TrancheScolaritePayload,
} from '@/features/finance/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/** Une ligne en cours d'édition : les champs restent en texte tant qu'on saisit. */
interface LigneBrouillon {
  libelle: string
  pourcentage: string
  date_echeance: string
}

/**
 * Échéancier de la scolarité : le découpage de l'année en tranches et leurs
 * dates d'exigibilité.
 *
 * Il se remplace en bloc et non tranche par tranche : la somme des pourcentages
 * doit valoir 100, et une écriture ligne à ligne laisserait l'établissement
 * dans un état incohérent — 40 % enregistrés pendant qu'on saisit les 60 %
 * suivants, avec des relances fausses entre les deux.
 *
 * Le pourcentage plutôt qu'un montant : deux écoles n'ont pas la même
 * scolarité, une remise change le total d'une famille, et un reliquat s'ajoute
 * au dossier. Un échéancier en parts suit ces variations sans ressaisie.
 */
export function EcheancierCard({
  anneeScolaireId,
  montantExemple,
}: {
  anneeScolaireId: number
  /** Scolarité de référence, pour montrer ce que chaque part représente. */
  montantExemple?: number | null
}) {
  const queryClient = useQueryClient()
  const can = useAuthStore((s) => s.can)
  const peutModifier = can('finance.manage')

  const [lignes, setLignes] = useState<LigneBrouillon[]>([])
  const [enregistrement, setEnregistrement] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['tranches-scolarite', anneeScolaireId],
    queryFn: () => fetchTranchesScolarite(anneeScolaireId),
  })

  useEffect(() => {
    if (!data) return

    setLignes(
      data.tranches.map((tranche) => ({
        libelle: tranche.libelle,
        pourcentage: String(tranche.pourcentage),
        date_echeance: tranche.date_echeance,
      })),
    )
  }, [data])

  const somme = lignes.reduce((total, ligne) => total + (Number(ligne.pourcentage) || 0), 0)
  const sommeValide = lignes.length === 0 || Math.abs(somme - 100) < 0.01
  const datesCompletes = lignes.every((ligne) => ligne.libelle.trim() !== '' && ligne.date_echeance !== '')
  const datesUniques = new Set(lignes.map((l) => l.date_echeance)).size === lignes.length

  const modifier = (index: number, champ: keyof LigneBrouillon, valeur: string) =>
    setLignes((actuel) => actuel.map((ligne, i) => (i === index ? { ...ligne, [champ]: valeur } : ligne)))

  const ajouter = () =>
    setLignes((actuel) => [
      ...actuel,
      {
        libelle: `${actuel.length + 1}re tranche`.replace('1re', actuel.length === 0 ? '1re' : `${actuel.length + 1}e`),
        // Le reliquat pour atteindre 100 : c'est presque toujours ce qu'on veut.
        pourcentage: String(Math.max(0, 100 - somme)),
        date_echeance: '',
      },
    ])

  const retirer = (index: number) => setLignes((actuel) => actuel.filter((_, i) => i !== index))

  const enregistrer = async () => {
    setEnregistrement(true)
    try {
      const charge: TrancheScolaritePayload[] = lignes.map((ligne) => ({
        libelle: ligne.libelle.trim(),
        pourcentage: Number(ligne.pourcentage),
        date_echeance: ligne.date_echeance,
      }))

      await remplacerTranchesScolarite(anneeScolaireId, charge)
      queryClient.invalidateQueries({ queryKey: ['tranches-scolarite'] })
      queryClient.invalidateQueries({ queryKey: ['insolvables'] })
      succes(
        charge.length === 0
          ? 'Échéancier supprimé : la scolarité redevient exigible en une fois.'
          : `${charge.length} tranche(s) enregistrée(s).`,
      )
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnregistrement(false)
    }
  }

  return (
    <Card>
      <h2 className="mb-3 flex items-center gap-2 border-b border-navy-100/70 pb-2 font-display text-sm font-bold text-navy-900">
        <CalendarClock className="h-4 w-4 text-gold-500" />
        Échéancier de la scolarité
      </h2>

      <p className="mb-3 flex items-start gap-2 rounded-xl bg-cream-100 px-3.5 py-2.5 text-xs text-navy-600">
        <Info className="mt-0.5 h-3.5 w-3.5 flex-none" />
        <span>
          Les tranches décident de ce qui est dû <strong>à une date donnée</strong> : le portail parent y lit ses
          échéances, et la liste des insolvables ne retient plus que le retard réel. Sans tranche, toute la scolarité
          reste exigible immédiatement.
        </span>
      </p>

      {isLoading ? (
        <Spinner />
      ) : (
        <>
          <div className="flex flex-col gap-2">
            {lignes.length === 0 && (
              <p className="rounded-xl border border-dashed border-navy-200 px-3.5 py-4 text-center text-sm text-navy-400">
                Aucune tranche : la scolarité est exigible en une fois.
              </p>
            )}

            {lignes.map((ligne, index) => (
              <div key={index} className="grid grid-cols-12 items-end gap-2">
                <div className="col-span-12 sm:col-span-5">
                  <Input
                    label={index === 0 ? 'Libellé' : undefined}
                    value={ligne.libelle}
                    disabled={!peutModifier}
                    onChange={(e) => modifier(index, 'libelle', e.target.value)}
                  />
                </div>
                <div className="col-span-5 sm:col-span-2">
                  <Input
                    label={index === 0 ? '%' : undefined}
                    type="number"
                    min={0.01}
                    max={100}
                    step={0.01}
                    value={ligne.pourcentage}
                    disabled={!peutModifier}
                    onChange={(e) => modifier(index, 'pourcentage', e.target.value)}
                  />
                </div>
                <div className="col-span-6 sm:col-span-4">
                  <Input
                    label={index === 0 ? 'Échéance' : undefined}
                    type="date"
                    value={ligne.date_echeance}
                    disabled={!peutModifier}
                    onChange={(e) => modifier(index, 'date_echeance', e.target.value)}
                  />
                </div>
                {peutModifier && (
                  <button
                    type="button"
                    title="Retirer la tranche"
                    onClick={() => retirer(index)}
                    className="col-span-1 mb-1 flex h-9 items-center justify-center rounded-lg text-navy-400 transition-colors hover:bg-red-50 hover:text-red-600"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                )}
              </div>
            ))}
          </div>

          <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-navy-100 pt-3">
            <span className={`text-xs font-semibold ${sommeValide ? 'text-green-600' : 'text-red-500'}`}>
              Total : {somme.toFixed(2)} % {sommeValide ? '' : '— doit valoir 100 %'}
              {montantExemple != null && sommeValide && lignes.length > 0 && (
                <span className="ml-2 font-normal text-navy-400">
                  (sur une scolarité de {francs(montantExemple)})
                </span>
              )}
            </span>

            {peutModifier && (
              <div className="flex gap-2">
                <Button size="sm" variant="secondary" onClick={ajouter}>
                  <Plus className="h-3.5 w-3.5" />
                  Ajouter une tranche
                </Button>
                <Button
                  size="sm"
                  onClick={enregistrer}
                  disabled={enregistrement || !sommeValide || !datesCompletes || !datesUniques}
                >
                  Enregistrer
                </Button>
              </div>
            )}
          </div>

          {!datesUniques && (
            <p className="mt-2 text-xs font-semibold text-red-500">
              Deux tranches ne peuvent pas partager la même date d'échéance.
            </p>
          )}
          {!datesCompletes && lignes.length > 0 && (
            <p className="mt-2 text-xs text-navy-400">Chaque tranche demande un libellé et une date.</p>
          )}
        </>
      )}
    </Card>
  )
}
