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

import type { ApprovalType } from '../../types/features';
import { normalizeApiError } from '../../api/errors';
import { approvalKeys } from '../../api/query-keys';
import { fetchDirectoryUsers } from '../users/user-api';
import { createApproval } from '../approvals/approval-api';

const TYPES: ApprovalType[] = ['ACCEPTANCE', 'QUOTATION', 'CHANGE_REQUEST', 'EXCEPTION'];

const schema = z.object({
  assigned_to: z.string().min(1, 'Select an approver'),
  type: z.enum(['ACCEPTANCE', 'QUOTATION', 'CHANGE_REQUEST', 'EXCEPTION']),
  title: z.string().trim().min(3, 'At least 3 characters').max(255),
  description: z.string().trim().max(10_000).optional(),
  due_at: z.string().optional(),
});

type FormValues = z.infer<typeof schema>;

const EMPTY: FormValues = {
  assigned_to: '',
  type: 'ACCEPTANCE',
  title: '',
  description: '',
  due_at: '',
};

export function RequestApprovalDialog({
  serviceRequestId,
  open,
  onClose,
}: {
  serviceRequestId: string;
  open: boolean;
  onClose: () => void;
}) {
  const queryClient = useQueryClient();

  const directory = useQuery({
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
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: EMPTY });

  const mutation = useMutation({
    mutationFn: (values: FormValues) =>
      createApproval(serviceRequestId, {
        assigned_to: values.assigned_to,
        type: values.type,
        title: values.title,
        description: values.description !== '' ? values.description : null,
        due_at: values.due_at !== '' ? values.due_at : null,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: approvalKeys.all });
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
    <Dialog open={open} onClose={handleClose} fullWidth maxWidth="sm">
      <form onSubmit={handleSubmit(onSubmit)}>
        <DialogTitle>Request approval</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error != null && <Alert severity="error">{error.message}</Alert>}

            <Controller
              name="assigned_to"
              control={control}
              render={({ field }) => (
                <TextField
                  {...field}
                  select
                  label="Approver"
                  error={errors.assigned_to != null}
                  helperText={errors.assigned_to?.message}
                >
                  {(directory.data ?? []).map((user) => (
                    <MenuItem key={user.id} value={user.id}>
                      {user.name}
                    </MenuItem>
                  ))}
                </TextField>
              )}
            />

            <Controller
              name="type"
              control={control}
              render={({ field }) => (
                <TextField {...field} select label="Type">
                  {TYPES.map((type) => (
                    <MenuItem key={type} value={type}>
                      {type}
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
              label="Description (optional)"
              multiline
              minRows={3}
              {...register('description')}
            />

            <TextField
              label="Due date (optional)"
              type="date"
              slotProps={{ inputLabel: { shrink: true } }}
              {...register('due_at')}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleClose}>Cancel</Button>
          <Button type="submit" variant="contained" disabled={isSubmitting || mutation.isPending}>
            Request approval
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
