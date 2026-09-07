import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import { useParams } from 'react-router-dom';

import type { InvoiceStatus } from '../../types/features';
import { invoiceKeys } from '../../api/query-keys';
import { usePermission } from '../../permissions/Can';
import { fetchInvoice, issueInvoice, markInvoicePaid, voidInvoice } from './invoice-api';

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

export function InvoiceDetailPage() {
  const { invoiceId = '' } = useParams();
  const { hasPermission } = usePermission();
  const queryClient = useQueryClient();

  const invoiceQuery = useQuery({
    queryKey: invoiceKeys.detail(invoiceId),
    queryFn: () => fetchInvoice(invoiceId),
    enabled: invoiceId !== '',
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: invoiceKeys.detail(invoiceId) });
    void queryClient.invalidateQueries({ queryKey: invoiceKeys.lists() });
  };

  const transition = useMutation({
    mutationFn: (action: 'issue' | 'mark-paid' | 'void') =>
      action === 'issue'
        ? issueInvoice(invoiceId)
        : action === 'mark-paid'
          ? markInvoicePaid(invoiceId)
          : voidInvoice(invoiceId),
    onSuccess: invalidate,
  });

  if (invoiceQuery.isPending) {
    return <Typography>Loading…</Typography>;
  }

  if (invoiceQuery.error != null || invoiceQuery.data == null) {
    return <Alert severity="error">Unable to load this invoice.</Alert>;
  }

  const invoice = invoiceQuery.data;
  const items = invoice.items ?? [];
  const can = (permission: string) => hasPermission(permission);

  return (
    <Stack spacing={2} sx={{ maxWidth: 860 }}>
      <Box>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          <Typography variant="h4" component="h1">
            {invoice.invoice_number ?? 'Draft invoice'}
          </Typography>
          <Chip size="small" color={STATUS_COLOR[invoice.status]} label={invoice.status} />
        </Stack>
        <Typography color="text.secondary">{invoice.customer?.name ?? '—'}</Typography>
      </Box>

      {transition.error != null && (
        <Alert severity="error">{(transition.error as Error).message}</Alert>
      )}

      <Card variant="outlined">
        <CardContent>
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Description</TableCell>
                  <TableCell align="right">Qty</TableCell>
                  <TableCell align="right">Unit price</TableCell>
                  <TableCell align="right">Amount</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {items.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>{item.description}</TableCell>
                    <TableCell align="right">{item.quantity}</TableCell>
                    <TableCell align="right">
                      {currencyFormatter.format(Number(item.unit_price))}
                    </TableCell>
                    <TableCell align="right">
                      {currencyFormatter.format(Number(item.amount))}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
          <Divider sx={{ my: 2 }} />
          <Stack spacing={0.5} sx={{ alignItems: 'flex-end' }}>
            <Typography variant="body2">
              Subtotal: {currencyFormatter.format(Number(invoice.subtotal))}
            </Typography>
            <Typography variant="body2">
              Tax: {currencyFormatter.format(Number(invoice.tax_amount))}
            </Typography>
            <Typography variant="h6">
              Total: {currencyFormatter.format(Number(invoice.total_amount))}
            </Typography>
          </Stack>
        </CardContent>
      </Card>

      {invoice.due_at != null && (
        <Typography variant="body2" color="text.secondary">
          Due {new Date(invoice.due_at).toLocaleDateString()}
          {invoice.paid_at != null && ` · Paid ${new Date(invoice.paid_at).toLocaleDateString()}`}
          {invoice.issued_at != null &&
            ` · Issued ${new Date(invoice.issued_at).toLocaleDateString()}`}
        </Typography>
      )}

      <Stack direction="row" spacing={1}>
        {invoice.status === 'DRAFT' && can('invoice.issue') && (
          <Button
            variant="contained"
            disabled={transition.isPending}
            onClick={() => transition.mutate('issue')}
          >
            Issue invoice
          </Button>
        )}
        {(invoice.status === 'ISSUED' || invoice.status === 'OVERDUE') &&
          can('invoice.mark_paid') && (
            <Button
              variant="contained"
              color="success"
              disabled={transition.isPending}
              onClick={() => transition.mutate('mark-paid')}
            >
              Mark paid
            </Button>
          )}
        {(invoice.status === 'DRAFT' ||
          invoice.status === 'ISSUED' ||
          invoice.status === 'OVERDUE') &&
          can('invoice.void') && (
            <Button
              variant="outlined"
              color="error"
              disabled={transition.isPending}
              onClick={() => transition.mutate('void')}
            >
              Void
            </Button>
          )}
      </Stack>
    </Stack>
  );
}
