import { type ButtonHTMLAttributes } from 'react'
import { clsx } from 'clsx'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger'
type Size = 'sm' | 'md'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  size?: Size
}

const variantClasses: Record<Variant, string> = {
  primary:
    'bg-linear-to-b from-navy-700 to-navy-800 text-cream-50 shadow-card hover:from-navy-600 hover:to-navy-700 hover:shadow-lifted focus-visible:ring-navy-200 disabled:from-navy-300 disabled:to-navy-300 disabled:shadow-none',
  secondary:
    'bg-white text-navy-700 border border-navy-200 shadow-soft hover:border-navy-300 hover:bg-cream-50 hover:shadow-card focus-visible:ring-navy-100 disabled:opacity-60',
  ghost: 'bg-transparent text-navy-600 hover:bg-navy-50 hover:text-navy-800 focus-visible:ring-navy-100',
  danger:
    'bg-linear-to-b from-red-500 to-red-600 text-cream-50 shadow-card hover:shadow-lifted focus-visible:ring-red-50 disabled:from-red-300 disabled:to-red-300',
}

const sizeClasses: Record<Size, string> = {
  sm: 'px-3 py-1.5 text-xs',
  md: 'px-4 py-2.5 text-sm',
}

export function Button({ variant = 'primary', size = 'md', className, ...props }: ButtonProps) {
  return (
    <button
      className={clsx(
        'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl font-semibold tracking-tight transition-all duration-150 focus-visible:outline-none focus-visible:ring-4 active:scale-[0.97] disabled:cursor-not-allowed disabled:active:scale-100',
        variantClasses[variant],
        sizeClasses[size],
        className,
      )}
      {...props}
    />
  )
}
