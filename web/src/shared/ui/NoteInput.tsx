import { clsx } from 'clsx'

/** Message d'erreur si la note est hors barème, ou undefined si elle est valide (une case vide est valide : une note non encore saisie n'est pas une erreur). */
export function messageErreurNote(valeur: string, max: number, min = 0): string | undefined {
  if (valeur.trim() === '') return undefined
  const nombre = Number(valeur)
  if (Number.isNaN(nombre)) return 'Nombre invalide'
  if (nombre < min) return `Min ${min}`
  if (nombre > max) return `Max ${max}`
  return undefined
}

interface NoteInputProps {
  value: string
  onChange: (valeur: string) => void
  max: number
  min?: number
  step?: number
  className?: string
}

/**
 * Champ de saisie d'une note, validé à chaque frappe plutôt qu'à la
 * soumission : la case et son message d'erreur passent au rouge dès que la
 * valeur sort du barème, sans attendre l'appel API et son 422.
 */
export function NoteInput({ value, onChange, max, min = 0, step = 0.25, className }: NoteInputProps) {
  const erreur = messageErreurNote(value, max, min)

  return (
    <span className="inline-flex flex-col items-center gap-0.5">
      <input
        type="number"
        min={min}
        max={max}
        step={step}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={erreur ? true : undefined}
        title={erreur}
        className={clsx(
          'rounded-lg border px-2.5 py-1.5 text-sm shadow-soft focus:outline-none focus:ring-4',
          erreur
            ? 'border-red-400 text-red-600 focus:border-red-400 focus:ring-red-100'
            : 'border-navy-200 focus:border-navy-400 focus:ring-navy-100',
          className,
        )}
      />
      {erreur && <span className="text-[10px] font-medium leading-none text-red-500">{erreur}</span>}
    </span>
  )
}
