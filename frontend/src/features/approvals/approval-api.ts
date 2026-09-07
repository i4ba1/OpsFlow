import type { Approval, Paginated } from '../../types/features';
import { api } from '../../api/client';

export type ApprovalFilters = {
  page?: number;
  per_page?: number;
  status?: Approval['status'][];
  assigned_to?: string;
  service_request_id?: string;
  sort?: string;
};

export async function fetchApprovals(filters: ApprovalFilters): Promise<Paginated<Approval>> {
  const response = await api.get<Paginated<Approval>>('/approvals', { params: filters });
  return response.data;
}

export async function fetchApproval(id: string): Promise<Approval> {
  const response = await api.get<{ data: Approval }>(`/approvals/${id}`);
  return response.data.data;
}

export type CreateApprovalPayload = {
  assigned_to: string;
  type: Approval['type'];
  title: string;
  description?: string | null;
  due_at?: string | null;
};

export async function createApproval(
  serviceRequestId: string,
  payload: CreateApprovalPayload,
): Promise<Approval> {
  const response = await api.post<{ data: Approval }>(
    `/service-requests/${serviceRequestId}/approvals`,
    payload,
  );
  return response.data.data;
}

export async function respondToApproval(
  approvalId: string,
  decision: 'approve' | 'reject',
  responseNote?: string,
): Promise<Approval> {
  const response = await api.post<{ data: Approval }>(`/approvals/${approvalId}/${decision}`, {
    response_note: responseNote,
  });
  return response.data.data;
}

export async function cancelApproval(approvalId: string): Promise<Approval> {
  const response = await api.post<{ data: Approval }>(`/approvals/${approvalId}/cancel`);
  return response.data.data;
}
