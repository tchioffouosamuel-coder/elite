import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Save, Info } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Input } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { succes } from '@/shared/lib/alertes'
import {
  GAINS,
  enregistrerRemuneration,
  francs,
  simulerRemuneration,
  type ChampGain,
  type LigneRemuneration,
} from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

type Montants = Record<ChampGain, string>

const VIDE: Montants = {
  salaire_base: '',
  prime_anciennete: '',
  prime_communication: '',
  prime_transport: '',
  prime_recherche: '',
  prime_performance: '',
}

/**
 * Définition d'une rémunération.
 *
 * Le net est simulé en direct par le barème du serveur, jamais recalculé côté
 * navigateur : dupliquer ici les tranches d'IRPP et les plafonds de cotisation
 * garantirait qu'un jour les deux divergent, et c'est le bulletin qui ferait
 * foi. Sans cet aperçu, on fixerait un brut à l'aveugle et l'agent
 * découvrirait son net sur sa première fiche.
 */
export function RemunerationModal({
  personnel,
  onClose,
  onEnregistre,
}: {
  personnel: LigneRemuneration
  onClose: () => void
  onEnregistre: () => void
}) {
  const [montants, setMontants] = useState<Montants>(() => {
    if (!personnel.remuneration) return VIDE

    return Object.fromEntries(
      GAINS.map(({ champ }) => [champ, String(personnel.remuneration![champ] || '')]),
    ) as Montants
  })

  const [dateEffet, setDateEffet] = useState(new Date().toISOString().slice(0, 10))
  const [categorie, setCategorie] = useState(personnel.remuneration?.categorie ?? '')
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const nombres = useMemo(
    () =>
      Object.fromEntries(GAINS.map(({ champ }) => [champ, Number(montants[champ] || 0)])) as Record<ChampGain, number>,
    [montants],
  )

  const brut = Object.values(nombres).reduce((somme, v) => somme + v, 0)

  // Débounce : la simulation part au serveur, inutile de la relancer à chaque
  // frappe pendant qu'on tape un montant.
  const [gainsStables, setGainsStables] = useState(nombres)
  useEffect(() => {
    const minuteur = setTimeout(() => setGainsStables(nombres), 400)
    return () => clearTimeout(minuteur)
  }, [nombres])

  const { data: simulation, isFetching } = useQuery({
    queryKey: ['simulation-remuneration', gainsStables],
    queryFn: () => simulerRemuneration(gainsStables),
    enabled: brut > 0,
  })

  const enregistrer = async () => {
    setServerError(null)
    setSubmitting(true)
    try {
      await enregistrerRemuneration(personnel.id, {
        ...nombres,
        date_effet: dateEffet,
        categorie: categorie || undefined,
      })
      succes(`Rémunération de ${personnel.nom_complet} enregistrée.`)
      onEnregistre()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) setServerError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal
      title={`${personnel.remuneration ? 'Réviser' : 'Définir'} le salaire — ${personnel.nom_complet}`}
      onClose={onClose}
    >
      <div className="flex flex-col gap-4">
        <div className="grid gap-3 sm:grid-cols-2">
          {GAINS.map(({ champ, libelle, exonere }) => (
            <Input
              key={champ}
              label={libelle}
              type="number"
              min={0}
              value={montants[champ]}
              onChange={(e) => setMontants((m) => ({ ...m, [champ]: e.target.value }))}
              placeholder={exonere ? 'Exonéré jusqu’à 2 500' : '0'}
            />
          ))}
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          <Input
            label="Date d'effet"
            type="date"
            value={dateEffet}
            onChange={(e) => setDateEffet(e.target.value)}
          />
          <Input
            label="Catégorie (facultatif)"
            value={categorie}
            onChange={(e) => setCategorie(e.target.value)}
            placeholder="Ex. : 6, cadre…"
          />
        </div>

        {brut > 0 && (
          <div className="rounded-xl bg-cream-100 p-3">
            <div className="mb-2 flex items-center justify-between">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
                Ce que cela donne
              </span>
              {isFetching && <span className="text-xs text-navy-400">calcul…</span>}
            </div>

            <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4">
              {[
                ['Brut', simulation?.brut ?? brut, 'text-navy-900'],
                ['Base taxable', simulation?.base_taxable, 'text-navy-500'],
                ['Retenues', simulation?.charges_salariales, 'text-red-500'],
                ['Net à percevoir', simulation?.net_avant_deductions, 'text-green-600 font-bold'],
              ].map(([libelle, valeur, couleur]) => (
                <div key={libelle as string}>
                  <dt className="text-[11px] uppercase tracking-wide text-navy-400">{libelle as string}</dt>
                  <dd className={`tabular-nums ${couleur as string}`}>
                    {valeur === undefined ? '…' : francs(valeur as number)}
                  </dd>
                </div>
              ))}
            </dl>

            {simulation && (
              <p className="mt-2 border-t border-navy-100/70 pt-2 text-xs text-navy-500">
                Coût pour l'établissement, charges patronales comprises :{' '}
                <span className="font-semibold">{francs(simulation.cout_employeur)}</span>
              </p>
            )}
          </div>
        )}

        {simulation && simulation.retenues.length > 0 && (
          <details className="rounded-xl border border-navy-100 px-3 py-2">
            <summary className="cursor-pointer text-xs font-semibold text-navy-600">
              Détail des retenues légales
            </summary>
            <table className="mt-2 w-full text-xs">
              <tbody>
                {simulation.retenues
                  .filter((r) => r.montant_salarial > 0 || r.montant_patronal > 0)
                  .map((r) => (
                    <tr key={r.libelle} className="border-b border-navy-50 last:border-0">
                      <td className="py-1 pr-2 text-navy-600">{r.libelle}</td>
                      <td className="py-1 text-right tabular-nums text-red-500">
                        {r.montant_salarial > 0 ? francs(r.montant_salarial) : '—'}
                      </td>
                      <td className="py-1 pl-2 text-right tabular-nums text-navy-400">
                        {r.montant_patronal > 0 ? `${francs(r.montant_patronal)} (empl.)` : ''}
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </details>
        )}

        <p className="flex items-start gap-2 text-xs text-navy-500">
          <Info className="mt-0.5 h-3.5 w-3.5 flex-none" />
          La rémunération précédente est conservée : les bulletins déjà émis gardent leurs montants, la nouvelle ne
          s'applique qu'à partir de sa date d'effet.
        </p>

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button onClick={enregistrer} disabled={submitting || brut <= 0}>
            <Save className="h-4 w-4" />
            {submitting ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
