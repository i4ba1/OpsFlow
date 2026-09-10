import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Divider,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import { useState } from 'react';
import { useParams } from 'react-router-dom';

import { customerKeys } from '../../api/query-keys';
import { usePermission } from '../../permissions/Can';
import { CustomerContactDialog } from './CustomerContactDialog';
import { CustomerFormDialog } from './CustomerFormDialog';
import { archiveCustomer, fetchCustomer, fetchCustomerContacts } from './customer-api';

function Field({ label, value }: { label: string; value: string | null }) {
  return (
    <Stack direction="row" spacing={2}>
      <Typography variant="body2" color="text.secondary" sx={{ width: 160 }}>
        {label}
      </Typography>
      <Typography variant="body2">{value ?? '—'}</Typography>
    </Stack>
  );
}

export function CustomerDetailPage() {
  const { customerId = '' } = useParams();
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();
  const [editOpen, setEditOpen] = useState(false);
  const [contactOpen, setContactOpen] = useState(false);

  const customerQuery = useQuery({
    queryKey: customerKeys.detail(customerId),
    queryFn: () => fetchCustomer(customerId),
    enabled: customerId !== '',
  });

  const contactsQuery = useQuery({
    queryKey: customerKeys.contacts(customerId),
    queryFn: () => fetchCustomerContacts(customerId),
    enabled: customerId !== '',
  });

  const archive = useMutation({
    mutationFn: () => archiveCustomer(customerId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: customerKeys.all });
    },
  });

  if (customerQuery.isPending) {
    return <CircularProgress />;
  }

  if (customerQuery.error != null || customerQuery.data == null) {
    return <Alert severity="error">Unable to load this customer.</Alert>;
  }

  const customer = customerQuery.data;

  return (
    <Stack spacing={3}>
      <Stack
        direction="row"
        spacing={2}
        sx={{ alignItems: 'center', justifyContent: 'space-between' }}
      >
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          <Typography variant="h4" component="h1">
            {customer.name}
          </Typography>
          <Chip size="small" label={customer.status} />
          <Chip size="small" variant="outlined" label={customer.customer_code} />
        </Stack>
        <Stack direction="row" spacing={1}>
          {hasPermission('customer.update') && (
            <Button variant="outlined" onClick={() => setEditOpen(true)}>
              Edit
            </Button>
          )}
          {hasPermission('customer.archive') && customer.archived_at == null && (
            <Button
              color="error"
              variant="outlined"
              disabled={archive.isPending}
              onClick={() => archive.mutate()}
            >
              Archive
            </Button>
          )}
        </Stack>
      </Stack>

      {archive.error != null && <Alert severity="error">{archive.error.message}</Alert>}

      <Card variant="outlined">
        <CardContent>
          <Typography variant="subtitle2" gutterBottom>
            Profile
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Stack spacing={1}>
            <Field label="Legal name" value={customer.legal_name} />
            <Field label="Registration no." value={customer.registration_number} />
            <Field label="Email" value={customer.email} />
            <Field label="Phone" value={customer.phone} />
            <Field label="Billing email" value={customer.billing_email} />
            <Field label="Address" value={customer.address_line_1} />
            <Field label="Address line 2" value={customer.address_line_2} />
            <Field label="City" value={customer.city} />
            <Field label="Postal code" value={customer.postal_code} />
            <Field label="Country" value={customer.country_code} />
          </Stack>
        </CardContent>
      </Card>

      <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
        <Typography variant="h6">Contacts</Typography>
        {hasPermission('customer.update') && (
          <Button variant="contained" size="small" onClick={() => setContactOpen(true)}>
            Add contact
          </Button>
        )}
      </Stack>

      <TableContainer component={Paper} variant="outlined">
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Name</TableCell>
              <TableCell>Email</TableCell>
              <TableCell>Phone</TableCell>
              <TableCell>Job title</TableCell>
              <TableCell>Primary</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {(contactsQuery.data ?? []).map((contact) => (
              <TableRow key={contact.id}>
                <TableCell>{contact.name}</TableCell>
                <TableCell>{contact.email}</TableCell>
                <TableCell>{contact.phone ?? '—'}</TableCell>
                <TableCell>{contact.job_title ?? '—'}</TableCell>
                <TableCell>{contact.is_primary ? 'Yes' : 'No'}</TableCell>
              </TableRow>
            ))}
            {contactsQuery.data != null && contactsQuery.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={5}>
                  <Typography color="text.secondary" align="center">
                    No contacts yet.
                  </Typography>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <CustomerFormDialog open={editOpen} customer={customer} onClose={() => setEditOpen(false)} />
      <CustomerContactDialog
        customerId={customer.id}
        open={contactOpen}
        onClose={() => setContactOpen(false)}
      />
    </Stack>
  );
}
