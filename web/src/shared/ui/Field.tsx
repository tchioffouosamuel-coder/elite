import { type InputHTMLAttributes, type SelectHTMLAttributes, type ReactNode, type ComponentType } from 'react'
import { ChevronDown } from 'lucide-react'
import { clsx } from 'clsx'

const fieldClasses =
  'w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm text-navy-900 shadow-soft transition-colors placeholder:text-navy-300 focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100'

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
}

export function Input({ label, error, className, id, icon: Icon, ...props }: InputProps) {
  return (
    <FieldWrapper label={label} error={error} htmlFor={id}>
      <div className="relative">
        {Icon && <Icon className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-300" />}
        <input
          id={id}
          className={clsx(fieldClasses, Icon && 'pl-10', error && 'border-red-400 focus:border-red-400 focus:ring-red-100', className)}
          {...props}
        />
      </div>
    </FieldWrapper>
  )
}

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label?: string
  error?: string
}

export function Select({ label, error, className, id, children, ...props }: SelectProps) {
  return (
    <FieldWrapper label={label} error={error} htmlFor={id}>
      <div className="relative">
        <select
          id={id}
          className={clsx(
            fieldClasses,
            'appearance-none bg-white pr-10',
            error && 'border-red-400 focus:border-red-400 focus:ring-red-100',
            className,
          )}
          {...props}
        >
          {children}
        </select>
        <ChevronDown className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400" />
      </div>
    </FieldWrapper>
  )
}
