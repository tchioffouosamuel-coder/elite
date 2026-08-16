import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Copy, Users, AlertTriangle } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { succes } from '@/shared/lib/alertes'
import {
  GAINS,
  appliquerRemuneration,
  francs,
  simulerRemuneration,
  type ChampGain,
  type LigneRemuneration,
} from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

/**
 * Copie d'une rémunération vers plusieurs agents.
 *
 * La grille salariale d'un établissement compte quelques niveaux, pas quarante :
 * recopier celui d'un collègue évite de ressaisir six montants à l'identique
 * et d'y glisser une faute. Les montants restent affichés et modifiables avant
 * application — on part d'un modèle, on ne le subit pas.
 */
export function CopierRemunerationModal({
  cibles,
  modeles,
  onClose,
  onApplique,
}: {
  /** Agents sélectionnés, destinataires de la rémunération. */
  cibles: LigneRemuneration[]
  /** Agents disposant déjà d'une rémunération, utilisables comme modèle. */
  modeles: LigneRemuneration[]
  onClose: () => void
  onApplique: () => void
}) {
  const [sourceId, setSourceId] = useState<number | ''>(modeles[0]?.id ?? '')
  const [dateEffet, setDateEffet] = useState(new Date().toISOString().slice(0, 10))
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const source = modeles.find((m) => m.id === sourceId)

  const montants = useMemo(
    () =>
      Object.fromEntries(
        GAINS.map(({ champ }) => [champ, source?.remuneration?.[champ] ?? 0]),
      ) as Record<ChampGain, number>,
    [source],
  )

  const { data: simulation } = useQuery({
    queryKey: ['simulation-remuneration', montants],
    queryFn: () => simulerRemuneration(montants),
    enabled: Boolean(source),
  })

  // Un agent qui a déjà une rémunération la verra remplacée à cette date :
  // le dire avant vaut mieux que le découvrir après.
  const dejaPourvus = cibles.filter((c) => c.remuneration !== null)

  const appliquer = async () => {
    if (!source) return

    setServerError(null)
    setSubmitting(true)
    try {
      const { appliquee } = await appliquerRemuneration({
        personnel_ids: cibles.map((c) => c.id),
        source_personnel_id: source.id,
        date_effet: dateEffet,
        categorie: source.remuneration?.categorie ?? undefined,
      })

      succes(`Rémunération appliquée à ${appliquee} agent(s).`)
      onApplique()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) setServerError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={`Copier une rémunération vers ${cibles.length} agent(s)`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        {modeles.length === 0 ? (
          <p className="rounded-xl bg-gold-50 px-3.5 py-2.5 text-sm text-gold-800 ring-1 ring-gold-100">
            Aucun agent n'a encore de rémunération définie : il faut en fixer une première à la main avant de pouvoir
            la recopier.
          </p>
        ) : (
          <>
            <Select label="Copier depuis" value={sourceId} onChange={(e) => setSourceId(Number(e.target.value))}>
              {modeles.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.nom_complet} — {francs(m.remuneration!.brut)} brut
                </option>
              ))}
            </Select>

            {source?.remuneration && (
              <div className="rounded-xl bg-cream-100 p-3">
                <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-navy-500">
                  Montants qui seront appliqués
                </div>
                <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-3">
                  {GAINS.filter(({ champ }) => montants[champ] > 0).map(({ champ, libelle }) => (
                    <div key={champ} className="flex justify-between gap-2">
                      <dt className="truncate text-navy-500">{libelle}</dt>
                      <dd className="flex-none tabular-nums font-semibold">{francs(montants[champ])}</dd>
                    </div>
                  ))}
                </dl>

                {simulation && (
                  <p className="mt-2 border-t border-navy-100/70 pt-2 text-xs text-navy-500">
                    Brut <span className="font-semibold">{francs(simulation.brut)}</span> · net{' '}
                    <span className="font-semibold text-green-600">{francs(simulation.net_avant_deductions)}</span> ·
                    coût employeur <span className="font-semibold">{francs(simulation.cout_employeur)}</span>
                    <span className="block">Par agent, et pour chacun des {cibles.length} sélectionnés.</span>
                  </p>
                )}
              </div>
            )}

            <Input
              label="Date d'effet"
              type="date"
              value={dateEffet}
              onChange={(e) => setDateEffet(e.target.value)}
            />

            <details className="rounded-xl border border-navy-100 px-3 py-2">
              <summary className="cursor-pointer text-xs font-semibold text-navy-600">
                <Users className="mr-1 inline h-3.5 w-3.5" />
                Destinataires ({cibles.length})
              </summary>
              <ul className="mt-2 flex flex-col gap-1 text-xs text-navy-600">
                {cibles.map((c) => (
                  <li key={c.id} className="flex justify-between gap-2">
                    <span className="truncate">{c.nom_complet}</span>
                    {c.remuneration && (
                      <span className="flex-none text-gold-700">actuel : {francs(c.remuneration.brut)}</span>
                    )}
                  </li>
                ))}
              </ul>
            </details>

            {dejaPourvus.length > 0 && (
              <p className="flex items-start gap-2 rounded-xl bg-gold-50 px-3 py-2 text-xs text-gold-800 ring-1 ring-gold-100">
                <AlertTriangle className="mt-0.5 h-3.5 w-3.5 flex-none" />
                <span>
                  {dejaPourvus.length} agent(s) ont déjà une rémunération. La nouvelle s'ajoute à leur historique et
                  prend effet à la date choisie — les bulletins déjà émis ne changent pas.
                </span>
              </p>
            )}
          </>
        )}

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button onClick={appliquer} disabled={submitting || !source}>
            <Copy className="h-4 w-4" />
            {submitting ? 'Application…' : `Appliquer à ${cibles.length} agent(s)`}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
