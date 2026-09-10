import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Button,
  Card,
  CardContent,
  Divider,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { useState } from 'react';

import type { ServiceRequest, ServiceRequestPriority } from '../../types/features';
import { normalizeApiError } from '../../api/errors';
import { requestKeys } from '../../api/query-keys';
import { usePermission } from '../../permissions/Can';
import { fetchDirectoryUsers } from '../users/user-api';
import { STATUS_LABEL, isTerminal } from './request-utils';
import { assignRequest, changeRequestPriority, transitionRequest } from './request-api';

const PRIORITIES: ServiceRequestPriority[] = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];

/**
 * Assignment, priority and state-machine controls for a request detail page.
 * Transition targets come from the API (`available_transitions`), already
 * filtered to the edges the current user may walk.
 */
export function RequestActionsPanel({ request }: { request: ServiceRequest }) {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [assigneeId, setAssigneeId] = useState(request.assignee?.id ?? '');
  const [priority, setPriority] = useState<ServiceRequestPriority>(request.priority);
  const [note, setNote] = useState('');

  const terminal = isTerminal(request.status);
  const canAssign = hasPermission('request.assign') && !terminal;
  const canChangePriority = hasPermission('request.change_priority') && !terminal;
  const transitions = request.available_transitions ?? [];

  const directory = useQuery({
    queryKey: ['directory', 'users'],
    queryFn: fetchDirectoryUsers,
    enabled: canAssign,
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: requestKeys.detail(request.id) });
    void queryClient.invalidateQueries({ queryKey: requestKeys.lists() });
  };

  // Write the server's updated resource straight into the detail cache so the
  // version used by the next action is always current (avoids a stale-version
  // conflict when actions are fired in quick succession).
  const apply = (updated: ServiceRequest) => {
    queryClient.setQueryData(requestKeys.detail(request.id), updated);
    invalidate();
  };

  const assign = useMutation({
    mutationFn: (userId: string) => assignRequest(request.id, userId),
    onSuccess: apply,
  });

  const changePriority = useMutation({
    mutationFn: (value: ServiceRequestPriority) => changeRequestPriority(request.id, value),
    onSuccess: apply,
  });

  const transition = useMutation({
    mutationFn: (status: string) =>
      transitionRequest(request.id, status, note !== '' ? note : undefined, request.version),
    onSuccess: (updated) => {
      setNote('');
      apply(updated);
    },
  });

  const error =
    (assign.error ?? changePriority.error ?? transition.error)
      ? normalizeApiError(assign.error ?? changePriority.error ?? transition.error)
      : null;

  return (
    <Card variant="outlined">
      <CardContent>
        <Typography variant="subtitle2" gutterBottom>
          Manage
        </Typography>
        <Divider sx={{ mb: 2 }} />

        {error != null && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error.message}
          </Alert>
        )}

        <Stack spacing={2}>
          {canAssign && (
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <TextField
                select
                size="small"
                label="Assignee"
                value={assigneeId}
                onChange={(event) => setAssigneeId(event.target.value)}
                sx={{ minWidth: 220 }}
              >
                <MenuItem value="">Unassigned</MenuItem>
                {(directory.data ?? []).map((user) => (
                  <MenuItem key={user.id} value={user.id}>
                    {user.name}
                  </MenuItem>
                ))}
              </TextField>
              <Button
                variant="outlined"
                disabled={assigneeId === '' || assign.isPending}
                onClick={() => assign.mutate(assigneeId)}
              >
                Assign
              </Button>
            </Stack>
          )}

          {canChangePriority && (
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <TextField
                select
                size="small"
                label="Priority"
                value={priority}
                onChange={(event) => setPriority(event.target.value as ServiceRequestPriority)}
                sx={{ minWidth: 220 }}
              >
                {PRIORITIES.map((value) => (
                  <MenuItem key={value} value={value}>
                    {value}
                  </MenuItem>
                ))}
              </TextField>
              <Button
                variant="outlined"
                disabled={priority === request.priority || changePriority.isPending}
                onClick={() => changePriority.mutate(priority)}
              >
                Update priority
              </Button>
            </Stack>
          )}

          {transitions.length > 0 && (
            <Stack spacing={1}>
              <TextField
                label="Transition note (optional)"
                size="small"
                value={note}
                onChange={(event) => setNote(event.target.value)}
              />
              <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>
                {transitions.map((transitionOption) => (
                  <Button
                    key={transitionOption.status}
                    variant="contained"
                    size="small"
                    disabled={transition.isPending}
                    onClick={() => transition.mutate(transitionOption.status)}
                  >
                    Mark {STATUS_LABEL[transitionOption.status]}
                  </Button>
                ))}
              </Stack>
            </Stack>
          )}

          {!canAssign && !canChangePriority && transitions.length === 0 && (
            <Typography color="text.secondary" variant="body2">
              No actions are available for you on this request.
            </Typography>
          )}
        </Stack>
      </CardContent>
    </Card>
  );
}
