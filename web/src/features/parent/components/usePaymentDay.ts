import { useEffect, useState } from 'react'

// Dates civiles à Douala (UTC+1) : le fuseau de l'appareil du parent peut différer.
function schoolDay(): number {
  const date = new Date(Date.now() + 60 * 60 * 1000)
  return Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate())
}

export function usePaymentDay() {
  const [today, setToday] = useState(schoolDay)
  useEffect(() => {
    const refresh = () => setToday(schoolDay())
    const timer = window.setInterval(refresh, 60_000)
    document.addEventListener('visibilitychange', refresh)
    window.addEventListener('focus', refresh)
    return () => {
      window.clearInterval(timer)
      document.removeEventListener('visibilitychange', refresh)
      window.removeEventListener('focus', refresh)
    }
  }, [])
  return today
}

export function paymentDaysRemaining(date: string | null, today: number): number | null {
  if (!date) return null
  const due = Date.parse(`${date.slice(0, 10)}T00:00:00Z`)
  return Number.isFinite(due) ? Math.round((due - today) / 86_400_000) : null
}
