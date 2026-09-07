import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  Card,
  CardContent,
  Chip,
  LinearProgress,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';

import { slaKeys } from '../../api/query-keys';
import { fetchSlaPolicies } from './sla-api';

export function SlaPoliciesPage() {
  const { data, isPending, error } = useQuery({
    queryKey: slaKeys.lists(),
    queryFn: fetchSlaPolicies,
  });

  return (
    <Stack spacing={2}>
      <Typography variant="h4" component="h1">
        SLA Policies
      </Typography>

      {isPending && <LinearProgress />}
      {error != null && <Alert severity="error">{error.message}</Alert>}

      <Card variant="outlined">
        <CardContent>
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Name</TableCell>
                  <TableCell>Timezone</TableCell>
                  <TableCell>Calendar</TableCell>
                  <TableCell>Targets</TableCell>
                  <TableCell>Status</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {(data ?? []).map((policy) => (
                  <TableRow key={policy.id} hover>
                    <TableCell>
                      <Typography variant="body2" sx={{ fontWeight: 600 }}>
                        {policy.name}
                      </Typography>
                      {policy.description != null && (
                        <Typography variant="caption" color="text.secondary">
                          {policy.description}
                        </Typography>
                      )}
                    </TableCell>
                    <TableCell>{policy.timezone}</TableCell>
                    <TableCell>{policy.business_calendar_id ? 'Yes' : '—'}</TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={0.5} sx={{ flexWrap: 'wrap', rowGap: 0.5 }}>
                        {policy.targets.map((target) => (
                          <Chip
                            key={target.priority}
                            size="small"
                            variant="outlined"
                            label={`${target.priority}: ${target.resolution_minutes}m`}
                          />
                        ))}
                      </Stack>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="small"
                        color={policy.active ? 'success' : 'default'}
                        label={policy.active ? 'Active' : 'Inactive'}
                      />
                    </TableCell>
                  </TableRow>
                ))}
                {data != null && data.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={5}>
                      <Typography color="text.secondary" align="center">
                        No SLA policies yet.
                      </Typography>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>
    </Stack>
  );
}
