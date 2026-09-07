import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  Box,
  Card,
  CardContent,
  Grid,
  LinearProgress,
  Stack,
  Typography,
} from '@mui/material';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import type { DashboardVolume } from '../../types/features';
import { fetchDashboardOverview, fetchDashboardVolume } from './dashboard-api';

const PRIORITY_COLORS: Record<string, string> = {
  CRITICAL: '#d32f2f',
  HIGH: '#f57c00',
  MEDIUM: '#0288d1',
  LOW: '#757575',
};

function StatCard({
  label,
  value,
  tone = 'default',
}: {
  label: string;
  value: string | number;
  tone?: 'default' | 'error' | 'warning' | 'success';
}) {
  const color =
    tone === 'error'
      ? 'error.main'
      : tone === 'warning'
        ? 'warning.main'
        : tone === 'success'
          ? 'success.main'
          : 'text.primary';

  return (
    <Card variant="outlined">
      <CardContent>
        <Typography variant="body2" color="text.secondary" gutterBottom>
          {label}
        </Typography>
        <Typography variant="h4" sx={{ color }}>
          {value}
        </Typography>
      </CardContent>
    </Card>
  );
}

export function DashboardPage() {
  const overview = useQuery({
    queryKey: ['dashboard', 'overview'],
    queryFn: fetchDashboardOverview,
  });

  const volume = useQuery({
    queryKey: ['dashboard', 'volume'],
    queryFn: fetchDashboardVolume,
  });

  if (overview.isPending || volume.isPending) {
    return <LinearProgress />;
  }

  if (overview.error != null || overview.data == null) {
    return <Alert severity="error">Unable to load the dashboard.</Alert>;
  }

  const o = overview.data;
  const v: DashboardVolume | undefined = volume.data;

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" component="h1">
          Operations dashboard
        </Typography>
        <Typography color="text.secondary">Live snapshot of requests, SLAs and workload</Typography>
      </Box>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, sm: 6, md: 4, lg: 2 }}>
          <StatCard label="Open requests" value={o.open_requests} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 4, lg: 2 }}>
          <StatCard label="Critical" value={o.critical_open} tone="error" />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 4, lg: 2 }}>
          <StatCard label="At-risk SLA" value={o.at_risk_sla} tone="warning" />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 4, lg: 2 }}>
          <StatCard label="Breached SLA" value={o.breached_sla} tone="error" />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 4, lg: 2 }}>
          <StatCard
            label="SLA compliance"
            value={`${o.sla_compliance_percent ?? '—'}%`}
            tone="success"
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 4, lg: 2 }}>
          <StatCard
            label="Median resolution"
            value={o.median_resolution_hours != null ? `${o.median_resolution_hours}h` : '—'}
          />
        </Grid>
      </Grid>

      {v != null && (
        <Grid container spacing={3}>
          <Grid size={{ xs: 12, lg: 7 }}>
            <Card variant="outlined">
              <CardContent>
                <Typography variant="subtitle2" gutterBottom>
                  Requests over the last 30 days
                </Typography>
                <ResponsiveContainer width="100%" height={260}>
                  <BarChart data={v.daily}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="date" tickFormatter={(d: string) => d.slice(5)} fontSize={11} />
                    <YAxis allowDecimals={false} fontSize={11} />
                    <Tooltip />
                    <Bar dataKey="count" fill="#0b3d6e" radius={[2, 2, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          </Grid>
          <Grid size={{ xs: 12, lg: 5 }}>
            <Card variant="outlined">
              <CardContent>
                <Typography variant="subtitle2" gutterBottom>
                  Requests by priority
                </Typography>
                <ResponsiveContainer width="100%" height={260}>
                  <PieChart>
                    <Pie
                      data={v.priority_counts}
                      dataKey="count"
                      nameKey="priority"
                      innerRadius={50}
                      outerRadius={90}
                      paddingAngle={2}
                    >
                      {v.priority_counts.map((entry) => (
                        <Cell
                          key={entry.priority}
                          fill={PRIORITY_COLORS[entry.priority] ?? '#9e9e9e'}
                        />
                      ))}
                    </Pie>
                    <Tooltip />
                    <Legend />
                  </PieChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      )}
    </Stack>
  );
}
