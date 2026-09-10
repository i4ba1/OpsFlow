import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Stack,
  TextField,
} from '@mui/material';
import type { SubmitHandler } from 'react-hook-form';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import { normalizeApiError } from '../../api/errors';
import { customerKeys } from '../../api/query-keys';
import { createCustomerContact } from './customer-api';

const schema = z.object({
  name: z.string().trim().min(1, 'Required').max(255),
  email: z.string().email('Enter a valid email'),
  phone: z.string().max(50).optional(),
  job_title: z.string().max(255).optional(),
  is_primary: z.boolean(),
});

type FormValues = z.infer<typeof schema>;

const EMPTY: FormValues = { name: '', email: '', phone: '', job_title: '', is_primary: false };

export function CustomerContactDialog({
  customerId,
  open,
  onClose,
}: {
  customerId: string;
  open: boolean;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: EMPTY });

  const mutation = useMutation({
    mutationFn: (values: FormValues) =>
      createCustomerContact(customerId, {
        name: values.name,
        email: values.email,
        phone: values.phone !== '' ? values.phone : null,
        job_title: values.job_title !== '' ? values.job_title : null,
        is_primary: values.is_primary,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: customerKeys.contacts(customerId) });
      reset(EMPTY);
      onClose();
    },
  });

  const error = mutation.error != null ? normalizeApiError(mutation.error) : null;

  const handleClose = () => {
    mutation.reset();
    reset(EMPTY);
    onClose();
  };

  const onSubmit: SubmitHandler<FormValues> = (values) => mutation.mutate(values);

  return (
    <Dialog open={open} onClose={handleClose} fullWidth maxWidth="xs">
      <form onSubmit={handleSubmit(onSubmit)}>
        <DialogTitle>Add contact</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error != null && <Alert severity="error">{error.message}</Alert>}
            <TextField
              label="Name"
              {...register('name')}
              error={errors.name != null}
              helperText={errors.name?.message}
            />
            <TextField
              label="Email"
              {...register('email')}
              error={errors.email != null}
              helperText={errors.email?.message}
            />
            <TextField label="Phone" {...register('phone')} />
            <TextField label="Job title" {...register('job_title')} />
            <FormControlLabel
              control={<Checkbox {...register('is_primary')} />}
              label="Primary contact"
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleClose}>Cancel</Button>
          <Button type="submit" variant="contained" disabled={isSubmitting || mutation.isPending}>
            Add contact
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
