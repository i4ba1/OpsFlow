import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
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

import type { ServiceRequestPriority } from '../../types/features';
import { normalizeApiError } from '../../api/errors';
import { customerKeys, projectKeys, requestKeys } from '../../api/query-keys';
import { usePermission } from '../../permissions/Can';
import { fetchCustomers } from '../customers/customer-api';
import { fetchProjects } from '../projects/project-api';
import { createRequest } from './request-api';

const PRIORITIES: ServiceRequestPriority[] = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];

const schema = z.object({
  customer_id: z.string().optional(),
  project_id: z.string().optional(),
  title: z.string().trim().min(5, 'At least 5 characters').max(255),
  description: z.string().trim().min(10, 'At least 10 characters').max(10_000),
  priority: z.enum(['CRITICAL', 'HIGH', 'MEDIUM', 'LOW']),
});

type FormValues = z.infer<typeof schema>;

const EMPTY: FormValues = {
  customer_id: '',
  project_id: '',
  title: '',
  description: '',
  priority: 'MEDIUM',
};

export function CreateRequestDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();
  // Customer-portal users cannot list customers; their request is tied to the
  // customer they are a contact of, inferred server-side.
  const needsCustomer = hasPermission('customer.view');

  const customers = useQuery({
    queryKey: customerKeys.list({ per_page: 100 }),
    queryFn: () => fetchCustomers({ per_page: 100, sort: 'name' }),
    enabled: open && needsCustomer,
  });

  const {
    control,
    register,
    handleSubmit,
    reset,
    setError,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: EMPTY,
  });

  const customerId = watch('customer_id');

  const projects = useQuery({
    queryKey: projectKeys.list({ customer_id: customerId, per_page: 100 }),
    queryFn: () => fetchProjects({ customer_id: customerId, per_page: 100, sort: 'name' }),
    enabled: open && customerId !== undefined && customerId !== '',
  });

  const mutation = useMutation({
    mutationFn: createRequest,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: requestKeys.lists() });
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

  const onSubmit: SubmitHandler<FormValues> = (values) => {
    if (needsCustomer && (values.customer_id === undefined || values.customer_id === '')) {
      setError('customer_id', { type: 'manual', message: 'Select a customer' });
      return;
    }

    mutation.mutate({
      customer_id: values.customer_id !== '' ? values.customer_id : null,
      project_id: values.project_id !== '' ? values.project_id : null,
      title: values.title,
      description: values.description,
      priority: values.priority,
    });
  };

  return (
    <Dialog open={open} onClose={handleClose} fullWidth maxWidth="sm">
      <form onSubmit={handleSubmit(onSubmit)}>
        <DialogTitle>New service request</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error != null && <Alert severity="error">{error.message}</Alert>}

            {needsCustomer && (
              <Controller
                name="customer_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    select
                    label="Customer"
                    error={errors.customer_id != null}
                    helperText={errors.customer_id?.message}
                    onChange={(event) => {
                      field.onChange(event);
                      setValue('project_id', '');
                    }}
                  >
                    {(customers.data?.data ?? []).map((customer) => (
                      <MenuItem key={customer.id} value={customer.id}>
                        {customer.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
            )}

            <Controller
              name="project_id"
              control={control}
              render={({ field }) => (
                <TextField {...field} select label="Project (optional)">
                  <MenuItem value="">None</MenuItem>
                  {(projects.data?.data ?? []).map((project) => (
                    <MenuItem key={project.id} value={project.id}>
                      {project.name}
                    </MenuItem>
                  ))}
                </TextField>
              )}
            />

            <TextField
              label="Title"
              {...register('title')}
              error={errors.title != null}
              helperText={errors.title?.message}
            />

            <TextField
              label="Description"
              multiline
              minRows={4}
              {...register('description')}
              error={errors.description != null}
              helperText={errors.description?.message}
            />

            <Controller
              name="priority"
              control={control}
              render={({ field }) => (
                <TextField {...field} select label="Priority">
                  {PRIORITIES.map((priority) => (
                    <MenuItem key={priority} value={priority}>
                      {priority}
                    </MenuItem>
                  ))}
                </TextField>
              )}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleClose}>Cancel</Button>
          <Button type="submit" variant="contained" disabled={isSubmitting || mutation.isPending}>
            Create request
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
