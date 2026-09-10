import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { useState } from 'react';
import { useParams } from 'react-router-dom';

import { approvalKeys } from '../../api/query-keys';
import { useAuth } from '../../auth/auth-context';
import { usePermission } from '../../permissions/Can';
import { cancelApproval, fetchApproval, respondToApproval } from './approval-api';

const STATUS_COLOR: Record<string, 'default' | 'warning' | 'success' | 'error'> = {
  PENDING: 'warning',
  APPROVED: 'success',
  REJECTED: 'error',
  CANCELLED: 'default',
  EXPIRED: 'default',
};

export function ApprovalDetailPage() {
  const { approvalId = '' } = useParams();
  const { user } = useAuth();
  const { hasPermission } = usePermission();
  const queryClient = useQueryClient();
  const [note, setNote] = useState('');
  const [localError, setLocalError] = useState<string | null>(null);

  const approvalQuery = useQuery({
    queryKey: approvalKeys.detail(approvalId),
    queryFn: () => fetchApproval(approvalId),
    enabled: approvalId !== '',
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: approvalKeys.detail(approvalId) });
    void queryClient.invalidateQueries({ queryKey: approvalKeys.lists() });
  };

  const decisionMutation = useMutation({
    mutationFn: ({ decision, note: n }: { decision: 'approve' | 'reject'; note?: string }) =>
      respondToApproval(approvalId, decision, n),
    onSuccess: invalidate,
    onError: (e: Error) => setLocalError(e.message),
  });

  const cancelMutation = useMutation({
    mutationFn: () => cancelApproval(approvalId),
    onSuccess: invalidate,
    onError: (e: Error) => setLocalError(e.message),
  });

  if (approvalQuery.isPending) {
    return <Typography>Loading…</Typography>;
  }

  if (approvalQuery.error != null || approvalQuery.data == null) {
    return <Alert severity="error">Unable to load this approval.</Alert>;
  }

  const approval = approvalQuery.data;
  const isAssignee = user?.id === approval.assignee.id;
  const isPending = approval.status === 'PENDING';

  return (
    <Stack spacing={2} sx={{ maxWidth: 720 }}>
      <Box>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          <Typography variant="h4" component="h1">
            {approval.title}
          </Typography>
          <Chip label={approval.status} color={STATUS_COLOR[approval.status]} size="small" />
        </Stack>
        <Typography color="text.secondary">{approval.type}</Typography>
      </Box>

      {localError != null && <Alert severity="error">{localError}</Alert>}

      <Card variant="outlined">
        <CardContent>
          <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap' }}>
            {approval.description ?? 'No description provided.'}
          </Typography>
          <Divider sx={{ my: 2 }} />
          <Stack spacing={0.5}>
            <Typography variant="body2">
              <strong>Requested by:</strong> {approval.requested_by.name}
            </Typography>
            <Typography variant="body2">
              <strong>Assignee:</strong> {approval.assignee.name}
            </Typography>
            <Typography variant="body2">
              <strong>Created:</strong> {new Date(approval.created_at).toLocaleString()}
            </Typography>
            {approval.due_at != null && (
              <Typography variant="body2">
                <strong>Due:</strong> {new Date(approval.due_at).toLocaleString()}
              </Typography>
            )}
            {approval.responded_at != null && (
              <Typography variant="body2">
                <strong>Responded:</strong> {new Date(approval.responded_at).toLocaleString()}
              </Typography>
            )}
            {approval.response_note != null && (
              <Typography variant="body2">
                <strong>Response note:</strong> {approval.response_note}
              </Typography>
            )}
          </Stack>
        </CardContent>
      </Card>

      {isPending && isAssignee && (
        <Card variant="outlined">
          <CardContent>
            <Stack spacing={2}>
              <Typography variant="subtitle2">Your decision</Typography>
              <TextField
                label="Response note (optional)"
                multiline
                minRows={2}
                value={note}
                onChange={(event) => setNote(event.target.value)}
              />
              <Stack direction="row" spacing={1}>
                <Button
                  variant="contained"
                  color="success"
                  disabled={decisionMutation.isPending}
                  onClick={() => decisionMutation.mutate({ decision: 'approve', note })}
                >
                  Approve
                </Button>
                <Button
                  variant="contained"
                  color="error"
                  disabled={decisionMutation.isPending}
                  onClick={() => decisionMutation.mutate({ decision: 'reject', note })}
                >
                  Reject
                </Button>
              </Stack>
            </Stack>
          </CardContent>
        </Card>
      )}

      {isPending && hasPermission('approval.cancel') && (
        <Stack direction="row" sx={{ justifyContent: 'flex-end' }}>
          <Button
            variant="outlined"
            color="inherit"
            disabled={cancelMutation.isPending}
            onClick={() => cancelMutation.mutate()}
          >
            Cancel approval
          </Button>
        </Stack>
      )}
    </Stack>
  );
}
