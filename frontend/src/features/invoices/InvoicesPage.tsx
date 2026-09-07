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

import type { InvoiceStatus } from '../../types/features';
import { invoiceKeys } from '../../api/query-keys';
import { fetchInvoices } from './invoice-api';

const STATUS_COLOR: Record<InvoiceStatus, 'default' | 'warning' | 'success' | 'error' | 'info'> = {
  DRAFT: 'default',
  ISSUED: 'info',
  PAID: 'success',
  OVERDUE: 'error',
  VOID: 'default',
};

const currencyFormatter = new Intl.NumberFormat('en-SG', {
  style: 'currency',
  currency: 'SGD',
});

export function InvoicesPage() {
  const navigate = useNavigate();
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(15);

  const { data, isPending, error } = useQuery({
    queryKey: invoiceKeys.list({ page: page + 1, per_page: rowsPerPage }),
    queryFn: () =>
      fetchInvoices({
        page: page + 1,
        per_page: rowsPerPage,
        sort: '-created_at',
      }),
  });

  return (
    <Stack spacing={2}>
      <Typography variant="h4" component="h1">
        Invoices
      </Typography>

      {isPending && <LinearProgress />}
      {error != null && <Alert severity="error">{error.message}</Alert>}

      <TableContainer component={Paper}>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Number</TableCell>
              <TableCell>Customer</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Due</TableCell>
              <TableCell align="right">Total</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {(data?.data ?? []).map((invoice) => (
              <TableRow
                key={invoice.id}
                hover
                onClick={() => navigate(`/app/invoices/${invoice.id}`)}
                sx={{ cursor: 'pointer' }}
              >
                <TableCell>{invoice.invoice_number ?? 'Draft'}</TableCell>
                <TableCell>{invoice.customer?.name ?? '—'}</TableCell>
                <TableCell>
                  <Chip size="small" color={STATUS_COLOR[invoice.status]} label={invoice.status} />
                </TableCell>
                <TableCell>
                  {invoice.due_at != null ? new Date(invoice.due_at).toLocaleDateString() : '—'}
                </TableCell>
                <TableCell align="right">
                  {currencyFormatter.format(Number(invoice.total_amount))}
                </TableCell>
              </TableRow>
            ))}
            {data != null && data.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={5}>
                  <Typography color="text.secondary" align="center">
                    No invoices found.
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
