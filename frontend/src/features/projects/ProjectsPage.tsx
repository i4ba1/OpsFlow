import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  Button,
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

import { fetchProjects } from './project-api';
import { ProjectFormDialog } from './ProjectFormDialog';
import { projectKeys } from '../../api/query-keys';
import { usePermission } from '../../permissions/Can';

const searchSchema = z.object({ search: z.string() });

type SearchForm = z.infer<typeof searchSchema>;

export function ProjectsPage() {
  const navigate = useNavigate();
  const { hasPermission } = usePermission();
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(15);
  const [search, setSearch] = useState('');
  const [createOpen, setCreateOpen] = useState(false);

  const { data, isPending, error } = useQuery({
    queryKey: projectKeys.list({
      page: page + 1,
      per_page: rowsPerPage,
      search: search || undefined,
    }),
    queryFn: () =>
      fetchProjects({
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
          Projects
        </Typography>
        {hasPermission('project.create') && (
          <Button variant="contained" onClick={() => setCreateOpen(true)}>
            New project
          </Button>
        )}
      </Stack>

      <form onSubmit={handleSubmit(onSubmit)}>
        <TextField label="Search projects" size="small" {...register('search')} />
      </form>

      {isPending && <LinearProgress />}
      {error != null && <Alert severity="error">{error.message}</Alert>}

      <TableContainer component={Paper}>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Code</TableCell>
              <TableCell>Name</TableCell>
              <TableCell>Customer</TableCell>
              <TableCell>Status</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {(data?.data ?? []).map((project) => (
              <TableRow
                key={project.id}
                hover
                onClick={() => navigate(`/app/projects/${project.id}`)}
                sx={{ cursor: 'pointer' }}
              >
                <TableCell>{project.project_code}</TableCell>
                <TableCell>{project.name}</TableCell>
                <TableCell>{project.customer?.name ?? '—'}</TableCell>
                <TableCell>
                  <Chip size="small" label={project.status} />
                </TableCell>
              </TableRow>
            ))}
            {data != null && data.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={4}>
                  <Typography color="text.secondary" align="center">
                    No projects found.
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

      <ProjectFormDialog open={createOpen} onClose={() => setCreateOpen(false)} />
    </Stack>
  );
}
