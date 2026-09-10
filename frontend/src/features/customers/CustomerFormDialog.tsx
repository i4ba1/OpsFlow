import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  MenuItem,
  Stack,
  TextField,
} from '@mui/material';
import type { SubmitHandler } from 'react-hook-form';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';

import type { Customer, CustomerStatus } from '../../types/features';
import { normalizeApiError } from '../../api/errors';
import { customerKeys } from '../../api/query-keys';
import { createCustomer, updateCustomer } from './customer-api';

const STATUSES: CustomerStatus[] = ['ACTIVE', 'INACTIVE', 'ARCHIVED'];

const optionalEmail = z.union([z.string().email('Enter a valid email'), z.literal('')]).optional();

const schema = z.object({
  name: z.string().trim().min(1, 'Required').max(255),
  legal_name: z.string().max(255).optional(),
  registration_number: z.string().max(100).optional(),
  email: optionalEmail,
  phone: z.string().max(50).optional(),
  billing_email: optionalEmail,
  address_line_1: z.string().max(255).optional(),
  address_line_2: z.string().max(255).optional(),
  city: z.string().max(150).optional(),
  postal_code: z.string().max(20).optional(),
  country_code: z.string().max(2, 'ISO 3166 alpha-2').optional(),
  status: z.enum(['ACTIVE', 'INACTIVE', 'ARCHIVED']),
});

type FormValues = z.infer<typeof schema>;

const EMPTY: FormValues = {
  name: '',
  legal_name: '',
  registration_number: '',
  email: '',
  phone: '',
  billing_email: '',
  address_line_1: '',
  address_line_2: '',
  city: '',
  postal_code: '',
  country_code: 'SG',
  status: 'ACTIVE',
};

function toForm(customer: Customer): FormValues {
  return {
    name: customer.name,
    legal_name: customer.legal_name ?? '',
    registration_number: customer.registration_number ?? '',
    email: customer.email ?? '',
    phone: customer.phone ?? '',
    billing_email: customer.billing_email ?? '',
    address_line_1: customer.address_line_1 ?? '',
    address_line_2: customer.address_line_2 ?? '',
    city: customer.city ?? '',
    postal_code: customer.postal_code ?? '',
    country_code: customer.country_code || 'SG',
    status: customer.status,
  };
}

function clean(value: string | undefined): string | null {
  return value !== undefined && value !== '' ? value : null;
}

export function CustomerFormDialog({
  open,
  customer,
  onClose,
}: {
  open: boolean;
  customer?: Customer;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const editing = customer !== undefined;

  const {
    control,
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: editing ? toForm(customer) : EMPTY,
  });

  const mutation = useMutation({
    mutationFn: (values: FormValues) => {
      const payload = {
        name: values.name,
        legal_name: clean(values.legal_name),
        registration_number: clean(values.registration_number),
        email: clean(values.email),
        phone: clean(values.phone),
        billing_email: clean(values.billing_email),
        address_line_1: clean(values.address_line_1),
        address_line_2: clean(values.address_line_2),
        city: clean(values.city),
        postal_code: clean(values.postal_code),
        country_code: values.country_code !== '' ? values.country_code : undefined,
      };

      return editing ? updateCustomer(customer.id, payload) : createCustomer(payload);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: customerKeys.all });
      mutation.reset();
      onClose();
    },
  });

  const error = mutation.error != null ? normalizeApiError(mutation.error) : null;

  const handleClose = () => {
    mutation.reset();
    reset(editing ? toForm(customer) : EMPTY);
    onClose();
  };

  const onSubmit: SubmitHandler<FormValues> = (values) => mutation.mutate(values);

  return (
    <Dialog open={open} onClose={handleClose} fullWidth maxWidth="sm">
      <form onSubmit={handleSubmit(onSubmit)}>
        <DialogTitle>{editing ? 'Edit customer' : 'New customer'}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error != null && <Alert severity="error">{error.message}</Alert>}

            <TextField
              label="Name"
              {...register('name')}
              error={errors.name != null}
              helperText={errors.name?.message}
            />
            <Stack direction="row" spacing={2}>
              <TextField label="Legal name" fullWidth {...register('legal_name')} />
              <TextField label="Registration no." fullWidth {...register('registration_number')} />
            </Stack>
            <Stack direction="row" spacing={2}>
              <TextField
                label="Email"
                fullWidth
                {...register('email')}
                error={errors.email != null}
                helperText={errors.email?.message}
              />
              <TextField label="Phone" fullWidth {...register('phone')} />
            </Stack>
            <TextField
              label="Billing email"
              {...register('billing_email')}
              error={errors.billing_email != null}
              helperText={errors.billing_email?.message}
            />
            <TextField label="Address line 1" {...register('address_line_1')} />
            <TextField label="Address line 2" {...register('address_line_2')} />
            <Stack direction="row" spacing={2}>
              <TextField label="City" fullWidth {...register('city')} />
              <TextField label="Postal code" fullWidth {...register('postal_code')} />
              <TextField
                label="Country"
                fullWidth
                {...register('country_code')}
                error={errors.country_code != null}
                helperText={errors.country_code?.message}
              />
            </Stack>

            {editing && (
              <Controller
                name="status"
                control={control}
                render={({ field }) => (
                  <TextField {...field} select label="Status">
                    {STATUSES.map((status) => (
                      <MenuItem key={status} value={status}>
                        {status}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleClose}>Cancel</Button>
          <Button type="submit" variant="contained" disabled={isSubmitting || mutation.isPending}>
            {editing ? 'Save changes' : 'Create customer'}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
