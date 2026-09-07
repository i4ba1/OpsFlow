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
  Typography,
} from '@mui/material';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';

import type { ApprovalStatus } from '../../types/features';
import { approvalKeys } from '../../api/query-keys';
import { fetchApprovals } from './approval-api';

const STATUS_COLOR: Record<ApprovalStatus, 'default' | 'warning' | 'success' | 'error' | 'info'> = {
  PENDING: 'warning',
  APPROVED: 'success',
  REJECTED: 'error',
  CANCELLED: 'default',
  EXPIRED: 'default',
};

export function ApprovalsPage() {
  const navigate = useNavigate();
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(15);

  const { data, isPending, error } = useQuery({
    queryKey: approvalKeys.list({ page: page + 1, per_page: rowsPerPage }),
    queryFn: () =>
      fetchApprovals({
        page: page + 1,
        per_page: rowsPerPage,
        sort: '-created_at',
      }),
  });

  return (
    <Stack spacing={2}>
      <Typography variant="h4" component="h1">
        Approvals
      </Typography>

      {isPending && <LinearProgress />}
      {error != null && <Alert severity="error">{error.message}</Alert>}

      <TableContainer component={Paper}>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Title</TableCell>
              <TableCell>Type</TableCell>
              <TableCell>Requested by</TableCell>
              <TableCell>Assignee</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Due</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {(data?.data ?? []).map((approval) => (
              <TableRow
                key={approval.id}
                hover
                onClick={() => navigate(`/app/approvals/${approval.id}`)}
                sx={{ cursor: 'pointer' }}
              >
                <TableCell>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {approval.title}
                  </Typography>
                </TableCell>
                <TableCell>
                  <Chip size="small" variant="outlined" label={approval.type} />
                </TableCell>
                <TableCell>{approval.requested_by.name}</TableCell>
                <TableCell>{approval.assignee.name}</TableCell>
                <TableCell>
                  <Chip
                    size="small"
                    color={STATUS_COLOR[approval.status]}
                    label={approval.status}
                  />
                </TableCell>
                <TableCell>
                  {approval.due_at != null ? new Date(approval.due_at).toLocaleDateString() : '—'}
                </TableCell>
              </TableRow>
            ))}
            {data != null && data.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={6}>
                  <Typography color="text.secondary" align="center">
                    No approvals found.
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
