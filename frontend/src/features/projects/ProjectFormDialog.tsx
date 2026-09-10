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

import type { Project, ProjectStatus } from '../../types/features';
import { normalizeApiError } from '../../api/errors';
import { customerKeys, projectKeys } from '../../api/query-keys';
import { fetchCustomers } from '../customers/customer-api';
import { fetchDirectoryUsers } from '../users/user-api';
import { createProject, updateProject } from './project-api';

const STATUSES: ProjectStatus[] = ['DRAFT', 'ACTIVE', 'ON_HOLD', 'COMPLETED', 'ARCHIVED'];

const schema = z.object({
  customer_id: z.string().min(1, 'Select a customer'),
  name: z.string().trim().min(1, 'Required').max(255),
  description: z.string().max(5000).optional(),
  status: z.enum(['DRAFT', 'ACTIVE', 'ON_HOLD', 'COMPLETED', 'ARCHIVED']),
  starts_on: z.string().optional(),
  ends_on: z.string().optional(),
  manager_id: z.string().optional(),
});

type FormValues = z.infer<typeof schema>;

const EMPTY: FormValues = {
  customer_id: '',
  name: '',
  description: '',
  status: 'ACTIVE',
  starts_on: '',
  ends_on: '',
  manager_id: '',
};

function toForm(project: Project): FormValues {
  return {
    customer_id: project.customer?.id ?? '',
    name: project.name,
    description: project.description ?? '',
    status: project.status,
    starts_on: project.starts_on ?? '',
    ends_on: project.ends_on ?? '',
    manager_id: project.manager?.id ?? '',
  };
}

export function ProjectFormDialog({
  open,
  project,
  onClose,
}: {
  open: boolean;
  project?: Project;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();
  const editing = project !== undefined;

  const customers = useQuery({
    queryKey: customerKeys.list({ per_page: 100 }),
    queryFn: () => fetchCustomers({ per_page: 100, sort: 'name' }),
    enabled: open,
  });

  const managers = useQuery({
    queryKey: ['directory', 'users'],
    queryFn: fetchDirectoryUsers,
    enabled: open,
  });

  const {
    control,
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: editing ? toForm(project) : EMPTY,
  });

  const mutation = useMutation({
    mutationFn: (values: FormValues) => {
      const payload = {
        customer_id: values.customer_id,
        name: values.name,
        description: values.description !== '' ? values.description : null,
        status: values.status,
        starts_on: values.starts_on !== '' ? values.starts_on : null,
        ends_on: values.ends_on !== '' ? values.ends_on : null,
        manager_id: values.manager_id !== '' ? values.manager_id : null,
      };

      return editing ? updateProject(project.id, payload) : createProject(payload);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: projectKeys.all });
      mutation.reset();
      onClose();
    },
  });

  const error = mutation.error != null ? normalizeApiError(mutation.error) : null;

  const handleClose = () => {
    mutation.reset();
    reset(editing ? toForm(project) : EMPTY);
    onClose();
  };

  const onSubmit: SubmitHandler<FormValues> = (values) => mutation.mutate(values);

  return (
    <Dialog open={open} onClose={handleClose} fullWidth maxWidth="sm">
      <form onSubmit={handleSubmit(onSubmit)}>
        <DialogTitle>{editing ? 'Edit project' : 'New project'}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error != null && <Alert severity="error">{error.message}</Alert>}

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
                >
                  {(customers.data?.data ?? []).map((customer) => (
                    <MenuItem key={customer.id} value={customer.id}>
                      {customer.name}
                    </MenuItem>
                  ))}
                </TextField>
              )}
            />

            <TextField
              label="Name"
              {...register('name')}
              error={errors.name != null}
              helperText={errors.name?.message}
            />

            <TextField label="Description" multiline minRows={3} {...register('description')} />

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

            <Controller
              name="manager_id"
              control={control}
              render={({ field }) => (
                <TextField {...field} select label="Manager (optional)">
                  <MenuItem value="">Unassigned</MenuItem>
                  {(managers.data ?? []).map((user) => (
                    <MenuItem key={user.id} value={user.id}>
                      {user.name}
                    </MenuItem>
                  ))}
                </TextField>
              )}
            />

            <Stack direction="row" spacing={2}>
              <TextField
                label="Starts on"
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('starts_on')}
              />
              <TextField
                label="Ends on"
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('ends_on')}
              />
            </Stack>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleClose}>Cancel</Button>
          <Button type="submit" variant="contained" disabled={isSubmitting || mutation.isPending}>
            {editing ? 'Save changes' : 'Create project'}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
