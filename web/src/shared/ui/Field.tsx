import {
  type InputHTMLAttributes,
  type SelectHTMLAttributes,
  type TextareaHTMLAttributes,
  type ReactNode,
  type ComponentType,
  type Ref,
  type ChangeEvent,
  type FocusEvent,
  type KeyboardEvent as ReactKeyboardEvent,
  Children,
  isValidElement,
  useCallback,
  useEffect,
  useId,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
} from 'react'
import { createPortal } from 'react-dom'
import { Check, ChevronDown, Search } from 'lucide-react'
import { clsx } from 'clsx'

const fieldClasses =
  'w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm text-navy-900 shadow-soft transition-colors placeholder:text-navy-300 focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100 disabled:cursor-not-allowed disabled:border-navy-100 disabled:bg-cream-100 disabled:text-navy-400 disabled:shadow-none'

interface WrapperProps {
  label?: string
  error?: string
  children: ReactNode
  htmlFor?: string
}

export function FieldWrapper({ label, error, children, htmlFor }: WrapperProps) {
  return (
    <label htmlFor={htmlFor} className="flex flex-col gap-1.5">
      {label && <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{label}</span>}
      {children}
      {error && <span className="text-xs font-medium text-red-500">{error}</span>}
    </label>
  )
}

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  icon?: ComponentType<{ className?: string }>
  endAdornment?: ReactNode
}

export function Input({ label, error, className, id, icon: Icon, endAdornment, ...props }: InputProps) {
  return (
    <FieldWrapper label={label} error={error} htmlFor={id}>
      <div className="relative">
        {Icon && <Icon className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-300" />}
        <input
          id={id}
          className={clsx(fieldClasses, Icon && 'pl-10', endAdornment && 'pr-10', error && 'border-red-400 focus:border-red-400 focus:ring-red-100', className)}
          {...props}
        />
        {endAdornment && <div className="absolute right-3 top-1/2 flex -translate-y-1/2 items-center">{endAdornment}</div>}
      </div>
    </FieldWrapper>
  )
}

function formaterMontant(valeur: number): string {
  return valeur.toLocaleString('fr-FR')
}

/**
 * Regroupe les milliers à chaque frappe (pas seulement à la sortie du champ),
 * en replaçant le curseur au même rang de chiffres pour qu'il ne saute pas en
 * fin de champ à chaque caractère tapé.
 */
export function useMontantSaisie(valeur: number | null | undefined, onChange: (valeur: number) => void) {
  const ref = useRef<HTMLInputElement>(null)
  const curseurVise = useRef<number | null>(null)

  useLayoutEffect(() => {
    if (curseurVise.current === null) return
    ref.current?.setSelectionRange(curseurVise.current, curseurVise.current)
    curseurVise.current = null
  })

  return {
    ref,
    type: 'text' as const,
    inputMode: 'numeric' as const,
    autoComplete: 'off',
    value: valeur !== null && valeur !== undefined ? formaterMontant(valeur) : '',
    onFocus: (e: FocusEvent<HTMLInputElement>) => requestAnimationFrame(() => e.target.select()),
    onChange: (e: ChangeEvent<HTMLInputElement>) => {
      const input = e.target
      const positionCurseur = input.selectionStart ?? input.value.length
      const chiffresAvantCurseur = input.value.slice(0, positionCurseur).replace(/\D/g, '').length
      const chiffres = input.value.replace(/\D/g, '')
      const nombre = chiffres === '' ? 0 : Number(chiffres)
      const formate = chiffres === '' ? '' : formaterMontant(nombre)

      let position = 0
      let compte = 0
      if (chiffresAvantCurseur > 0) {
        position = formate.length
        for (let i = 0; i < formate.length; i++) {
          if (/\d/.test(formate[i])) compte++
          if (compte === chiffresAvantCurseur) {
            position = i + 1
            break
          }
        }
      }

      curseurVise.current = position
      onChange(nombre)
    },
  }
}

interface MontantInputProps {
  label?: string
  error?: string
  value: number | null | undefined
  onChange: (valeur: number) => void
  onBlur?: () => void
  id?: string
  name?: string
  placeholder?: string
  autoFocus?: boolean
  disabled?: boolean
  className?: string
}

/** Champ de saisie d'un montant en francs CFA (entiers), groupé par milliers pour rester lisible. */
export function MontantInput({ label, error, value, onChange, onBlur, id, name, placeholder, autoFocus, disabled, className }: MontantInputProps) {
  const champ = useMontantSaisie(value, onChange)

  return (
    <FieldWrapper label={label} error={error} htmlFor={id}>
      <input
        id={id}
        name={name}
        placeholder={placeholder}
        autoFocus={autoFocus}
        disabled={disabled}
        {...champ}
        onBlur={onBlur}
        className={clsx(fieldClasses, 'text-right tabular-nums', error && 'border-red-400 focus:border-red-400 focus:ring-red-100', className)}
      />
    </FieldWrapper>
  )
}

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string
  error?: string
}

