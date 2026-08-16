import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { BarChart3, TrendingUp, TrendingDown, Landmark, Scale, AlertTriangle, CheckCircle2 } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card, StatCard } from '@/shared/ui/Card'
import { Input } from '@/shared/ui/Field'
import { Tabs } from '@/shared/ui/Tabs'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { useAuthStore } from '@/shared/store/authStore'
import {
  fetchBalance,
  fetchResultat,
  fetchTableauDeBord,
  fetchTresorerie,
  francs,
  type LigneCompte,
} from '@/features/finance/api'

type Onglet = 'bord' | 'resultat' | 'tresorerie' | 'balance'

/**
 * États financiers.
 *
 * Quatre lectures d'une même réalité, séparées en onglets parce qu'elles
 * répondent à quatre questions distinctes : où en est-on, qu'a-t-on gagné et
 * dépensé, que reste-t-il en caisse, et les comptes sont-ils cohérents.
 */
export function RapportsFinanciersPage() {
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)

  const [onglet, setOnglet] = useState<Onglet>('bord')
  const [du, setDu] = useState('')
  const [au, setAu] = useState('')

  const periode = { du: du || null, au: au || null }

  const bord = useQuery({
    queryKey: ['rapport-bord', activeSchoolId],
    queryFn: fetchTableauDeBord,
    enabled: onglet === 'bord',
  })
  const resultat = useQuery({
    queryKey: ['rapport-resultat', activeSchoolId, du, au],
    queryFn: () => fetchResultat(periode),
    enabled: onglet === 'resultat',
  })
  const tresorerie = useQuery({
    queryKey: ['rapport-tresorerie', activeSchoolId, du, au],
    queryFn: () => fetchTresorerie(periode),
    enabled: onglet === 'tresorerie',
  })
  const balance = useQuery({
    queryKey: ['rapport-balance', activeSchoolId, du, au],
    queryFn: () => fetchBalance(periode),
    enabled: onglet === 'balance',
  })

  const actif = { bord, resultat, tresorerie, balance }[onglet]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Rapports financiers"
        sousTitre="Résultat, trésorerie et balance — reconstruits depuis le journal comptable."
        icon={BarChart3}
        actions={
          onglet !== 'bord' && (
            <div className="flex items-end gap-2">
              <Input label="Du" type="date" value={du} onChange={(e) => setDu(e.target.value)} />
              <Input label="Au" type="date" value={au} onChange={(e) => setAu(e.target.value)} />
            </div>
          )
        }
      />

      <Tabs
        tabs={[
          { key: 'bord', label: 'Tableau de bord' },
          { key: 'resultat', label: 'Compte de résultat' },
          { key: 'tresorerie', label: 'Trésorerie' },
          { key: 'balance', label: 'Balance' },
        ]}
        active={onglet}
        onChange={(cle) => setOnglet(cle as Onglet)}
      />

      {actif.isLoading ? (
        <Spinner />
      ) : actif.isError ? (
        <ErrorState />
      ) : (
        <>
          {onglet === 'bord' && bord.data && (
            <div className="flex flex-col gap-4">
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                  label="Recouvrement scolarité"
                  value={`${bord.data.scolarite.taux_recouvrement} %`}
                  icon={TrendingUp}
                  accent="green"
                  hint={`${francs(bord.data.scolarite.recouvre)} sur ${francs(bord.data.scolarite.attendu)}`}
                />
                <StatCard
                  label="Reste à recouvrer"
                  value={francs(bord.data.scolarite.reste)}
                  icon={AlertTriangle}
                  accent="red"
                  hint={`${bord.data.scolarite.insolvables} insolvable(s)`}
                />
                <StatCard
                  label="Coût du personnel"
                  value={francs(bord.data.paie.cout_employeur)}
                  icon={TrendingDown}
                  accent="gold"
                  hint={`${bord.data.paie.bulletins} bulletin(s) arrêté(s)`}
                />
                <StatCard
                  label="Trésorerie disponible"
                  value={francs(bord.data.tresorerie)}
                  icon={Landmark}
                  accent="navy"
                />
              </div>

              <Card>
                <h2 className="mb-3 border-b border-navy-100/70 pb-2 font-display text-sm font-bold text-navy-900">
                  Résultat de l'exercice
                </h2>
                <div className="grid gap-3 sm:grid-cols-3">
                  {[
                    ['Produits', bord.data.resultat.produits, 'text-green-600'],
                    ['Charges', bord.data.resultat.charges, 'text-red-500'],
                    [
                      'Résultat',
                      bord.data.resultat.solde,
                      bord.data.resultat.solde >= 0 ? 'text-green-600' : 'text-red-500',
                    ],
                  ].map(([libelle, valeur, couleur]) => (
                    <div key={libelle as string} className="rounded-xl bg-cream-100 p-3 text-center">
                      <div className="text-[11px] uppercase tracking-wide text-navy-400">{libelle as string}</div>
                      <div className={`text-lg font-bold tabular-nums ${couleur as string}`}>
                        {francs(valeur as number)}
                      </div>
                    </div>
                  ))}
                </div>
                {bord.data.paie.a_regler > 0 && (
                  <p className="mt-3 text-xs text-gold-700">
                    {francs(bord.data.paie.a_regler)} de salaires arrêtés restent à régler.
                  </p>
                )}
              </Card>
            </div>
          )}

          {onglet === 'resultat' && resultat.data && (
            <div className="grid gap-4 lg:grid-cols-2">
              <ColonneComptes titre="Produits" lignes={resultat.data.produits.lignes} total={resultat.data.produits.total} ton="green" />
              <ColonneComptes titre="Charges" lignes={resultat.data.charges.lignes} total={resultat.data.charges.total} ton="red" />

              <Card className="lg:col-span-2">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <div className="text-[11px] uppercase tracking-wide text-navy-400">Résultat de la période</div>
                    <div
                      className={`text-2xl font-bold tabular-nums ${
                        resultat.data.resultat >= 0 ? 'text-green-600' : 'text-red-500'
                      }`}
                    >
                      {francs(resultat.data.resultat)}
                    </div>
                  </div>
                  <div className="text-right">
                    <div className="text-[11px] uppercase tracking-wide text-navy-400">Taux de charges</div>
                    <div className="text-lg font-bold tabular-nums text-navy-700">{resultat.data.taux_charges} %</div>
                    <div className="text-xs text-navy-400">des recettes absorbées</div>
                  </div>
                </div>
              </Card>
            </div>
          )}

          {onglet === 'tresorerie' && tresorerie.data && (
            <Card>
              <h2 className="mb-3 border-b border-navy-100/70 pb-2 font-display text-sm font-bold text-navy-900">
                Soldes de trésorerie
              </h2>
              <table className="w-full text-sm">
                <tbody>
                  {tresorerie.data.comptes.map((c) => (
                    <tr key={c.code} className="border-b border-navy-50">
                      <td className="py-2 font-semibold text-navy-500">{c.code}</td>
                      <td className="py-2">{c.libelle}</td>
                      <td className="py-2 text-right tabular-nums text-green-600">{francs(c.debit)}</td>
                      <td className="py-2 text-right tabular-nums text-red-500">{francs(c.credit)}</td>
                      <td
                        className={`py-2 text-right font-bold tabular-nums ${
                          c.solde >= 0 ? 'text-navy-900' : 'text-red-500'
                        }`}
                      >
                        {francs(c.solde)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <div className="mt-3 flex items-center justify-between rounded-xl bg-navy-800 px-4 py-3 text-cream-50">
                <span className="text-sm font-semibold">Disponible</span>
                <span className="text-lg font-bold tabular-nums">{francs(tresorerie.data.disponible)}</span>
              </div>
            </Card>
          )}

          {onglet === 'balance' && balance.data && (
            <Card>
              <div className="mb-3 flex items-center justify-between border-b border-navy-100/70 pb-2">
                <h2 className="font-display text-sm font-bold text-navy-900">Balance générale</h2>
                <span
                  className={`inline-flex items-center gap-1.5 text-xs font-semibold ${
                    balance.data.equilibre ? 'text-green-600' : 'text-red-500'
                  }`}
                >
                  {balance.data.equilibre ? <CheckCircle2 className="h-4 w-4" /> : <Scale className="h-4 w-4" />}
                  {balance.data.equilibre ? 'Équilibrée' : 'Déséquilibrée — écriture incomplète'}
                </span>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full min-w-[36rem] text-sm">
                  <thead>
                    <tr className="border-b border-navy-100 text-xs uppercase tracking-wide text-navy-400">
                      <th className="py-2 text-left">Compte</th>
                      <th className="py-2 text-right">Débit</th>
                      <th className="py-2 text-right">Crédit</th>
                      <th className="py-2 text-right">Solde</th>
                    </tr>
                  </thead>
                  <tbody>
                    {balance.data.lignes.map((l) => (
                      <tr key={l.code} className="border-b border-navy-50">
                        <td className="py-2">
                          <span className="font-semibold text-navy-500">{l.code}</span> {l.libelle}
                        </td>
                        <td className="py-2 text-right tabular-nums">{francs(l.debit)}</td>
                        <td className="py-2 text-right tabular-nums">{francs(l.credit)}</td>
                        <td className="py-2 text-right font-semibold tabular-nums">{francs(l.solde)}</td>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot>
                    <tr className="font-bold">
                      <td className="py-2">Totaux</td>
                      <td className="py-2 text-right tabular-nums">{francs(balance.data.total_debit)}</td>
                      <td className="py-2 text-right tabular-nums">{francs(balance.data.total_credit)}</td>
                      <td />
                    </tr>
                  </tfoot>
                </table>
              </div>
            </Card>
          )}
        </>
      )}
    </div>
  )
}

function ColonneComptes({
  titre,
  lignes,
  total,
  ton,
}: {
  titre: string
  lignes: LigneCompte[]
  total: number
  ton: 'green' | 'red'
}) {
  const couleur = ton === 'green' ? 'text-green-600' : 'text-red-500'

  return (
    <Card>
      <h2 className="mb-3 border-b border-navy-100/70 pb-2 font-display text-sm font-bold text-navy-900">{titre}</h2>
      {lignes.length === 0 ? (
        <p className="text-sm text-navy-400">Aucun mouvement sur la période.</p>
      ) : (
        <ul className="flex flex-col">
          {lignes.map((l) => (
            <li key={l.code} className="flex items-baseline justify-between gap-3 border-b border-navy-50 py-2 text-sm">
              <span className="min-w-0 truncate">
                <span className="font-semibold text-navy-500">{l.code}</span> {l.libelle}
              </span>
              <span className={`flex-none font-semibold tabular-nums ${couleur}`}>{francs(l.montant)}</span>
            </li>
          ))}
        </ul>
      )}
      <div className="mt-3 flex items-center justify-between">
        <span className="text-sm font-semibold text-navy-700">Total</span>
        <span className={`text-lg font-bold tabular-nums ${couleur}`}>{francs(total)}</span>
      </div>
    </Card>
  )
}
