import type { Paginated, AppNotification } from '../../types/features';
import { api } from '../../api/client';

export async function fetchNotifications(params?: {
  page?: number;
  per_page?: number;
}): Promise<Paginated<AppNotification>> {
  const response = await api.get<Paginated<AppNotification>>('/notifications', { params });
  return response.data;
}

export async function fetchUnreadCount(): Promise<number> {
  const response = await api.get<{ data: { count: number } }>('/notifications/unread-count');
  return response.data.data.count;
}

export async function markNotificationRead(id: string): Promise<void> {
  await api.post(`/notifications/${id}/read`);
}

export async function markAllNotificationsRead(): Promise<void> {
  await api.post('/notifications/read-all');
}
