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
    'bg-navy-700 text-cream-50 shadow-soft hover:bg-navy-600 hover:shadow-card disabled:bg-navy-300 disabled:shadow-none',
  secondary:
    'bg-white text-navy-700 border border-navy-200 shadow-soft hover:border-navy-300 hover:bg-cream-50 disabled:opacity-60',
  ghost: 'bg-transparent text-navy-600 hover:bg-navy-50 hover:text-navy-800',
  danger: 'bg-red-500 text-cream-50 shadow-soft hover:bg-red-600 disabled:bg-red-300',
}

const sizeClasses: Record<Size, string> = {
  sm: 'px-3 py-1.5 text-xs',
  md: 'px-4 py-2.5 text-sm',
}

export function Button({ variant = 'primary', size = 'md', className, ...props }: ButtonProps) {
  return (
    <button
      className={clsx(
        'inline-flex items-center justify-center gap-2 rounded-xl font-semibold tracking-tight transition-all duration-150 active:scale-[0.97] disabled:cursor-not-allowed disabled:active:scale-100',
        variantClasses[variant],
        sizeClasses[size],
        className,
      )}
      {...props}
    />
  )
}
