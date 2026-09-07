import type { Attachment, Comment } from '../../types/features';
import { api } from '../../api/client';

export async function fetchComments(serviceRequestId: string): Promise<Comment[]> {
  const response = await api.get<{ data: Comment[] }>(
    `/service-requests/${serviceRequestId}/comments`,
  );
  return response.data.data;
}

export async function createComment(
  serviceRequestId: string,
  body: string,
  visibility: 'PUBLIC' | 'INTERNAL',
): Promise<Comment> {
  const response = await api.post<{ data: Comment }>(
    `/service-requests/${serviceRequestId}/comments`,
    { body, visibility },
  );
  return response.data.data;
}

export async function fetchAttachments(serviceRequestId: string): Promise<Attachment[]> {
  const response = await api.get<{ data: Attachment[] }>(
    `/service-requests/${serviceRequestId}/attachments`,
  );
  return response.data.data;
}

export async function uploadAttachment(serviceRequestId: string, file: File): Promise<Attachment> {
  const formData = new FormData();
  formData.append('file', file);

  const response = await api.post<{ data: Attachment }>(
    `/service-requests/${serviceRequestId}/attachments`,
    formData,
    { headers: { 'Content-Type': 'multipart/form-data' } },
  );
  return response.data.data;
}

export function attachmentDownloadUrl(id: string): string {
  return `${import.meta.env.VITE_API_BASE_URL ?? '/api/v1'}/attachments/${id}/download`;
}
