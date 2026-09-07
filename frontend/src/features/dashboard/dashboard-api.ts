import type {
  DashboardOverview,
  DashboardSlaTrend,
  DashboardVolume,
  TeamPerformanceRow,
} from '../../types/features';
import { api } from '../../api/client';

export async function fetchDashboardOverview(): Promise<DashboardOverview> {
  const response = await api.get<{ data: DashboardOverview }>('/dashboard/overview');
  return response.data.data;
}

export async function fetchDashboardVolume(): Promise<DashboardVolume> {
  const response = await api.get<{ data: DashboardVolume }>('/dashboard/request-volume');
  return response.data.data;
}

export async function fetchDashboardSla(): Promise<DashboardSlaTrend> {
  const response = await api.get<{ data: DashboardSlaTrend }>('/dashboard/sla');
  return response.data.data;
}

export async function fetchTeamPerformance(): Promise<TeamPerformanceRow[]> {
  const response = await api.get<{ data: TeamPerformanceRow[] }>('/dashboard/team-performance');
  return response.data.data;
}
