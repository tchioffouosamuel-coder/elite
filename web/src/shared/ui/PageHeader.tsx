import { type ComponentType, type ReactNode } from 'react'

/**
 * En-tête de page : titre, sous-titre et zone d'actions. Le conteneur est
 * lui-même `flex-wrap` : quand les actions (parfois 5 boutons ou plus) ne
 * tiennent pas à côté du titre, elles passent entièrement sur leur propre
 * ligne plutôt que de partager la largeur avec le titre — sinon `min-w-0`
 * laisse le titre se faire écraser (troncature en "…") au profit des actions.
 */
export function PageHeader({
  titre,
  sousTitre,
  icon: Icon,
  actions,
}: {
  titre: string
  sousTitre?: string
  icon?: ComponentType<{ className?: string }>
  actions?: ReactNode
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <div className="flex min-w-0 items-center gap-3">
        {Icon && (
          <span className="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-linear-to-br from-gold-50 to-gold-100 shadow-soft ring-1 ring-gold-100">
            <Icon className="h-5 w-5 text-gold-600" />
          </span>
        )}
        <div className="min-w-0">
          <h1 className="truncate font-display text-xl font-bold tracking-tight text-navy-900 sm:text-2xl">{titre}</h1>
          {sousTitre && <p className="truncate text-sm text-navy-400">{sousTitre}</p>}
        </div>
      </div>

      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}
