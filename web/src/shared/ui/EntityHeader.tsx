import { type ComponentType, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { clsx } from 'clsx'
import { ActionsMenu, type ActionGroup } from '@/shared/ui/ActionsMenu'

/**
 * En-tête d'une fiche d'entité (élève, agent, classe…). Trois choses au même
 * endroit : qui on regarde, dans quel état il est, et tout ce qu'on peut faire
 * pour lui — y compris les opérations qui appartiennent à d'autres modules.
 *
 * `actions` reçoit les deux ou trois gestes les plus fréquents, visibles sans
 * ouvrir quoi que ce soit ; `menu` reçoit le reste, rangé par section. Sortir
 * de la fiche pour aller chercher une opération dans le menu latéral n'est
 * plus nécessaire, c'est tout l'objet de ce composant.
 */
export function EntityHeader({
  retour,
  avatar,
  icon: Icon,
  titre,
  sousTitre,
  badges,
  actions,
  menu,
  menuLabel,
}: {
  retour?: { to: string; label: string }
  /** Photo ou initiales : prioritaire sur `icon` quand l'entité a un visage. */
  avatar?: ReactNode
  icon?: ComponentType<{ className?: string }>
  titre: string
  sousTitre?: ReactNode
  badges?: ReactNode
  actions?: ReactNode
  menu?: ActionGroup[]
  menuLabel?: string
}) {
  return (
    <div className="flex flex-col gap-3">
      {retour && (
        <Link
          to={retour.to}
          className="flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 transition-colors hover:text-navy-700"
        >
          <ArrowLeft className="h-4 w-4" />
          {retour.label}
        </Link>
      )}

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 items-center gap-3">
          {avatar ??
            (Icon && (
              <span className="flex h-12 w-12 flex-none items-center justify-center rounded-xl bg-linear-to-br from-gold-50 to-gold-100 shadow-soft ring-1 ring-gold-100">
                <Icon className="h-5 w-5 text-gold-600" />
              </span>
            ))}
          <div className="min-w-0">
            <h1 className="truncate font-display text-xl font-bold tracking-tight text-navy-900 sm:text-2xl">{titre}</h1>
            {sousTitre && <p className="truncate text-sm text-navy-400">{sousTitre}</p>}
          </div>
        </div>

        {(actions || menu) && (
          <div className="flex flex-wrap items-center gap-2">
            {actions}
            {menu && <ActionsMenu groupes={menu} label={menuLabel ?? 'Actions'} />}
          </div>
        )}
      </div>

      {badges && <div className="flex flex-wrap items-center gap-2">{badges}</div>}
    </div>
  )
}

/** Pastille d'identité : photo si elle existe, initiales sinon. */
export function Avatar({
  url,
  nom,
  className,
}: {
  url?: string | null
  nom: string
  className?: string
}) {
  if (url) {
    return (
      <img
        src={url}
        alt={nom}
        className={clsx('h-14 w-14 flex-none rounded-full object-cover ring-1 ring-navy-100', className)}
      />
    )
  }

  const initiales = nom
    .split(' ')
    .map((partie) => partie[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase()

  return (
    <span
      className={clsx(
        'flex h-14 w-14 flex-none items-center justify-center rounded-full bg-navy-700 text-lg font-bold text-cream-50',
        className,
      )}
    >
      {initiales || '?'}
    </span>
  )
}