export function Textarea({ label, error, className, id, rows = 3, ...props }: TextareaProps) {
  return (
    <FieldWrapper label={label} error={error} htmlFor={id}>
      <textarea
        id={id}
        rows={rows}
        className={clsx(fieldClasses, 'resize-y', error && 'border-red-400 focus:border-red-400 focus:ring-red-100', className)}
        {...props}
      />
    </FieldWrapper>
  )
}

interface Option {
  value: string
  label: string
  disabled?: boolean
}

/** Concatène les enfants texte/nombre d'un nœud, en ignorant le reste (ex: {a} ({b}) → "a (b)"). */
function texteEnfants(node: ReactNode): string {
  return Children.toArray(node)
    .map((enfant) => (typeof enfant === 'string' || typeof enfant === 'number' ? String(enfant) : ''))
    .join('')
}

/** Aplatit les <option> passés en enfants JSX, y compris via .map()/fragments. */
function extraireOptions(children: ReactNode): Option[] {
  const options: Option[] = []

  Children.forEach(children, (enfant) => {
    if (!isValidElement<{ value?: string | number; children?: ReactNode; disabled?: boolean }>(enfant)) return
    if (enfant.type !== 'option') return

    const { value, children: libelle, disabled } = enfant.props
    options.push({
      value: value === undefined ? '' : String(value),
      label: texteEnfants(libelle),
      disabled,
    })
  })

  return options
}

/** Attache un même nœud DOM à plusieurs refs (celle de react-hook-form et la nôtre). */
function fusionnerRefs<T>(...refs: (Ref<T> | undefined)[]) {
  return (noeud: T | null) => {
    for (const ref of refs) {
      if (!ref) continue
      if (typeof ref === 'function') ref(noeud)
      else (ref as { current: T | null }).current = noeud
    }
  }
}

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label?: string
  error?: string
  ref?: Ref<HTMLSelectElement>
}

/**
 * Select recherchable façon Select2 : un <select> natif reste la source de
 * vérité (register() de react-hook-form s'y attache normalement, y compris
 * pour defaultValues/validation/reset), visuellement masqué derrière une
 * interface personnalisée avec recherche. Le menu est porté dans <body> pour
 * échapper au `overflow-hidden` des modales.
 */
