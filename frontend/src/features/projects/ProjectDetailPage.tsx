import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Divider,
  Stack,
  Typography,
} from '@mui/material';
import { useState } from 'react';
import { useParams } from 'react-router-dom';

import { projectKeys } from '../../api/query-keys';
import { usePermission } from '../../permissions/Can';
import { ProjectFormDialog } from './ProjectFormDialog';
import { archiveProject, fetchProject } from './project-api';

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

export function ProjectDetailPage() {
  const { projectId = '' } = useParams();
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();
  const [editOpen, setEditOpen] = useState(false);

  const projectQuery = useQuery({
    queryKey: projectKeys.detail(projectId),
    queryFn: () => fetchProject(projectId),
    enabled: projectId !== '',
  });

  const archive = useMutation({
    mutationFn: () => archiveProject(projectId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: projectKeys.all });
    },
  });

  if (projectQuery.isPending) {
    return <CircularProgress />;
  }

  if (projectQuery.error != null || projectQuery.data == null) {
    return <Alert severity="error">Unable to load this project.</Alert>;
  }

  const project = projectQuery.data;

  return (
    <Stack spacing={3}>
      <Stack
        direction="row"
        spacing={2}
        sx={{ alignItems: 'center', justifyContent: 'space-between' }}
      >
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          <Typography variant="h4" component="h1">
            {project.name}
          </Typography>
          <Chip size="small" label={project.status} />
          <Chip size="small" variant="outlined" label={project.project_code} />
        </Stack>
        <Stack direction="row" spacing={1}>
          {hasPermission('project.update') && (
            <Button variant="outlined" onClick={() => setEditOpen(true)}>
              Edit
            </Button>
          )}
          {hasPermission('project.archive') && project.archived_at == null && (
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
            Overview
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Stack spacing={1}>
            <Field label="Customer" value={project.customer?.name ?? null} />
            <Field label="Manager" value={project.manager?.name ?? null} />
            <Field label="Starts on" value={project.starts_on} />
            <Field label="Ends on" value={project.ends_on} />
            <Field label="Description" value={project.description} />
          </Stack>
        </CardContent>
      </Card>

      <ProjectFormDialog open={editOpen} project={project} onClose={() => setEditOpen(false)} />
    </Stack>
  );
}
