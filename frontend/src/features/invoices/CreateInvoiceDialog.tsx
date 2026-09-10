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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import type { SubmitHandler } from 'react-hook-form';
import { Controller, useFieldArray, useForm } from 'react-hook-form';
import { z } from 'zod';

import { normalizeApiError } from '../../api/errors';
import { customerKeys, invoiceKeys, projectKeys } from '../../api/query-keys';
import { fetchCustomers } from '../customers/customer-api';
import { fetchProjects } from '../projects/project-api';
import { createInvoice } from './invoice-api';

const schema = z.object({
  customer_id: z.string().min(1, 'Select a customer'),
  project_id: z.string().optional(),
  due_at: z.string().optional(),
  notes: z.string().max(5000).optional(),
  items: z
    .array(
      z.object({
        description: z.string().trim().min(1, 'Required').max(500),
        quantity: z.string().refine((value) => Number(value) > 0, 'Must be greater than 0'),
        unit_price: z.string().refine((value) => value !== '' && Number(value) >= 0, 'Must be ≥ 0'),
      }),
    )
    .min(1, 'Add at least one line item'),
});

type FormValues = z.infer<typeof schema>;

const EMPTY: FormValues = {
  customer_id: '',
  project_id: '',
  due_at: '',
  notes: '',
  items: [{ description: '', quantity: '1', unit_price: '0' }],
};

const currencyFormatter = new Intl.NumberFormat('en-SG', { style: 'currency', currency: 'SGD' });

export function CreateInvoiceDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient();

  const customers = useQuery({
    queryKey: customerKeys.list({ per_page: 100 }),
    queryFn: () => fetchCustomers({ per_page: 100, sort: 'name' }),
    enabled: open,
  });

  const {
    control,
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: EMPTY });

  const { fields, append, remove } = useFieldArray({ control, name: 'items' });

  const customerId = watch('customer_id');
  const items = watch('items');

  const projects = useQuery({
    queryKey: projectKeys.list({ customer_id: customerId, per_page: 100 }),
    queryFn: () => fetchProjects({ customer_id: customerId, per_page: 100, sort: 'name' }),
    enabled: open && customerId !== '',
  });

  const mutation = useMutation({
    mutationFn: (values: FormValues) =>
      createInvoice({
        customer_id: values.customer_id,
        project_id: values.project_id !== '' ? values.project_id : null,
        due_at: values.due_at !== '' ? values.due_at : null,
        notes: values.notes !== '' ? values.notes : null,
        items: values.items.map((item) => ({
          description: item.description,
          quantity: Number(item.quantity),
          unit_price: Number(item.unit_price),
        })),
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all });
      reset(EMPTY);
      onClose();
    },
  });

  const error = mutation.error != null ? normalizeApiError(mutation.error) : null;

  const subtotal = items.reduce(
    (total, item) => total + Number(item.quantity || 0) * Number(item.unit_price || 0),
    0,
  );

  const handleClose = () => {
    mutation.reset();
    reset(EMPTY);
    onClose();
  };

  const onSubmit: SubmitHandler<FormValues> = (values) => mutation.mutate(values);

  return (
    <Dialog open={open} onClose={handleClose} fullWidth maxWidth="md">
      <form onSubmit={handleSubmit(onSubmit)}>
        <DialogTitle>New invoice</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error != null && <Alert severity="error">{error.message}</Alert>}

            <Stack direction="row" spacing={2}>
              <Controller
                name="customer_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    select
                    label="Customer"
                    fullWidth
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

              <Controller
                name="project_id"
                control={control}
                render={({ field }) => (
                  <TextField {...field} select label="Project (optional)" fullWidth>
                    <MenuItem value="">None</MenuItem>
                    {(projects.data?.data ?? []).map((project) => (
                      <MenuItem key={project.id} value={project.id}>
                        {project.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
            </Stack>

            <TextField
              label="Due date (optional)"
              type="date"
              slotProps={{ inputLabel: { shrink: true } }}
              {...register('due_at')}
            />

            <Typography variant="subtitle2">Line items</Typography>

            {errors.items?.root != null && (
              <Alert severity="error">{errors.items.root.message}</Alert>
            )}

            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Description</TableCell>
                  <TableCell width={120}>Qty</TableCell>
                  <TableCell width={160}>Unit price</TableCell>
                  <TableCell width={60} />
                </TableRow>
              </TableHead>
              <TableBody>
                {fields.map((field, index) => (
                  <TableRow key={field.id}>
                    <TableCell>
                      <TextField
                        fullWidth
                        size="small"
                        {...register(`items.${index}.description`)}
                        error={errors.items?.[index]?.description != null}
                        helperText={errors.items?.[index]?.description?.message}
                      />
                    </TableCell>
                    <TableCell>
                      <TextField
                        fullWidth
                        size="small"
                        type="number"
                        slotProps={{ htmlInput: { min: 0, step: 'any' } }}
                        {...register(`items.${index}.quantity`)}
                        error={errors.items?.[index]?.quantity != null}
                      />
                    </TableCell>
                    <TableCell>
                      <TextField
                        fullWidth
                        size="small"
                        type="number"
                        slotProps={{ htmlInput: { min: 0, step: 'any' } }}
                        {...register(`items.${index}.unit_price`)}
                        error={errors.items?.[index]?.unit_price != null}
                      />
                    </TableCell>
                    <TableCell>
                      <Button
                        size="small"
                        color="error"
                        disabled={fields.length === 1}
                        onClick={() => remove(index)}
                      >
                        Remove
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
              <Button
                size="small"
                onClick={() => append({ description: '', quantity: '1', unit_price: '0' })}
              >
                Add line
              </Button>
              <Typography variant="subtitle1">
                Subtotal: {currencyFormatter.format(subtotal)}
              </Typography>
            </Stack>

            <TextField label="Notes (optional)" multiline minRows={2} {...register('notes')} />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleClose}>Cancel</Button>
          <Button type="submit" variant="contained" disabled={isSubmitting || mutation.isPending}>
            Create invoice
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
