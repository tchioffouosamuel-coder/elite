import { Bell, Mail, MessageCircle, MessageSquare } from 'lucide-react'

export type CanalNotification = 'sms' | 'whatsapp' | 'email' | 'interne'

const CANAUX: { valeur: CanalNotification; label: string; icon: typeof MessageSquare }[] = [
  { valeur: 'sms', label: 'SMS', icon: MessageSquare },
  { valeur: 'whatsapp', label: 'WhatsApp', icon: MessageCircle },
  { valeur: 'email', label: 'Email', icon: Mail },
  { valeur: 'interne', label: 'Notification simple', icon: Bell },
]

/**
 * Choix des canaux de confirmation du paiement à la famille — coché
 * individuellement plutôt qu'un simple booléen « notifier » : une famille
 * sans email ignore silencieusement la case email (cf. les contrôleurs
 * d'encaissement, qui n'envoient que ce que le tuteur peut recevoir).
 */
export function CanauxNotificationField({
  value,
  onChange,
}: {
  value: CanalNotification[]
  onChange: (value: CanalNotification[]) => void
}) {
  const toggle = (canal: CanalNotification) => {
    onChange(value.includes(canal) ? value.filter((c) => c !== canal) : [...value, canal])
  }

  return (
    <div className="flex flex-col gap-2">
      <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Notifier le paiement par</span>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {CANAUX.map(({ valeur, label, icon: Icon }) => (
          <label
            key={valeur}
            className="flex cursor-pointer items-center gap-2 rounded-xl border border-navy-100 bg-white px-3 py-2 text-sm text-navy-700"
          >
            <input
              type="checkbox"
              checked={value.includes(valeur)}
              onChange={() => toggle(valeur)}
              className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
            />
            <Icon className="h-4 w-4 flex-none text-navy-400" />
            <span className="truncate">{label}</span>
          </label>
        ))}
      </div>
    </div>
  )
}
