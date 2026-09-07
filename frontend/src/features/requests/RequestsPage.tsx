import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  Chip,
  LinearProgress,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TablePagination,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import { useState } from 'react';
import type { SubmitHandler } from 'react-hook-form';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { z } from 'zod';

import type { ServiceRequest, ServiceRequestPriority } from '../../types/features';
import { requestKeys } from '../../api/query-keys';
import { slaSummary } from '../sla/sla-utils';
import { fetchRequests } from './request-api';

const searchSchema = z.object({ search: z.string() });
type SearchForm = z.infer<typeof searchSchema>;

const PRIORITY_COLORS: Record<ServiceRequestPriority, 'error' | 'warning' | 'info' | 'default'> = {
  CRITICAL: 'error',
  HIGH: 'warning',
  MEDIUM: 'info',
  LOW: 'default',
};

export function RequestsPage() {
  const navigate = useNavigate();
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(15);
  const [search, setSearch] = useState('');

  const { data, isPending, error } = useQuery({
    queryKey: requestKeys.list({
      page: page + 1,
      per_page: rowsPerPage,
      search: search || undefined,
    }),
    queryFn: () =>
      fetchRequests({
        page: page + 1,
        per_page: rowsPerPage,
        search: search || undefined,
        sort: '-created_at',
      }),
  });

  const { register, handleSubmit } = useForm<SearchForm>({
    resolver: zodResolver(searchSchema),
    defaultValues: { search: '' },
  });

  const onSubmit: SubmitHandler<SearchForm> = ({ search: term }) => {
    setPage(0);
    setSearch(term);
  };

  return (
    <Stack spacing={2}>
      <Typography variant="h4" component="h1">
        Service Requests
      </Typography>

      <form onSubmit={handleSubmit(onSubmit)}>
        <TextField label="Search requests" size="small" {...register('search')} />
      </form>

      {isPending && <LinearProgress />}
      {error != null && <Alert severity="error">{error.message}</Alert>}

      <TableContainer component={Paper}>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Request</TableCell>
              <TableCell>Customer</TableCell>
              <TableCell>Priority</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>SLA</TableCell>
              <TableCell>Assignee</TableCell>
              <TableCell>Updated</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {(data?.data ?? []).map((request) => (
              <TableRow
                key={request.id}
                hover
                onClick={() => navigate(`/app/requests/${request.id}`)}
                sx={{ cursor: 'pointer' }}
              >
                <TableCell>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {request.request_number}
                  </Typography>
                  <Typography variant="body2" color="text.secondary">
                    {request.title}
                  </Typography>
                </TableCell>
                <TableCell>{request.customer?.name ?? '—'}</TableCell>
                <TableCell>
                  <Chip
                    size="small"
                    label={request.priority}
                    color={PRIORITY_COLORS[request.priority]}
                  />
                </TableCell>
                <TableCell>
                  <Chip size="small" label={request.status} />
                </TableCell>
                <TableCell>
                  <SlaCell request={request} />
                </TableCell>
                <TableCell>{request.assignee?.name ?? '—'}</TableCell>
                <TableCell>
                  <Typography variant="body2" color="text.secondary">
                    {new Date(request.updated_at).toLocaleDateString()}
                  </Typography>
                </TableCell>
              </TableRow>
            ))}
            {data != null && data.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={6}>
                  <Typography color="text.secondary" align="center">
                    No requests found.
                  </Typography>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
        {data != null && (
          <TablePagination
            component="div"
            count={data.meta.total}
            page={page}
            rowsPerPage={rowsPerPage}
            onPageChange={(_, newPage) => setPage(newPage)}
            onRowsPerPageChange={(event) => {
              setRowsPerPage(parseInt(event.target.value, 10));
              setPage(0);
            }}
          />
        )}
      </TableContainer>
    </Stack>
  );
}

function SlaCell({ request }: { request: ServiceRequest }) {
  const { state, label, remainingLabel } = slaSummary(request);
  const color =
    state === 'BREACHED'
      ? 'error'
      : state === 'AT_RISK'
        ? 'warning'
        : state === 'MET'
          ? 'success'
          : 'default';

  return (
    <Stack spacing={0.25}>
      {request.resolution_due_at !== null && (
        <Chip size="small" variant="outlined" color={color} label={label} />
      )}
      {remainingLabel !== null && (
        <Typography variant="caption" color="text.secondary">
          {remainingLabel} left
        </Typography>
      )}
      {request.resolution_due_at === null && (
        <Typography variant="caption" color="text.secondary">
          —
        </Typography>
      )}
    </Stack>
  );
}