export function Select({ label, error, className, id, children, ref, value, onChange, onBlur, disabled, ...props }: SelectProps) {
  const options = useMemo(() => extraireOptions(children), [children])

  const [ouvert, setOuvert] = useState(false)
  const [recherche, setRecherche] = useState('')
  const [survol, setSurvol] = useState(0)
  const [affichage, setAffichage] = useState('')
  const [position, setPosition] = useState<{ top: number; left: number; width: number; ouvreVersLeHaut: boolean } | null>(null)

  const selectRef = useRef<HTMLSelectElement>(null)
  const conteneurRef = useRef<HTMLDivElement>(null)
  const declencheurRef = useRef<HTMLButtonElement>(null)
  const rechercheRef = useRef<HTMLInputElement>(null)
  const menuRef = useRef<HTMLDivElement>(null)

  const genereId = useId()
  const triggerId = id ?? genereId

  // Non contrôlé (register seul, sans prop value) : l'état affiché suit le
  // <select> caché, dont react-hook-form définit lui-même la valeur initiale
  // (defaultValues) au montage, avant que cet effet ne s'exécute.
  useEffect(() => {
    if (value === undefined) setAffichage(selectRef.current?.value ?? '')
  }, [value])

  const valeurCourante = value !== undefined ? String(value) : affichage
  const optionCourante = options.find((o) => o.value === valeurCourante)

  const filtres = useMemo(() => {
    const q = recherche.trim().toLowerCase()
    return q === '' ? options : options.filter((o) => o.label.toLowerCase().includes(q))
  }, [options, recherche])

  const positionner = () => {
    const rect = declencheurRef.current?.getBoundingClientRect()
    if (!rect) return
    const espaceBas = window.innerHeight - rect.bottom
    const ouvreVersLeHaut = espaceBas < 260 && rect.top > espaceBas
    setPosition({ top: ouvreVersLeHaut ? rect.top : rect.bottom, left: rect.left, width: rect.width, ouvreVersLeHaut })
  }

  const ouvrir = () => {
    if (disabled) return
    positionner()
    setRecherche('')
    setSurvol(Math.max(options.findIndex((o) => o.value === valeurCourante), 0))
    setOuvert(true)
  }

  const fermer = useCallback(
    (rendreLeFocus = false) => {
      setOuvert(false)
      onBlur?.({ target: selectRef.current } as unknown as FocusEvent<HTMLSelectElement>)
      if (rendreLeFocus) declencheurRef.current?.focus()
    },
    [onBlur],
  )

  useEffect(() => {
    if (!ouvert) return
    requestAnimationFrame(() => rechercheRef.current?.focus())

    const surClicExterieur = (e: MouseEvent) => {
      const cible = e.target as Node
      if (conteneurRef.current?.contains(cible) || menuRef.current?.contains(cible)) return
      fermer()
    }
    const surRepositionnement = () => positionner()

    document.addEventListener('mousedown', surClicExterieur)
    window.addEventListener('resize', surRepositionnement)
    window.addEventListener('scroll', surRepositionnement, true)
    return () => {
      document.removeEventListener('mousedown', surClicExterieur)
      window.removeEventListener('resize', surRepositionnement)
      window.removeEventListener('scroll', surRepositionnement, true)
    }
  }, [ouvert, fermer])

  const choisir = (option: Option) => {
    if (option.disabled) return

    const noeud = selectRef.current
    if (noeud) {
      // Déclenche le vrai setter natif puis un événement 'change' réel :
      // c'est ainsi que React (et donc onChange fourni par register()) détecte
      // un changement de valeur programmatique sur un <select>.
      const setter = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value')?.set
      setter?.call(noeud, option.value)
      noeud.dispatchEvent(new Event('change', { bubbles: true }))
    }
    setAffichage(option.value)
    fermer(true)
  }

  const surClavierDeclencheur = (e: ReactKeyboardEvent<HTMLButtonElement>) => {
    if (ouvert) return
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
      e.preventDefault()
      ouvrir()
    }
  }

  const surClavierRecherche = (e: ReactKeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setSurvol((i) => Math.min(i + 1, filtres.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setSurvol((i) => Math.max(i - 1, 0))
    } else if (e.key === 'Enter') {
      e.preventDefault()
      const option = filtres[survol]
      if (option) choisir(option)
    } else if (e.key === 'Escape') {
      e.preventDefault()
      fermer(true)
    }
  }

  return (
    <FieldWrapper label={label} error={error} htmlFor={triggerId}>
      <div ref={conteneurRef} className="relative">
        {/* Source de vérité pour react-hook-form : masqué visuellement mais
            fonctionnellement présent (defaultValues, validation, reset…). */}
        <select
          ref={fusionnerRefs(ref, selectRef)}
          tabIndex={-1}
          aria-hidden="true"
          className="sr-only"
          value={value}
          onChange={onChange}
          disabled={disabled}
          {...props}
        >
          {children}
        </select>

        <button
          ref={declencheurRef}
          type="button"
          id={triggerId}
          disabled={disabled}
          onClick={() => (ouvert ? fermer() : ouvrir())}
          onKeyDown={surClavierDeclencheur}
          className={clsx(
            fieldClasses,
            'flex items-center justify-between gap-2 text-left',
            error && 'border-red-400 focus:border-red-400 focus:ring-red-100',
            className,
          )}
        >
          <span className={clsx('truncate', !optionCourante?.label && 'text-navy-300')}>
            {optionCourante?.label || '—'}
          </span>
          <ChevronDown className={clsx('h-4 w-4 flex-none text-navy-400 transition-transform', ouvert && 'rotate-180')} />
        </button>

        {ouvert &&
          position &&
          createPortal(
            <div
              ref={menuRef}
              style={{
                position: 'fixed',
                left: position.left,
                width: position.width,
                ...(position.ouvreVersLeHaut ? { bottom: window.innerHeight - position.top + 4 } : { top: position.top + 4 }),
              }}
              className="z-[100] flex max-h-72 flex-col overflow-hidden rounded-xl border border-navy-100 bg-white shadow-lifted"
            >
              <div className="relative flex-none border-b border-navy-50 p-1.5">
                <Search className="pointer-events-none absolute left-4 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
                <input
                  ref={rechercheRef}
                  value={recherche}
                  onChange={(e) => {
                    setRecherche(e.target.value)
                    setSurvol(0)
                  }}
                  onKeyDown={surClavierRecherche}
                  placeholder="Rechercher…"
                  className="w-full rounded-lg border-none bg-cream-50 py-1.5 pl-8 pr-2 text-sm text-navy-900 placeholder:text-navy-300 focus:outline-none focus:ring-2 focus:ring-navy-100"
                />
              </div>
              <ul role="listbox" className="overflow-y-auto py-1">
                {filtres.length === 0 ? (
                  <li className="px-3 py-2 text-sm text-navy-300">Aucun résultat</li>
                ) : (
                  filtres.map((option, i) => (
                    <li
                      key={option.value}
                      role="option"
                      aria-selected={option.value === valeurCourante}
                      onMouseDown={(e) => e.preventDefault()}
                      onMouseEnter={() => setSurvol(i)}
                      onClick={() => choisir(option)}
                      className={clsx(
                        'flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm text-navy-700',
                        option.disabled && 'cursor-not-allowed text-navy-300',
                        !option.disabled && i === survol && 'bg-cream-100',
                        option.value === valeurCourante && 'font-semibold text-navy-900',
                      )}
                    >
                      <span className="truncate">{option.label || '—'}</span>
                      {option.value === valeurCourante && <Check className="h-3.5 w-3.5 flex-none text-gold-500" />}
                    </li>
                  ))
                )}
              </ul>
            </div>,
            document.body,
          )}
      </div>
    </FieldWrapper>
  )
}
