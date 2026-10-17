import { useTranslation } from 'react-i18next'

/** Longueur maximale imposée côté serveur (point 16 de la punch-list). */
export const APPRECIATION_SEQUENCE_MAX = 60

/**
 * Entrée compacte pour l'appréciation de séquence (1 ou 2 seulement, cf.
 * `AppreciationSequenceService` côté API) : un texte court avec compteur de
 * caractères, pensée pour tenir sous une note dans une cellule de grille.
 */
export function AppreciationSequenceInput({
  value,
  onChange,
  readOnly,
  eleveLabel,
  className = '',
}: {
  value: string
  onChange: (value: string) => void
  readOnly?: boolean
  /** Pour l'accessibilité (`aria-label`) — identifie la cellule dans une grille sans libellé visible propre. */
  eleveLabel?: string
  className?: string
}) {
  const { t } = useTranslation()

  return (
    <div className={`flex flex-col gap-0.5 ${className}`}>
      <input
        type="text"
        maxLength={APPRECIATION_SEQUENCE_MAX}
        readOnly={readOnly}
        value={value}
        aria-label={eleveLabel ? `${t('notes.appreciation_sequence')} — ${eleveLabel}` : t('notes.appreciation_sequence')}
        onChange={(e) => onChange(e.target.value)}
        placeholder={t('notes.appreciation_sequence_placeholder')}
        className="w-full min-w-0 rounded-md border border-navy-200 bg-white px-1.5 py-1 text-xs text-navy-700 placeholder:text-navy-300 focus:border-gold-400 focus:outline-none read-only:bg-cream-50 read-only:text-navy-400"
      />
      <span className="text-right text-[0.625rem] text-navy-400">
        {value.length}/{APPRECIATION_SEQUENCE_MAX}
      </span>
    </div>
  )
}
