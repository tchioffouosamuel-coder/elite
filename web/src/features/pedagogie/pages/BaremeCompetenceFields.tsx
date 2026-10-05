import { useTranslation } from 'react-i18next'
import { LIBELLES_COMPOSANTES, type Composante } from '@/features/primaire/api'
import { Input } from '@/shared/ui/Field'
import type { BaremeCompetencePayload } from '@/features/pedagogie/api'

/** Volets systématiques, plus « pratique » si la classe l'évalue. */
export function voletsActifs(evaluePratique: boolean): Composante[] {
  return evaluePratique ? ['oral', 'ecrit', 'savoir_etre', 'pratique'] : ['oral', 'ecrit', 'savoir_etre']
}

/** Barème vide : la maternelle note par appréciation, sans points à répartir. */
export const BAREME_VIDE: BaremeCompetencePayload = {
  notation: null,
  evalue_pratique: false,
  repartition_volets: null,
}

export interface BaremeSaisi {
  notation: number
  evalue_pratique: boolean
  repartition_volets: Partial<Record<Composante, number>>
}

/** Barème par défaut d'une attribution nouvelle, ou celui déjà enregistré. */
export function baremeInitial(source?: {
  notation: number | null
  evalue_pratique: boolean
  repartition_volets: Record<string, number>
}): BaremeSaisi {
  return {
    notation: source?.notation ?? 20,
    evalue_pratique: source?.evalue_pratique ?? false,
    repartition_volets: source?.repartition_volets ?? {},
  }
}

/**
 * Met le barème saisi en forme pour l'API. Chaque volet actif est envoyé,
 * celui qu'on laisse vide valant 0 point — c'est ainsi que l'on retire un
 * volet de la grille de saisie et du bulletin.
 */
export function payloadBareme(saisi: BaremeSaisi, maternelle: boolean): BaremeCompetencePayload {
  if (maternelle) return BAREME_VIDE

  return {
    notation: Number(saisi.notation),
    evalue_pratique: saisi.evalue_pratique,
    repartition_volets: Object.fromEntries(
      voletsActifs(saisi.evalue_pratique).map((volet) => [volet, Number(saisi.repartition_volets[volet]) || 0]),
    ),
  }
}

/**
 * Saisie du barème d'une compétence DANS UNE CLASSE : notation, volet pratique
 * et répartition des points.
 *
 * Le même bloc sert à l'attribution d'un lot de compétences et à la retouche
 * d'une attribution déjà posée — le réglage est le même, seul son périmètre
 * change. Il ne s'affiche pas en maternelle, qui évalue par appréciation (un
 * visage coché par volet) et n'a donc ni barème ni points à répartir.
 */
export function BaremeCompetenceFields({
  valeur,
  onChange,
}: {
  valeur: BaremeSaisi
  onChange: (suivant: BaremeSaisi) => void
}) {
  const { t } = useTranslation()
  const volets = voletsActifs(valeur.evalue_pratique)
  const somme = volets.reduce((total, volet) => total + (Number(valeur.repartition_volets[volet]) || 0), 0)
  // Purement indicatif : chaque volet est facultatif, celui qu'on laisse vide
  // compte pour 0 point (cf. ClasseCompetence::repartitionVolets côté API) —
  // la somme n'a donc pas à égaler le barème pour enregistrer.
  const equilibre = valeur.notation > 0 && Math.abs(somme - valeur.notation) < 0.01

  return (
    <div className="flex flex-col gap-3 rounded-xl border border-navy-100 bg-cream-50/60 p-3">
      <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
        {t('competences.bareme_dans_classe')}
      </span>

      <Input
        type="number"
        min={5}
        max={100}
        label={t('matieres.notation')}
        value={valeur.notation}
        onChange={(e) => onChange({ ...valeur, notation: Number(e.target.value) })}
      />

      <label className="flex items-center gap-2 text-sm text-navy-700">
        <input
          type="checkbox"
          className="h-4 w-4 rounded border-navy-300"
          checked={valeur.evalue_pratique}
          onChange={(e) => onChange({ ...valeur, evalue_pratique: e.target.checked })}
        />
        {t('matieres.evalue_pratique')}
      </label>

      <div className="grid grid-cols-2 gap-3">
        {volets.map((volet) => (
          <Input
            key={volet}
            type="number"
            min={0}
            step={0.5}
            label={LIBELLES_COMPOSANTES[volet]}
            value={valeur.repartition_volets[volet] ?? ''}
            onChange={(e) =>
              onChange({
                ...valeur,
                repartition_volets: { ...valeur.repartition_volets, [volet]: Number(e.target.value) },
              })
            }
          />
        ))}
      </div>

      <span className={`text-xs font-medium ${equilibre ? 'text-green-600' : 'text-navy-400'}`}>
        {t('matieres.repartition_somme', { somme, notation: valeur.notation })}
      </span>
    </div>
  )
}
