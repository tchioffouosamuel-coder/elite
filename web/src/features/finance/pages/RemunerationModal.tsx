import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Save, Info } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Input, MontantInput } from '@/shared/ui/Field'
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

type Montants = Record<ChampGain, number>

const VIDE: Montants = {
  salaire_base: 0,
  prime_anciennete: 0,
  prime_communication: 0,
  prime_transport: 0,
  prime_recherche: 0,
  prime_performance: 0,
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
  const { t, i18n } = useTranslation()
  const [montants, setMontants] = useState<Montants>(() => {
    if (!personnel.remuneration) return VIDE

    return Object.fromEntries(
      GAINS.map(({ champ }) => [champ, personnel.remuneration![champ] || 0]),
    ) as Montants
  })

  const [dateEffet, setDateEffet] = useState(new Date().toISOString().slice(0, 10))
  // Vacataire du technique : ni base ni primes, un taux et des heures.
  const [horaire, setHoraire] = useState(personnel.remuneration?.mode === 'horaire')
  const [tauxHoraire, setTauxHoraire] = useState(personnel.remuneration?.taux_horaire ?? 0)
  const [categorie, setCategorie] = useState(personnel.remuneration?.categorie ?? '')
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const nombres = useMemo(
    () =>
      Object.fromEntries(GAINS.map(({ champ }) => [champ, montants[champ] || 0])) as Record<ChampGain, number>,
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
        ...(horaire ? {} : nombres),
        mode: horaire ? 'horaire' : 'mensuel',
        taux_horaire: horaire ? tauxHoraire : undefined,
        date_effet: dateEffet,
        categorie: categorie || undefined,
      })
      succes(t('finance.remuneration.saved_for', { name: personnel.nom_complet }))
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
      title={t(personnel.remuneration ? 'finance.remuneration.modal_title_edit' : 'finance.remuneration.modal_title_create', {
        name: personnel.nom_complet,
      })}
      onClose={onClose}
    >
      <div className="flex flex-col gap-4">
        {/* Deux régimes dans le complexe : salaire mensuel au primaire,
            vacation horaire au technique. Le choix commande le formulaire. */}
        <div className="flex gap-2 rounded-xl bg-cream-100 p-1">
          {[
            { valeur: false, libelle: 'Salaire mensuel' },
            { valeur: true, libelle: 'Vacation horaire' },
          ].map((option) => (
            <button
              key={String(option.valeur)}
              type="button"
              onClick={() => setHoraire(option.valeur)}
              className={
                horaire === option.valeur
                  ? 'flex-1 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-navy-800 shadow-sm'
                  : 'flex-1 rounded-lg px-3 py-2 text-sm font-medium text-navy-500 hover:text-navy-700'
              }
            >
              {option.libelle}
            </button>
          ))}
        </div>

        {horaire ? (
          <div className="flex flex-col gap-2">
            <MontantInput
              label="Taux horaire (F CFA)"
              value={tauxHoraire}
              onChange={setTauxHoraire}
              placeholder="1 100"
            />
            <p className="text-xs text-navy-400">
              Le brut du mois se calcule heures × taux. Un vacataire n'a ni salaire de base ni primes, et les jours
              d'absence ne se retiennent pas : les heures non faites ne sont simplement pas payées.
            </p>
          </div>
        ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {GAINS.map(({ champ, exonere }) => (
            <MontantInput
              key={champ}
              label={t(`finance.remuneration.gains.${champ}`)}
              value={montants[champ]}
              onChange={(v) => setMontants((m) => ({ ...m, [champ]: v }))}
              placeholder={exonere ? t('finance.remuneration.exempt_until', { amount: '2 500' }) : '0'}
            />
          ))}
        </div>
        )}

        <div className="grid gap-3 sm:grid-cols-2">
          <Input
            label={t('finance.remuneration.effective_date')}
            type="date"
            value={dateEffet}
            onChange={(e) => setDateEffet(e.target.value)}
          />
          <Input
            label={t('finance.remuneration.category_optional')}
            value={categorie}
            onChange={(e) => setCategorie(e.target.value)}
            placeholder={t('finance.remuneration.category_placeholder')}
          />
        </div>

        {! horaire && brut > 0 && (
          <div className="rounded-xl bg-cream-100 p-3">
            <div className="mb-2 flex items-center justify-between">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
                {t('finance.remuneration.preview_title')}
              </span>
              {isFetching && <span className="text-xs text-navy-400">{t('finance.remuneration.calculating')}</span>}
            </div>

            <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4">
              {[
                ['finance.remuneration.gross', simulation?.brut ?? brut, 'text-navy-900'],
                ['finance.remuneration.taxable_base', simulation?.base_taxable, 'text-navy-500'],
                ['finance.remuneration.deductions', simulation?.charges_salariales, 'text-red-500'],
                ['finance.remuneration.net_to_receive', simulation?.net_avant_deductions, 'text-green-600 font-bold'],
              ].map(([cle, valeur, couleur]) => (
                <div key={cle as string}>
                  <dt className="text-[11px] uppercase tracking-wide text-navy-400">{t(cle as string)}</dt>
                  <dd className={`tabular-nums ${couleur as string}`}>
                    {valeur === undefined ? '…' : francs(valeur as number)}
                  </dd>
                </div>
              ))}
            </dl>

            {simulation && (
              <p className="mt-2 border-t border-navy-100/70 pt-2 text-xs text-navy-500">
                {t('finance.remuneration.employer_cost_included')}{' '}
                <span className="font-semibold">{francs(simulation.cout_employeur)}</span>
              </p>
            )}
          </div>
        )}

        {simulation && simulation.retenues.length > 0 && (
          <details className="rounded-xl border border-navy-100 px-3 py-2">
            <summary className="cursor-pointer text-xs font-semibold text-navy-600">
              {t('finance.remuneration.legal_deductions_detail')}
            </summary>
            <table className="mt-2 w-full text-xs">
              <tbody>
                {simulation.retenues
                  .filter((r) => r.montant_salarial > 0 || r.montant_patronal > 0)
                  .map((r) => (
                    <tr key={r.libelle} className="border-b border-navy-50 last:border-0">
                      <td className="py-1 pr-2 text-navy-600">{i18n.language.startsWith('en') ? r.libelle_en : r.libelle}</td>
                      <td className="py-1 text-right tabular-nums text-red-500">
                        {r.montant_salarial > 0 ? francs(r.montant_salarial) : '—'}
                      </td>
                      <td className="py-1 pl-2 text-right tabular-nums text-navy-400">
                        {r.montant_patronal > 0
                          ? t('finance.remuneration.employer_amount_short', { amount: francs(r.montant_patronal) })
                          : ''}
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </details>
        )}

        <p className="flex items-start gap-2 text-xs text-navy-500">
          <Info className="mt-0.5 h-3.5 w-3.5 flex-none" />
          {t('finance.remuneration.history_notice')}
        </p>

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button onClick={enregistrer} disabled={submitting || (horaire ? tauxHoraire <= 0 : brut <= 0)}>
            <Save className="h-4 w-4" />
            {submitting ? t('finance.remuneration.saving') : t('common.save')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
