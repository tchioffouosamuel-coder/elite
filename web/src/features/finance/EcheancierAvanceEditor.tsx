import { francs, type LigneEcheancier } from '@/features/finance/api'

export type { LigneEcheancier }

/**
 * Répartition égale du montant sur `nombreMois`, à partir de `moisDebut` — le
 * reliquat de l'arrondi est absorbé par le dernier mois. Sert de valeur par
 * défaut au tableau, que l'utilisateur peut ensuite corriger ligne par ligne.
 */
export function genererEcheancierUniforme(montant: number, nombreMois: number, moisDebut: string): LigneEcheancier[] {
  if (montant <= 0 || nombreMois <= 0 || !moisDebut) return []

  const base = Math.floor(montant / nombreMois)
  const reliquat = montant - base * nombreMois
  const [annee, mois] = moisDebut.slice(0, 7).split('-').map(Number)

  return Array.from({ length: nombreMois }, (_, i) => {
    const date = new Date(annee, mois - 1 + i, 1)
    return {
      mois: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-01`,
      montant: base + (i === nombreMois - 1 ? reliquat : 0),
    }
  })
}

export function sommeEcheancier(echeancier: LigneEcheancier[]): number {
  return echeancier.reduce((total, ligne) => total + (ligne.montant || 0), 0)
}

function formatMoisFr(mois: string): string {
  const [annee, m] = mois.slice(0, 7).split('-').map(Number)
  return new Date(annee, m - 1, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
}

/**
 * Tableau éditable mois par mois : une ligne par échéance, montant modifiable
 * individuellement. Signale en rouge toute ligne au-delà du plafond, et le
 * total tant qu'il ne correspond pas au montant de l'avance.
 */
export function EcheancierTable({
  echeancier,
  montantCible,
  plafondMensualite,
  onChangeLigne,
}: {
  echeancier: LigneEcheancier[]
  montantCible: number
  plafondMensualite: number | null
  onChangeLigne: (index: number, montant: number) => void
}) {
  const total = sommeEcheancier(echeancier)
  const totalCorrect = total === montantCible

  if (echeancier.length === 0) return null

  return (
    <div className="flex flex-col gap-2">
      <div className="max-h-56 overflow-y-auto rounded-xl border border-navy-100">
        <table className="w-full text-sm">
          <thead className="sticky top-0 bg-cream-50 text-xs uppercase tracking-wide text-navy-400">
            <tr>
              <th className="px-3 py-2 text-left font-semibold">Mois</th>
              <th className="px-3 py-2 text-right font-semibold">Montant (F CFA)</th>
            </tr>
          </thead>
          <tbody>
            {echeancier.map((ligne, i) => {
              const horsPlafond = plafondMensualite != null && ligne.montant > plafondMensualite
              return (
                <tr key={ligne.mois} className="border-t border-navy-50">
                  <td className="px-3 py-1.5 capitalize text-navy-600">{formatMoisFr(ligne.mois)}</td>
                  <td className="px-3 py-1.5">
                    <input
                      type="number"
                      min={1}
                      value={ligne.montant}
                      onChange={(e) => onChangeLigne(i, Number(e.target.value) || 0)}
                      className={`w-full rounded-lg border px-2 py-1 text-right tabular-nums outline-none focus:ring-2 focus:ring-navy-200 ${
                        horsPlafond ? 'border-red-400 bg-red-50 text-red-700' : 'border-navy-100 bg-white text-navy-800'
                      }`}
                    />
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
      <div className={`flex items-center justify-between px-1 text-xs ${totalCorrect ? 'text-navy-400' : 'font-semibold text-red-600'}`}>
        <span>Total réparti : {francs(total)}</span>
        <span>Montant de l'avance : {francs(montantCible)}</span>
      </div>
      {!totalCorrect && (
        <p className="px-1 text-xs font-semibold text-red-600">
          Le total réparti doit être exactement égal au montant de l'avance avant de valider.
        </p>
      )}
    </div>
  )
}
