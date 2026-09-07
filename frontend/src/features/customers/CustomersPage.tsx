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

import { fetchCustomers } from './customer-api';
import { customerKeys } from '../../api/query-keys';

const searchSchema = z.object({ search: z.string() });

type SearchForm = z.infer<typeof searchSchema>;

export function CustomersPage() {
  const navigate = useNavigate();
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(15);
  const [search, setSearch] = useState('');

  const { data, isPending, error } = useQuery({
    queryKey: customerKeys.list({
      page: page + 1,
      per_page: rowsPerPage,
      search: search || undefined,
    }),
    queryFn: () =>
      fetchCustomers({
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
      <Stack
        direction="row"
        spacing={2}
        sx={{ alignItems: 'center', justifyContent: 'space-between' }}
      >
        <Typography variant="h4" component="h1">
          Customers
        </Typography>
      </Stack>

      <form onSubmit={handleSubmit(onSubmit)}>
        <TextField label="Search customers" size="small" {...register('search')} />
      </form>

      {isPending && <LinearProgress />}

      {error != null && <Alert severity="error">{error.message}</Alert>}

      <TableContainer component={Paper}>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Code</TableCell>
              <TableCell>Name</TableCell>
              <TableCell>Email</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Contacts</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {(data?.data ?? []).map((customer) => (
              <TableRow
                key={customer.id}
                hover
                onClick={() => navigate(`/app/customers/${customer.id}`)}
                sx={{ cursor: 'pointer' }}
              >
                <TableCell>{customer.customer_code}</TableCell>
                <TableCell>{customer.name}</TableCell>
                <TableCell>{customer.email ?? '—'}</TableCell>
                <TableCell>
                  <Chip size="small" label={customer.status} />
                </TableCell>
                <TableCell>{customer.contacts_count ?? 0}</TableCell>
              </TableRow>
            ))}
            {data != null && data.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={5}>
                  <Typography color="text.secondary" align="center">
                    No customers found.
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
