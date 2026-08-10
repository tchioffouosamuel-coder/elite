import { Loader2, Inbox, AlertTriangle } from 'lucide-react'
import { useTranslation } from 'react-i18next'

export function Spinner({ label }: { label?: string }) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-navy-400">
      <Loader2 className="h-6 w-6 animate-spin text-navy-500" />
      <span className="text-sm font-medium">{label ?? t('common.loading')}</span>
    </div>
  )
}

export function EmptyState({ label }: { label?: string }) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-navy-200 py-16 text-center">
      <span className="flex h-11 w-11 items-center justify-center rounded-full bg-cream-100 text-navy-300">
        <Inbox className="h-5 w-5" />
      </span>
      <span className="text-sm font-medium text-navy-400">{label ?? t('common.empty')}</span>
    </div>
  )
}

export function ErrorState({ message }: { message?: string }) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-red-200 bg-red-50/40 py-16 text-center">
      <span className="flex h-11 w-11 items-center justify-center rounded-full bg-red-50 text-red-500">
        <AlertTriangle className="h-5 w-5" />
      </span>
      <span className="text-sm font-medium text-red-500">{message ?? t('common.error_generic')}</span>
    </div>
  )
}
