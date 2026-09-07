import type { BusinessCalendar, SlaPolicy } from '../../types/features';
import { api } from '../../api/client';

export async function fetchSlaPolicies(): Promise<SlaPolicy[]> {
  const response = await api.get<{ data: SlaPolicy[] }>('/sla-policies', {
    params: { per_page: 50 },
  });
  return response.data.data;
}

export async function fetchSlaPolicy(id: string): Promise<SlaPolicy> {
  const response = await api.get<{ data: SlaPolicy }>(`/sla-policies/${id}`);
  return response.data.data;
}

export type SlaPolicyPayload = {
  name: string;
  description?: string | null;
  timezone?: string;
  business_calendar_id?: string | null;
  active?: boolean;
  targets: {
    priority: string;
    first_response_minutes: number;
    resolution_minutes: number;
    warning_threshold_percent: number;
  }[];
};

export async function createSlaPolicy(payload: SlaPolicyPayload): Promise<SlaPolicy> {
  const response = await api.post<{ data: SlaPolicy }>('/sla-policies', payload);
  return response.data.data;
}

export async function updateSlaPolicy(
  id: string,
  payload: Partial<SlaPolicyPayload>,
): Promise<SlaPolicy> {
  const response = await api.patch<{ data: SlaPolicy }>(`/sla-policies/${id}`, payload);
  return response.data.data;
}

export async function fetchBusinessCalendars(): Promise<BusinessCalendar[]> {
  const response = await api.get<{ data: BusinessCalendar[] }>('/business-calendars', {
    params: { per_page: 50 },
  });
  return response.data.data;
}
