import type { Paginated, Project, ProjectFilters } from '../../types/features';
import { api } from '../../api/client';

export async function fetchProjects(filters: ProjectFilters): Promise<Paginated<Project>> {
  const response = await api.get<Paginated<Project>>('/projects', { params: filters });
  return response.data;
}

export async function fetchProject(id: string): Promise<Project> {
  const response = await api.get<{ data: Project }>(`/projects/${id}`);
  return response.data.data;
}

export type ProjectPayload = {
  customer_id: string;
  name: string;
  description?: string | null;
  status?: string;
  starts_on?: string | null;
  ends_on?: string | null;
  manager_id?: string | null;
};

export async function createProject(payload: ProjectPayload): Promise<Project> {
  const response = await api.post<{ data: Project }>('/projects', payload);
  return response.data.data;
}

export async function updateProject(
  id: string,
  payload: Partial<ProjectPayload>,
): Promise<Project> {
  const response = await api.patch<{ data: Project }>(`/projects/${id}`, payload);
  return response.data.data;
}

export async function archiveProject(id: string): Promise<Project> {
  const response = await api.post<{ data: Project }>(`/projects/${id}/archive`);
  return response.data.data;
}
