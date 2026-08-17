import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell, CheckCheck } from 'lucide-react'
import { clsx } from 'clsx'
import {
  fetchNotifications,
  marquerNotificationLue,
  marquerToutesNotificationsLues,
  type NotificationInterne,
} from '@/features/notifications/api'
import { Spinner } from '@/shared/ui/Feedback'
import { notification as popupNotification } from '@/shared/lib/alertes'
import { jouerSonNotification } from '@/shared/lib/son'

const INTERVALLE_SONDAGE_MS = 20_000

/**
 * Cloche de notifications internes : le pendant « en application » des SMS —
 * absences, annonces, etc. Pas de permission dédiée, chacun ne voit que les
 * siennes.
 *
 * Sondée toutes les 20 secondes plutôt qu'à l'ouverture d'un flux temps réel
 * (WebSocket) : ce serveur n'expose aucune infrastructure de diffusion, et un
 * sondage court reproduit l'essentiel de l'expérience « WhatsApp Web » —
 * toast, son, notification du système — sans service supplémentaire à
 * exploiter en production.
 */
export function NotificationBell() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [ouvert, setOuvert] = useState(false)
  const idsConnusRef = useRef<Set<number> | null>(null)

  const { data: notifications, isLoading } = useQuery({
    queryKey: ['notifications'],
    queryFn: fetchNotifications,
    refetchInterval: INTERVALLE_SONDAGE_MS,
    refetchIntervalInBackground: true,
  })

  const nonLues = notifications?.filter((n) => !n.lu).length ?? 0

  const invalider = () => {
    queryClient.invalidateQueries({ queryKey: ['notifications'] })
  }

  const ouvrirNotification = async (notif: NotificationInterne) => {
    if (!notif.lu) {
      await marquerNotificationLue(notif.id)
      invalider()
    }
    if (notif.lien) {
      setOuvert(false)
      navigate(notif.lien)
    }
  }

  const toutMarquerLu = async () => {
    await marquerToutesNotificationsLues()
    invalider()
  }

  // Autorisation des notifications système, demandée une fois, comme le fait
  // WhatsApp Web à la première visite — sans quoi rien ne pourrait jamais
  // atteindre l'utilisateur en dehors de l'onglet actif.
  useEffect(() => {
    if ('Notification' in window && Notification.permission === 'default') {
      void Notification.requestPermission()
    }
  }, [])

  // Détecte les notifications réellement nouvelles entre deux sondages —
  // jamais celles déjà là à l'ouverture de la page, pour ne pas rejouer tout
  // l'historique au premier chargement.
  useEffect(() => {
    if (!notifications) return

    if (idsConnusRef.current === null) {
      idsConnusRef.current = new Set(notifications.map((n) => n.id))
      return
    }

    const nouvelles = notifications.filter((n) => !n.lu && !idsConnusRef.current!.has(n.id))
    if (nouvelles.length === 0) {
      notifications.forEach((n) => idsConnusRef.current!.add(n.id))
      return
    }

    nouvelles.forEach((notif) => {
      popupNotification({
        titre: notif.titre,
        message: notif.message,
        onClick: () => ouvrirNotification(notif),
      })

      if ('Notification' in window && Notification.permission === 'granted' && document.visibilityState !== 'visible') {
        const systeme = new Notification(notif.titre, {
          body: notif.message,
          tag: `notif-${notif.id}`,
          // Sans quoi le navigateur affiche son icône générique par défaut
          // plutôt que celle de l'établissement.
          icon: '/favicon.svg',
        })
        systeme.onclick = () => {
          window.focus()
          ouvrirNotification(notif)
        }
      }

      idsConnusRef.current!.add(notif.id)
    })

    jouerSonNotification()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [notifications])

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOuvert((v) => !v)}
        className="relative flex h-9 w-9 flex-none items-center justify-center rounded-xl text-navy-500 transition-colors hover:bg-cream-100 hover:text-navy-800"
        aria-label={t('notifications.title')}
      >
        <Bell className="h-5 w-5" />
        {nonLues > 0 && (
          <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
            {nonLues > 9 ? '9+' : nonLues}
          </span>
        )}
      </button>

      {ouvert && (
        <>
          <div className="fixed inset-0 z-30" onClick={() => setOuvert(false)} />
          <div className="absolute right-0 top-full z-40 mt-2 w-80 max-w-[90vw] overflow-hidden rounded-xl border border-navy-100 bg-white shadow-lifted">
            <div className="flex items-center justify-between border-b border-navy-50 px-3.5 py-2.5">
              <h2 className="text-sm font-bold text-navy-800">{t('notifications.title')}</h2>
              {nonLues > 0 && (
                <button
                  onClick={toutMarquerLu}
                  className="flex items-center gap-1 text-xs font-medium text-navy-500 hover:text-navy-800"
                >
                  <CheckCheck className="h-3.5 w-3.5" />
                  {t('notifications.tout_marquer_lu')}
                </button>
              )}
            </div>

            <div className="max-h-96 overflow-y-auto">
              {isLoading ? (
                <div className="flex justify-center py-6">
                  <Spinner />
                </div>
              ) : !notifications || notifications.length === 0 ? (
                <p className="px-3.5 py-6 text-center text-sm text-navy-400">{t('notifications.aucune')}</p>
              ) : (
                <div className="flex flex-col divide-y divide-navy-50">
                  {notifications.map((notif) => (
                    <button
                      key={notif.id}
                      onClick={() => ouvrirNotification(notif)}
                      className={clsx(
                        'flex flex-col items-start gap-0.5 px-3.5 py-2.5 text-left transition-colors hover:bg-cream-50',
                        !notif.lu && 'bg-gold-50/60',
                      )}
                    >
                      <span className="flex w-full items-center gap-1.5">
                        {!notif.lu && <span className="h-1.5 w-1.5 flex-none rounded-full bg-gold-500" />}
                        <span className="truncate text-sm font-semibold text-navy-900">{notif.titre}</span>
                      </span>
                      <span className="line-clamp-2 text-xs text-navy-500">{notif.message}</span>
                      <span className="text-[10px] text-navy-300">
                        {new Date(notif.created_at).toLocaleString('fr-FR', {
                          day: '2-digit',
                          month: '2-digit',
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </span>
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  )
}
