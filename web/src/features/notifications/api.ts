import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface NotificationInterne {
  id: number
  type: string
  titre: string
  message: string
  lien: string | null
  lu: boolean
  lu_le: string | null
  created_at: string
}

export async function fetchNotifications(): Promise<NotificationInterne[]> {
  const { data } = await http.get<ApiResponse<NotificationInterne[]>>('/notifications')
  return data.data
}

export async function fetchNotificationsNonLues(): Promise<number> {
  const { data } = await http.get<ApiResponse<{ total: number }>>('/notifications/non-lues')
  return data.data.total
}

export async function marquerNotificationLue(id: number): Promise<void> {
  await http.post(`/notifications/${id}/lire`)
}

export async function marquerToutesNotificationsLues(): Promise<void> {
  await http.post('/notifications/tout-lire')
}
