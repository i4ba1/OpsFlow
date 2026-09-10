import type {
  Paginated,
  RequestFilters,
  ServiceRequest,
  ServiceRequestEvent,
} from '../../types/features';
import { api } from '../../api/client';

export async function fetchRequests(filters: RequestFilters): Promise<Paginated<ServiceRequest>> {
  const response = await api.get<Paginated<ServiceRequest>>('/service-requests', {
    params: filters,
  });
  return response.data;
}

export async function fetchRequest(id: string): Promise<ServiceRequest> {
  const response = await api.get<{ data: ServiceRequest }>(`/service-requests/${id}`);
  return response.data.data;
}

export type CreateRequestPayload = {
  customer_id?: string | null;
  project_id?: string | null;
  title: string;
  description: string;
  priority: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';
  category_id?: string | null;
};

export async function createRequest(payload: CreateRequestPayload): Promise<ServiceRequest> {
  const response = await api.post<{ data: ServiceRequest }>('/service-requests', payload);
  return response.data.data;
}

export type UpdateRequestPayload = {
  title?: string;
  description?: string;
  category_id?: string | null;
  version: number;
};

export async function updateRequest(
  id: string,
  payload: UpdateRequestPayload,
): Promise<ServiceRequest> {
  const response = await api.patch<{ data: ServiceRequest }>(`/service-requests/${id}`, payload);
  return response.data.data;
}

export async function assignRequest(id: string, assignedTo: string): Promise<ServiceRequest> {
  const response = await api.post<{ data: ServiceRequest }>(`/service-requests/${id}/assign`, {
    assigned_to: assignedTo,
  });
  return response.data.data;
}

export async function changeRequestPriority(
  id: string,
  priority: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW',
): Promise<ServiceRequest> {
  const response = await api.post<{ data: ServiceRequest }>(`/service-requests/${id}/priority`, {
    priority,
  });
  return response.data.data;
}

export async function transitionRequest(
  id: string,
  status: string,
  note?: string,
  version?: number,
): Promise<ServiceRequest> {
  const response = await api.post<{ data: ServiceRequest }>(`/service-requests/${id}/transition`, {
    status,
    note,
    version,
  });
  return response.data.data;
}

export async function fetchRequestHistory(id: string): Promise<ServiceRequestEvent[]> {
  const response = await api.get<{ data: ServiceRequestEvent[] }>(
    `/service-requests/${id}/history`,
  );
  return response.data.data;
}
