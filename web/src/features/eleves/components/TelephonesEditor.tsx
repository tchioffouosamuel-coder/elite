import { Plus, Star, Trash2 } from 'lucide-react'
import { clsx } from 'clsx'
import { NB_TELEPHONES_MIN, type TelephoneEntry } from '@/features/eleves/lib/telephones'

/**
 * Liste des numéros de téléphone d'un tuteur, en état contrôlé par le
 * parent : au moins `min` champs, extensibles, une seule case "principal"
 * cochable à la fois (comportement de radio implémenté à la main pour
 * rester un simple tableau `boolean`) — même pattern que l'inscription.
 */
export function TelephonesEditor({
  telephones,
  onChange,
  min = NB_TELEPHONES_MIN,
  label = 'Numéros de téléphone',
  addLabel = 'Ajouter un numéro',
  placeholder = 'Numéro de téléphone',
  principalTitle = 'Marquer comme principal',
  error,
}: {
  telephones: TelephoneEntry[]
  onChange: (next: TelephoneEntry[]) => void
  min?: number
  label?: string
  addLabel?: string
  placeholder?: string
  principalTitle?: string
  error?: string
}) {
  const marquerPrincipal = (i: number) => {
    onChange(telephones.map((tel, j) => ({ ...tel, is_principal: j === i })))
  }

  const modifierNumero = (i: number, numero: string) => {
    onChange(telephones.map((tel, j) => (j === i ? { ...tel, numero } : tel)))
  }

  const supprimer = (i: number) => {
    const etaitPrincipal = telephones[i]?.is_principal
    const next = telephones.filter((_, j) => j !== i)
    if (etaitPrincipal && next.length > 0) next[0] = { ...next[0], is_principal: true }
    onChange(next)
  }

  const ajouter = () => onChange([...telephones, { numero: '', is_principal: false }])

  return (
    <div className="flex flex-col gap-2">
      <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{label}</span>
      {telephones.map((tel, i) => (
        <div key={i} className="flex items-center gap-2">
          <input
            type="tel"
            placeholder={placeholder}
            value={tel.numero}
            onChange={(e) => modifierNumero(i, e.target.value)}
            className="w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm text-navy-900 shadow-soft transition-colors placeholder:text-navy-300 focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
          />
          <button
            type="button"
            onClick={() => marquerPrincipal(i)}
            title={principalTitle}
            className="flex-none rounded-lg p-2 text-navy-300 hover:bg-gold-50 hover:text-gold-500"
          >
            <Star className={clsx('h-4 w-4', tel.is_principal && 'fill-gold-400 text-gold-500')} />
          </button>
          {telephones.length > min && (
            <button
              type="button"
              onClick={() => supprimer(i)}
              className="flex-none rounded-lg p-2 text-navy-300 hover:bg-red-100 hover:text-red-500"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          )}
        </div>
      ))}
      {error && <span className="text-xs font-medium text-red-500">{error}</span>}
      <button
        type="button"
        onClick={ajouter}
        className="inline-flex w-fit items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-500 hover:bg-navy-50 hover:text-navy-700"
      >
        <Plus className="h-3.5 w-3.5" />
        {addLabel}
      </button>
    </div>
  )
}
