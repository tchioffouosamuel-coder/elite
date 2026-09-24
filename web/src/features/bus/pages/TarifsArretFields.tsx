import { useTranslation } from 'react-i18next'
import { MontantInput } from '@/shared/ui/Field'

export type CleTarif = 'tarif_aller_simple' | 'tarif_retour_simple' | 'tarif_aller_retour'

/**
 * Grille de prix d'un arrêt — c'est l'arrêt qui est facturé, pas le trajet.
 * Partagée par les deux formulaires d'arrêt (liste des arrêts, fiche trajet).
 */
export function TarifsArretFields({
  valeurs,
  onChange,
}: {
  valeurs: Partial<Record<CleTarif, number | null>>
  onChange: (cle: CleTarif, valeur: number) => void
}) {
  const { t } = useTranslation()

  return (
    <div className="rounded-xl border border-navy-100 p-3">
      <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-navy-500">{t('bus.tarifs_title')}</p>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        {(['tarif_aller_simple', 'tarif_retour_simple', 'tarif_aller_retour'] as const).map((cle) => (
          <MontantInput
            key={cle}
            label={t(`bus.${cle.replace('tarif_', '')}`)}
            value={valeurs[cle] ?? undefined}
            onChange={(v) => onChange(cle, v)}
          />
        ))}
      </div>
      <p className="mt-2 text-xs text-navy-400">{t('bus.tarifs_arret_hint')}</p>
    </div>
  )
}
