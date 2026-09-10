import type { DirectoryUser } from '../../types/features';
import { api } from '../../api/client';

/**
 * Active organisation members for assignee/approver pickers. Backed by the
 * minimal directory endpoint (request.assign / approval.request), which does
 * not require the broader user.view permission.
 */
export async function fetchDirectoryUsers(): Promise<DirectoryUser[]> {
  const response = await api.get<{ data: DirectoryUser[] }>('/directory/users');
  return response.data.data;
}
